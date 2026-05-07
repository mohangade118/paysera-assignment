<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Message\TransactionSucceededMessage;
use App\Repository\AccountRepository;
use App\Services\TransactionService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
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
    public function transferCommitsUpdatesBalancesAndDispatchesMessage(): void
    {
        $from = $this->makeAccount(1, 100.0, 1);
        $to = $this->makeAccount(2, 50.0, 1);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');
        $connection->expects(self::never())->method('rollBack');

        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);

        $transactionId = 4242;
        $trackedTx = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->expects(self::once())
            ->method('find')
            ->with(Account::class, 2, LockMode::PESSIMISTIC_WRITE)
            ->willReturn($to);

        $entityManager->expects(self::exactly(3))->method('persist')->willReturnCallback(
            function (object $entity) use (&$trackedTx): void {
                if ($entity instanceof Transaction) {
                    $trackedTx = $entity;
                }
            }
        );

        $entityManager->expects(self::once())->method('flush')->willReturnCallback(
            function () use (&$trackedTx, $transactionId): void {
                self::assertInstanceOf(Transaction::class, $trackedTx);
                self::setPrivateProperty($trackedTx, 'id', $transactionId);
            }
        );

        $dispatched = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched) {
                $dispatched = $message;

                return new Envelope($message);
            });

        $service = new TransactionService($entityManager, $accountRepository, $messageBus);

        $result = $service->transfer(10, 1, 2, 10.0, 'memo', 'rcpt');

        self::assertSame($trackedTx, $result);
        self::assertSame(90.0, $from->getBalance());
        self::assertSame(60.0, $to->getBalance());
        self::assertSame('SUCCESS', $result->getStatus());
        self::assertSame(10.0, $result->getAmount());
        self::assertSame('memo', $result->getNote());
        self::assertSame('rcpt', $result->getReceipt());

        self::assertInstanceOf(TransactionSucceededMessage::class, $dispatched);
        self::assertSame($transactionId, $dispatched->transactionId);
    }

    #[Test]
    public function transferThrowsNotFoundWhenFromAccountDoesNotExist(): void
    {
        [$entityManager, $accountRepository, $messageBus] = $this->createFailureMocks();

        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn(null);
        $accountRepository->method('find')->with(9)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('from_account_id not found');

        $this->makeService($entityManager, $accountRepository, $messageBus)->transfer(1, 9, 2, 1.0);
    }

    #[Test]
    public function transferThrowsAccessDeniedWhenFromAccountNotOwned(): void
    {
        [$entityManager, $accountRepository, $messageBus] = $this->createFailureMocks();
        $foreign = $this->makeAccount(9, 1.0, 1);

        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn(null);
        $accountRepository->method('find')->with(9)->willReturn($foreign);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('from_account_id does not belong to the authenticated user');

        $this->makeService($entityManager, $accountRepository, $messageBus)->transfer(1, 9, 2, 1.0);
    }

    #[Test]
    public function transferThrowsWhenFromAccountInactive(): void
    {
        [$entityManager, $accountRepository, $messageBus] = $this->createFailureMocks();
        $from = $this->makeAccount(1, 10.0, 0);

        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('from_account_id is inactive');

        $this->makeService($entityManager, $accountRepository, $messageBus)->transfer(1, 1, 2, 1.0);
    }

    #[Test]
    public function transferThrowsNotFoundWhenToAccountDoesNotExist(): void
    {
        [$entityManager, $accountRepository, $messageBus] = $this->createFailureMocks();
        $from = $this->makeAccount(1, 10.0, 1);

        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);
        $entityManager->method('find')
            ->with(Account::class, 99, LockMode::PESSIMISTIC_WRITE)
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('to_account_id not found');

        $this->makeService($entityManager, $accountRepository, $messageBus)->transfer(1, 1, 99, 1.0);
    }

    #[Test]
    public function transferThrowsWhenToAccountInactive(): void
    {
        [$entityManager, $accountRepository, $messageBus] = $this->createFailureMocks();
        $from = $this->makeAccount(1, 10.0, 1);
        $to = $this->makeAccount(2, 5.0, 0);

        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);
        $entityManager->method('find')->willReturn($to);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('to_account_id is inactive');

        $this->makeService($entityManager, $accountRepository, $messageBus)->transfer(1, 1, 2, 1.0);
    }

    #[Test]
    public function transferThrowsWhenInsufficientBalance(): void
    {
        [$entityManager, $accountRepository, $messageBus] = $this->createFailureMocks();
        $from = $this->makeAccount(1, 5.0, 1);
        $to = $this->makeAccount(2, 0.0, 1);

        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);
        $entityManager->method('find')->willReturn($to);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('insufficient balance in from_account_id');

        $this->makeService($entityManager, $accountRepository, $messageBus)->transfer(1, 1, 2, 10.0);
    }

    #[Test]
    public function transferUsesEmptyStringsWhenNoteAndReceiptNull(): void
    {
        $from = $this->makeAccount(1, 20.0, 1);
        $to = $this->makeAccount(2, 0.0, 1);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('commit');

        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);

        $trackedTx = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($to);
        $entityManager->method('persist')->willReturnCallback(
            function (object $entity) use (&$trackedTx): void {
                if ($entity instanceof Transaction) {
                    $trackedTx = $entity;
                }
            }
        );
        $entityManager->method('flush')->willReturnCallback(
            function () use (&$trackedTx): void {
                self::assertInstanceOf(Transaction::class, $trackedTx);
                self::setPrivateProperty($trackedTx, 'id', 1);
            }
        );

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturnCallback(fn (object $m) => new Envelope($m));

        $service = new TransactionService($entityManager, $accountRepository, $messageBus);
        $result = $service->transfer(1, 1, 2, 1.0, null, null);

        self::assertSame('', $result->getNote());
        self::assertSame('', $result->getReceipt());
    }

    #[Test]
    public function transferRollsBackWhenFlushFails(): void
    {
        $from = $this->makeAccount(1, 10.0, 1);
        $to = $this->makeAccount(2, 0.0, 1);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->expects(self::once())->method('rollBack');
        $connection->method('isTransactionActive')->willReturn(true);

        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->method('findOneOwnedByUserIdForUpdate')->willReturn($from);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('find')->willReturn($to);
        $entityManager->method('persist');
        $entityManager->method('flush')->willThrowException(new \RuntimeException('db down'));

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('db down');

        (new TransactionService($entityManager, $accountRepository, $messageBus))->transfer(1, 1, 2, 1.0);
    }

    /**
     * @return array{EntityManagerInterface, AccountRepository, MessageBusInterface, Connection}
     */
    private function createFailureMocks(): array
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollBack');
        $connection->method('isTransactionActive')->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $accountRepository = $this->createMock(AccountRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::never())->method('dispatch');

        return [$entityManager, $accountRepository, $messageBus, $connection];
    }

    private function makeService(
        EntityManagerInterface $entityManager,
        AccountRepository $accountRepository,
        MessageBusInterface $messageBus,
    ): TransactionService {
        return new TransactionService($entityManager, $accountRepository, $messageBus);
    }

    private function makeAccount(int $id, float $balance, int $status): Account
    {
        $account = new Account();
        $user = $this->createMock(User::class);
        self::setPrivateProperty($account, 'id', $id);
        self::setPrivateProperty($account, 'balance', $balance);
        self::setPrivateProperty($account, 'status', $status);
        self::setPrivateProperty($account, 'user', $user);

        return $account;
    }

    /**
     * @template T of object
     *
     * @param T $object
     */
    private static function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
