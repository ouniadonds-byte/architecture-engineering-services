<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : admin/request.php
 * Rôle    : Fiche détaillée d'une demande + changement de statut.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/_layout.php';

Auth::requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$repo = new RequestRepository(Database::getConnection());

// ----- Changement de statut (POST + CSRF, puis redirection : motif PRG) -----
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Csrf::isValid($_POST['csrf_token'] ?? null)
        && $repo->updateStatus($id, (string) ($_POST['status'] ?? ''))) {
        header('Location: request.php?id=' . $id . '&updated=1');
    } else {
        header('Location: request.php?id=' . $id . '&error=1');
    }
    exit;
}

$request = $id ? $repo->find($id) : null;

if ($request === null) {
    http_response_code(404);
    adminHeader('Demande introuvable');
    echo '<h1>Demande introuvable</h1><p><a class="admin-link" href="index.php">← Retour au tableau de bord</a></p>';
    adminFooter();
    exit;
}

$d = $request['details'];
$ref = str_pad((string) $request['id'], 5, '0', STR_PAD_LEFT);

/** Affiche une ligne « libellé : valeur » de la fiche. */
function row(string $label, ?string $value): void
{
    echo '<div class="detail-row"><dt>' . e($label) . '</dt><dd>' . e($value ?? '—') . '</dd></div>';
}

adminHeader('Demande #' . $ref);
?>
<p><a class="admin-link" href="index.php">← Retour au tableau de bord</a></p>

<div class="request-head">
    <div>
        <h1>Demande #<?= e($ref) ?></h1>
        <p>
            <span class="type-tag type-tag--<?= e($request['request_type']) ?>"><?= e(Labels::of(Labels::REQUEST_TYPES, $request['request_type'])) ?></span>
            <span class="badge badge--<?= e($request['status']) ?>"><?= e(Labels::of(Labels::STATUSES, $request['status'])) ?></span>
        </p>
    </div>

    <!-- Changement de statut -->
    <form class="status-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <label for="status">Statut</label>
        <select id="status" name="status">
            <?php foreach (Labels::STATUSES as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $request['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="admin-btn" type="submit">Mettre à jour</button>
    </form>
</div>

<?php if (isset($_GET['updated'])): ?>
    <p class="admin-alert admin-alert--success" role="status">Statut mis à jour.</p>
<?php elseif (isset($_GET['error'])): ?>
    <p class="admin-alert admin-alert--error" role="alert">La mise à jour a échoué. Rechargez la page et réessayez.</p>
<?php endif; ?>

<div class="detail-grid">
    <section class="detail-card">
        <h2>Client</h2>
        <dl>
            <?php
            row('Nom', $request['full_name']);
            row('E-mail', $request['email']);
            row('Téléphone', $request['phone']);
            row('Pays', $request['country']);
            row('Ville', $request['city']);
            ?>
        </dl>
        <p class="detail-actions">
            <a class="admin-btn" href="mailto:<?= e($request['email']) ?>?subject=<?= e(rawurlencode('Votre demande #' . $ref)) ?>">Répondre par e-mail</a>
            <?php if ($request['phone']): ?>
                <a class="admin-btn admin-btn--ghost" href="https://wa.me/<?= e(preg_replace('/\D/', '', $request['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
        </p>
    </section>

    <section class="detail-card">
        <h2>Détails de la demande</h2>
        <dl>
            <?php
            row('Reçue le', frDate($request['created_at']));
            row('Dernière modification', frDate($request['updated_at']));

            // Champs propres au type de demande
            switch ($request['request_type']) {
                case 'custom_project':
                    row('Prestation', Labels::of(Labels::PROJECT_TYPES, $d['project_type'] ?? null));
                    row('Usage', Labels::of(Labels::BUILDING_USES, $d['building_use'] ?? null));
                    row('Localisation', $d['location'] ?? null);
                    row('Surface du terrain', isset($d['land_area_m2'])
                        ? number_format((float) $d['land_area_m2'], 0, ',', ' ') . ' m²' : null);
                    row('Budget', isset($d['budget_min']) || isset($d['budget_max'])
                        ? usd($d['budget_min'] ?? null) . ' – ' . usd($d['budget_max'] ?? null) : null);
                    row('Livraison souhaitée', isset($d['desired_deadline']) ? frDate($d['desired_deadline'], false) : null);
                    break;

                case 'plan_purchase':
                    row('Plan', ($d['reference'] ?? '') . ' — ' . ($d['plan_title'] ?? ''));
                    row('Prix au moment de la commande', usd($d['unit_price'] ?? null) . ' ' . ($d['currency'] ?? ''));
                    row('Licence', Labels::of(Labels::LICENSES, $d['license_type'] ?? null));
                    row('Personnalisation', !empty($d['needs_customization']) ? 'Oui, devis complémentaire' : 'Non');
                    break;

                case 'training_enrollment':
                    row('Formation', $d['training_title'] ?? null);
                    row('Durée / prix', ($d['duration_hours'] ?? '?') . ' h — ' . usd($d['price'] ?? null));
                    row('Mode de suivi', Labels::of(Labels::MODES, $d['delivery_mode'] ?? null));
                    row('Niveau actuel', Labels::of(Labels::LEVELS, $d['current_level'] ?? null));
                    row('Début souhaité', isset($d['preferred_start']) ? frDate($d['preferred_start'], false) : null);
                    break;
            }
            ?>
        </dl>
    </section>

    <section class="detail-card detail-card--full">
        <h2>Message du client</h2>
        <!-- nl2br après e() : les retours à la ligne sont conservés, sans risque XSS -->
        <p class="detail-message"><?= $request['message'] !== null ? nl2br(e($request['message'])) : '<em>Aucun message.</em>' ?></p>
    </section>
</div>
<?php
adminFooter();
