<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\CreateTransactionRequest;
use App\Entity\User;
use App\Services\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TransactionController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/transaction', name: 'app_transaction', methods: ['POST'])]
    public function add(#[MapRequestPayload] CreateTransactionRequest $dto): Response
    {
        // At this point Symfony has already:
        // - decoded JSON
        // - mapped it to CreateTransactionRequest
        // - validated it (based on constraints in the DTO)

        $fromId = (int) $dto->from_account_id;
        $toId = (int) $dto->to_account_id;
        $amount = (float) $dto->amount;

        $domainContext = [
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => $amount,
        ];

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authenticated user is required');
        }

        $this->logger->info('transaction_transfer_requested', $domainContext);

        try {
            $transaction = $this->transactionService->transfer(
                $user->getId(),
                $fromId,
                $toId,
                $amount,
                $dto->note,
                $dto->receipt
            );
        } catch (\Throwable $e) {
            $this->logger->error('transaction_transfer_failed', [
                ...$domainContext,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->info('transaction_transfer_succeeded', [
            ...$domainContext,
            'transaction_id' => $transaction->getId(),
        ]);

        return $this->json([
            'message' => 'add transaction success',
            'transaction_id' => $transaction->getId(),
        ]);
    }
}
