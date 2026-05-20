<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTransactionRequest
{
    #[Assert\NotNull(message: 'from_account_id is required')]
    #[Assert\Type(type: 'integer', message: 'from_account_id must be an integer')]
    #[Assert\Positive(message: 'from_account_id must be greater than 0')]
    public ?int $from_account_id = null;

    #[Assert\NotNull(message: 'to_account_id is required')]
    #[Assert\Type(type: 'integer', message: 'to_account_id must be an integer')]
    #[Assert\Positive(message: 'to_account_id must be greater than 0')]
    public ?int $to_account_id = null;

    #[Assert\NotNull(message: 'amount is required')]
    #[Assert\Type(type: 'numeric', message: 'amount must be numeric')]
    #[Assert\Positive(message: 'amount must be greater than 0')]
    public ?float $amount = null;

    #[Assert\Type(type: 'string', message: 'note must be a string')]
    public ?string $note = null;

    #[Assert\Type(type: 'string', message: 'receipt must be a string')]
    public ?string $receipt = null;

    #[Assert\Expression(
        "this.from_account_id != this.to_account_id",
        message: "from_account_id and to_account_id must be different"
    )]
    public bool $differentAccounts = true;
}

