<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Message\TransactionSucceededMessage;
use App\Repository\AccountRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function transfer(
        int $authenticatedUserId,
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        ?string $note = null,
        ?string $receipt = null,
    ): Transaction {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            /** @var Account|null $from */
            $from = $this->accountRepository->findOneOwnedByUserIdForUpdate($fromAccountId, $authenticatedUserId);
            if (null === $from) {
                $fromAccountExists = $this->accountRepository->find($fromAccountId);
                if (null === $fromAccountExists) {
                    throw new NotFoundHttpException('from_account_id not found');
                }

                throw new AccessDeniedHttpException('from_account_id does not belong to the authenticated user');
            }
            /** @var Account|null $from */
            if (1 !== $from->getStatus()) {
                throw new BadRequestHttpException('from_account_id is inactive');
            }

            /** @var Account|null $to */
            $to = $this->entityManager->find(Account::class, $toAccountId, LockMode::PESSIMISTIC_WRITE);
            if (null === $to) {
                throw new NotFoundHttpException('to_account_id not found');
            }
            if (1 !== $to->getStatus()) {
                throw new BadRequestHttpException('to_account_id is inactive');
            }

            if ($from->getBalance() < $amount) {
                throw new BadRequestHttpException('insufficient balance in from_account_id');
            }

            $transaction = new Transaction();
            $transaction->setFromAccount($from);
            $transaction->setToAccount($to);
            $transaction->setAmount($amount);
            $transaction->setStatus('SUCCESS');
            $transaction->setNote($note ?? '');
            $transaction->setReceipt($receipt ?? '');

            $from->setBalance($from->getBalance() - $amount);
            $to->setBalance($to->getBalance() + $amount);

            $this->entityManager->persist($transaction);
            $this->entityManager->persist($from);
            $this->entityManager->persist($to);
            $this->entityManager->flush();

            $connection->commit();
            $this->messageBus->dispatch(new TransactionSucceededMessage((int) $transaction->getId()));
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }

        return $transaction;
    }
}
