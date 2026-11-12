<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/RequestRepository.php
 * Rôle    : Lecture et mise à jour des demandes pour l'espace admin
 *           (patron « Repository » : toutes les requêtes SQL au même
 *           endroit). SELECT avec jointures, agrégats, filtres
 *           dynamiques, UPDATE — toujours en requêtes préparées.
 */
declare(strict_types=1);

final class RequestRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Chiffres clés du tableau de bord (agrégats COUNT / SUM). */
    public function getStats(): array
    {
        return $this->pdo->query(
            "SELECT COUNT(*)                                       AS total,
                    COALESCE(SUM(status = 'new'), 0)               AS new_count,
                    COALESCE(SUM(request_type = 'custom_project'), 0)      AS projects,
                    COALESCE(SUM(request_type = 'plan_purchase'), 0)       AS plans,
                    COALESCE(SUM(request_type = 'training_enrollment'), 0) AS trainings
               FROM requests"
        )->fetch();
    }

    /**
     * Liste filtrée des demandes (vue v_requests_overview).
     * La clause WHERE est construite dynamiquement, mais les VALEURS passent
     * toujours par des paramètres liés : aucune concaténation de saisie.
     */
    public function search(string $type = '', string $status = '', string $query = '', int $limit = 100): array
    {
        $conditions = [];
        $params = [];

        if (array_key_exists($type, Labels::REQUEST_TYPES)) {
            $conditions[] = 'request_type = :type';
            $params[':type'] = $type;
        }
        if (array_key_exists($status, Labels::STATUSES)) {
            $conditions[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($query !== '') {
            // Échappement des jokers LIKE (% et _) saisis par l'utilisateur
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $conditions[] = '(full_name LIKE :q1 OR email LIKE :q2)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
        }

        $sql = 'SELECT request_id, created_at, request_type, status, full_name, email, subject
                  FROM v_requests_overview'
             . ($conditions ? ' WHERE ' . implode(' AND ', $conditions) : '')
             . ' ORDER BY created_at DESC, request_id DESC
                 LIMIT ' . max(1, min($limit, 500));   // entier borné, pas une saisie

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Une demande complète : informations communes + client + détails du type. */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.request_type, r.status, r.message, r.ip_address, r.created_at, r.updated_at,
                    c.full_name, c.email, c.phone, c.country, c.city
               FROM requests r
               INNER JOIN clients c ON c.id = r.client_id
              WHERE r.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch();

        if ($request === false) {
            return null;
        }

        $detailSql = match ($request['request_type']) {
            'custom_project' =>
                'SELECT * FROM custom_project_details WHERE request_id = :id',
            'plan_purchase' =>
                'SELECT d.*, p.reference, p.title AS plan_title
                   FROM plan_purchase_details d
                   INNER JOIN plans p ON p.id = d.plan_id
                  WHERE d.request_id = :id',
            'training_enrollment' =>
                'SELECT d.*, t.title AS training_title, t.duration_hours, t.price
                   FROM training_enrollment_details d
                   INNER JOIN trainings t ON t.id = d.training_id
                  WHERE d.request_id = :id',
        };

        $stmt = $this->pdo->prepare($detailSql);
        $stmt->execute([':id' => $id]);
        $request['details'] = $stmt->fetch() ?: [];

        return $request;
    }

    /** Change le statut d'une demande. Retourne false si le statut est invalide. */
    public function updateStatus(int $id, string $status): bool
    {
        if (!array_key_exists($status, Labels::STATUSES)) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE requests SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $id]);
        return true;
    }
}
