<?php

declare(strict_types=1);

namespace App\Guestbook;

/**
 * Value object immuable représentant un message du livre d'or.
 *
 * La construction passe TOUJOURS par {@see self::create()} (ou
 * {@see self::fromArray()}), qui valide les invariants. Un Message qui existe
 * est donc forcément valide (auteur + texte non vides, longueurs bornées).
 */
final class Message
{
    public const MAX_AUTHOR_LENGTH = 60;
    public const MAX_BODY_LENGTH = 1000;

    private function __construct(
        public readonly string $author,
        public readonly string $body,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Crée un message validé. L'auteur et le texte sont « trimés » ;
     * le résultat doit être non vide et ne pas dépasser les longueurs max.
     *
     * @throws InvalidMessageException si invalide
     */
    public static function create(
        string $author,
        string $body,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $author = trim($author);
        $body = trim($body);

        if ($author === '') {
            throw new InvalidMessageException("L'auteur ne peut pas être vide.");
        }
        if ($body === '') {
            throw new InvalidMessageException('Le message ne peut pas être vide.');
        }
        if (mb_strlen($author) > self::MAX_AUTHOR_LENGTH) {
            throw new InvalidMessageException(
                sprintf("L'auteur dépasse %d caractères.", self::MAX_AUTHOR_LENGTH)
            );
        }
        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new InvalidMessageException(
                sprintf('Le message dépasse %d caractères.', self::MAX_BODY_LENGTH)
            );
        }

        return new self($author, $body, $createdAt ?? new \DateTimeImmutable('now'));
    }

    /**
     * Représentation sérialisable (pour le cache Redis : JSON-safe).
     *
     * @return array{author: string, body: string, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'author' => $this->author,
            'body' => $this->body,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Reconstruit un Message depuis sa forme sérialisée (cache ou ligne DB).
     *
     * @param array{author?: mixed, body?: mixed, created_at?: mixed} $row
     *
     * @throws InvalidMessageException si les données sont incohérentes
     */
    public static function fromArray(array $row): self
    {
        $author = (string) ($row['author'] ?? '');
        $body = (string) ($row['body'] ?? '');
        $createdRaw = (string) ($row['created_at'] ?? '');

        $createdAt = $createdRaw !== ''
            ? self::parseDate($createdRaw)
            : new \DateTimeImmutable('now');

        return self::create($author, $body, $createdAt);
    }

    private static function parseDate(string $raw): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception $e) {
            throw new InvalidMessageException("Date invalide : {$raw}", 0, $e);
        }
    }
}
