<?php

declare(strict_types=1);

namespace App\Health;

use App\Cache\CacheInterface;
use App\Guestbook\MessageRepositoryInterface;

/**
 * Logique des sondes de santé, séparée des routes HTTP pour rester testable.
 *
 *  - liveness()  : l'app répond (aucune dépendance) → toujours « ok ».
 *  - readiness() : DB + Redis joignables → prêt à recevoir du trafic.
 */
final class HealthChecker
{
    /**
     * @return array{status: string}
     */
    public function liveness(): array
    {
        return ['status' => 'ok'];
    }

    /**
     * Readiness : prête seulement si la DB ET le cache répondent.
     * Toute exception d'une sonde est traitée comme « non disponible ».
     *
     * @return array{ready: bool, checks: array{db: bool, redis: bool}}
     */
    public function readiness(MessageRepositoryInterface $repository, CacheInterface $cache): array
    {
        $db = $this->probe(static fn (): bool => $repository->ping());
        $redis = $this->probe(static fn (): bool => $cache->ping());

        return [
            'ready' => $db && $redis,
            'checks' => [
                'db' => $db,
                'redis' => $redis,
            ],
        ];
    }

    /**
     * @param callable():bool $probe
     */
    private function probe(callable $probe): bool
    {
        try {
            return $probe() === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
