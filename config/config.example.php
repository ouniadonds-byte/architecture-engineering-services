<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : config/config.example.php
 * Rôle    : MODÈLE de configuration (versionné sur GitHub, sans secret).
 *           Copiez-le en « config.php » et renseignez vos identifiants.
 *           config.php est exclu de Git (.gitignore).
 */
declare(strict_types=1);

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'archi_services',
        'user'     => 'archi_app',                  // compte à privilèges limités (app_user.sql)
        'password' => 'REMPLACEZ_MOI',
        'charset'  => 'utf8mb4',
    ],
    'app' => [
        'debug'            => false,                // true en local pour voir les erreurs détaillées
        'timezone'         => 'Africa/Lome',
        'min_submit_delay' => 30,                   // secondes minimum entre deux envois (anti-spam)
    ],
];
