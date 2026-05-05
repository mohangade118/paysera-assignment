<?php

namespace App\Message;

final class TransactionSucceededMessage
{
    public function __construct(public readonly int $transactionId)
    {
    }
}
