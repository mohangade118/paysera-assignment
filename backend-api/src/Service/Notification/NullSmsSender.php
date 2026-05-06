<?php

namespace App\Service\Notification;

use Psr\Log\LoggerInterface;

/**
 * No external SMS provider; logs intended sends for local/dev use.
 */
final class NullSmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(string $toNumber, string $message): void
    {
        $this->logger->info('notification.sms.skipped_no_provider', [
            'to' => $toNumber,
            'message_preview' => mb_substr($message, 0, 120),
        ]);
    }
}
