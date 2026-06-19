<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Levée quand une variable d'environnement obligatoire est absente.
 */
final class MissingConfigException extends \RuntimeException
{
}
