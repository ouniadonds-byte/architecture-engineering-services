<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : admin/login.php
 * Rôle    : Page de connexion de l'administrateur.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/_layout.php';

// Déjà connecté : direction le tableau de bord
if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
        $error = 'Session expirée, veuillez réessayer.';
    } elseif ($username === '' || $password === '') {
        $error = 'Veuillez remplir les deux champs.';
    } else {
        try {
            $error = (new Auth(Database::getConnection()))->attempt($username, $password);
        } catch (PDOException $e) {
            error_log('[admin/login] ' . $e->getMessage());
            $error = 'Base de données indisponible. Vérifiez que MySQL est démarré.';
        }

        if ($error === null) {
            // Motif Post/Redirect/Get : évite le renvoi du formulaire avec F5
            header('Location: index.php');
            exit;
        }
    }
}

adminHeader('Connexion', false);
?>
<section class="login-card">
    <a class="logo" href="../index.html">
        <span class="logo__name">OUNIADON</span>
        <span class="logo__tag">Espace administrateur</span>
    </a>
    <h1>Connexion</h1>

    <?php if ($error !== null): ?>
        <p class="admin-alert admin-alert--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <label for="username">Identifiant</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button class="admin-btn" type="submit">Se connecter</button>
    </form>
</section>
<?php
adminFooter();
