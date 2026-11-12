<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/ClientRequest.php
 * Rôle    : Demande client — validation côté serveur puis insertion
 *           sécurisée en base :
 *             1. validate() : contrôle et nettoie chaque champ reçu ;
 *             2. save()     : insère client + demande + détails dans une
 *                             TRANSACTION, avec des requêtes préparées PDO.
 *           La validation serveur est indispensable : la validation
 *           JavaScript peut être contournée (outils de développement,
 *           requête envoyée directement à l'API...).
 */
declare(strict_types=1);

final class ClientRequest
{
    /* ---------- Listes blanches (identiques aux ENUM de schema.sql) ---------- */
    public const TYPES          = ['custom_project', 'plan_purchase', 'training_enrollment'];
    private const PROJECT_TYPES = ['architectural_design', '3d_visualization', 'structural_study',
                                   'bim_modeling', 'renovation', 'interior_design'];
    private const BUILDING_USES = ['residential', 'commercial', 'institutional', 'mixed'];
    private const LICENSES      = ['single_build', 'multi_build'];
    private const MODES         = ['online', 'onsite'];
    private const LEVELS        = ['beginner', 'intermediate', 'advanced'];

    private const NAME_PATTERN  = "/^[\p{L}][\p{L}\s'’.-]*[\p{L}.]$/u";
    private const PLACE_PATTERN = "/^[\p{L}\d\s'’,.()-]+$/u";

    /** Données validées et nettoyées, prêtes pour l'insertion. */
    private array $data = [];

    /** Erreurs par champ : ['email' => 'Adresse e-mail invalide.'] */
    private array $errors = [];

    public function __construct(private PDO $pdo)
    {
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getType(): string
    {
        return $this->data['request_type'] ?? '';
    }

    /* =====================================================================
       1. VALIDATION
       ===================================================================== */

    /**
     * Valide les données brutes ($_POST) et remplit $this->data.
     *
     * @return bool true si aucune erreur
     */
    public function validate(array $input): bool
    {
        $this->data = [];
        $this->errors = [];

        // --- Type de demande ---
        $type = $this->text($input, 'request_type');
        if (!in_array($type, self::TYPES, true)) {
            $this->errors['request_type'] = 'Type de demande invalide.';
            return false;                       // impossible de continuer sans type
        }
        $this->data['request_type'] = $type;

        // --- Coordonnées ---
        $name = $this->text($input, 'full_name');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $this->errors['full_name'] = 'Le nom doit contenir entre 3 et 120 caractères.';
        } elseif (!preg_match(self::NAME_PATTERN, $name) || count(preg_split('/\s+/u', $name)) < 2) {
            $this->errors['full_name'] = 'Veuillez indiquer votre prénom et votre nom (lettres uniquement).';
        }
        $this->data['full_name'] = $name;

        $email = mb_strtolower($this->text($input, 'email'));
        if (mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors['email'] = 'Adresse e-mail invalide.';
        }
        $this->data['email'] = $email;

        $phone = $this->text($input, 'phone');
        $digits = preg_replace('/\D/', '', $phone);
        if ($phone !== '' && (!preg_match('/^\+?[\d\s().-]+$/', $phone) || strlen($digits) < 8 || strlen($digits) > 15)) {
            $this->errors['phone'] = 'Numéro invalide : 8 à 15 chiffres.';
        }
        $this->data['phone'] = $phone !== '' ? $phone : null;

        $this->data['country'] = $this->optionalText($input, 'country', 80, self::NAME_PATTERN, 'Nom de pays invalide.');
        $this->data['city']    = $this->optionalText($input, 'city', 80, self::PLACE_PATTERN, 'Nom de ville invalide.');

        // --- Message : obligatoire (20 caractères) pour un projet sur mesure ---
        $message = $this->text($input, 'message');
        if ($type === 'custom_project' && mb_strlen($message) < 20) {
            $this->errors['message'] = 'Décrivez votre projet en au moins 20 caractères.';
        } elseif (mb_strlen($message) > 2000) {
            $this->errors['message'] = 'Le message ne doit pas dépasser 2000 caractères.';
        }
        $this->data['message'] = $message !== '' ? $message : null;

        // --- Consentement ---
        if (($input['consent_given'] ?? '') !== '1') {
            $this->errors['consent_given'] = 'Votre accord est nécessaire pour traiter la demande.';
        }

        // --- Champs propres au type de demande ---
        match ($type) {
            'custom_project'      => $this->validateCustomProject($input),
            'plan_purchase'       => $this->validatePlanPurchase($input),
            'training_enrollment' => $this->validateTraining($input),
        };

        return $this->errors === [];
    }

    private function validateCustomProject(array $input): void
    {
        $this->data['project_type'] = $this->choice($input, 'project_type', self::PROJECT_TYPES, 'Veuillez choisir une prestation.');
        $this->data['building_use'] = $this->choice($input, 'building_use', self::BUILDING_USES, "Veuillez choisir l'usage du bâtiment.");
        $this->data['location']     = $this->optionalText($input, 'location', 150, self::PLACE_PATTERN, 'Localisation invalide.');

        $this->data['land_area_m2'] = $this->optionalNumber($input, 'land_area_m2', 0.01, 1_000_000, 'Surface invalide (1 à 1 000 000 m²).');
        $this->data['budget_min']   = $this->optionalNumber($input, 'budget_min', 0, 9_999_999_999, 'Budget minimum invalide.');
        $this->data['budget_max']   = $this->optionalNumber($input, 'budget_max', 0, 9_999_999_999, 'Budget maximum invalide.');

        // Règle croisée (également garantie par la contrainte CHECK ck_cpd_range)
        if ($this->data['budget_min'] !== null && $this->data['budget_max'] !== null
            && $this->data['budget_min'] > $this->data['budget_max']) {
            $this->errors['budget_max'] = 'Le budget maximum doit être supérieur ou égal au minimum.';
        }

        $this->data['desired_deadline'] = $this->optionalFutureDate($input, 'desired_deadline');
    }

    private function validatePlanPurchase(array $input): void
    {
        $planId = filter_var($input['plan_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        // Le plan doit exister ET être actif : on lit son prix en base (requête préparée).
        // Le prix vient donc du serveur, jamais du navigateur (impossible de le falsifier).
        $plan = false;
        if ($planId !== false) {
            $stmt = $this->pdo->prepare('SELECT id, price, currency FROM plans WHERE id = :id AND is_active = 1');
            $stmt->execute([':id' => $planId]);
            $plan = $stmt->fetch();
        }

        if ($plan === false) {
            $this->errors['plan_id'] = 'Veuillez choisir un plan valide.';
        } else {
            $this->data['plan_id']    = (int) $plan['id'];
            $this->data['unit_price'] = $plan['price'];
            $this->data['currency']   = $plan['currency'];
        }

        $this->data['license_type']        = $this->choice($input, 'license_type', self::LICENSES, 'Veuillez choisir une licence.');
        $this->data['needs_customization'] = ($input['needs_customization'] ?? '') === '1' ? 1 : 0;
    }

    private function validateTraining(array $input): void
    {
        $trainingId = filter_var($input['training_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $exists = false;
        if ($trainingId !== false) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM trainings WHERE id = :id AND is_active = 1');
            $stmt->execute([':id' => $trainingId]);
            $exists = (int) $stmt->fetchColumn() === 1;
        }

        if (!$exists) {
            $this->errors['training_id'] = 'Veuillez choisir une formation valide.';
        } else {
            $this->data['training_id'] = $trainingId;
        }

        $this->data['delivery_mode']   = $this->choice($input, 'delivery_mode', self::MODES, 'Veuillez choisir un mode de suivi.');
        $this->data['current_level']   = $this->choice($input, 'current_level', self::LEVELS, 'Veuillez indiquer votre niveau.');
        $this->data['preferred_start'] = $this->optionalFutureDate($input, 'preferred_start');
    }

    /* =====================================================================
       2. ENREGISTREMENT (transaction + requêtes préparées)
       ===================================================================== */

    /**
     * Insère la demande validée. Tout ou rien : si une seule requête échoue,
     * rollBack() annule les précédentes (pas de client sans demande, ni de
     * demande sans détails).
     *
     * @return int identifiant de la demande créée
     * @throws LogicException si validate() n'a pas réussi
     * @throws PDOException   en cas d'erreur SQL (après annulation)
     */
    public function save(?string $ipAddress): int
    {
        if ($this->data === [] || $this->errors !== []) {
            throw new LogicException('Appelez validate() avec succès avant save().');
        }

        $this->pdo->beginTransaction();

        try {
            $clientId  = $this->upsertClient();
            $requestId = $this->insertRequest($clientId, $ipAddress);
            $this->insertDetails($requestId);

            $this->pdo->commit();
            return $requestId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Crée le client, ou le met à jour s'il existe déjà (même e-mail, clé UNIQUE).
     * « id = LAST_INSERT_ID(id) » permet de récupérer l'id dans les deux cas.
     */
    private function upsertClient(): int
    {
        $sql = 'INSERT INTO clients (full_name, email, phone, country, city)
                VALUES (:full_name, :email, :phone, :country, :city)
                ON DUPLICATE KEY UPDATE
                    id        = LAST_INSERT_ID(id),
                    full_name = VALUES(full_name),
                    phone     = COALESCE(VALUES(phone), phone),
                    country   = COALESCE(VALUES(country), country),
                    city      = COALESCE(VALUES(city), city)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $this->data['full_name'],
            ':email'     => $this->data['email'],
            ':phone'     => $this->data['phone'],
            ':country'   => $this->data['country'],
            ':city'      => $this->data['city'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertRequest(int $clientId, ?string $ipAddress): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO requests (client_id, request_type, message, consent_given, ip_address)
             VALUES (:client_id, :request_type, :message, 1, :ip)'
        );
        $stmt->execute([
            ':client_id'    => $clientId,
            ':request_type' => $this->data['request_type'],
            ':message'      => $this->data['message'],
            ':ip'           => filter_var($ipAddress, FILTER_VALIDATE_IP) ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Insère la ligne de détails dans la table correspondant au type. */
    private function insertDetails(int $requestId): void
    {
        [$sql, $params] = match ($this->data['request_type']) {
            'custom_project' => [
                'INSERT INTO custom_project_details
                    (request_id, project_type, building_use, location, land_area_m2,
                     budget_min, budget_max, desired_deadline)
                 VALUES (:request_id, :project_type, :building_use, :location, :land_area_m2,
                         :budget_min, :budget_max, :desired_deadline)',
                [
                    ':project_type'     => $this->data['project_type'],
                    ':building_use'     => $this->data['building_use'],
                    ':location'         => $this->data['location'],
                    ':land_area_m2'     => $this->data['land_area_m2'],
                    ':budget_min'       => $this->data['budget_min'],
                    ':budget_max'       => $this->data['budget_max'],
                    ':desired_deadline' => $this->data['desired_deadline'],
                ],
            ],
            'plan_purchase' => [
                'INSERT INTO plan_purchase_details
                    (request_id, plan_id, license_type, needs_customization, unit_price, currency)
                 VALUES (:request_id, :plan_id, :license_type, :needs_customization, :unit_price, :currency)',
                [
                    ':plan_id'             => $this->data['plan_id'],
                    ':license_type'        => $this->data['license_type'],
                    ':needs_customization' => $this->data['needs_customization'],
                    ':unit_price'          => $this->data['unit_price'],
                    ':currency'            => $this->data['currency'],
                ],
            ],
            'training_enrollment' => [
                'INSERT INTO training_enrollment_details
                    (request_id, training_id, delivery_mode, current_level, preferred_start)
                 VALUES (:request_id, :training_id, :delivery_mode, :current_level, :preferred_start)',
                [
                    ':training_id'     => $this->data['training_id'],
                    ':delivery_mode'   => $this->data['delivery_mode'],
                    ':current_level'   => $this->data['current_level'],
                    ':preferred_start' => $this->data['preferred_start'],
                ],
            ],
        };

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':request_id' => $requestId] + $params);
    }

    /* =====================================================================
       3. MÉTHODES UTILITAIRES DE NETTOYAGE
       ===================================================================== */

    /** Texte nettoyé (espaces en trop retirés) ; '' si absent ou non textuel. */
    private function text(array $input, string $key): string
    {
        $value = $input[$key] ?? '';
        return is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? '') : '';
    }

    /** Valeur obligatoire parmi une liste blanche. */
    private function choice(array $input, string $key, array $allowed, string $error): ?string
    {
        $value = $this->text($input, $key);
        if (!in_array($value, $allowed, true)) {
            $this->errors[$key] = $error;
            return null;
        }
        return $value;
    }

    /** Texte facultatif : null si vide, erreur si trop long ou mal formé. */
    private function optionalText(array $input, string $key, int $max, string $pattern, string $error): ?string
    {
        $value = $this->text($input, $key);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max || !preg_match($pattern, $value)) {
            $this->errors[$key] = $error;
        }
        return $value;
    }

    /** Nombre facultatif borné : null si vide. */
    private function optionalNumber(array $input, string $key, float $min, float $max, string $error): ?float
    {
        $value = $this->text($input, $key);
        if ($value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($number === false || $number < $min || $number > $max) {
            $this->errors[$key] = $error;
            return null;
        }
        return $number;
    }

    /** Date facultative au format AAAA-MM-JJ, aujourd'hui ou plus tard. */
    private function optionalFutureDate(array $input, string $key): ?string
    {
        $value = $this->text($input, $key);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        // Le re-formatage détecte les dates impossibles (ex. 2026-02-31)
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->errors[$key] = 'Date invalide.';
            return null;
        }
        if ($date < new DateTimeImmutable('today')) {
            $this->errors[$key] = 'La date doit être dans le futur.';
            return null;
        }
        return $value;
    }
}
