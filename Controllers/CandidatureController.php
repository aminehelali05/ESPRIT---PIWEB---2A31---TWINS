<?php

include_once(__DIR__ . '/../config.php');

class CandidatureController
{
    private PDO $pdo;
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureSchema();
        $this->ensureExtendedSchema();
        $this->backfillFromLegacyApplications();
    }

    private function ensureSchema(): void
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS candidatures (
                id INT AUTO_INCREMENT PRIMARY KEY,
                job_offer_id INT NOT NULL,
                freelancer_id INT NOT NULL,
                message TEXT NOT NULL,
                status ENUM("pending","accepted","rejected") NOT NULL DEFAULT "pending",
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_candidatures_offer_freelancer (job_offer_id, freelancer_id),
                KEY idx_candidatures_job_offer_id (job_offer_id),
                KEY idx_candidatures_freelancer_id (freelancer_id),
                KEY idx_candidatures_status (status),
                CONSTRAINT fk_candidatures_offer FOREIGN KEY (job_offer_id) REFERENCES job_offers(id) ON DELETE CASCADE,
                CONSTRAINT fk_candidatures_freelancer FOREIGN KEY (freelancer_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        } catch (Throwable $exception) {
            error_log('CandidatureController::ensureSchema - ' . $exception->getMessage());
        }
    }

    private function candidatureHasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM candidatures LIKE " . $this->pdo->quote($column));
            $this->columnCache[$column] = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    private function ensureColumn(string $column, string $definition): void
    {
        if ($this->candidatureHasColumn($column)) {
            return;
        }

        try {
            $this->pdo->exec("ALTER TABLE candidatures ADD COLUMN {$column} {$definition}");
            $this->columnCache[$column] = true;
        } catch (Throwable $exception) {
            error_log('CandidatureController::ensureColumn(' . $column . ') - ' . $exception->getMessage());
        }
    }

    private function ensureExtendedSchema(): void
    {
        $this->ensureColumn('job_offer_id', 'INT NULL AFTER id');
        $this->backfillJobOfferIdFromLegacyColumn();
        $this->ensureColumn('status', 'ENUM("pending","accepted","rejected") NOT NULL DEFAULT "pending" AFTER message');
        $this->ensureColumn('proposed_budget', 'DECIMAL(12,2) NULL AFTER message');
        $this->ensureColumn('estimated_delivery_days', 'INT NULL AFTER proposed_budget');
        $this->ensureColumn('skills_experience', 'TEXT NULL AFTER estimated_delivery_days');
        $this->ensureColumn('attachments_json', 'LONGTEXT NULL AFTER skills_experience');
    }

    private function backfillJobOfferIdFromLegacyColumn(): void
    {
        if (!$this->candidatureHasColumn('offre_id') || !$this->candidatureHasColumn('job_offer_id')) {
            return;
        }

        try {
            $this->pdo->exec('UPDATE candidatures SET job_offer_id = offre_id WHERE job_offer_id IS NULL AND offre_id IS NOT NULL');
        } catch (Throwable $exception) {
            error_log('CandidatureController::backfillJobOfferIdFromLegacyColumn - ' . $exception->getMessage());
        }
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function validateMessage(string $message): string
    {
        $message = $this->sanitizeText($message);
        if ($message === '') {
            throw new RuntimeException('Application message is required.');
        }
        if (mb_strlen($message) < 20 || mb_strlen($message) > 3000) {
            throw new RuntimeException('Application message must be between 20 and 3000 characters.');
        }
        return $message;
    }

    public function normalizeApplicationPayload(array $payload): array
    {
        $message = $this->validateMessage((string) ($payload['message'] ?? $payload['cover_letter'] ?? ''));
        $proposedBudgetRaw = trim((string) ($payload['proposed_budget'] ?? ''));
        $estimatedDaysRaw = trim((string) ($payload['estimated_delivery_days'] ?? ''));
        $skillsExperience = $this->sanitizeText((string) ($payload['skills_experience'] ?? ''));

        if ($proposedBudgetRaw === '') {
            throw new RuntimeException('Proposed budget is required.');
        }
        if (!is_numeric($proposedBudgetRaw) || (float) $proposedBudgetRaw <= 0) {
            throw new RuntimeException('Proposed budget must be greater than 0.');
        }
        $proposedBudget = round((float) $proposedBudgetRaw, 2);
        if ($proposedBudget > 10000000) {
            throw new RuntimeException('Proposed budget is too high.');
        }

        if ($estimatedDaysRaw === '') {
            throw new RuntimeException('Estimated delivery time is required.');
        }
        if (!ctype_digit($estimatedDaysRaw) || (int) $estimatedDaysRaw <= 0) {
            throw new RuntimeException('Estimated delivery time must be a whole number of days.');
        }
        $estimatedDays = (int) $estimatedDaysRaw;
        if ($estimatedDays > 3650) {
            throw new RuntimeException('Estimated delivery time is too long.');
        }

        if ($skillsExperience === '') {
            throw new RuntimeException('Skills and experience are required.');
        }
        if (mb_strlen($skillsExperience) < 20 || mb_strlen($skillsExperience) > 2000) {
            throw new RuntimeException('Skills and experience must be between 20 and 2000 characters.');
        }

        return [
            'message' => $message,
            'proposed_budget' => $proposedBudget,
            'estimated_delivery_days' => $estimatedDays,
            'skills_experience' => $skillsExperience,
        ];
    }

    private function normalizeFilesArray(array $fileField): array
    {
        if (!isset($fileField['name'])) {
            return [];
        }

        if (!is_array($fileField['name'])) {
            return [$fileField];
        }

        $files = [];
        $count = count($fileField['name']);
        for ($index = 0; $index < $count; $index++) {
            $files[] = [
                'name' => $fileField['name'][$index] ?? '',
                'type' => $fileField['type'][$index] ?? '',
                'tmp_name' => $fileField['tmp_name'][$index] ?? '',
                'error' => $fileField['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $fileField['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    public function storeAttachmentsFromFiles(array $fileField, int $freelancerId): ?string
    {
        $files = array_values(array_filter(
            $this->normalizeFilesArray($fileField),
            static fn(array $file): bool => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));

        if ($files === []) {
            return null;
        }

        if (count($files) > 3) {
            throw new RuntimeException('You can upload up to 3 attachments.');
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', '7z', 'png', 'jpg', 'jpeg', 'webp'];
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'image/png',
            'image/jpeg',
            'image/webp',
        ];

        $uploadDir = dirname(__DIR__) . '/assets/uploads/candidatures/';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Unable to create the attachments directory.');
        }

        $stored = [];
        foreach ($files as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One of the attachments could not be uploaded.');
            }

            $size = (int) ($file['size'] ?? 0);
            if ($size > 10 * 1024 * 1024) {
                throw new RuntimeException('Each attachment must be smaller than 10 MB.');
            }

            $originalName = trim((string) ($file['name'] ?? 'attachment'));
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('Unsupported attachment type: ' . $originalName);
            }

            $mimeType = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeType = (string) finfo_file($finfo, (string) ($file['tmp_name'] ?? ''));
                    finfo_close($finfo);
                }
            }
            if ($mimeType !== '' && !in_array($mimeType, $allowedMimeTypes, true)) {
                throw new RuntimeException('Unsupported attachment type: ' . $originalName);
            }

            $filename = sprintf(
                'cand_%d_%d_%s.%s',
                max(1, $freelancerId),
                time(),
                bin2hex(random_bytes(6)),
                $extension
            );
            $destination = $uploadDir . $filename;

            if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destination)) {
                throw new RuntimeException('Unable to save the uploaded attachment.');
            }

            $stored[] = [
                'path' => 'assets/uploads/candidatures/' . $filename,
                'original_name' => $originalName !== '' ? $originalName : $filename,
                'mime_type' => $mimeType,
                'size' => $size,
            ];
        }

        return json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function decodeAttachments(?string $json): array
    {
        $decoded = json_decode((string) ($json ?? ''), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function backfillFromLegacyApplications(): void
    {
        try {
            $sql = 'SELECT a.job_offer_id, a.freelancer_id,
                           COALESCE(NULLIF(TRIM(a.cover_letter), ""), CONCAT("Application from freelancer #", a.freelancer_id)) AS message,
                           CASE
                               WHEN a.status IN ("accepted", "rejected") THEN a.status
                               ELSE "pending"
                           END AS status
                    FROM job_offer_applications a
                    LEFT JOIN candidatures c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                    WHERE c.id IS NULL';
            $stmt = $this->pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($rows === []) {
                return;
            }

            $insert = $this->pdo->prepare('INSERT INTO candidatures (job_offer_id, freelancer_id, message, status, created_at, updated_at)
                VALUES (:job_offer_id, :freelancer_id, :message, :status, NOW(), NOW())');
            foreach ($rows as $row) {
                $message = $this->sanitizeText((string) ($row['message'] ?? ''));
                if ($message === '') {
                    $message = 'Legacy application imported from the existing job offer applications.';
                }
                $insert->execute([
                    'job_offer_id' => (int) ($row['job_offer_id'] ?? 0),
                    'freelancer_id' => (int) ($row['freelancer_id'] ?? 0),
                    'message' => $message,
                    'status' => (string) ($row['status'] ?? 'pending'),
                ]);
            }
        } catch (Throwable $exception) {
            error_log('CandidatureController::backfillFromLegacyApplications - ' . $exception->getMessage());
        }
    }

    public function create(int $offreId, int $freelancerId, string $message): int
    {
        $message = $this->validateMessage($message);
        $stmt = $this->pdo->prepare('INSERT INTO candidatures (job_offer_id, freelancer_id, message, status, created_at, updated_at)
            VALUES (:job_offer_id, :freelancer_id, :message, :status, NOW(), NOW())');
        $stmt->execute([
            'job_offer_id' => $offreId,
            'freelancer_id' => $freelancerId,
            'message' => $message,
            'status' => 'pending',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listByOffer(int $offreId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.*, c.job_offer_id AS offre_id, u.first_name, u.last_name, u.email
            FROM candidatures c
            INNER JOIN users u ON u.id = c.freelancer_id
            WHERE c.job_offer_id = :job_offer_id
            ORDER BY c.created_at DESC');
        $stmt->execute(['job_offer_id' => $offreId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByOfferAndFreelancer(int $offreId, int $freelancerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT *, job_offer_id AS offre_id FROM candidatures WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1');
        $stmt->execute([
            'job_offer_id' => $offreId,
            'freelancer_id' => $freelancerId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $id, string $message): bool
    {
        $message = $this->validateMessage($message);
        $stmt = $this->pdo->prepare('UPDATE candidatures SET message = :message, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([
            'message' => $message,
            'id' => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM candidatures WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
