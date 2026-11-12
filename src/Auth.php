<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/Auth.php
 * Rôle    : Authentification de l'administrateur.
 *           - mots de passe vérifiés avec password_verify() (bcrypt) ;
 *           - verrouillage du compte après 5 échecs (anti force brute) ;
 *           - régénération de l'identifiant de session à la connexion
 *             (anti fixation de session) ;
 *           - déconnexion automatique après 30 min d'inactivité.
 */
declare(strict_types=1);

final class Auth
{
    private const MAX_ATTEMPTS   = 5;
    private const LOCK_MINUTES   = 15;
    private const IDLE_TIMEOUT   = 1800;     // secondes (30 min)
    private const SESSION_KEY    = 'admin';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Tente une connexion.
     *
     * @return string|null null si succès, sinon le message d'erreur à afficher
     */
    public function attempt(string $username, string $password): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, password_hash, failed_attempts, locked_until
               FROM admins WHERE username = :username'
        );
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        // Message volontairement identique (utilisateur inconnu ou mauvais mot
        // de passe) : on ne révèle pas quels identifiants existent.
        $genericError = 'Identifiant ou mot de passe incorrect.';

        if ($admin === false) {
            // On calcule quand même un hash : le temps de réponse reste identique
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return $genericError;
        }

        if ($admin['locked_until'] !== null && new DateTimeImmutable($admin['locked_until']) > new DateTimeImmutable()) {
            return 'Compte temporairement verrouillé après plusieurs échecs. Réessayez dans ' . self::LOCK_MINUTES . ' minutes.';
        }

        if (!password_verify($password, $admin['password_hash'])) {
            $this->registerFailure((int) $admin['id'], (int) $admin['failed_attempts'] + 1);
            return $genericError;
        }

        // Succès : remise à zéro des échecs et date de dernière connexion
        $this->pdo->prepare(
            'UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id'
        )->execute([':id' => $admin['id']]);

        // Nouvel identifiant de session : empêche la fixation de session
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = [
            'id'        => (int) $admin['id'],
            'username'  => $admin['username'],
            'last_seen' => time(),
        ];

        return null;
    }

    private function registerFailure(int $adminId, int $attempts): void
    {
        $lock = $attempts >= self::MAX_ATTEMPTS;
        $stmt = $this->pdo->prepare(
            'UPDATE admins
                SET failed_attempts = :attempts,
                    locked_until = IF(:lock = 1, NOW() + INTERVAL ' . self::LOCK_MINUTES . ' MINUTE, NULL)
              WHERE id = :id'
        );
        $stmt->execute([
            ':attempts' => $lock ? 0 : $attempts,    // compteur remis à zéro une fois verrouillé
            ':lock'     => $lock ? 1 : 0,
            ':id'       => $adminId,
        ]);
    }

    /** L'administrateur est-il connecté (et actif depuis moins de 30 min) ? */
    public static function check(): bool
    {
        $admin = $_SESSION[self::SESSION_KEY] ?? null;
        if ($admin === null) {
            return false;
        }
        if (time() - $admin['last_seen'] > self::IDLE_TIMEOUT) {
            self::logout();
            return false;
        }
        $_SESSION[self::SESSION_KEY]['last_seen'] = time();
        return true;
    }

    /** À placer en haut de chaque page protégée. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function username(): string
    {
        return $_SESSION[self::SESSION_KEY]['username'] ?? '';
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }
}
