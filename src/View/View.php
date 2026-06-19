<?php

declare(strict_types=1);

namespace App\View;

use App\Guestbook\Message;

/**
 * Rendu HTML server-side du livre d'or.
 *
 * SÉCURITÉ : toute donnée d'origine utilisateur (auteur, texte) passe par
 * {@see self::escape()} (htmlspecialchars) avant injection dans le HTML →
 * protection contre le XSS. Aucune sortie ne contourne cet échappement.
 */
final class View
{
    /**
     * Échappe une chaîne pour une insertion sûre dans du HTML.
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Page d'accueil : compteur de vues (Redis) + formulaire + liste (DB/cache).
     *
     * @param list<Message> $messages
     */
    public static function home(int $views, array $messages): string
    {
        $items = '';
        if ($messages === []) {
            $items = '<li class="empty">Aucun message pour l\'instant. Soyez le premier !</li>';
        } else {
            foreach ($messages as $message) {
                $items .= self::messageItem($message);
            }
        }

        $viewsLabel = self::escape((string) $views);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Livre d'or</title>
                <style>
                    body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
                    .views { color: #555; }
                    form { display: grid; gap: .5rem; margin: 1.5rem 0; }
                    input, textarea { padding: .5rem; font: inherit; }
                    button { padding: .5rem 1rem; font: inherit; cursor: pointer; }
                    ul { list-style: none; padding: 0; }
                    li { border-top: 1px solid #ddd; padding: .75rem 0; }
                    li .meta { color: #777; font-size: .85rem; }
                    li.empty { color: #999; }
                </style>
            </head>
            <body>
                <h1>Livre d'or</h1>
                <p class="views">Page vue <strong>{$viewsLabel}</strong> fois.</p>

                <form method="post" action="/">
                    <input type="text" name="author" placeholder="Votre nom" maxlength="60" required>
                    <textarea name="body" placeholder="Votre message" rows="3" maxlength="1000" required></textarea>
                    <button type="submit">Signer le livre d'or</button>
                </form>

                <h2>Derniers messages</h2>
                <ul>
                    {$items}
                </ul>
            </body>
            </html>
            HTML;
    }

    private static function messageItem(Message $message): string
    {
        $author = self::escape($message->author);
        $body = nl2br(self::escape($message->body));
        $date = self::escape($message->createdAt->format('d/m/Y H:i'));

        return <<<HTML
            <li>
                <div>{$body}</div>
                <div class="meta">— {$author}, le {$date}</div>
            </li>
            HTML;
    }
}
