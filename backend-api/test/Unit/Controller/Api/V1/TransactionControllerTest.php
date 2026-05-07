<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api\V1;

use App\Controller\Api\V1\TransactionController;
use App\Dto\CreateTransactionRequest;
use App\Entity\Transaction;
use App\Entity\User;
use App\Services\TransactionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class TransactionControllerTest extends TestCase
{
    #[Test]
    public function addReturnsJsonOnSuccessfulTransfer(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = 99.5;
        $dto->note = 'note-a';
        $dto->receipt = 'receipt-b';

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(7);

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getId')->willReturn(42);

        $transactionService = $this->createMock(TransactionService::class);
        $transactionService->expects(self::once())
            ->method('transfer')
            ->with(7, 1, 2, 99.5, 'note-a', 'receipt-b')
            ->willReturn($transaction);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context): void {
                self::assertContains($message, ['transaction_transfer_requested', 'transaction_transfer_succeeded'], 'unexpected info message: '.$message);
                self::assertSame(1, $context['from_account_id'] ?? null);
                self::assertSame(2, $context['to_account_id'] ?? null);
                self::assertSame(99.5, $context['amount'] ?? null);
                if ($message === 'transaction_transfer_succeeded') {
                    self::assertSame(42, $context['transaction_id'] ?? null);
                }
            });

        $controller = $this->createController($transactionService, $logger, $user);
        $response = $controller->add($dto);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('add transaction success', $data['message']);
        self::assertSame(42, $data['transaction_id']);
    }

    #[Test]
    public function addRequiresAppUserWhenTokenMissing(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = 10.0;

        $transactionService = $this->createMock(TransactionService::class);
        $transactionService->expects(self::never())->method('transfer');

        $logger = $this->createMock(LoggerInterface::class);

        $controller = $this->createController($transactionService, $logger, null, null);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Authenticated user is required');

        $controller->add($dto);
    }

    #[Test]
    public function addRequiresAppUserWhenTokenUserIsNotEntityUser(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = 10.0;

        $foreignUser = new class implements UserInterface {
            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void {}

            public function getUserIdentifier(): string
            {
                return 'x';
            }
        };

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($foreignUser);

        $transactionService = $this->createMock(TransactionService::class);
        $transactionService->expects(self::never())->method('transfer');

        $logger = $this->createMock(LoggerInterface::class);

        $controller = $this->createController($transactionService, $logger, null, $token);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Authenticated user is required');

        $controller->add($dto);
    }

    #[Test]
    public function addRethrowsAndLogsWhenTransferFails(): void
    {
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 3;
        $dto->to_account_id = 4;
        $dto->amount = 5.0;

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(9);

        $cause = new BadRequestHttpException('insufficient balance');
        $transactionService = $this->createMock(TransactionService::class);
        $transactionService->expects(self::once())
            ->method('transfer')
            ->willThrowException($cause);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('transaction_transfer_requested', self::callback(function (array $context): bool {
                return 3 === ($context['from_account_id'] ?? null)
                    && 4 === ($context['to_account_id'] ?? null)
                    && 5.0 === ($context['amount'] ?? null);
            }));
        $logger->expects(self::once())
            ->method('error')
            ->with('transaction_transfer_failed', self::callback(function (array $context) use ($cause): bool {
                return ($context['from_account_id'] ?? null) === 3
                    && ($context['to_account_id'] ?? null) === 4
                    && ($context['amount'] ?? null) === 5.0
                    && ($context['exception'] ?? null) === BadRequestHttpException::class
                    && ($context['message'] ?? null) === $cause->getMessage();
            }));

        $controller = $this->createController($transactionService, $logger, $user);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('insufficient balance');

        $controller->add($dto);
    }

    private function createController(
        TransactionService $transactionService,
        LoggerInterface $logger,
        ?User $user = null,
        ?TokenInterface $tokenOverride = null,
    ): TransactionController {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        if ($tokenOverride !== null) {
            $tokenStorage->method('getToken')->willReturn($tokenOverride);
        } elseif ($user !== null) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->method('getToken')->willReturn($token);
        } else {
            $tokenStorage->method('getToken')->willReturn(null);
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => match ($id) {
            'security.token_storage' => true,
            'serializer' => false,
            default => false,
        });
        $container->method('get')->with('security.token_storage')->willReturn($tokenStorage);

        $controller = new TransactionController($transactionService, $logger);
        $controller->setContainer($container);

        return $controller;
    }
}
