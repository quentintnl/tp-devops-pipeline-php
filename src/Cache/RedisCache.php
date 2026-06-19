<?php

declare(strict_types=1);

namespace App\Cache;

use Predis\ClientInterface;

/**
 * Implémentation Redis du cache via predis (pur PHP, aucune extension à compiler).
 *
 * Les valeurs non scalaires sont sérialisées en JSON pour rester lisibles et
 * interopérables côté Redis.
 */
final class RedisCache implements CacheInterface
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function increment(string $key): int
    {
        return (int) $this->client->incr($key);
    }

    public function get(string $key): mixed
    {
        $raw = $this->client->get($key);
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        // Valeur stockée brute (non-JSON) → on la renvoie telle quelle.
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $payload = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
        $this->client->set($key, $payload, 'EX', max(1, $ttlSeconds));
    }

    public function delete(string $key): void
    {
        $this->client->del([$key]);
    }

    public function ping(): bool
    {
        try {
            return (string) $this->client->ping() === 'PONG';
        } catch (\Throwable) {
            return false;
        }
    }
}
