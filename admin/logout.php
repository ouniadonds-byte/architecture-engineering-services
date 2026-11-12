<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : admin/logout.php
 * Rôle    : Déconnexion (POST + jeton CSRF uniquement).
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::isValid($_POST['csrf_token'] ?? null)) {
    Auth::logout();
}

header('Location: login.php');
exit;
