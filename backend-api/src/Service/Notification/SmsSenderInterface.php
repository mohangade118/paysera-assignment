<?php

namespace App\Service\Notification;

interface SmsSenderInterface
{
    public function send(string $toNumber, string $message): void;
}
