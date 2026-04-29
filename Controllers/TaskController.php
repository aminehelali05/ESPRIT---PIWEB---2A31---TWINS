<?php

include_once(__DIR__ . '/../config.php');

class TaskController
{
    private PDO $pdo;
    private const TASK_STATUSES = ['todo', 'in_progress', 'done'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureSchema();
        $this->backfillLegacyTasks();
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
            return (bool) ($stmt && $stmt->fetch(PDO::FETCH_NUM));
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function ensureSchema(): void
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                status ENUM("todo","in_progress","done") NOT NULL DEFAULT "todo",
                deadline DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_tasks_project_id (project_id),
                KEY idx_tasks_status (status),
                KEY idx_tasks_deadline (deadline),
                CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        } catch (Throwable $exception) {
            error_log('TaskController::ensureSchema - ' . $exception->getMessage());
        }
    }

    private function backfillLegacyTasks(): void
    {
        if (!$this->tableExists('taches')) {
            try {
                $this->pdo->exec("UPDATE tasks SET status = 'in_progress' WHERE status = 'blocked'");
            } catch (Throwable $exception) {
                error_log('TaskController::backfillLegacyTasks - ' . $exception->getMessage());
            }
            return;
        }

        try {
            $this->pdo->exec('INSERT IGNORE INTO tasks (id, project_id, title, description, status, deadline, created_at, updated_at)
                SELECT id, projet_id, title, description, status, deadline, created_at, updated_at
                FROM taches');
            $this->pdo->exec("UPDATE tasks SET status = 'in_progress' WHERE status = 'blocked'");
        } catch (Throwable $exception) {
            error_log('TaskController::backfillLegacyTasks - ' . $exception->getMessage());
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
        if ($status === 'blocked') {
            $status = 'in_progress';
        }
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

    private function withLegacyAlias(array $task): array
    {
        $task['projet_id'] = $task['project_id'] ?? ($task['projet_id'] ?? null);
        return $task;
    }

    public function listByProject(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tasks WHERE project_id = :project_id ORDER BY deadline ASC, id DESC');
        $stmt->execute(['project_id' => $projectId]);
        return array_map(fn(array $task): array => $this->withLegacyAlias($task), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listWithProjects(): array
    {
        $sql = 'SELECT t.*, p.title AS project_title, p.owner_id
                FROM tasks t
                INNER JOIN projects p ON p.id = t.project_id
                ORDER BY t.deadline ASC, t.id DESC';
        return array_map(fn(array $task): array => $this->withLegacyAlias($task), $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(int $projectId, array $payload): int
    {
        $clean = $this->validatePayload($payload);
        $stmt = $this->pdo->prepare('INSERT INTO tasks (project_id, title, description, status, deadline, created_at, updated_at)
            VALUES (:project_id, :title, :description, :status, :deadline, NOW(), NOW())');
        $stmt->execute($clean + ['project_id' => $projectId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $taskId, array $payload): bool
    {
        $clean = $this->validatePayload($payload);
        $stmt = $this->pdo->prepare('UPDATE tasks
            SET title = :title,
                description = :description,
                status = :status,
                deadline = :deadline,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute($clean + ['id' => $taskId]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $taskId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $taskId]);
        return $stmt->rowCount() > 0;
    }
}
