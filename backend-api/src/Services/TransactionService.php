<?php

namespace App\Services;

use App\Entity\Account;
use App\Entity\Transaction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function transfer(int $fromAccountId, int $toAccountId, float $amount, ?string $note = null, ?string $receipt = null): Transaction
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            /** @var Account|null $from */
            $from = $this->entityManager->find(Account::class, $fromAccountId, LockMode::PESSIMISTIC_WRITE);
            if ($from === null) {
                throw new NotFoundHttpException('from_account_id not found');
            }

            /** @var Account|null $to */
            $to = $this->entityManager->find(Account::class, $toAccountId, LockMode::PESSIMISTIC_WRITE);
            if ($to === null) {
                throw new NotFoundHttpException('to_account_id not found');
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

            return $transaction;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

}
