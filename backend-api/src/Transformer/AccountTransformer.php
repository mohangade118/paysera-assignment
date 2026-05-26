<?php

declare(strict_types=1);

namespace App\Transformer;

use App\Entity\Account;

final class AccountTransformer
{
    /**
     * @return array{id: int, balance: float, currencyType: string, status: int}
     */
    public function transform(Account $account): array
    {
        return [
            'accountId' => $account->getId(),
            'balance' => $account->getBalance(),
            'currencyType' => $account->getCurrencyType(),
            'accountStatus' => $account->getStatus(),
        ];
    }

    /**
     * @param list<Account> $accounts
     *
     * @return list<array{id: int, balance: float, currencyType: string, status: int}>
     */
    public function transformCollection(array $accounts): array
    {
        return array_map($this->transform(...), $accounts);
    }
}
