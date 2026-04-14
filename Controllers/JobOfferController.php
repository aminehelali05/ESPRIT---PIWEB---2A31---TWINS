<?php

include_once(__DIR__ . '/../config.php');

class JobOfferController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
    }

    public function listUsers(): array
    {
        try {
            return $this->pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            return [];
        }
    }

    public function userLabel(array $row): string
    {
        $full = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($full !== '') {
            return $full;
        }
        $email = trim((string) ($row['email'] ?? ''));
        return $email !== '' ? $email : ('User #' . (int) ($row['id'] ?? 0));
    }

    public function parseDateTimeLocal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value) ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT o.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM job_offer_applications a WHERE a.job_offer_id = o.id) AS applications_count
                FROM job_offers o
                LEFT JOIN users u ON u.id = o.client_id
                ORDER BY o.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buildBackofficeStats(array $rows): array
    {
        $open = 0;
        $apps = 0;
        foreach ($rows as $row) {
            if ((string) ($row['status'] ?? '') === 'open') {
                $open++;
            }
            $apps += (int) ($row['applications_count'] ?? 0);
        }
        return ['total' => count($rows), 'open' => $open, 'applications' => $apps];
    }

    public function createFromBackoffice(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO job_offers (title, description, budget, skills_required, location, experience_level, project_type, status, deadline_at, client_id, created_at, updated_at) VALUES (:title, :description, :budget, :skills_required, :location, :experience_level, :project_type, :status, :deadline_at, :client_id, NOW(), NOW())');
        $stmt->execute([
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'budget' => max(0, (float) ($payload['budget'] ?? 0)),
            'skills_required' => trim((string) ($payload['skills_required'] ?? '')) ?: null,
            'location' => trim((string) ($payload['location'] ?? '')) ?: null,
            'experience_level' => trim((string) ($payload['experience_level'] ?? 'Mid')) ?: 'Mid',
            'project_type' => trim((string) ($payload['project_type'] ?? 'Fixed Price')) ?: 'Fixed Price',
            'status' => trim((string) ($payload['status'] ?? 'open')),
            'deadline_at' => $payload['deadline_at'] ?? null,
            'client_id' => (int) ($payload['client_id'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $offerId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE job_offers SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $offerId]);
    }

    public function deleteCascade(int $offerId): void
    {
        $this->pdo->prepare('DELETE FROM job_offer_applications WHERE job_offer_id = :id')->execute(['id' => $offerId]);
        $this->pdo->prepare('DELETE FROM contracts WHERE job_offer_id = :id')->execute(['id' => $offerId]);
        $this->pdo->prepare('DELETE FROM job_offers WHERE id = :id')->execute(['id' => $offerId]);
    }

    public function listFrontofficeRows(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $type = trim((string) ($filters['type'] ?? 'all'));
        $experience = trim((string) ($filters['experience'] ?? 'all'));
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'newest')));

        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(o.title LIKE :q OR o.description LIKE :q OR o.skills_required LIKE :q OR o.location LIKE :q OR o.project_type LIKE :q OR o.experience_level LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if (in_array($status, ['open', 'closed', 'in_progress', 'archived'], true)) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }
        if (in_array($type, ['Fixed Price', 'Hourly', 'Retainer', 'Long-term'], true)) {
            $where[] = 'o.project_type = :project_type';
            $params['project_type'] = $type;
        }
        if (in_array($experience, ['Junior', 'Mid', 'Senior', 'Expert'], true)) {
            $where[] = 'o.experience_level = :experience_level';
            $params['experience_level'] = $experience;
        }

        $order = 'o.created_at DESC';
        if ($sort === 'budget_asc') {
            $order = 'o.budget ASC, o.created_at DESC';
        } elseif ($sort === 'budget_desc') {
            $order = 'o.budget DESC, o.created_at DESC';
        } elseif ($sort === 'deadline') {
            $order = 'o.deadline_at IS NULL, o.deadline_at ASC, o.created_at DESC';
        }

        $sql = 'SELECT o.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM job_offer_applications a WHERE a.job_offer_id = o.id) AS applications_count,
                (SELECT COUNT(*) FROM job_offer_applications a WHERE a.job_offer_id = o.id AND a.status = "pending") AS pending_count
                FROM job_offers o
                INNER JOIN users u ON u.id = o.client_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isOwnedByClient(int $offerId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM job_offers WHERE id = :id AND client_id = :client_id LIMIT 1');
        $stmt->execute(['id' => $offerId, 'client_id' => $clientId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createByClient(array $payload): int
    {
        return $this->createFromBackoffice($payload);
    }

    public function findById(int $offerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM job_offers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $offerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateOwnedByClient(int $offerId, int $clientId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE job_offers SET title = :title, description = :description, budget = :budget, skills_required = :skills_required, location = :location, experience_level = :experience_level, project_type = :project_type, status = :status, deadline_at = :deadline_at, updated_at = NOW() WHERE id = :id AND client_id = :client_id');
        return $stmt->execute([
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'budget' => max(0, (float) ($payload['budget'] ?? 0)),
            'skills_required' => trim((string) ($payload['skills_required'] ?? '')) ?: null,
            'location' => trim((string) ($payload['location'] ?? '')) ?: null,
            'experience_level' => trim((string) ($payload['experience_level'] ?? 'Mid')),
            'project_type' => trim((string) ($payload['project_type'] ?? 'Fixed Price')),
            'status' => trim((string) ($payload['status'] ?? 'open')),
            'deadline_at' => $payload['deadline_at'] ?? null,
            'id' => $offerId,
            'client_id' => $clientId,
        ]);
    }

    public function deleteOwnedByClient(int $offerId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM job_offers WHERE id = :id AND client_id = :client_id');
        return $stmt->execute(['id' => $offerId, 'client_id' => $clientId]);
    }

    public function freelancerAlreadyApplied(int $offerId, int $freelancerId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM job_offer_applications WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1');
        $stmt->execute(['job_offer_id' => $offerId, 'freelancer_id' => $freelancerId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function applyToOffer(int $offerId, int $freelancerId, ?string $coverLetter): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO job_offer_applications (job_offer_id, freelancer_id, cover_letter, status, applied_at, created_at, updated_at) VALUES (:job_offer_id, :freelancer_id, :cover_letter, :status, NOW(), NOW(), NOW())');
        $stmt->execute([
            'job_offer_id' => $offerId,
            'freelancer_id' => $freelancerId,
            'cover_letter' => $coverLetter ?: null,
            'status' => 'pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function freelancerApplicationsMap(int $freelancerId): array
    {
        $stmt = $this->pdo->prepare('SELECT job_offer_id, status, cover_letter, applied_at FROM job_offer_applications WHERE freelancer_id = :freelancer_id');
        $stmt->execute(['freelancer_id' => $freelancerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $app) {
            $map[(int) ($app['job_offer_id'] ?? 0)] = $app;
        }
        return $map;
    }

    public function contractsMapForFreelancer(int $freelancerId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_offer_id, status FROM contracts WHERE freelancer_id = :freelancer_id');
        $stmt->execute(['freelancer_id' => $freelancerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offerId = (int) ($row['job_offer_id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $map[$offerId] = [
                'id' => (int) ($row['id'] ?? 0),
                'status' => (string) ($row['status'] ?? 'draft'),
            ];
        }
        return $map;
    }

    public function contractsMapForClient(int $clientId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_offer_id, status FROM contracts WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offerId = (int) ($row['job_offer_id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $map[$offerId] = [
                'id' => (int) ($row['id'] ?? 0),
                'status' => (string) ($row['status'] ?? 'draft'),
            ];
        }
        return $map;
    }

    public function clientApplicationsByOffer(int $clientId): array
    {
        $sql = 'SELECT a.*, u.first_name, u.last_name, u.email
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN users u ON u.id = a.freelancer_id
                WHERE o.client_id = :client_id
                ORDER BY a.applied_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['client_id' => $clientId]);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $app) {
            $offerId = (int) ($app['job_offer_id'] ?? 0);
            if (!isset($grouped[$offerId])) {
                $grouped[$offerId] = [];
            }
            $grouped[$offerId][] = $app;
        }
        return $grouped;
    }

    public function decideApplication(int $clientId, int $applicationId, string $decision): void
    {
        $this->pdo->beginTransaction();
        try {
            $appQuery = $this->pdo->prepare('SELECT a.*, o.client_id, o.budget, o.id AS offer_id, o.title AS offer_title FROM job_offer_applications a INNER JOIN job_offers o ON o.id = a.job_offer_id WHERE a.id = :id LIMIT 1 FOR UPDATE');
            $appQuery->execute(['id' => $applicationId]);
            $application = $appQuery->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                throw new RuntimeException('Application not found.');
            }
            if ((int) ($application['client_id'] ?? 0) !== $clientId) {
                throw new RuntimeException('You can only manage candidates for your own offers.');
            }

            $update = $this->pdo->prepare('UPDATE job_offer_applications SET status = :status, decided_at = NOW(), updated_at = NOW() WHERE id = :id');
            $update->execute(['status' => $decision, 'id' => $applicationId]);

            if ($decision === 'accepted') {
                $rejectOthers = $this->pdo->prepare('UPDATE job_offer_applications SET status = :status, decided_at = NOW(), updated_at = NOW() WHERE job_offer_id = :job_offer_id AND id <> :id AND status = :pending');
                $rejectOthers->execute([
                    'status' => 'rejected',
                    'job_offer_id' => (int) ($application['job_offer_id'] ?? 0),
                    'id' => $applicationId,
                    'pending' => 'pending',
                ]);

                $this->updateStatus((int) ($application['offer_id'] ?? 0), 'in_progress');
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
