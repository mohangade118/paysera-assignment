<?php

namespace App\Controller\Api\V1;

use App\Dto\CreateTransactionRequest;
use App\Services\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class TransactionController extends AbstractController
{
    public function __construct(private readonly TransactionService $transactionService)
    {
    }

    #[Route('/api/v1/transaction', name: 'app_transaction', methods: ['POST'])]
    public function add(#[MapRequestPayload] CreateTransactionRequest $dto): Response
    {
        // At this point Symfony has already:
        // - decoded JSON
        // - mapped it to CreateTransactionRequest
        // - validated it (based on constraints in the DTO)

        $transaction = $this->transactionService->transfer(
            (int) $dto->from_account_id,
            (int) $dto->to_account_id,
            (float) $dto->amount,
            $dto->note,
            $dto->receipt
        );

        return $this->json([
            'message' => 'add transaction success',
            'transaction_id' => $transaction->getId(),
        ]);
    }
}
