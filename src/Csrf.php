<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/Csrf.php
 * Rôle    : Protection contre la falsification de requêtes inter-sites
 *           (CSRF). Un jeton aléatoire est stocké en session et inséré
 *           dans le formulaire ; le serveur refuse tout envoi sans le
 *           bon jeton (un site pirate ne peut pas le connaître).
 */
declare(strict_types=1);

final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /** Retourne le jeton de la session (créé au premier appel). */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            // 32 octets aléatoires cryptographiquement sûrs = 64 caractères hexadécimaux
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Vérifie le jeton reçu. hash_equals évite les attaques temporelles. */
    public static function isValid(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION[self::SESSION_KEY])
            && hash_equals($_SESSION[self::SESSION_KEY], $token);
    }
}
