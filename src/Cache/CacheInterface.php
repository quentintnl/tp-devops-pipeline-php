<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Contrat de cache clé/valeur (Redis en prod, fake in-memory en test).
 *
 * Le service métier dépend de cette interface → logique cache testable sans
 * aucune I/O réseau.
 */
interface CacheInterface
{
    /**
     * Incrémente atomiquement un compteur et retourne sa nouvelle valeur.
     * La clé est créée à 1 si elle n'existe pas.
     */
    public function increment(string $key): int;

    /**
     * Lit une valeur. Retourne null si la clé est absente.
     *
     * @return mixed null si miss ; sinon la valeur désérialisée
     */
    public function get(string $key): mixed;

    /**
     * Écrit une valeur avec une durée de vie (TTL) en secondes.
     */
    public function set(string $key, mixed $value, int $ttlSeconds): void;

    /**
     * Supprime une clé (invalidation de cache). No-op si absente.
     */
    public function delete(string $key): void;

    /**
     * Sonde de disponibilité (readiness). True si le cache répond.
     */
    public function ping(): bool;
}
