<?php

namespace App\MessageHandler;

use App\Message\TransactionSucceededMessage;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
final class TransactionSucceededHandler
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM_EMAIL)%')]
        private readonly string $fromEmail,
    ) {
    }

    public function __invoke(TransactionSucceededMessage $message): void
    {
        $this->logger->info('messenger.transaction_succeeded.handling', [
            'transaction_id' => $message->transactionId,
        ]);

        $transaction = $this->transactionRepository->find($message->transactionId);
        if ($transaction === null) {
            $this->logger->warning('messenger.transaction_succeeded.transaction_missing', [
                'transaction_id' => $message->transactionId,
            ]);

            return;
        }

        if ($transaction->getNotificationsSentAt() !== null) {
            $this->logger->info('messenger.transaction_succeeded.already_notified', [
                'transaction_id' => $message->transactionId,
            ]);

            return;
        }

        $fromUser = $transaction->getFromAccount()?->getUser();
        $toUser = $transaction->getToAccount()?->getUser();
        if ($fromUser === null || $toUser === null) {
            $this->logger->warning('messenger.transaction_succeeded.user_missing', [
                'transaction_id' => $message->transactionId,
                'from_user_id' => $fromUser?->getId(),
                'to_user_id' => $toUser?->getId(),
            ]);

            return;
        }

        $subject = sprintf('Transaction %d succeeded', (int) $transaction->getId());
        $body = sprintf(
            "Your transaction was successful.\n\nTransaction ID: %d\nAmount: %s\n",
            (int) $transaction->getId(),
            (string) $transaction->getAmount(),
        );

        try {
            if ($fromUser->getEmail() !== '') {
                $this->mailer->send((new Email())
                    ->from($this->fromEmail)
                    ->to($fromUser->getEmail())
                    ->subject($subject)
                    ->text($body));
            }

            if ($toUser->getEmail() !== '') {
                $this->mailer->send((new Email())
                    ->from($this->fromEmail)
                    ->to($toUser->getEmail())
                    ->subject($subject)
                    ->text($body));
            }

            $transaction->setNotificationsSentAt(new DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('messenger.transaction_succeeded.notifications_sent', [
                'transaction_id' => $message->transactionId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('messenger.transaction_succeeded.notifications_failed', [
                'transaction_id' => $message->transactionId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
