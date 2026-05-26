<?php

declare(strict_types=1);

namespace App\Transformer;

use App\Entity\User;

final class UserTransformer
{
    /**
     * @return array{id: int, firstName: string, lastName: string, email: string}
     */
    public function transform(User $user): array
    {
        return [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
        ];
    }
}
