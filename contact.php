<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : contact.php
 * Rôle    : Formulaire de demande (projet sur mesure, achat de plan,
 *           inscription à une formation). Validation stricte côté client
 *           (assets/js/form-validation.js) puis côté serveur
 *           (api/submit-request.php -> src/ClientRequest.php).
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

/*
 * Catalogues des listes déroulantes, lus en base via la classe Catalog (PDO).
 * Si la base est injoignable, la page s'affiche quand même avec un message.
 */
$plans = [];
$trainings = [];
$catalogError = false;

try {
    $catalog   = new Catalog(Database::getConnection());
    $plans     = $catalog->getPlans();
    $trainings = $catalog->getTrainings();
} catch (PDOException $e) {
    error_log('[contact] ' . $e->getMessage());
    $catalogError = true;
}

// Jeton anti-CSRF inséré dans un champ caché du formulaire
$csrfToken = Csrf::token();

/*
 * Présélection depuis l'URL (ex. contact.php?type=plan_purchase&plan=2).
 * Les valeurs de $_GET ne sont JAMAIS affichées telles quelles : on les
 * compare à une liste blanche (in_array strict) ou on les convertit en entier.
 */
$type = $_GET['type'] ?? 'custom_project';
if (!in_array($type, ClientRequest::TYPES, true)) {
    $type = 'custom_project';
}
$selectedPlan     = filter_input(INPUT_GET, 'plan', FILTER_VALIDATE_INT) ?: 0;
$selectedTraining = filter_input(INPUT_GET, 'training', FILTER_VALIDATE_INT) ?: 0;

