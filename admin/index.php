<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : admin/index.php
 * Rôle    : Tableau de bord — chiffres clés et liste filtrable des
 *           demandes reçues.
 */
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/_layout.php';

Auth::requireLogin();

// Filtres reçus en GET (validés par liste blanche dans RequestRepository)
$type   = (string) ($_GET['type'] ?? '');
$status = (string) ($_GET['status'] ?? '');
$query  = trim((string) ($_GET['q'] ?? ''));

$repo = new RequestRepository(Database::getConnection());
$stats = $repo->getStats();
$requests = $repo->search($type, $status, mb_substr($query, 0, 100));

adminHeader('Tableau de bord');
?>
<h1>Tableau de bord</h1>

<!-- ===== Chiffres clés ===== -->
<ul class="stat-tiles" role="list">
    <li class="stat-tile"><span class="stat-tile__value"><?= e($stats['total']) ?></span><span>demandes au total</span></li>
    <li class="stat-tile stat-tile--accent"><span class="stat-tile__value"><?= e($stats['new_count']) ?></span><span>nouvelles à traiter</span></li>
    <li class="stat-tile"><span class="stat-tile__value"><?= e($stats['projects']) ?></span><span>projets sur mesure</span></li>
    <li class="stat-tile"><span class="stat-tile__value"><?= e($stats['plans']) ?></span><span>achats de plans</span></li>
    <li class="stat-tile"><span class="stat-tile__value"><?= e($stats['trainings']) ?></span><span>inscriptions</span></li>
</ul>

<!-- ===== Filtres (formulaire GET : l'URL reste partageable) ===== -->
<form class="admin-filters" method="get" role="search">
    <label class="visually-hidden" for="f-type">Type</label>
    <select id="f-type" name="type">
        <option value="">Tous les types</option>
        <?php foreach (Labels::REQUEST_TYPES as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $type === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <label class="visually-hidden" for="f-status">Statut</label>
    <select id="f-status" name="status">
        <option value="">Tous les statuts</option>
        <?php foreach (Labels::STATUSES as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>

    <label class="visually-hidden" for="f-q">Rechercher</label>
    <input type="search" id="f-q" name="q" value="<?= e($query) ?>" placeholder="Nom ou e-mail…" maxlength="100">

    <button class="admin-btn" type="submit">Filtrer</button>
    <?php if ($type !== '' || $status !== '' || $query !== ''): ?>
        <a class="admin-link" href="index.php">Réinitialiser</a>
    <?php endif; ?>
</form>

<!-- ===== Liste des demandes ===== -->
<?php if ($requests === []): ?>
    <p class="admin-empty">Aucune demande ne correspond à ces critères.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="admin-table">
            <caption class="visually-hidden">Demandes reçues, les plus récentes en premier</caption>
            <thead>
                <tr>
                    <th scope="col">Réf.</th>
                    <th scope="col">Date</th>
                    <th scope="col">Client</th>
                    <th scope="col">Demande</th>
                    <th scope="col">Statut</th>
                    <th scope="col"><span class="visually-hidden">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r):
                    // Pour un projet, « subject » contient le code de prestation : on le traduit
                    $subject = $r['request_type'] === 'custom_project'
                        ? Labels::of(Labels::PROJECT_TYPES, $r['subject'])
                        : $r['subject'];
                    $ref = str_pad((string) $r['request_id'], 5, '0', STR_PAD_LEFT);
                ?>
                <tr>
                    <td data-label="Réf.">#<?= e($ref) ?></td>
                    <td data-label="Date"><?= e(frDate($r['created_at'])) ?></td>
                    <td data-label="Client"><strong><?= e($r['full_name']) ?></strong><br><small><?= e($r['email']) ?></small></td>
                    <td data-label="Demande"><span class="type-tag type-tag--<?= e($r['request_type']) ?>"><?= e(Labels::of(Labels::REQUEST_TYPES, $r['request_type'])) ?></span><br><small><?= e($subject) ?></small></td>
                    <td data-label="Statut"><span class="badge badge--<?= e($r['status']) ?>"><?= e(Labels::of(Labels::STATUSES, $r['status'])) ?></span></td>
                    <td><a class="admin-link" href="request.php?id=<?= e($r['request_id']) ?>">Ouvrir →</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="admin-count"><?= count($requests) ?> demande<?= count($requests) > 1 ? 's' : '' ?> affichée<?= count($requests) > 1 ? 's' : '' ?></p>
<?php endif; ?>
<?php
adminFooter();
