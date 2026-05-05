<?php

namespace App\Service\Notification;

use Twilio\Rest\Client;

final class TwilioSmsSender
{
    private Client $client;

    public function __construct(
        string $accountSid,
        string $authToken,
        private readonly string $fromNumber
    ) {
        $this->client = new Client($accountSid, $authToken);
    }

    public function send(string $toNumber, string $message): void
    {
        $this->client->messages->create($toNumber, [
            'from' => $this->fromNumber,
            'body' => $message,
        ]);
    }
}
