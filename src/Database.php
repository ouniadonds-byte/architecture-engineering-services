<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/Database.php
 * Rôle    : Connexion unique à MySQL/MariaDB via l'interface PDO.
 *           Patron de conception SINGLETON : une seule instance de PDO
 *           est créée et partagée, quel que soit le nombre d'appels.
 */
declare(strict_types=1);

final class Database
{
    /** Instance unique de PDO (null tant que la connexion n'est pas ouverte). */
    private static ?PDO $connection = null;

    /** Paramètres de connexion fournis par config/config.php. */
    private static array $settings = [];

    /** Constructeur privé : interdit « new Database() » depuis l'extérieur. */
    private function __construct()
    {
    }

    /** Empêche la duplication de l'instance unique. */
    private function __clone()
    {
    }

    /** Enregistre les paramètres de connexion (appelé par bootstrap.php). */
    public static function configure(array $settings): void
    {
        self::$settings = $settings;
    }

    /**
     * Retourne la connexion PDO, en la créant au premier appel (« lazy loading »).
     *
     * @throws PDOException si le serveur est injoignable ou les identifiants faux
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $s = self::$settings;

            // DSN (Data Source Name) : pilote, hôte, port, base et encodage
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $s['host'],
                $s['port'],
                $s['name'],
                $s['charset']
            );

            self::$connection = new PDO($dsn, $s['user'], $s['password'], [
                // Toute erreur SQL lève une exception (au lieu d'échouer en silence)
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Résultats sous forme de tableaux associatifs ['colonne' => valeur]
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // VRAIES requêtes préparées côté serveur : la requête et les données
                // voyagent séparément, ce qui rend l'injection SQL impossible
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$connection;
    }
}
