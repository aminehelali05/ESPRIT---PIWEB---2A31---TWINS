<?php

include_once(__DIR__ . '/../config.php');

class TaskController
{
    private PDO $pdo;
    private const TASK_STATUSES = ['todo', 'in_progress', 'done'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
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
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    private function columnExists(string $column): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM tasks LIKE " . $this->pdo->quote($column));
            return (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function ensureColumn(string $column, string $definition): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
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
        $estimatedTimeHoursRaw = trim((string) ($payload['estimated_time_hours'] ?? ''));
        $estimatedTimeHours = $estimatedTimeHoursRaw === '' ? null : (float) $estimatedTimeHoursRaw;
        $generatedContent = trim((string) ($payload['generated_content'] ?? ''));
        $subtasks = $payload['subtasks'] ?? null;
        $subtasksJson = null;

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
        if ($estimatedTimeHours !== null) {
            if (!is_numeric($estimatedTimeHoursRaw) || $estimatedTimeHours < 0 || $estimatedTimeHours > 10000) {
                throw new RuntimeException('Estimated time must be a valid value between 0 and 10000.');
            }
        }
        if ($subtasks !== null) {
            $subtasksArray = is_array($subtasks) ? $subtasks : [];
            $subtasksArray = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $subtasksArray), static fn(string $value): bool => $value !== ''));
            if ($subtasksArray !== []) {
                $encoded = json_encode($subtasksArray, JSON_UNESCAPED_UNICODE);
                if ($encoded === false) {
                    throw new RuntimeException('Subtasks could not be encoded.');
                }
                $subtasksJson = $encoded;
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'deadline' => $deadline,
            'estimated_time_hours' => $estimatedTimeHours,
            'generated_content' => $generatedContent !== '' ? $generatedContent : null,
            'subtasks_json' => $subtasksJson,
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
        $stmt = $this->pdo->prepare('INSERT INTO tasks (project_id, title, description, status, deadline, estimated_time_hours, generated_content, subtasks_json, created_at, updated_at)
            VALUES (:project_id, :title, :description, :status, :deadline, :estimated_time_hours, :generated_content, :subtasks_json, NOW(), NOW())');
        $stmt->execute($clean + ['project_id' => $projectId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $taskId, array $payload): bool
    {
        $clean = $this->validatePayload($payload);
        $existingStmt = $this->pdo->prepare('SELECT estimated_time_hours, generated_content, subtasks_json FROM tasks WHERE id = :id LIMIT 1');
        $existingStmt->execute(['id' => $taskId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!array_key_exists('estimated_time_hours', $payload)) {
            $clean['estimated_time_hours'] = array_key_exists('estimated_time_hours', $existing) ? $existing['estimated_time_hours'] : null;
        }
        if (!array_key_exists('generated_content', $payload)) {
            $clean['generated_content'] = array_key_exists('generated_content', $existing) ? $existing['generated_content'] : null;
        }
        if (!array_key_exists('subtasks', $payload)) {
            $clean['subtasks_json'] = array_key_exists('subtasks_json', $existing) ? $existing['subtasks_json'] : null;
        }
        $stmt = $this->pdo->prepare('UPDATE tasks
            SET title = :title,
                description = :description,
                status = :status,
                deadline = :deadline,
                estimated_time_hours = :estimated_time_hours,
                generated_content = :generated_content,
                subtasks_json = :subtasks_json,
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
