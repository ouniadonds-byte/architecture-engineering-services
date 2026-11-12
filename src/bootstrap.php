<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/bootstrap.php
 * Rôle    : Point d'initialisation commun à tous les scripts PHP :
 *           configuration, fuseau horaire, gestion des erreurs, session
 *           sécurisée et chargement automatique des classes (autoload).
 */
declare(strict_types=1);

// 1. Configuration (tableau retourné par config/config.php)
$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration manquante : copiez config/config.example.php en config/config.php.');
}
$config = require $configFile;

date_default_timezone_set($config['app']['timezone']);

// 2. Erreurs : jamais affichées au visiteur en production (fuite d'informations)
error_reporting(E_ALL);
ini_set('display_errors', $config['app']['debug'] ? '1' : '0');
ini_set('log_errors', '1');

// 3. Fonctions d'affichage partagées (e(), usd(), frDate())
require_once __DIR__ . '/helpers.php';

// Autoload : « new Database » charge automatiquement src/Database.php
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 4. Session avec cookie sécurisé (nécessaire pour le jeton CSRF)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,      // cookie inaccessible au JavaScript (anti-XSS)
        'cookie_samesite' => 'Lax',     // non envoyé par les requêtes d'autres sites (anti-CSRF)
        'use_strict_mode' => true,      // refuse les identifiants de session inventés
    ]);
}

// 5. Initialisation de la connexion (une seule fois, patron Singleton)
Database::configure($config['db']);
