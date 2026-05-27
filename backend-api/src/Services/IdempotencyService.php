<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\CreateTransactionRequest;
use App\Idempotency\IdempotencyStoreInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class IdempotencyService
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    private const KEY_PATTERN = '/^[A-Za-z0-9_-]{1,128}$/';

    public function __construct(
        private readonly IdempotencyStoreInterface $store,
        private readonly int $ttlSeconds,
        private readonly int $processingTimeoutSeconds,
    ) {
    }

    public static function buildRedisKey(int $userId, string $idempotencyKey): string
    {
        return sprintf('idempotency:txn:%d:%s', $userId, $idempotencyKey);
    }

    public static function hashRequest(CreateTransactionRequest $dto): string
    {
        $payload = [
            'amount' => $dto->amount,
            'from_account_id' => $dto->from_account_id,
            'note' => $dto->note,
            'receipt' => $dto->receipt,
            'to_account_id' => $dto->to_account_id,
        ];

        return hash('sha256', json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    public function assertValidKey(string $idempotencyKey): void
    {
        if ('' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('Idempotency-Key header is required');
        }

        if (!preg_match(self::KEY_PATTERN, $idempotencyKey)) {
            throw new BadRequestHttpException('Idempotency-Key must be 1-128 characters and contain only letters, numbers, underscores, or hyphens');
        }
    }

    /**
     * @param callable(): array{http_status: int, body: array<string, mixed>} $operation
     *
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function execute(
        int $userId,
        string $idempotencyKey,
        string $requestHash,
        callable $operation,
    ): array {
        $this->assertValidKey($idempotencyKey);

        $redisKey = self::buildRedisKey($userId, $idempotencyKey);

        if (!$this->claimOrHandleExisting($redisKey, $requestHash)) {
            return $this->resolveExisting($redisKey, $requestHash);
        }

        try {
            $result = $operation();
            $this->store->save($redisKey, [
                'status' => self::STATUS_COMPLETED,
                'request_hash' => $requestHash,
                'http_status' => $result['http_status'],
                'body' => $result['body'],
                'transaction_id' => $result['body']['transaction_id'] ?? null,
                'created_at' => time(),
            ], $this->ttlSeconds);

            return $result;
        } catch (HttpExceptionInterface $e) {
            $statusCode = $e->getStatusCode();
            $body = [
                'success' => false,
                'message' => $e->getMessage() ?: (JsonResponse::$statusTexts[$statusCode] ?? 'Error'),
            ];

            $this->store->save($redisKey, [
                'status' => self::STATUS_FAILED,
                'request_hash' => $requestHash,
                'http_status' => $statusCode,
                'body' => $body,
                'transaction_id' => null,
                'created_at' => time(),
            ], $this->ttlSeconds);

            return [
                'http_status' => $statusCode,
                'body' => $body,
            ];
        } catch (\Throwable $e) {
            $this->store->release($redisKey);

            throw $e;
        }
    }

    private function claimOrHandleExisting(string $redisKey, string $requestHash): bool
    {
        $processingRecord = [
            'status' => self::STATUS_PROCESSING,
            'request_hash' => $requestHash,
            'http_status' => null,
            'body' => null,
            'transaction_id' => null,
            'created_at' => time(),
        ];

        if ($this->store->claim($redisKey, $processingRecord, $this->ttlSeconds)) {
            return true;
        }

        $existing = $this->store->get($redisKey);
        if (null === $existing) {
            return $this->store->claim($redisKey, $processingRecord, $this->ttlSeconds);
        }

        if (self::STATUS_PROCESSING === ($existing['status'] ?? null) && $this->isProcessingStale($existing)) {
            $this->store->release($redisKey);

            return $this->store->claim($redisKey, $processingRecord, $this->ttlSeconds);
        }

        return false;
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    private function resolveExisting(string $redisKey, string $requestHash): array
    {
        $existing = $this->store->get($redisKey);
        if (null === $existing) {
            throw new ConflictHttpException('Idempotency key already in use');
        }

        $storedHash = (string) ($existing['request_hash'] ?? '');
        if ($storedHash !== $requestHash) {
            throw new ConflictHttpException('Idempotency key reused with different request');
        }

        $status = (string) ($existing['status'] ?? '');
        if (self::STATUS_PROCESSING === $status) {
            throw new ConflictHttpException('Idempotency key already in use');
        }

        if (!\in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)) {
            throw new ConflictHttpException('Idempotency key already in use');
        }

        return [
            'http_status' => (int) ($existing['http_status'] ?? 200),
            'body' => \is_array($existing['body'] ?? null) ? $existing['body'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isProcessingStale(array $record): bool
    {
        $createdAt = (int) ($record['created_at'] ?? 0);
        if ($createdAt <= 0) {
            return true;
        }

        return (time() - $createdAt) >= $this->processingTimeoutSeconds;
    }
}
