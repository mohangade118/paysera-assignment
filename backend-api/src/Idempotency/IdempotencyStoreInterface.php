<?php

declare(strict_types=1);

namespace App\Idempotency;

interface IdempotencyStoreInterface
{
    /**
     * Atomically store a processing record if the key does not exist.
     *
     * @param array<string, mixed> $record
     */
    public function claim(string $key, array $record, int $ttlSeconds): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $record
     */
    public function save(string $key, array $record, int $ttlSeconds): void;

    public function release(string $key): void;
}
