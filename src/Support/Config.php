<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lecture de la configuration depuis l'environnement (getenv).
 *
 * Fail Fast : `require()` lève une exception si une variable obligatoire est
 * absente plutôt que de laisser l'app démarrer dans un état incohérent.
 */
final class Config
{
    /**
     * Récupère une variable obligatoire. Lève si absente ou vide.
     *
     * @throws MissingConfigException
     */
    public static function require(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new MissingConfigException(
                "Variable d'environnement requise manquante : {$key}"
            );
        }

        return $value;
    }

    /**
     * Récupère une variable optionnelle, avec valeur par défaut.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * Récupère une variable obligatoire et la convertit en entier.
     *
     * @throws MissingConfigException si absente
     */
    public static function requireInt(string $key): int
    {
        return (int) self::require($key);
    }

    /**
     * Variable entière optionnelle avec défaut.
     */
    public static function getInt(string $key, int $default): int
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return (int) $value;
    }
}
