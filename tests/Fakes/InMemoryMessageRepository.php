<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Guestbook\Message;
use App\Guestbook\MessageRepositoryInterface;

/**
 * Dépôt en mémoire pour les tests — aucune I/O. Permet aussi de simuler une
 * panne de stockage (ping false) pour tester la readiness.
 */
final class InMemoryMessageRepository implements MessageRepositoryInterface
{
    /** @var list<Message> */
    public array $messages = [];

    public function __construct(private readonly bool $healthy = true)
    {
    }

    public function add(Message $message): void
    {
        // On insère en tête : le plus récent d'abord (comme ORDER BY id DESC).
        array_unshift($this->messages, $message);
    }

    public function latest(int $limit): array
    {
        return array_slice($this->messages, 0, max(0, $limit));
    }

    public function ping(): bool
    {
        return $this->healthy;
    }
}
