<?php

include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/CandidatureController.php');

class JobOfferController
{
    private PDO $pdo;
    private CandidatureController $candidatureController;
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureJobOffersSchema();
        $this->candidatureController = new CandidatureController($this->pdo);
    }

    private function jobOfferHasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM job_offers LIKE " . $this->pdo->quote($column));
            $this->columnCache[$column] = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    private function ensureJobOffersSchema(): void
    {
        if ($this->jobOfferHasColumn('title')) {
            return;
        }

        try {
            $this->pdo->exec("ALTER TABLE job_offers ADD COLUMN title VARCHAR(180) NOT NULL DEFAULT '' AFTER id");
            $this->columnCache['title'] = true;
        } catch (Throwable $exception) {
            error_log('JobOfferController::ensureJobOffersSchema - ' . $exception->getMessage());
        }
    }

    private function normalizedTitle(array $payload): string
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            return 'Untitled Offer';
        }

        $snippet = preg_replace('/\s+/', ' ', $description);
        return mb_substr((string) $snippet, 0, 120);
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function normalizeDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value) ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    private function validateOfferPayload(array $payload): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $skills = $this->sanitizeText((string) ($payload['skills_required'] ?? ''));
        $location = $this->sanitizeText((string) ($payload['location'] ?? ''));
        $budget = max(0, (float) ($payload['budget'] ?? 0));
        $deadline = $this->normalizeDateTime((string) ($payload['deadline_at'] ?? ''));
        $status = strtolower($this->sanitizeText((string) ($payload['status'] ?? 'open')));
        $experience = $this->sanitizeText((string) ($payload['experience_level'] ?? 'Mid'));
        $projectType = $this->sanitizeText((string) ($payload['project_type'] ?? 'Fixed Price'));
        $clientId = (int) ($payload['client_id'] ?? 0);

        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }
        if (mb_strlen($title) < 10 || mb_strlen($title) > 140) {
            throw new RuntimeException('Title must be between 10 and 140 characters.');
        }
        if ($description === '') {
            throw new RuntimeException('Description is required.');
        }
        if (mb_strlen($description) < 40 || mb_strlen($description) > 3500) {
            throw new RuntimeException('Description must be between 40 and 3500 characters.');
        }
        if ($skills !== '' && (mb_strlen($skills) < 3 || mb_strlen($skills) > 255)) {
            throw new RuntimeException('Skills must be between 3 and 255 characters.');
        }
        if ($location !== '' && (mb_strlen($location) < 2 || mb_strlen($location) > 120)) {
            throw new RuntimeException('Location must be between 2 and 120 characters.');
        }
        if ($budget <= 0) {
            throw new RuntimeException('Budget must be greater than 0.');
        }
        if ($budget > 10000000) {
            throw new RuntimeException('Budget is too high.');
        }
        if ($deadline !== null && new DateTimeImmutable($deadline) <= new DateTimeImmutable()) {
            throw new RuntimeException('Deadline must be in the future.');
        }
        if (!in_array($status, ['open', 'in_progress', 'closed', 'archived'], true)) {
            throw new RuntimeException('Invalid status selected.');
        }
        if (!in_array($experience, ['Junior', 'Mid', 'Senior', 'Expert'], true)) {
            throw new RuntimeException('Invalid experience level.');
        }
        if (!in_array($projectType, ['Fixed Price', 'Hourly', 'Retainer', 'Long-term'], true)) {
            throw new RuntimeException('Invalid project type.');
        }
        if ($clientId <= 0) {
            throw new RuntimeException('Client is required.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'skills_required' => $skills,
            'location' => $location,
            'budget' => $budget,
            'deadline_at' => $deadline,
            'status' => $status,
            'experience_level' => $experience,
            'project_type' => $projectType,
            'client_id' => $clientId,
        ];
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
        $this->ensureJobOffersSchema();
        $clean = $this->validateOfferPayload($payload);
        $stmt = $this->pdo->prepare('INSERT INTO job_offers (title, description, budget, skills_required, location, experience_level, project_type, status, deadline_at, client_id, created_at, updated_at) VALUES (:title, :description, :budget, :skills_required, :location, :experience_level, :project_type, :status, :deadline_at, :client_id, NOW(), NOW())');
        $stmt->execute([
            'title' => $clean['title'],
            'description' => $clean['description'],
            'budget' => $clean['budget'],
            'skills_required' => $clean['skills_required'] ?: null,
            'location' => $clean['location'] ?: null,
            'experience_level' => $clean['experience_level'],
            'project_type' => $clean['project_type'],
            'status' => $clean['status'],
            'deadline_at' => $clean['deadline_at'],
            'client_id' => $clean['client_id'],
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
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM candidatures WHERE offre_id = :id')->execute(['id' => $offerId]);
            $this->pdo->prepare('DELETE FROM job_offer_applications WHERE job_offer_id = :id')->execute(['id' => $offerId]);
            $this->pdo->prepare('DELETE FROM contracts WHERE job_offer_id = :id')->execute(['id' => $offerId]);
            $this->pdo->prepare('DELETE FROM job_offers WHERE id = :id')->execute(['id' => $offerId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
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
        $this->ensureJobOffersSchema();
        $clean = $this->validateOfferPayload($payload);
        $stmt = $this->pdo->prepare('UPDATE job_offers SET title = :title, description = :description, budget = :budget, skills_required = :skills_required, location = :location, experience_level = :experience_level, project_type = :project_type, status = :status, deadline_at = :deadline_at, updated_at = NOW() WHERE id = :id AND client_id = :client_id');
        return $stmt->execute([
            'title' => $clean['title'],
            'description' => $clean['description'],
            'budget' => $clean['budget'],
            'skills_required' => $clean['skills_required'] ?: null,
            'location' => $clean['location'] ?: null,
            'experience_level' => $clean['experience_level'],
            'project_type' => $clean['project_type'],
            'status' => $clean['status'],
            'deadline_at' => $clean['deadline_at'],
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

    public function applyToOffer(int $offerId, int $freelancerId, array $payload = [], array $files = []): int
    {
        return $this->saveCandidatureForFreelancer($offerId, $freelancerId, null, $payload, $files);
    }

    public function saveCandidatureForFreelancer(int $offerId, int $freelancerId, ?int $candidatureId, array $payload, array $files = []): int
    {
        $clean = $this->candidatureController->normalizeApplicationPayload($payload);

        $this->pdo->beginTransaction();
        try {
            $applicationStmt = $this->pdo->prepare('SELECT * FROM job_offer_applications WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1 FOR UPDATE');
            $applicationStmt->execute([
                'job_offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
            ]);
            $applicationRow = $applicationStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($candidatureId > 0) {
                $candidatureStmt = $this->pdo->prepare('SELECT * FROM candidatures WHERE id = :id AND offre_id = :offre_id AND freelancer_id = :freelancer_id LIMIT 1 FOR UPDATE');
                $candidatureStmt->execute([
                    'id' => $candidatureId,
                    'offre_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                ]);
            } else {
                $candidatureStmt = $this->pdo->prepare('SELECT * FROM candidatures WHERE offre_id = :offre_id AND freelancer_id = :freelancer_id LIMIT 1 FOR UPDATE');
                $candidatureStmt->execute([
                    'offre_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                ]);
            }
            $candidatureRow = $candidatureStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $attachmentsJson = $this->candidatureController->storeAttachmentsFromFiles($files, $freelancerId);
            if ($attachmentsJson === null && $candidatureRow) {
                $attachmentsJson = (string) ($candidatureRow['attachments_json'] ?? '') ?: null;
            }

            if ($applicationRow) {
                $updateApp = $this->pdo->prepare('UPDATE job_offer_applications SET cover_letter = :cover_letter, updated_at = NOW() WHERE id = :id');
                $updateApp->execute([
                    'cover_letter' => $clean['message'],
                    'id' => (int) ($applicationRow['id'] ?? 0),
                ]);
                $applicationId = (int) ($applicationRow['id'] ?? 0);
            } else {
                $insertApp = $this->pdo->prepare('INSERT INTO job_offer_applications (job_offer_id, freelancer_id, cover_letter, status, applied_at, created_at, updated_at) VALUES (:job_offer_id, :freelancer_id, :cover_letter, :status, NOW(), NOW(), NOW())');
                $insertApp->execute([
                    'job_offer_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                    'cover_letter' => $clean['message'],
                    'status' => 'pending',
                ]);
                $applicationId = (int) $this->pdo->lastInsertId();
            }

            if ($candidatureRow) {
                $updateCandidature = $this->pdo->prepare('UPDATE candidatures
                    SET message = :message,
                        proposed_budget = :proposed_budget,
                        estimated_delivery_days = :estimated_delivery_days,
                        skills_experience = :skills_experience,
                        attachments_json = :attachments_json,
                        updated_at = NOW()
                    WHERE id = :id');
                $updateCandidature->execute([
                    'message' => $clean['message'],
                    'proposed_budget' => $clean['proposed_budget'],
                    'estimated_delivery_days' => $clean['estimated_delivery_days'],
                    'skills_experience' => $clean['skills_experience'],
                    'attachments_json' => $attachmentsJson,
                    'id' => (int) ($candidatureRow['id'] ?? 0),
                ]);
            } else {
                $insertCandidature = $this->pdo->prepare('INSERT INTO candidatures (
                    offre_id, freelancer_id, message, proposed_budget, estimated_delivery_days, skills_experience, attachments_json, created_at, updated_at
                ) VALUES (
                    :offre_id, :freelancer_id, :message, :proposed_budget, :estimated_delivery_days, :skills_experience, :attachments_json, NOW(), NOW()
                )');
                $insertCandidature->execute([
                    'offre_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                    'message' => $clean['message'],
                    'proposed_budget' => $clean['proposed_budget'],
                    'estimated_delivery_days' => $clean['estimated_delivery_days'],
                    'skills_experience' => $clean['skills_experience'],
                    'attachments_json' => $attachmentsJson,
                ]);
            }

            $this->pdo->commit();
            return $applicationId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteCandidatureForFreelancer(int $offerId, int $freelancerId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt1 = $this->pdo->prepare('DELETE FROM job_offer_applications WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id');
            $stmt1->execute(['job_offer_id' => $offerId, 'freelancer_id' => $freelancerId]);
            $stmt2 = $this->pdo->prepare('DELETE FROM candidatures WHERE offre_id = :offre_id AND freelancer_id = :freelancer_id');
            $stmt2->execute(['offre_id' => $offerId, 'freelancer_id' => $freelancerId]);
            $this->pdo->commit();
            return ($stmt1->rowCount() + $stmt2->rowCount()) > 0;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteCandidatureById(int $applicationId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare('SELECT job_offer_id, freelancer_id FROM job_offer_applications WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $applicationId]);
            $row = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }

            $this->pdo->prepare('DELETE FROM job_offer_applications WHERE id = :id')->execute(['id' => $applicationId]);
            $this->pdo->prepare('DELETE FROM candidatures WHERE offre_id = :offre_id AND freelancer_id = :freelancer_id')->execute([
                'offre_id' => (int) ($row['job_offer_id'] ?? 0),
                'freelancer_id' => (int) ($row['freelancer_id'] ?? 0),
            ]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function listAllCandidatures(): array
    {
        $sql = 'SELECT c.id AS candidature_id, c.offre_id, c.freelancer_id, c.message, c.proposed_budget, c.estimated_delivery_days,
                       c.skills_experience, c.attachments_json, c.created_at AS candidature_created_at,
                       a.id AS application_id, a.status, a.applied_at, a.decided_at,
                       o.title AS offer_title, o.client_id, u.first_name, u.last_name, u.email
                FROM candidatures c
                INNER JOIN job_offers o ON o.id = c.offre_id
                INNER JOIN users u ON u.id = c.freelancer_id
                LEFT JOIN job_offer_applications a ON a.job_offer_id = c.offre_id AND a.freelancer_id = c.freelancer_id
                ORDER BY c.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function freelancerApplicationsMap(int $freelancerId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id AS application_id, c.id AS candidature_id, a.job_offer_id, a.status, a.cover_letter, a.applied_at,
                c.message, c.proposed_budget, c.estimated_delivery_days, c.skills_experience, c.attachments_json
            FROM job_offer_applications a
            INNER JOIN candidatures c ON c.offre_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
            WHERE a.freelancer_id = :freelancer_id');
        $stmt->execute(['freelancer_id' => $freelancerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $app) {
            $map[(int) ($app['job_offer_id'] ?? 0)] = $app;
        }
        return $map;
    }

    public function contractsMapForFreelancer(int $freelancerId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_offer_id, client_signed, freelancer_signed, freelancer_refused_at
            FROM contracts
            WHERE freelancer_id = :freelancer_id');
        $stmt->execute(['freelancer_id' => $freelancerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offerId = (int) ($row['job_offer_id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $clientSigned = (int) ($row['client_signed'] ?? 0) === 1;
            $freelancerSigned = (int) ($row['freelancer_signed'] ?? 0) === 1;
            $refusedAt = trim((string) ($row['freelancer_refused_at'] ?? ''));
            $map[$offerId] = [
                'id' => (int) ($row['id'] ?? 0),
                'client_signed' => $clientSigned,
                'freelancer_signed' => $freelancerSigned,
                'workflow_state' => $freelancerSigned ? 'completed' : ($refusedAt !== '' ? 'refused' : ($clientSigned ? 'waiting_freelancer' : 'waiting_client')),
            ];
        }
        return $map;
    }

    public function contractsMapForClient(int $clientId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_offer_id, client_signed, freelancer_signed, freelancer_refused_at
            FROM contracts
            WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offerId = (int) ($row['job_offer_id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $clientSigned = (int) ($row['client_signed'] ?? 0) === 1;
            $freelancerSigned = (int) ($row['freelancer_signed'] ?? 0) === 1;
            $refusedAt = trim((string) ($row['freelancer_refused_at'] ?? ''));
            $map[$offerId] = [
                'id' => (int) ($row['id'] ?? 0),
                'client_signed' => $clientSigned,
                'freelancer_signed' => $freelancerSigned,
                'workflow_state' => $freelancerSigned ? 'completed' : ($refusedAt !== '' ? 'refused' : ($clientSigned ? 'waiting_freelancer' : 'waiting_client')),
            ];
        }
        return $map;
    }

    public function clientApplicationsByOffer(int $clientId): array
    {
        $sql = 'SELECT a.*, c.id AS candidature_id, c.message, c.proposed_budget, c.estimated_delivery_days,
                       c.skills_experience, c.attachments_json, u.first_name, u.last_name, u.email
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN candidatures c ON c.offre_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
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

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
