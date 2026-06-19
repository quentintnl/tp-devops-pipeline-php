<?php

declare(strict_types=1);

namespace App\Guestbook;

/**
 * Implémentation PDO (MariaDB/MySQL) du dépôt de messages.
 *
 * Toutes les requêtes sont préparées (paramétrées) — aucune concaténation de
 * valeurs dans le SQL (anti-injection).
 */
final class PdoMessageRepository implements MessageRepositoryInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function add(Message $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (author, body, created_at) VALUES (:author, :body, :created_at)'
        );
        $stmt->execute([
            ':author' => $message->author,
            ':body' => $message->body,
            ':created_at' => $message->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<Message>
     */
    public function latest(int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT author, body, created_at FROM messages ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', max(0, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        $messages = [];
        /** @var array{author: string, body: string, created_at: string} $row */
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $messages[] = Message::fromArray($row);
        }

        return $messages;
    }

    public function ping(): bool
    {
        try {
            $this->pdo->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
