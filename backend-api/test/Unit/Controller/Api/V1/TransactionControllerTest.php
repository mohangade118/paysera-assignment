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
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class TransactionControllerTest extends TestCase
{
    #[Test]
    public function addReturnsSuccessJsonWhenTransferSucceeds(): void
    {
        $user = $this->createUser();
        $transaction = new Transaction();
        $transaction->setAmount(10.0)
            ->setStatus('SUCCESS')
            ->setNote('')
            ->setReceipt('');
        $this->setEntityId($transaction, 42);

        $transactionService = $this->createMock(TransactionService::class);
        $transactionService->expects(self::once())
            ->method('transfer')
            ->with($user->getId(), 1, 2, 10.0, 'note', null)
            ->willReturn($transaction);

        $controller = $this->createController($transactionService, $user);
        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = 10.0;
        $dto->note = 'note';

        $response = $controller->add($dto);
        $payload = json_decode($response->getContent() ?: '', true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('add transaction success', $payload['message']);
    }

    #[Test]
    public function addThrowsWhenUserNotAuthenticated(): void
    {
        $transactionService = $this->createMock(TransactionService::class);
        $controller = $this->createController($transactionService, null);

        $dto = new CreateTransactionRequest();
        $dto->from_account_id = 1;
        $dto->to_account_id = 2;
        $dto->amount = 10.0;

        $this->expectException(AccessDeniedHttpException::class);
        $controller->add($dto);
    }

    private function createController(TransactionService $transactionService, ?User $user): TransactionController
    {
        $controller = new TransactionController($transactionService, new NullLogger());
        $controller->setContainer($this->createContainer($user));

        return $controller;
    }

    private function createContainer(?User $user): \Symfony\Component\DependencyInjection\Container
    {
        $container = new \Symfony\Component\DependencyInjection\Container();
        $tokenStorage = new TokenStorage();
        if (null !== $user) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }
        $container->set('security.token_storage', $tokenStorage);
        $container->set('request_stack', new RequestStack());
        $container->set('serializer', new Serializer(
            [new ArrayDenormalizer(), new ObjectNormalizer()],
            [new JsonEncoder()],
        ));

        return $container;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setFirstName('Amit')
            ->setLastName('Sharma')
            ->setEmail('mohangade118@gmail.com')
            ->setContactNo('8793281988')
            ->setPassword('hashed')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $reflection = new \ReflectionClass(User::class);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($user, 1);

        return $user;
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($entity, $id);
    }
}
