<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : database/create_admin.php
 * Rôle    : Crée (ou réinitialise) un compte administrateur.
 *           À lancer UNIQUEMENT en ligne de commande :
 *             C:\xampp\php\php.exe database\create_admin.php <identifiant> <mot_de_passe>
 *           Le dossier database/ est de plus interdit d'accès web (.htaccess).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Script réservé à la ligne de commande.');
}

require __DIR__ . '/../src/bootstrap.php';

[$script, $username, $password] = array_pad($argv, 3, '');

if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username) || strlen($password) < 10) {
    fwrite(STDERR, "Usage : php database/create_admin.php <identifiant> <mot_de_passe>\n");
    fwrite(STDERR, "  - identifiant : 3 à 50 caractères (lettres, chiffres, _ . -)\n");
    fwrite(STDERR, "  - mot de passe : 10 caractères minimum\n");
    exit(1);
}

// password_hash() : bcrypt avec sel aléatoire. Seule l'empreinte est stockée.
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = Database::getConnection()->prepare(
    'INSERT INTO admins (username, password_hash) VALUES (:username, :hash)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), failed_attempts = 0, locked_until = NULL'
);
$stmt->execute([':username' => $username, ':hash' => $hash]);

echo "Compte administrateur « {$username} » prêt. Connexion : http://localhost/archi-services/admin/\n";
