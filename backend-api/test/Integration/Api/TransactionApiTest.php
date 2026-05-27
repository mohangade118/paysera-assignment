<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\DataFixtures\AccountFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Tests\Support\AuthenticatesWithJwt;
use App\Tests\Support\ReloadsDoctrineFixtures;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransactionApiTest extends WebTestCase
{
    use AuthenticatesWithJwt;
    use ReloadsDoctrineFixtures;

    #[Test]
    public function testTransferBetweenOwnAccountsSucceeds(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $accountRepo = static::getContainer()->get(AccountRepository::class);
        $accounts = $accountRepo->findAll();
        self::assertGreaterThanOrEqual(2, \count($accounts));

        $user1Accounts = array_values(array_filter(
            $accounts,
            static fn (Account $a): bool => $a->getUser()?->getEmail() === 'mohangade118@gmail.com',
        ));
        self::assertCount(2, $user1Accounts);

        $fromAccount = $user1Accounts[0];
        $toAccount = $user1Accounts[1];
        $fromId = $fromAccount->getId();
        $toId = $toAccount->getId();

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => 10.0,
            'note' => 'test transfer',
        ], $this->uniqueIdempotencyKey('integration-transfer-success'));

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertSame('add transaction success', $payload['message']);
        self::assertArrayHasKey('transaction_id', $payload);

        static::getContainer()->get('doctrine')->getManager()->clear();
        $fromRefreshed = $accountRepo->find($fromId);
        $toRefreshed = $accountRepo->find($toId);
        self::assertNotNull($fromRefreshed);
        self::assertNotNull($toRefreshed);
        self::assertSame(90.0, $fromRefreshed->getBalance());
        self::assertSame(110.0, $toRefreshed->getBalance());
    }

    #[Test]
    public function testDuplicateRequestWithSameIdempotencyKeyReplaysWithoutSecondTransfer(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $accountRepo = static::getContainer()->get(AccountRepository::class);
        $user1Accounts = array_values(array_filter(
            $accountRepo->findAll(),
            static fn (Account $a): bool => $a->getUser()?->getEmail() === 'mohangade118@gmail.com',
        ));
        self::assertCount(2, $user1Accounts);

        $fromId = $user1Accounts[0]->getId();
        $toId = $user1Accounts[1]->getId();
        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $body = [
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => 10.0,
            'note' => 'duplicate test',
        ];
        $idempotencyKey = $this->uniqueIdempotencyKey('integration-duplicate-transfer');

        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, $body, $idempotencyKey);
        self::assertResponseIsSuccessful();
        $firstPayload = json_decode($client->getResponse()->getContent() ?: '', true);

        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, $body, $idempotencyKey);
        self::assertResponseIsSuccessful();
        $secondPayload = json_decode($client->getResponse()->getContent() ?: '', true);

        self::assertSame($firstPayload['transaction_id'], $secondPayload['transaction_id']);

        static::getContainer()->get('doctrine')->getManager()->clear();
        $fromRefreshed = $accountRepo->find($fromId);
        $toRefreshed = $accountRepo->find($toId);
        self::assertNotNull($fromRefreshed);
        self::assertNotNull($toRefreshed);
        self::assertSame(90.0, $fromRefreshed->getBalance());
        self::assertSame(110.0, $toRefreshed->getBalance());
    }

    #[Test]
    public function testTransferRequiresIdempotencyKeyHeader(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [
            'from_account_id' => 1,
            'to_account_id' => 2,
            'amount' => 10.0,
        ]);

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertStringContainsString('Idempotency-Key', $payload['message']);
    }

    #[Test]
    public function testTransferReturnsConflictWhenIdempotencyKeyReusedWithDifferentBody(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $accountRepo = static::getContainer()->get(AccountRepository::class);
        $user1Accounts = array_values(array_filter(
            $accountRepo->findAll(),
            static fn (Account $a): bool => $a->getUser()?->getEmail() === 'mohangade118@gmail.com',
        ));
        self::assertCount(2, $user1Accounts);

        $fromId = $user1Accounts[0]->getId();
        $toId = $user1Accounts[1]->getId();
        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $idempotencyKey = $this->uniqueIdempotencyKey('integration-conflict-transfer');

        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => 10.0,
        ], $idempotencyKey);
        self::assertResponseIsSuccessful();

        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => 20.0,
        ], $idempotencyKey);

        self::assertResponseStatusCodeSame(409);
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertStringContainsString('different request', $payload['message']);
    }

    #[Test]
    public function testTransferValidationFailsForMissingFields(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [], $this->uniqueIdempotencyKey('integration-validation'));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function testTransferFailsWhenFromAccountNotOwned(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $accountRepo = static::getContainer()->get(AccountRepository::class);
        $user1Accounts = [];
        $user2FromAccount = null;
        foreach ($accountRepo->findAll() as $account) {
            $email = $account->getUser()?->getEmail();
            if ('mohangade118@gmail.com' === $email) {
                $user1Accounts[] = $account;
            }
            if ('mohangade08@gmail.com' === $email) {
                $user2FromAccount = $account;
            }
        }
        self::assertNotNull($user2FromAccount);
        self::assertNotEmpty($user1Accounts);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [
            'from_account_id' => $user2FromAccount->getId(),
            'to_account_id' => $user1Accounts[0]->getId(),
            'amount' => 5.0,
        ], $this->uniqueIdempotencyKey('integration-forbidden-transfer'));

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function testTransferFailsWithInsufficientBalance(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $accountRepo = static::getContainer()->get(AccountRepository::class);
        $user1Accounts = array_values(array_filter(
            $accountRepo->findAll(),
            static fn (Account $a): bool => $a->getUser()?->getEmail() === 'mohangade118@gmail.com',
        ));
        self::assertCount(2, $user1Accounts);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $this->requestAuthenticated($client, 'POST', '/api/v1/transactions', $token, [
            'from_account_id' => $user1Accounts[0]->getId(),
            'to_account_id' => $user1Accounts[1]->getId(),
            'amount' => 500.0,
        ], $this->uniqueIdempotencyKey('integration-insufficient-balance'));

        self::assertResponseStatusCodeSame(400);
    }

    private function uniqueIdempotencyKey(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(8));
    }
}
