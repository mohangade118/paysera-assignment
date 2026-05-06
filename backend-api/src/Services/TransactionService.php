<?php

namespace App\Services;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accountRepository,
    ) {}

    public function transfer(
        int $authenticatedUserId,
        int $fromAccountId,
        int $toAccountId,
        float $amount,
        ?string $note = null,
        ?string $receipt = null
    ): Transaction
    {
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            /** @var Account|null $from */
            $from = $this->accountRepository->findOneOwnedByUserIdForUpdate($fromAccountId, $authenticatedUserId);

            if ($from === null) {
                $fromAccountExists = $this->accountRepository->find($fromAccountId);
                if ($fromAccountExists === null) {
                    throw new NotFoundHttpException('from_account_id not found');
                }

                throw new AccessDeniedHttpException('from_account_id does not belong to the authenticated user');
            }

            /** @var Account|null $from */
            if ($from->getStatus() !== 1) {
                throw new BadRequestHttpException('from_account_id is inactive');
            }

            /** @var Account|null $to */
            $to = $this->entityManager->find(Account::class, $toAccountId, LockMode::PESSIMISTIC_WRITE);
            if ($to === null) {
                throw new NotFoundHttpException('to_account_id not found');
            }
            if ($to->getStatus() !== 1) {
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
            $this->sendEmailNotification($transaction);
            return $transaction;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }


    private function sendEmailNotification(Transaction $transaction): void
    {
        $email = (new Email())
        ->from('mohangade118@gmail.com')
        ->to($transaction->getFromAccount()->getUser()->getEmail())
        ->subject('Transaction Succeeded')
        ->text('Transaction Succeeded');
        
        $this->mailer->send($email);
    }

}
