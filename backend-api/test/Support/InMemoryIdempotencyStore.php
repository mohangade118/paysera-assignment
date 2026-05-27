<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Idempotency\IdempotencyStoreInterface;

final class InMemoryIdempotencyStore implements IdempotencyStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $records = [];

    public function claim(string $key, array $record, int $ttlSeconds): bool
    {
        if (isset($this->records[$key])) {
            return false;
        }

        $this->records[$key] = $record;

        return true;
    }

    public function get(string $key): ?array
    {
        return $this->records[$key] ?? null;
    }

    public function save(string $key, array $record, int $ttlSeconds): void
    {
        $this->records[$key] = $record;
    }

    public function release(string $key): void
    {
        unset($this->records[$key]);
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
