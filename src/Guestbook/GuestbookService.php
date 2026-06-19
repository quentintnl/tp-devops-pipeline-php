<?php

declare(strict_types=1);

namespace App\Guestbook;

use App\Cache\CacheInterface;

/**
 * Cœur métier du livre d'or. Orchestre dépôt (DB) et cache (Redis) via leurs
 * interfaces — entièrement testable sans services Docker.
 *
 *  - recordView()     : incrémente le compteur de vues (Redis).
 *  - recentMessages() : sert le cache si présent, sinon lit la DB puis peuple le cache.
 *  - addMessage()     : valide + persiste (DB) + invalide le cache liste.
 */
final class GuestbookService
{
    public const VIEW_COUNTER_KEY = 'guestbook:views';
    public const RECENT_LIST_KEY = 'guestbook:recent';
    public const RECENT_LIMIT = 5;
    public const RECENT_TTL_SECONDS = 30;

    public function __construct(
        private readonly MessageRepositoryInterface $repository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Compteur de vues de la page d'accueil. Retourne la valeur après incrément.
     */
    public function recordView(): int
    {
        return $this->cache->increment(self::VIEW_COUNTER_KEY);
    }

    /**
     * Les derniers messages (au plus RECENT_LIMIT). Cache best-effort : un cache
     * touché est servi tel quel ; un miss déclenche une lecture DB puis peuple
     * le cache pour les requêtes suivantes.
     *
     * @return list<Message>
     */
    public function recentMessages(): array
    {
        $cached = $this->cache->get(self::RECENT_LIST_KEY);
        if (is_array($cached)) {
            return array_map(
                static fn (array $row): Message => Message::fromArray($row),
                $cached,
            );
        }

        $messages = $this->repository->latest(self::RECENT_LIMIT);

        $this->cache->set(
            self::RECENT_LIST_KEY,
            array_map(static fn (Message $m): array => $m->toArray(), $messages),
            self::RECENT_TTL_SECONDS,
        );

        return $messages;
    }

    /**
     * Ajoute un message : validation (Message::create) → persistance DB →
     * invalidation du cache liste (la prochaine lecture rechargera depuis la DB).
     *
     * @throws InvalidMessageException si l'entrée est invalide
     */
    public function addMessage(string $author, string $body): Message
    {
        $message = Message::create($author, $body);

        $this->repository->add($message);
        $this->cache->delete(self::RECENT_LIST_KEY);

        return $message;
    }
}
