<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : admin/_layout.php
 * Rôle    : Gabarit commun des pages admin (en-tête HTML, barre
 *           supérieure, pied de page) + en-têtes HTTP de sécurité.
 */
declare(strict_types=1);

// L'espace admin ne doit jamais être indexé, mis en cache ni affiché dans un cadre
header('X-Frame-Options: DENY');                 // anti « clickjacking »
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function adminHeader(string $title, bool $showBar = true): void
{
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a0b0d">
    <title><?= e($title) ?> · Admin OUNIADON</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin">
    <?php if ($showBar): ?>
    <header class="admin-bar">
        <div class="admin-container admin-bar__inner">
            <a class="logo" href="index.php">
                <span class="logo__name">OUNIADON</span>
                <span class="logo__tag">Espace administrateur</span>
            </a>
            <div class="admin-bar__user">
                <span>Connecté : <strong><?= e(Auth::username()) ?></strong></span>
                <a class="admin-link" href="../index.html" target="_blank" rel="noopener">Voir le site ↗</a>
                <!-- Déconnexion en POST + jeton CSRF : un lien piégé ne peut pas déconnecter -->
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                    <button class="admin-btn admin-btn--ghost" type="submit">Déconnexion</button>
                </form>
            </div>
        </div>
    </header>
    <?php endif; ?>
    <main class="admin-container admin-main">
    <?php
}

function adminFooter(): void
{
    ?>
    </main>
    <footer class="admin-container admin-footer">
        OUNIADON HAIROULAH · ID 411030513 · تطوير تطبيقات الويب (2561)
    </footer>
</body>
</html>
    <?php
}
