<?php

include_once(__DIR__ . '/../config.php');

class TacheController
{
    private PDO $pdo;
    private const TASK_STATUSES = ['todo', 'in_progress', 'blocked', 'done'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS taches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                projet_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT "todo",
                deadline DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_taches_project_id (projet_id),
                KEY idx_taches_status (status),
                CONSTRAINT fk_taches_project FOREIGN KEY (projet_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        } catch (Throwable $exception) {
            error_log('TacheController::ensureSchema - ' . $exception->getMessage());
        }
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function startsWithUppercase(string $value): bool
    {
        return (bool) preg_match('/^\p{Lu}/u', $value);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : null;
    }

    private function validatePayload(array $payload): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $status = strtolower($this->sanitizeText((string) ($payload['status'] ?? 'todo')));
        $deadline = $this->normalizeDate((string) ($payload['deadline'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Task title is required.');
        }
        if (mb_strlen($title) < 5 || mb_strlen($title) > 255) {
            throw new RuntimeException('Task title must be between 5 and 255 characters.');
        }
        if (!$this->startsWithUppercase($title)) {
            throw new RuntimeException('Task title must start with an uppercase letter.');
        }
        if (!preg_match('/^[\p{L}\p{N} ,.!?:;()\'"\/&+\-]+$/u', $title)) {
            throw new RuntimeException('Task title contains invalid characters.');
        }
        if ($description === '') {
            throw new RuntimeException('Task description is required.');
        }
        if (mb_strlen($description) < 20 || mb_strlen($description) > 4000) {
            throw new RuntimeException('Task description must be between 20 and 4000 characters.');
        }
        if (!in_array($status, self::TASK_STATUSES, true)) {
            throw new RuntimeException('Invalid task status.');
        }
        if ($deadline === null) {
            throw new RuntimeException('Task deadline is invalid.');
        }
        if (new DateTimeImmutable($deadline) <= new DateTimeImmutable('today')) {
            throw new RuntimeException('Task deadline must be after today.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'deadline' => $deadline,
        ];
    }

    public function listByProject(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM taches WHERE projet_id = :projet_id ORDER BY deadline ASC, id DESC');
        $stmt->execute(['projet_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listWithProjects(): array
    {
        $sql = 'SELECT t.*, p.title AS project_title, p.owner_id
                FROM taches t
                INNER JOIN projects p ON p.id = t.projet_id
                ORDER BY t.deadline ASC, t.id DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(int $projectId, array $payload): int
    {
        $clean = $this->validatePayload($payload);
        $stmt = $this->pdo->prepare('INSERT INTO taches (projet_id, title, description, status, deadline, created_at, updated_at)
            VALUES (:projet_id, :title, :description, :status, :deadline, NOW(), NOW())');
        $stmt->execute($clean + ['projet_id' => $projectId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $taskId, array $payload): bool
    {
        $clean = $this->validatePayload($payload);
        $stmt = $this->pdo->prepare('UPDATE taches
            SET title = :title,
                description = :description,
                status = :status,
                deadline = :deadline,
                updated_at = NOW()
            WHERE id = :id');
        return $stmt->execute($clean + ['id' => $taskId]);
    }

    public function delete(int $taskId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM taches WHERE id = :id');
        return $stmt->execute(['id' => $taskId]);
    }
}
