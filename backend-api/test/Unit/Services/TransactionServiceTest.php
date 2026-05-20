<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Message\TransactionSucceededMessage;
use App\Repository\AccountRepository;
use App\Services\TransactionService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class TransactionServiceTest extends TestCase
{
    #[Test]
    public function transferUpdatesBalancesAndDispatchesMessage(): void
    {
        $user = $this->createUser(1);
        $from = $this->createAccount(10, $user, 100.0);
        $to = $this->createAccount(20, $this->createUser(2), 50.0);

        $connection = $this->createStub(Connection::class);
        $connection->method('beginTransaction');
        $connection->method('commit');
        $connection->method('isTransactionActive')->willReturn(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeast(1))->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())->method('find')->willReturn($to);
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::exactly(3))->method('persist')->willReturnCallback(
            static function (object $entity): void {
                if ($entity instanceof Transaction) {
                    $reflection = new \ReflectionClass(Transaction::class);
                    $reflection->getProperty('id')->setValue($entity, 99);
                }
            },
        );

        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->expects(self::once())
            ->method('findOneOwnedByUserIdForUpdate')
            ->with(10, 1)
            ->willReturn($from);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(TransactionSucceededMessage::class))
            ->willReturn(new Envelope(new TransactionSucceededMessage(99)));

        $service = new TransactionService($entityManager, $accountRepository, $messageBus);
        $transaction = $service->transfer(1, 10, 20, 25.0, 'note', 'receipt');

        self::assertSame(75.0, $from->getBalance());
        self::assertSame(75.0, $to->getBalance());
        self::assertSame('SUCCESS', $transaction->getStatus());
        self::assertSame(25.0, $transaction->getAmount());
    }

    #[Test]
    public function transferFailsWhenFromAccountNotOwned(): void
    {
        $otherUser = $this->createUser(2);
        $existingAccount = $this->createAccount(10, $otherUser, 100.0);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->expects(self::once())
            ->method('findOneOwnedByUserIdForUpdate')
            ->willReturn(null);
        $accountRepository->expects(self::once())
            ->method('find')
            ->with(10)
            ->willReturn($existingAccount);

        $service = new TransactionService(
            $entityManager,
            $accountRepository,
            $this->createStub(MessageBusInterface::class),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $service->transfer(1, 10, 20, 10.0);
    }

    #[Test]
    public function transferFailsWhenFromAccountNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $accountRepository = $this->createStub(AccountRepository::class);
        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn(null);
        $accountRepository->method('find')->willReturn(null);

        $service = new TransactionService(
            $entityManager,
            $accountRepository,
            $this->createStub(MessageBusInterface::class),
        );

        $this->expectException(NotFoundHttpException::class);
        $service->transfer(1, 99, 20, 10.0);
    }

    #[Test]
    public function transferFailsWithInsufficientBalance(): void
    {
        $user = $this->createUser(1);
        $from = $this->createAccount(10, $user, 5.0);
        $to = $this->createAccount(20, $this->createUser(2), 100.0);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($to);

        $accountRepository = $this->createStub(AccountRepository::class);
        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);

        $service = new TransactionService(
            $entityManager,
            $accountRepository,
            $this->createStub(MessageBusInterface::class),
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('insufficient balance');
        $service->transfer(1, 10, 20, 10.0);
    }

    private function createUser(int $id): User
    {
        $user = new User();
        $user->setFirstName('Test')
            ->setLastName('User')
            ->setEmail("user{$id}@example.com")
            ->setContactNo(str_pad((string) $id, 10, '0', STR_PAD_LEFT))
            ->setPassword('hashed')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        return $user;
    }

    private function createAccount(int $id, User $user, float $balance): Account
    {
        $account = new Account();
        $account->setUser($user)
            ->setBalance($balance)
            ->setCurrencyType('INR')
            ->setStatus(1);

        return $account;
    }
}
