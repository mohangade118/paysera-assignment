<?php

declare(strict_types=1);

namespace App\Idempotency;

use Symfony\Component\Cache\Adapter\RedisAdapter;

final class RedisIdempotencyStore implements IdempotencyStoreInterface
{
    private readonly \Redis $redis;

    public function __construct(string $redisUrl)
    {
        $connection = RedisAdapter::createConnection($redisUrl);
        if (!$connection instanceof \Redis) {
            throw new \RuntimeException('Redis idempotency requires the phpredis extension.');
        }

        $this->redis = $connection;
    }

    public function claim(string $key, array $record, int $ttlSeconds): bool
    {
        $payload = json_encode($record, \JSON_THROW_ON_ERROR);

        return (bool) $this->redis->set($key, $payload, ['nx', 'ex' => $ttlSeconds]);
    }

    public function get(string $key): ?array
    {
        $payload = $this->redis->get($key);
        if (false === $payload || !\is_string($payload) || '' === $payload) {
            return null;
        }

        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        if (!\is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    public function save(string $key, array $record, int $ttlSeconds): void
    {
        $payload = json_encode($record, \JSON_THROW_ON_ERROR);
        $this->redis->set($key, $payload, ['ex' => $ttlSeconds]);
    }

    public function release(string $key): void
    {
        $this->redis->del($key);
    }
}
