<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Cache\CacheInterface;

/**
 * Cache dont le ping LÈVE une exception — sert à prouver que la readiness
 * traite une sonde qui jette comme « non disponible » (et non comme un crash).
 */
final class ThrowingCache implements CacheInterface
{
    public function increment(string $key): int
    {
        throw new \RuntimeException('cache down');
    }

    public function get(string $key): mixed
    {
        throw new \RuntimeException('cache down');
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        throw new \RuntimeException('cache down');
    }

    public function delete(string $key): void
    {
        throw new \RuntimeException('cache down');
    }

    public function ping(): bool
    {
        throw new \RuntimeException('cache down');
    }
}
