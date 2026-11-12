<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : api/submit-request.php
 * Rôle    : Point d'entrée du formulaire (appelé en fetch par
 *           form-validation.js). Contrôles de sécurité, validation
 *           serveur, insertion en base, réponse JSON.
 *
 * Codes HTTP renvoyés :
 *   201 Created               demande enregistrée
 *   403 Forbidden             jeton CSRF absent ou invalide
 *   405 Method Not Allowed    autre méthode que POST
 *   422 Unprocessable Entity  données invalides (détail par champ)
 *   429 Too Many Requests     envois trop rapprochés
 *   500 Internal Server Error erreur inattendue (détail dans les logs)
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** Envoie une réponse JSON avec le code HTTP donné, puis arrête le script. */
function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Seule la méthode POST est acceptée
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['success' => false, 'message' => 'Méthode non autorisée.']);
}

// 2. Jeton CSRF : prouve que l'envoi vient bien de notre formulaire
if (!Csrf::isValid($_POST['csrf_token'] ?? null)) {
    respond(403, ['success' => false, 'message' => 'Session expirée. Rechargez la page puis renvoyez le formulaire.']);
}

// 3. Honeypot : un robot remplit le champ caché. On simule un succès sans rien enregistrer.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    respond(201, ['success' => true, 'message' => 'Merci ! Votre demande a bien été envoyée.']);
}

// 4. Limitation de fréquence (par session) contre les envois répétés
$delay = (int) $config['app']['min_submit_delay'];
$lastSubmit = $_SESSION['last_submit_at'] ?? 0;
if (time() - $lastSubmit < $delay) {
    $wait = $delay - (time() - $lastSubmit);
    respond(429, ['success' => false, 'message' => "Merci de patienter {$wait} secondes avant un nouvel envoi."]);
}

try {
    $request = new ClientRequest(Database::getConnection());

    // 5. Validation serveur (la validation JavaScript peut être contournée)
    if (!$request->validate($_POST)) {
        respond(422, [
            'success' => false,
            'message' => 'Certains champs sont invalides. Veuillez les corriger.',
            'errors'  => $request->getErrors(),
        ]);
    }

    // 6. Enregistrement (transaction + requêtes préparées)
    $requestId = $request->save($_SERVER['REMOTE_ADDR'] ?? null);
    $_SESSION['last_submit_at'] = time();

    $messages = [
        'custom_project'      => 'Merci ! Votre demande de projet est enregistrée. Vous recevrez un devis sous 48 h.',
        'plan_purchase'       => 'Merci ! Votre commande de plan est enregistrée. Les modalités de paiement vous seront envoyées sous 48 h.',
        'training_enrollment' => 'Merci ! Votre inscription est enregistrée. Vous recevrez le programme et les dates sous 48 h.',
    ];

    respond(201, [
        'success'   => true,
        'message'   => $messages[$request->getType()] . ' Référence : #' . str_pad((string) $requestId, 5, '0', STR_PAD_LEFT),
        'requestId' => $requestId,
    ]);
} catch (Throwable $e) {
    // Le détail technique va dans le journal du serveur, jamais au visiteur
    error_log('[submit-request] ' . $e::class . ': ' . $e->getMessage());
    respond(500, [
        'success' => false,
        'message' => 'Une erreur technique est survenue. Veuillez réessayer dans quelques instants.',
    ]);
}
