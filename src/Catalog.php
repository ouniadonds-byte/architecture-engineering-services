<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/Catalog.php
 * Rôle    : Lecture des catalogues (plans prêts à bâtir et formations)
 *           pour alimenter les listes déroulantes du formulaire.
 */
declare(strict_types=1);

final class Catalog
{
    /** Injection de dépendance : la connexion est fournie au constructeur. */
    public function __construct(private PDO $pdo)
    {
    }

    /** Plans actifs, du moins cher au plus cher. */
    public function getPlans(): array
    {
        // Aucune donnée utilisateur dans cette requête : query() suffit
        return $this->pdo->query(
            'SELECT id, reference, title, price, currency
               FROM plans
              WHERE is_active = 1
           ORDER BY price'
        )->fetchAll();
    }

    /** Formations actives, par logiciel. */
    public function getTrainings(): array
    {
        return $this->pdo->query(
            'SELECT id, code, title, software, duration_hours, price, currency
               FROM trainings
              WHERE is_active = 1
           ORDER BY id'
        )->fetchAll();
    }
}
