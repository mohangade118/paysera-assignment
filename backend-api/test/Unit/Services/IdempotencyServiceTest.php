<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\IdempotencyService;
use App\Tests\Support\InMemoryIdempotencyStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IdempotencyServiceTest extends TestCase
{
    private InMemoryIdempotencyStore $store;

    private IdempotencyService $service;

    protected function setUp(): void
    {
        $this->store = new InMemoryIdempotencyStore();
        $this->service = new IdempotencyService($this->store, ttlSeconds: 3600, processingTimeoutSeconds: 30);
    }

    #[Test]
    public function executeRunsOperationOnceAndReplaysCompletedResult(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): array {
            ++$calls;

            return [
                'http_status' => 200,
                'body' => [
                    'message' => 'add transaction success',
                    'transaction_id' => 99,
                ],
            ];
        };

        $first = $this->service->execute(1, 'key-1', 'hash-a', $operation);
        $second = $this->service->execute(1, 'key-1', 'hash-a', $operation);

        self::assertSame(1, $calls);
        self::assertSame(200, $first['http_status']);
        self::assertSame(99, $first['body']['transaction_id']);
        self::assertSame($first, $second);
    }

    #[Test]
    public function executeReplaysFailedResultWithoutRerunningOperation(): void
    {
        $calls = 0;
        $operation = function () use (&$calls): array {
            ++$calls;

            throw new NotFoundHttpException('from_account_id not found');
        };

        $first = $this->service->execute(1, 'key-fail', 'hash-b', $operation);
        $second = $this->service->execute(1, 'key-fail', 'hash-b', $operation);

        self::assertSame(1, $calls);
        self::assertSame(404, $first['http_status']);
        self::assertFalse($first['body']['success']);
        self::assertSame('from_account_id not found', $first['body']['message']);
        self::assertSame($first, $second);
    }

    #[Test]
    public function executeThrowsConflictWhenRequestHashDiffers(): void
    {
        $this->service->execute(1, 'key-conflict', 'hash-one', static fn (): array => [
            'http_status' => 200,
            'body' => ['message' => 'ok', 'transaction_id' => 1],
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Idempotency key reused with different request');

        $this->service->execute(1, 'key-conflict', 'hash-two', static fn (): array => [
            'http_status' => 200,
            'body' => ['message' => 'ok', 'transaction_id' => 2],
        ]);
    }

    #[Test]
    public function executeThrowsConflictWhenProcessingIsInFlight(): void
    {
        $redisKey = IdempotencyService::buildRedisKey(1, 'processing-key');
        $this->store->claim($redisKey, [
            'status' => IdempotencyService::STATUS_PROCESSING,
            'request_hash' => 'hash-c',
            'created_at' => time(),
        ], 3600);

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Idempotency key already in use');

        $this->service->execute(1, 'processing-key', 'hash-c', static fn (): array => [
            'http_status' => 200,
            'body' => ['message' => 'ok', 'transaction_id' => 1],
        ]);
    }

    #[Test]
    public function executeReclaimsStaleProcessingRecord(): void
    {
        $redisKey = IdempotencyService::buildRedisKey(1, 'stale-key');
        $this->store->claim($redisKey, [
            'status' => IdempotencyService::STATUS_PROCESSING,
            'request_hash' => 'hash-d',
            'created_at' => time() - 60,
        ], 3600);

        $calls = 0;
        $result = $this->service->execute(1, 'stale-key', 'hash-d', function () use (&$calls): array {
            ++$calls;

            return [
                'http_status' => 200,
                'body' => ['message' => 'ok', 'transaction_id' => 5],
            ];
        });

        self::assertSame(1, $calls);
        self::assertSame(5, $result['body']['transaction_id']);
    }

    #[Test]
    public function assertValidKeyRejectsInvalidValues(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->service->assertValidKey('invalid key!');
    }

    #[Test]
    public function executeReleasesKeyAndRethrowsOnUnexpectedError(): void
    {
        $redisKey = IdempotencyService::buildRedisKey(1, 'release-key');

        try {
            $this->service->execute(1, 'release-key', 'hash-e', static function (): array {
                throw new \RuntimeException('boom');
            });
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            // expected
        }

        self::assertNull($this->store->get($redisKey));

        $calls = 0;
        $result = $this->service->execute(1, 'release-key', 'hash-e', function () use (&$calls): array {
            ++$calls;

            return [
                'http_status' => 200,
                'body' => ['message' => 'ok', 'transaction_id' => 7],
            ];
        });

        self::assertSame(1, $calls);
        self::assertSame(7, $result['body']['transaction_id']);
    }
}