/** Échappe une valeur pour l'affichage HTML (protection XSS). */
function e(string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Formate un prix en dollars : 1200 -> "$1 200". */
function usd(float $amount): string
{
    return '$' . number_format($amount, 0, ',', ' ');
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr" class="no-js">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Demandez un devis, commandez un plan prêt à bâtir ou inscrivez-vous à une formation Archicad, Lumion, Revit ou Robot.">
    <meta name="author" content="OUNIADON Hairoulah">
    <meta name="theme-color" content="#0a0b0d">
    <title>Contact · OUNIADON Architecture &amp; Ingénierie</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Inter:wght@400;500;600&display=swap">

    <link rel="stylesheet" href="assets/css/base.css">
    <link rel="stylesheet" href="assets/css/layout.css">
    <link rel="stylesheet" href="assets/css/components.css">

    <script src="assets/js/main.js" defer></script>
    <script src="assets/js/form-validation.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu</a>

    <!-- ================= EN-TÊTE ================= -->
    <header class="site-header" id="site-header">
        <div class="container site-header__inner">
            <a href="index.html" class="logo" aria-label="OUNIADON, retour à l'accueil">
                <span class="logo__name">OUNIADON</span>
                <span class="logo__tag">Architecture &amp; Ingénierie</span>
            </a>

            <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Ouvrir le menu">
                <span class="nav-toggle__bar"></span>
                <span class="nav-toggle__bar"></span>
                <span class="nav-toggle__bar"></span>
            </button>

            <nav class="site-nav" id="site-nav" aria-label="Navigation principale">
                <ul class="site-nav__list" role="list">
                    <li><a class="site-nav__link" href="index.html">Accueil</a></li>
                    <li><a class="site-nav__link" href="index.html#services">Services</a></li>
                    <li><a class="site-nav__link" href="portfolio.html">Portfolio</a></li>
                    <li><a class="site-nav__link" href="index.html#plans">Plans</a></li>
                    <li><a class="site-nav__link" href="index.html#formations">Formations</a></li>
                    <li><a class="btn btn--primary site-nav__cta" href="contact.php" aria-current="page">Démarrer un projet</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main id="main">
        <section class="page-hero" style="min-height:min(45vh, 480px)" aria-labelledby="page-title">
            <img class="page-hero__bg" src="assets/images/contact-bg.webp" alt="" width="1600" height="1067">
            <div class="container page-hero__content">
                <span class="eyebrow">Contact</span>
                <h1 id="page-title">Parlons de votre <em class="accent">projet</em></h1>
                <p class="lead">Devis sur mesure, achat de plan ou inscription à une formation : un seul formulaire.</p>
            </div>
        </section>

        <section class="section" style="padding-top: var(--space-lg)">
            <div class="container contact-layout">
                <!-- ===== Colonne d'information ===== -->
                <aside class="contact-aside" aria-label="Informations pratiques">
                    <h2 style="font-size:var(--fs-h3)">Comment ça marche ?</h2>
                    <ul role="list">
                        <li><strong>1. Choisissez votre demande</strong>Le formulaire s'adapte automatiquement.</li>
                        <li><strong>2. Décrivez votre besoin</strong>Plus vous êtes précis, plus notre réponse le sera.</li>
                        <li><strong>3. Réponse sous 48 h</strong>Par e-mail, avec un devis ou les modalités de paiement.</li>
                    </ul>
                    <p class="field__hint">Les champs marqués <span class="req" style="color:var(--clr-gold)">*</span> sont obligatoires. Vos données servent uniquement à traiter votre demande.</p>
                </aside>

                <!-- ===== Formulaire =====
                     novalidate : on désactive les bulles natives du navigateur pour
                     afficher nos propres messages (validation JavaScript stricte).
                     Les attributs HTML5 (required, pattern, min, max...) restent
                     présents : ils documentent les règles et servent de filet de sécurité. -->
                <form class="form" id="request-form" action="api/submit-request.php" method="post" novalidate>
                    <!-- Jeton anti-CSRF : vérifié par le serveur à chaque envoi -->
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <?php if ($catalogError): ?>
                        <p class="form-status is-error" role="alert">Le catalogue est momentanément indisponible. Vous pouvez tout de même envoyer une demande de projet sur mesure.</p>
                    <?php endif; ?>

                    <!-- ---------- 1. Type de demande ---------- -->
                    <fieldset>
                        <legend><span>01</span>Votre demande</legend>
                        <div class="choice-grid" id="type-choices">
                            <div class="choice">
                                <input type="radio" id="type-custom" name="request_type" value="custom_project" <?= $type === 'custom_project' ? 'checked' : '' ?>>
                                <label for="type-custom"><strong>Projet sur mesure</strong><span>Conception, 3D, structure, BIM</span></label>
                            </div>
                            <div class="choice">
                                <input type="radio" id="type-plan" name="request_type" value="plan_purchase" <?= $type === 'plan_purchase' ? 'checked' : '' ?>>
                                <label for="type-plan"><strong>Plan prêt à bâtir</strong><span>Villa, duplex, immeuble</span></label>
                            </div>
                            <div class="choice">
                                <input type="radio" id="type-training" name="request_type" value="training_enrollment" <?= $type === 'training_enrollment' ? 'checked' : '' ?>>
                                <label for="type-training"><strong>Formation</strong><span>Archicad, Lumion, Revit, Robot</span></label>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ---------- 2. Coordonnées ---------- -->
                    <fieldset>
                        <legend><span>02</span>Vos coordonnées</legend>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="full_name">Nom complet <span class="req">*</span></label>
                                <input type="text" id="full_name" name="full_name" autocomplete="name" required minlength="3" maxlength="120" aria-describedby="full_name-error">
                                <span class="field__error" id="full_name-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="email">E-mail <span class="req">*</span></label>
                                <input type="email" id="email" name="email" autocomplete="email" inputmode="email" required maxlength="254" aria-describedby="email-error">
                                <span class="field__error" id="email-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="phone">Téléphone (WhatsApp)</label>
                                <input type="tel" id="phone" name="phone" autocomplete="tel" inputmode="tel" placeholder="+228 90 00 00 00" maxlength="20" aria-describedby="phone-error">
                                <span class="field__error" id="phone-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="country">Pays</label>
                                <input type="text" id="country" name="country" autocomplete="country-name" maxlength="80" aria-describedby="country-error">
                                <span class="field__error" id="country-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="city">Ville</label>
                                <input type="text" id="city" name="city" autocomplete="address-level2" maxlength="80" aria-describedby="city-error">
                                <span class="field__error" id="city-error" aria-live="polite"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ---------- 3a. Projet sur mesure ---------- -->
                    <fieldset data-type="custom_project">
                        <legend><span>03</span>Votre projet</legend>
                        <div class="form-grid">
                            <div class="field">
                                <label for="project_type">Prestation souhaitée <span class="req">*</span></label>
                                <select id="project_type" name="project_type" required aria-describedby="project_type-error">
                                    <option value="">Choisir…</option>
                                    <option value="architectural_design">Conception architecturale</option>
                                    <option value="3d_visualization">Visualisation 3D</option>
                                    <option value="structural_study">Étude structurelle</option>
                                    <option value="bim_modeling">Modélisation BIM</option>
                                    <option value="renovation">Rénovation</option>
                                    <option value="interior_design">Architecture intérieure</option>
                                </select>
                                <span class="field__error" id="project_type-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="building_use">Usage du bâtiment <span class="req">*</span></label>
                                <select id="building_use" name="building_use" required aria-describedby="building_use-error">
                                    <option value="residential">Résidentiel</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="institutional">Institutionnel</option>
                                    <option value="mixed">Mixte</option>
                                </select>
                                <span class="field__error" id="building_use-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="location">Localisation du terrain</label>
                                <input type="text" id="location" name="location" maxlength="150" placeholder="Quartier, ville" aria-describedby="location-error">
                                <span class="field__error" id="location-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="land_area_m2">Surface du terrain (m²)</label>
                                <input type="number" id="land_area_m2" name="land_area_m2" min="1" max="1000000" step="0.01" inputmode="decimal" aria-describedby="land_area_m2-error">
                                <span class="field__error" id="land_area_m2-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="budget_min">Budget minimum (USD)</label>
                                <input type="number" id="budget_min" name="budget_min" min="0" step="100" inputmode="numeric" aria-describedby="budget_min-error">
                                <span class="field__error" id="budget_min-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="budget_max">Budget maximum (USD)</label>
                                <input type="number" id="budget_max" name="budget_max" min="0" step="100" inputmode="numeric" aria-describedby="budget_max-error">
                                <span class="field__error" id="budget_max-error" aria-live="polite"></span>
                            </div>
                            <div class="field field--full">
                                <label for="desired_deadline">Date de livraison souhaitée</label>
                                <input type="date" id="desired_deadline" name="desired_deadline" min="<?= e($today) ?>" aria-describedby="desired_deadline-error">
                                <span class="field__error" id="desired_deadline-error" aria-live="polite"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ---------- 3b. Achat de plan ---------- -->
                    <fieldset data-type="plan_purchase">
                        <legend><span>03</span>Votre plan</legend>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="plan_id">Plan choisi <span class="req">*</span></label>
                                <select id="plan_id" name="plan_id" required aria-describedby="plan_id-error">
                                    <option value="">Choisir un plan…</option>
                                    <?php foreach ($plans as $plan): ?>
                                        <option value="<?= e($plan['id']) ?>" <?= (int) $plan['id'] === $selectedPlan ? 'selected' : '' ?>>
                                            <?= e($plan['title']) ?> — <?= e(usd((float) $plan['price'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="field__error" id="plan_id-error" aria-live="polite"></span>
                            </div>
                            <div class="field field--full">
                                <span class="label" id="license-label">Licence <span class="req">*</span></span>
                                <div class="inline-options" role="radiogroup" aria-labelledby="license-label">
                                    <label class="check"><input type="radio" name="license_type" value="single_build" checked> Construction unique</label>
                                    <label class="check"><input type="radio" name="license_type" value="multi_build"> Constructions multiples</label>
                                </div>
                            </div>
                            <div class="field field--full">
                                <label class="check"><input type="checkbox" name="needs_customization" value="1"> Je souhaite personnaliser ce plan (devis complémentaire)</label>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ---------- 3c. Inscription formation ---------- -->
                    <fieldset data-type="training_enrollment">
                        <legend><span>03</span>Votre formation</legend>
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="training_id">Formation choisie <span class="req">*</span></label>
                                <select id="training_id" name="training_id" required aria-describedby="training_id-error">
                                    <option value="">Choisir une formation…</option>
                                    <?php foreach ($trainings as $training): ?>
                                        <option value="<?= e($training['id']) ?>" <?= (int) $training['id'] === $selectedTraining ? 'selected' : '' ?>>
                                            <?= e($training['title']) ?> — <?= e($training['duration_hours']) ?> h — <?= e(usd((float) $training['price'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="field__error" id="training_id-error" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <span class="label" id="mode-label">Mode de suivi <span class="req">*</span></span>
                                <div class="inline-options" role="radiogroup" aria-labelledby="mode-label">
                                    <label class="check"><input type="radio" name="delivery_mode" value="online" checked> En ligne</label>
                                    <label class="check"><input type="radio" name="delivery_mode" value="onsite"> En présentiel</label>
                                </div>
                            </div>
                            <div class="field">
                                <label for="current_level">Votre niveau actuel <span class="req">*</span></label>
                                <select id="current_level" name="current_level" required aria-describedby="current_level-error">
                                    <option value="beginner">Débutant</option>
                                    <option value="intermediate">Intermédiaire</option>
                                    <option value="advanced">Avancé</option>
                                </select>
                                <span class="field__error" id="current_level-error" aria-live="polite"></span>
                            </div>
                            <div class="field field--full">
                                <label for="preferred_start">Date de début souhaitée</label>
                                <input type="date" id="preferred_start" name="preferred_start" min="<?= e($today) ?>" aria-describedby="preferred_start-error">
                                <span class="field__error" id="preferred_start-error" aria-live="polite"></span>
                            </div>
                        </div>
                    </fieldset>

                    <!-- ---------- 4. Message et consentement ---------- -->
                    <fieldset>
                        <legend><span>04</span>Votre message</legend>
                        <div class="field">
                            <label for="message">Message <span class="req" id="message-req">*</span></label>
                            <textarea id="message" name="message" maxlength="2000" placeholder="Décrivez votre projet, vos attentes, vos contraintes…" aria-describedby="message-error message-count"></textarea>
                            <span class="char-count" id="message-count" aria-live="polite">0 / 2000</span>
                            <span class="field__error" id="message-error" aria-live="polite"></span>
                        </div>

                        <!-- Honeypot anti-spam : un humain ne voit pas ce champ, un robot le remplit -->
                        <div class="hp-field" aria-hidden="true">
                            <label for="website">Ne pas remplir</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="field">
                            <label class="check">
                                <input type="checkbox" id="consent_given" name="consent_given" value="1" required aria-describedby="consent_given-error">
                                <span>J'accepte que mes données soient utilisées pour traiter ma demande. <span class="req">*</span></span>
                            </label>
                            <span class="field__error" id="consent_given-error" aria-live="polite"></span>
                        </div>
                    </fieldset>

                    <div class="form__actions">
                        <p class="form-status" id="form-status" role="status" aria-live="polite"></p>
                        <button class="btn btn--primary" type="submit" id="submit-btn">Envoyer ma demande</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <!-- ================= PIED DE PAGE ================= -->
    <footer class="site-footer">
        <div class="container">
            <div class="site-footer__grid">
                <div>
                    <a href="index.html" class="logo">
                        <span class="logo__name">OUNIADON</span>
                        <span class="logo__tag">Architecture &amp; Ingénierie</span>
                    </a>
                    <p class="lead" style="margin-top:1rem;font-size:.95rem">Architecture, structure et visualisation 3D. Lomé · En ligne partout dans le monde.</p>
                </div>
                <nav aria-label="Liens du pied de page">
                    <h3>Navigation</h3>
                    <ul role="list">
                        <li><a href="index.html#services">Services</a></li>
                        <li><a href="portfolio.html">Portfolio</a></li>
                        <li><a href="index.html#plans">Plans</a></li>
                        <li><a href="index.html#formations">Formations</a></li>
                    </ul>
                </nav>
                <div>
                    <h3>Contact</h3>
                    <ul role="list">
                        <li><a href="contact.php">Formulaire de contact</a></li>
                        <li><a href="https://github.com/ouniadonds-byte" target="_blank" rel="noopener">GitHub</a></li>
                    </ul>
                </div>
            </div>
            <div class="site-footer__bottom">
                <p>© <span data-year><?= e(date('Y')) ?></span> OUNIADON Hairoulah. Tous droits réservés.</p>
                <p>Projet académique · ID 411030513</p>
            </div>
        </div>
    </footer>
</body>
</html>
