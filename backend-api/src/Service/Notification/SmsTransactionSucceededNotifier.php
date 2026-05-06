<?php

namespace App\Service\Notification;

use App\Entity\Transaction;
use App\Entity\User;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class SmsTransactionSucceededNotifier implements TransactionSucceededNotifierInterface
{
    public function __construct(
        private readonly SmsSenderInterface $smsSender,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyChannel(
        Transaction $transaction,
        User $fromUser,
        User $toUser,
        string $subject,
        string $body,
    ): void {
        if ($transaction->getSmsNotificationsSentAt() !== null) {
            return;
        }

        $channelOk = true;

        foreach ([$fromUser, $toUser] as $user) {
            $toNumber = '+91'.$user->getContactNo();
            if ($toNumber === null || $toNumber === '') {
                continue;
            }

            try {
                $this->smsSender->send($toNumber, $subject);
            } catch (\Throwable $e) {
                $channelOk = false;
                $this->logger->error('messenger.transaction_succeeded.sms_failed', [
                    'transaction_id' => $transaction->getId(),
                    'user_id' => $user->getId(),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($channelOk) {
            $transaction->setSmsNotificationsSentAt(new DateTimeImmutable());
        }
    }
}
