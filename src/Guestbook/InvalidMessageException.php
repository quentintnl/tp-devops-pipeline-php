<?php

declare(strict_types=1);

namespace App\Guestbook;

/**
 * Levée quand un message ne respecte pas les règles de validation
 * (auteur/texte vide ou trop long). Fail Fast : on refuse, on ne tronque pas.
 */
final class InvalidMessageException extends \InvalidArgumentException
{
}
