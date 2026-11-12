<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/Labels.php
 * Rôle    : Traduction en français des codes stockés en base (valeurs
 *           ENUM en anglais) pour l'affichage dans l'espace admin.
 */
declare(strict_types=1);

final class Labels
{
    public const REQUEST_TYPES = [
        'custom_project'      => 'Projet sur mesure',
        'plan_purchase'       => 'Achat de plan',
        'training_enrollment' => 'Formation',
    ];

    public const STATUSES = [
        'new'         => 'Nouveau',
        'in_progress' => 'En cours',
        'completed'   => 'Terminé',
        'cancelled'   => 'Annulé',
    ];

    public const PROJECT_TYPES = [
        'architectural_design' => 'Conception architecturale',
        '3d_visualization'     => 'Visualisation 3D',
        'structural_study'     => 'Étude structurelle',
        'bim_modeling'         => 'Modélisation BIM',
        'renovation'           => 'Rénovation',
        'interior_design'      => 'Architecture intérieure',
    ];

    public const BUILDING_USES = [
        'residential'   => 'Résidentiel',
        'commercial'    => 'Commercial',
        'institutional' => 'Institutionnel',
        'mixed'         => 'Mixte',
    ];

    public const LICENSES = [
        'single_build' => 'Construction unique',
        'multi_build'  => 'Constructions multiples',
    ];

    public const MODES = [
        'online' => 'En ligne',
        'onsite' => 'En présentiel',
    ];

    public const LEVELS = [
        'beginner'     => 'Débutant',
        'intermediate' => 'Intermédiaire',
        'advanced'     => 'Avancé',
    ];

    /** Libellé français d'un code, ou le code lui-même s'il est inconnu. */
    public static function of(array $group, ?string $code): string
    {
        return $code === null ? '—' : ($group[$code] ?? $code);
    }
}
