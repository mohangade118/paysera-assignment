<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\CreateTransactionRequest;
use App\Entity\User;
use App\Services\IdempotencyService;
use App\Services\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class TransactionController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly IdempotencyService $idempotencyService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/transactions', name: 'app_transaction', methods: ['POST'])]
    public function add(
        #[MapRequestPayload] CreateTransactionRequest $dto,
        Request $request,
    ): Response {
        $fromId = (int) $dto->from_account_id;
        $toId = (int) $dto->to_account_id;
        $amount = (float) $dto->amount;

        $idempotencyKey = (string) $request->headers->get('Idempotency-Key', '');
        if ('' === trim($idempotencyKey)) {
            throw new BadRequestHttpException('Idempotency-Key header is required');
        }

        $domainContext = [
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => $amount,
            'idempotency_key' => $idempotencyKey,
        ];

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authenticated user is required');
        }

        $requestHash = IdempotencyService::hashRequest($dto);

        $this->logger->info('transaction_transfer_requested', $domainContext);

        try {
            $result = $this->idempotencyService->execute(
                $user->getId(),
                $idempotencyKey,
                $requestHash,
                function () use ($user, $fromId, $toId, $amount, $dto): array {
                    $transaction = $this->transactionService->transfer(
                        $user->getId(),
                        $fromId,
                        $toId,
                        $amount,
                        $dto->note,
                        $dto->receipt
                    );

                    return [
                        'http_status' => 200,
                        'body' => [
                            'message' => 'add transaction success',
                            'transaction_id' => $transaction->getId(),
                        ],
                    ];
                },
            );
        } catch (\Throwable $e) {
            $this->logger->error('transaction_transfer_failed', [
                ...$domainContext,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $transactionId = $result['body']['transaction_id'] ?? null;
        if (null !== $transactionId) {
            $this->logger->info('transaction_transfer_succeeded', [
                ...$domainContext,
                'transaction_id' => $transactionId,
            ]);
        }

        return $this->json($result['body'], $result['http_status']);
    }
}
