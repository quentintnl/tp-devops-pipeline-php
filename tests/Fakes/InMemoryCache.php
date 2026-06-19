<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Cache\CacheInterface;

/**
 * Cache en mémoire pour les tests. Expose le store et un flag de santé pour
 * piloter les scénarios (hit/miss, panne readiness).
 */
final class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $store = [];

    /** @var list<string> Clés supprimées (pour assertions d'invalidation). */
    public array $deleted = [];

    public function __construct(private readonly bool $healthy = true)
    {
    }

    public function increment(string $key): int
    {
        $current = isset($this->store[$key]) ? (int) $this->store[$key] : 0;
        $this->store[$key] = $current + 1;

        return $this->store[$key];
    }

    public function get(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $this->store[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
        $this->deleted[] = $key;
    }

    public function ping(): bool
    {
        return $this->healthy;
    }
}
