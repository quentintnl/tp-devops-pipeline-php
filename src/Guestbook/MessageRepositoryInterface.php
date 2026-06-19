<?php

declare(strict_types=1);

namespace App\Guestbook;

/**
 * Contrat de persistance des messages.
 *
 * Le service métier dépend de cette interface, jamais d'une implémentation
 * concrète → testable avec un fake in-memory, sans aucune I/O réseau.
 */
interface MessageRepositoryInterface
{
    /**
     * Persiste un message.
     *
     * @throws \RuntimeException en cas d'échec de stockage
     */
    public function add(Message $message): void;

    /**
     * Retourne les N derniers messages, du plus récent au plus ancien.
     *
     * @return list<Message>
     */
    public function latest(int $limit): array;

    /**
     * Sonde de disponibilité (readiness). True si le stockage répond.
     */
    public function ping(): bool;
}
