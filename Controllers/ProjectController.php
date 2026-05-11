<?php

include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/TaskController.php');
include_once(__DIR__ . '/../services/OpenRouterService.php');

class ProjectController
{
    private PDO $pdo;
    private TaskController $taskController;
    private OpenRouterService $openRouterService;
    private const PROJECT_STATUSES = ['planning', 'active', 'completed', 'on_hold', 'archived'];
    private const PROJECT_VISIBILITIES = ['team', 'public', 'private'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->taskController = new TaskController($this->pdo);
        $this->openRouterService = new OpenRouterService($this->pdo);
    }

    private function ensureAiFeatureSchema(): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    // Store a project mail and optionally send to members (owner + provided member ids)
    public function sendProjectMail(int $projectId, int $senderId, string $subject, string $body, array $memberIds = [], bool $isDigest = false, string $digestFrequency = 'daily'): bool
    {
        $project = $this->findById($projectId);
        if (!$project) {
            throw new RuntimeException('Project not found');
        }

        $stmt = $this->pdo->prepare('INSERT INTO project_mail (project_id, sender_id, subject, body, is_digest, digest_frequency, created_at) VALUES (:project_id, :sender_id, :subject, :body, :is_digest, :digest_frequency, NOW())');
        $stmt->execute([
            'project_id' => $projectId,
            'sender_id' => $senderId,
            'subject' => trim($subject),
            'body' => trim($body),
            'is_digest' => $isDigest ? 1 : 0,
            'digest_frequency' => in_array($digestFrequency, ['daily','weekly','none'], true) ? $digestFrequency : 'daily',
        ]);
        $mailId = (int) $this->pdo->lastInsertId();

        // store an internal message linked to this project
        try {
            $msg = $this->pdo->prepare('INSERT INTO messages (sender_id, content, created_at, entity_type, entity_id) VALUES (:sender_id, :content, NOW(), :etype, :eid)');
            $msg->execute([
                'sender_id' => $senderId,
                'content' => $body,
                'etype' => 'project',
                'eid' => $projectId,
            ]);
        } catch (Throwable $e) {
            // ignore
        }

        // send email to owner and provided members (best-effort)
        $recipients = [];
        if ((int)$project['owner_id'] > 0) {
            $owner = $this->pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
            $owner->execute(['id' => $project['owner_id']]);
            $ow = $owner->fetch(PDO::FETCH_ASSOC);
            if ($ow && trim((string)$ow['email']) !== '') {
                $recipients[] = $ow['email'];
            }
        }

        foreach ($memberIds as $mid) {
            $m = $this->pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
            $m->execute(['id' => (int)$mid]);
            $row = $m->fetch(PDO::FETCH_ASSOC);
            if ($row && trim((string)$row['email']) !== '') {
                $recipients[] = $row['email'];
            }
        }

        $recipients = array_values(array_unique(array_filter($recipients, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        if ($mailId > 0) {
            foreach (array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn(int $id): bool => $id > 0))) as $memberId) {
                $recipientEmail = null;
                foreach ($memberIds as $idx => $rawMemberId) {
                    if ((int) $rawMemberId === $memberId && isset($recipients[$idx])) {
                        $recipientEmail = $recipients[$idx];
                        break;
                    }
                }
                try {
                    $this->pdo->prepare('INSERT INTO project_mail_recipients (mail_id, user_id, recipient_email, is_digest, digest_frequency, sent_at, created_at)
                        VALUES (:mail_id, :user_id, :recipient_email, :is_digest, :digest_frequency, NOW(), NOW())')
                        ->execute([
                            'mail_id' => $mailId,
                            'user_id' => $memberId,
                            'recipient_email' => $recipientEmail,
                            'is_digest' => $isDigest ? 1 : 0,
                            'digest_frequency' => in_array($digestFrequency, ['daily', 'weekly', 'none'], true) ? $digestFrequency : 'daily',
                        ]);
                } catch (Throwable $exception) {
                }
            }
        }
        if ($recipients !== []) {
            $to = implode(',', $recipients);
            $headers = 'From: ' . (string) config::get('SMTP_FROM_EMAIL', 'no-reply@localhost');
            @mail($to, $subject, $body, $headers);
        }

        return true;
    }

    public function listProjectMail(int $projectId, int $limit = 50): array
    {
        $limit = max(1, min(300, $limit));
        $stmt = $this->pdo->prepare('SELECT pm.*, u.first_name, u.last_name
            FROM project_mail pm
            LEFT JOIN users u ON u.id = pm.sender_id
            WHERE pm.project_id = :project_id
            ORDER BY pm.created_at DESC
            LIMIT ' . $limit);
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute an existing task with AI and return a structured result.
     * Stores output in task_outputs. Returns ['output_text', 'output_type', 'task_output_id'].
     */
    public function executeTaskWithAI(int $taskId, int $userId): array
    {
        // Load task + project
        $taskStmt = $this->pdo->prepare(
            'SELECT t.*, p.title AS project_title, p.description AS project_description, p.owner_id
             FROM tasks t
             INNER JOIN projects p ON p.id = t.project_id
             WHERE t.id = :id LIMIT 1'
        );
        $taskStmt->execute(['id' => $taskId]);
        $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            throw new RuntimeException('Task not found.');
        }

        $taskTitle       = (string)($task['title']               ?? '');
        $taskDescription = (string)($task['description']         ?? '');
        $projectTitle    = (string)($task['project_title']       ?? '');
        $projectDesc     = (string)($task['project_description'] ?? '');

        // Classify output type from task content
        $lowerTitle = strtolower($taskTitle . ' ' . $taskDescription);
        $outputType = 'text';
        if (preg_match('/\b(report|summary|analysis|overview|brief|memo|documentation)\b/', $lowerTitle)) {
            $outputType = 'report';
        } elseif (preg_match('/\b(plan|roadmap|strategy|steps|checklist|list)\b/', $lowerTitle)) {
            $outputType = 'plan';
        } elseif (preg_match('/\b(code|script|function|class|module|api|endpoint)\b/', $lowerTitle)) {
            $outputType = 'code';
        } elseif (preg_match('/\b(json|data|structure|schema|config)\b/', $lowerTitle)) {
            $outputType = 'json';
        }

        // Build execution prompt
        $systemPrompt = <<<SYS
You are an expert AI assistant that EXECUTES tasks for software/business projects.
You do not describe what to do — you DO IT and return the actual deliverable.

Rules:
- Return a COMPLETE, READY-TO-USE output. Not a plan. The real thing.
- If the task is a report → write the full report.
- If the task is a checklist/plan → write a detailed, actionable checklist.
- If the task is code → write working, commented code.
- If the task is analysis → perform the analysis with real insights.
- Use clean markdown formatting: headings (##), bold (**), bullet points (-).
- Be thorough but concise. Maximum 600 words.
- Start immediately with the output. No preamble like "Here is your report".
SYS;

        $userPrompt = <<<USR
Project: {$projectTitle}
Project Context: {$projectDesc}

Task Title: {$taskTitle}
Task Description: {$taskDescription}

Execute this task completely. Return the actual deliverable now.
USR;

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userPrompt],
        ];

        $resp = $this->openRouterService->callChat($messages, [
            'temperature' => 0.3,
            'max_tokens'  => 900,
        ]);

        $outputText = trim((string)($resp['choices'][0]['message']['content'] ?? ''));
        if ($outputText === '') {
            throw new RuntimeException('AI returned an empty response. Please try again.');
        }

        // Store in task_outputs
        $taskOutputId = null;
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO task_outputs (project_id, task_id, user_id, prompt, output_json, output_text, created_at)
                 VALUES (:project_id, :task_id, :user_id, :prompt, :output_json, :output_text, NOW())'
            );
            $ins->execute([
                'project_id'  => (int)($task['project_id'] ?? 0),
                'task_id'     => $taskId,
                'user_id'     => $userId,
                'prompt'      => $taskTitle . ': ' . $taskDescription,
                'output_json' => json_encode(['type' => $outputType, 'text' => $outputText], JSON_UNESCAPED_UNICODE),
                'output_text' => $outputText,
            ]);
            $taskOutputId = (int)$this->pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('executeTaskWithAI storage error: ' . $e->getMessage());
        }

        return [
            'output_text'    => $outputText,
            'output_type'    => $outputType,
            'task_output_id' => $taskOutputId,
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
        return trim((string) ($row['email'] ?? '')) ?: ('User #' . (int) ($row['id'] ?? 0));
    }

    public function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : null;
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function startsWithUppercase(string $value): bool
    {
        return (bool) preg_match('/^\p{Lu}/u', $value);
    }

    private function matchesPattern(string $value, string $pattern): bool
    {
        return (bool) preg_match($pattern, $value);
    }

    private function validateProjectPayload(array $payload, bool $requireOwner = true): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $shortDescription = $this->sanitizeText((string) ($payload['short_description'] ?? ''));
        $technologies = $this->sanitizeText((string) ($payload['technologies'] ?? ''));
        $status = strtolower($this->sanitizeText((string) ($payload['status'] ?? 'planning')));
        $visibility = strtolower($this->sanitizeText((string) ($payload['visibility'] ?? 'team')));
        $progress = (int) ($payload['progress_percent'] ?? 0);
        $ownerId = (int) ($payload['owner_id'] ?? 0);
        $contractId = (int) ($payload['contract_id'] ?? 0);
        $budgetRaw = trim((string) ($payload['budget'] ?? ''));
        $budget = $budgetRaw === '' ? null : (float) $budgetRaw;
        $dueDate = $this->parseDate((string) ($payload['due_date'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Project title is required.');
        }
        if (mb_strlen($title) < 10 || mb_strlen($title) > 140) {
            throw new RuntimeException('Project title must be between 10 and 140 characters.');
        }
        if (!$this->startsWithUppercase($title)) {
            throw new RuntimeException('Project title must start with an uppercase letter.');
        }
        if (!$this->matchesPattern($title, '/^[\p{L}\p{N} ,.!?:;()\'"\/&+\-]+$/u')) {
            throw new RuntimeException('Project title contains invalid characters.');
        }

        if ($description === '') {
            throw new RuntimeException('Project description is required.');
        }
        if (mb_strlen($description) < 60 || mb_strlen($description) > 5000) {
            throw new RuntimeException('Project description must be between 60 and 5000 characters.');
        }
        if (!$this->startsWithUppercase($description)) {
            throw new RuntimeException('Project description must start with an uppercase letter.');
        }

        if ($shortDescription !== '') {
            if (mb_strlen($shortDescription) < 15 || mb_strlen($shortDescription) > 220) {
                throw new RuntimeException('Short description must be between 15 and 220 characters.');
            }
            if (!$this->startsWithUppercase($shortDescription)) {
                throw new RuntimeException('Short description must start with an uppercase letter.');
            }
        }

        if ($technologies !== '') {
            if (mb_strlen($technologies) < 3 || mb_strlen($technologies) > 255) {
                throw new RuntimeException('Technologies must be between 3 and 255 characters.');
            }
            if (!$this->matchesPattern($technologies, '/^[\p{L}\p{N} ,.+#()\/_\-]+$/u')) {
                throw new RuntimeException('Technologies contain invalid characters.');
            }
        }

        if (!in_array($status, self::PROJECT_STATUSES, true)) {
            throw new RuntimeException('Invalid project status.');
        }
        if (!in_array($visibility, self::PROJECT_VISIBILITIES, true)) {
            throw new RuntimeException('Invalid project visibility.');
        }
        if ($progress < 0 || $progress > 100) {
            throw new RuntimeException('Progress must be between 0 and 100.');
        }
        if ($requireOwner && $ownerId <= 0) {
            throw new RuntimeException('Project owner is required.');
        }
        if ($budget !== null) {
            if (!is_numeric($budgetRaw) || $budget < 0) {
                throw new RuntimeException('Budget must be a valid positive number.');
            }
            if ($budget > 10000000) {
                throw new RuntimeException('Budget is too high.');
            }
        }
        if (trim((string) ($payload['due_date'] ?? '')) !== '') {
            if ($dueDate === null) {
                throw new RuntimeException('Due date is invalid.');
            }
            if (new DateTimeImmutable($dueDate) < new DateTimeImmutable('today')) {
                throw new RuntimeException('Due date cannot be in the past.');
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'cover_image' => trim((string) ($payload['cover_image'] ?? '')) ?: null,
            'short_description' => $shortDescription !== '' ? $shortDescription : null,
            'technologies' => $technologies !== '' ? $technologies : null,
            'status' => $status,
            'progress_percent' => $progress,
            'budget' => $budget,
            'due_date' => $dueDate,
            'owner_id' => $ownerId,
            'contract_id' => $contractId > 0 ? $contractId : null,
            'visibility' => $visibility,
        ];
    }

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT p.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS tasks_count
                FROM projects p
                LEFT JOIN users u ON u.id = p.owner_id
                ORDER BY p.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buildBackofficeStats(array $rows): array
    {
        $active = 0;
        $completed = 0;
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'planning');
            if ($status === 'active') {
                $active++;
            }
            if ($status === 'completed') {
                $completed++;
            }
        }
        return ['total' => count($rows), 'active' => $active, 'completed' => $completed];
    }

    public function buildBackofficeDashboard(array $filters = []): array
    {
        $fromDate = trim((string) ($filters['from'] ?? ''));
        $toDate = trim((string) ($filters['to'] ?? ''));
        $query = strtolower(trim((string) ($filters['q'] ?? '')));

        $rows = $this->listBackofficeRows();
        $projectIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows);
        $tasksByProject = $projectIds !== [] ? $this->tasksMapForProjects($projectIds) : [];
        $inDateRange = static function (?string $value) use ($fromDate, $toDate): bool {
            if ($value === null || trim($value) === '') {
                return true;
            }
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                return true;
            }
            if ($fromDate !== '') {
                $fromTimestamp = strtotime($fromDate . ' 00:00:00');
                if ($fromTimestamp !== false && $timestamp < $fromTimestamp) {
                    return false;
                }
            }
            if ($toDate !== '') {
                $toTimestamp = strtotime($toDate . ' 23:59:59');
                if ($toTimestamp !== false && $timestamp > $toTimestamp) {
                    return false;
                }
            }
            return true;
        };

        $filteredRows = array_values(array_filter($rows, static function (array $row) use ($query, $inDateRange): bool {
            if (!$inDateRange((string) ($row['created_at'] ?? ''))) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            return str_contains(strtolower((string) json_encode($row)), $query);
        }));
        $filteredProjectIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $filteredRows);
        $filteredTasksByProject = [];
        foreach ($filteredProjectIds as $projectId) {
            $filteredTasksByProject[$projectId] = $tasksByProject[$projectId] ?? [];
        }

        $statusDistribution = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
        $workloadByOwner = [];
        $durationByStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
        $durationCountByStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
        $burndownBuckets = [];
        $completionPercentages = [];
        $onTimeTotal = 0;
        $onTimeDone = 0;

        foreach ($filteredRows as $projectRow) {
            $completionPercentages[] = (int) ($projectRow['progress_percent'] ?? 0);
            $owner = trim((string) ($projectRow['first_name'] ?? '') . ' ' . (string) ($projectRow['last_name'] ?? ''));
            $ownerKey = $owner !== '' ? $owner : 'Unknown';
            $projectTasks = $filteredTasksByProject[(int) ($projectRow['id'] ?? 0)] ?? [];
            $workloadByOwner[$ownerKey] = ($workloadByOwner[$ownerKey] ?? 0) + count($projectTasks);

            foreach ($projectTasks as $taskRow) {
                $status = strtolower((string) ($taskRow['status'] ?? 'todo'));
                if (!isset($statusDistribution[$status])) {
                    $statusDistribution[$status] = 0;
                    $durationByStatus[$status] = 0;
                    $durationCountByStatus[$status] = 0;
                }
                $statusDistribution[$status]++;
                $createdAt = (string) ($taskRow['created_at'] ?? '');
                $deadline = (string) ($taskRow['deadline'] ?? '');
                if ($createdAt !== '' && $deadline !== '' && strtotime($deadline) !== false && strtotime($createdAt) !== false) {
                    $days = max(0, (int) floor((strtotime($deadline) - strtotime($createdAt)) / 86400));
                    $durationByStatus[$status] += $days;
                    $durationCountByStatus[$status]++;
                }
                if ($deadline !== '' && strtotime($deadline) !== false) {
                    $dayKey = date('Y-m-d', strtotime($deadline));
                    if (!isset($burndownBuckets[$dayKey])) {
                        $burndownBuckets[$dayKey] = 0;
                    }
                    if ($status !== 'done') {
                        $burndownBuckets[$dayKey]++;
                    }
                    if ($status === 'done') {
                        $onTimeTotal++;
                        $updatedAt = (string) ($taskRow['updated_at'] ?? $taskRow['created_at'] ?? 'now');
                        if (strtotime($updatedAt) <= strtotime($deadline)) {
                            $onTimeDone++;
                        }
                    }
                }
            }
        }

        arsort($workloadByOwner);
        ksort($burndownBuckets);
        $averageCompletion = $completionPercentages !== [] ? round(array_sum($completionPercentages) / count($completionPercentages), 1) : 0;
        $onTimeRate = $onTimeTotal > 0 ? round(($onTimeDone / $onTimeTotal) * 100, 1) : 0;
        $durationAverages = [];
        foreach ($durationByStatus as $status => $sum) {
            $durationAverages[$status] = ($durationCountByStatus[$status] ?? 0) > 0 ? round($sum / $durationCountByStatus[$status], 1) : 0;
        }

        return [
            'filters' => ['from' => $fromDate, 'to' => $toDate, 'q' => $query],
            'rows' => $filteredRows,
            'tasks_by_project' => $filteredTasksByProject,
            'stats' => [
                'total' => count($filteredRows),
                'active' => count(array_filter($filteredRows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'active')),
                'completed' => count(array_filter($filteredRows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'completed')),
                'average_completion' => $averageCompletion,
                'on_time_rate' => $onTimeRate,
            ],
            'charts' => [
                'status_distribution' => $statusDistribution,
                'duration_averages' => $durationAverages,
                'workload_by_owner' => $workloadByOwner,
                'burndown' => $burndownBuckets,
            ],
        ];
    }

    public function create(array $payload): int
    {
        $clean = $this->validateProjectPayload($payload, true);
        $stmt = $this->pdo->prepare('INSERT INTO projects (title, description, cover_image, short_description, technologies, status, progress_percent, budget, due_date, owner_id, contract_id, visibility, created_at, updated_at) VALUES (:title, :description, :cover_image, :short_description, :technologies, :status, :progress_percent, :budget, :due_date, :owner_id, :contract_id, :visibility, NOW(), NOW())');
        $stmt->execute($clean);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProgressStatus(int $projectId, string $status, int $progress): bool
    {
        $stmt = $this->pdo->prepare('UPDATE projects SET status = :status, progress_percent = :progress, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([
            'status' => $status,
            'progress' => max(0, min(100, $progress)),
            'id' => $projectId,
        ]);
    }

    public function deleteById(int $projectId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = :id');
        return $stmt->execute(['id' => $projectId]);
    }

    public function findById(int $projectId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listFrontofficeRows(int $userId, array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $statusFilter = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $visibilityFilter = strtolower(trim((string) ($filters['visibility'] ?? 'all')));
        $techFilter = trim((string) ($filters['tech'] ?? 'all'));
        $sortFilter = strtolower(trim((string) ($filters['sort'] ?? 'newest')));

        $where = ['(p.owner_id = :uid OR p.visibility IN ("team", "public"))'];
        $params = ['uid' => $userId];

        if ($q !== '') {
            $where[] = '(p.title LIKE :q OR p.description LIKE :q OR p.short_description LIKE :q OR p.technologies LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if (in_array($statusFilter, ['planning', 'active', 'completed', 'on_hold', 'archived'], true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $statusFilter;
        }
        if (in_array($visibilityFilter, ['private', 'team', 'public'], true)) {
            $where[] = 'p.visibility = :visibility';
            $params['visibility'] = $visibilityFilter;
        }
        if ($techFilter !== '' && $techFilter !== 'all') {
            $where[] = 'p.technologies LIKE :tech';
            $params['tech'] = '%' . $techFilter . '%';
        }

        $orderBy = 'p.created_at DESC';
        if ($sortFilter === 'progress') {
            $orderBy = 'p.progress_percent DESC, p.created_at DESC';
        } elseif ($sortFilter === 'budget_desc') {
            $orderBy = 'p.budget DESC, p.created_at DESC';
        } elseif ($sortFilter === 'budget_asc') {
            $orderBy = 'p.budget ASC, p.created_at DESC';
        }

        $sql = 'SELECT p.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS tasks_count
                FROM projects p
                LEFT JOIN users u ON u.id = p.owner_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOwnedByUser(int $projectId, int $ownerId, array $payload): bool
    {
        $clean = $this->validateProjectPayload($payload + ['owner_id' => $ownerId], false);
        $stmt = $this->pdo->prepare('UPDATE projects SET title = :title, description = :description, cover_image = :cover_image, short_description = :short_description, technologies = :technologies, status = :status, progress_percent = :progress_percent, budget = :budget, due_date = :due_date, contract_id = :contract_id, visibility = :visibility, updated_at = NOW() WHERE id = :id AND owner_id = :owner_id');
        return $stmt->execute($clean + ['id' => $projectId, 'owner_id' => $ownerId]);
    }

    public function deleteOwnedByUser(int $projectId, int $ownerId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = :id AND owner_id = :owner_id');
        return $stmt->execute(['id' => $projectId, 'owner_id' => $ownerId]);
    }

    public function listTasksByProject(int $projectId): array
    {
        return $this->taskController->listByProject($projectId);
    }

    public function createTask(array $payload): int
    {
        $projectId = (int) ($payload['project_id'] ?? $payload['projet_id'] ?? 0);
        if ($projectId <= 0) {
            throw new RuntimeException('Project is required.');
        }

        $ownerId = (int) ($payload['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $project = $this->findById($projectId);
            if (!$project || (int) ($project['owner_id'] ?? 0) !== $ownerId) {
                throw new RuntimeException('You can only create tasks for your own project.');
            }
        }

        return $this->taskController->create($projectId, [
            'title' => (string) ($payload['title'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'status' => (string) ($payload['status'] ?? 'todo'),
            'deadline' => (string) ($payload['deadline'] ?? ''),
        ]);
    }

    public function updateTask(int $taskId, array $payload): bool
    {
        return $this->taskController->update($taskId, $payload);
    }

    public function updateTaskForOwner(int $taskId, int $ownerId, array $payload): bool
    {
        $task = $this->pdo->prepare('SELECT t.id FROM tasks t INNER JOIN projects p ON p.id = t.project_id WHERE t.id = :id AND p.owner_id = :owner_id LIMIT 1');
        $task->execute(['id' => $taskId, 'owner_id' => $ownerId]);
        if (!$task->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('You can only update tasks from your own project.');
        }
        return $this->taskController->update($taskId, $payload);
    }

    public function deleteTask(int $taskId): bool
    {
        return $this->taskController->delete($taskId);
    }

    public function deleteTaskForOwner(int $taskId, int $ownerId): bool
    {
        $task = $this->pdo->prepare('SELECT t.id FROM tasks t INNER JOIN projects p ON p.id = t.project_id WHERE t.id = :id AND p.owner_id = :owner_id LIMIT 1');
        $task->execute(['id' => $taskId, 'owner_id' => $ownerId]);
        if (!$task->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('You can only delete tasks from your own project.');
        }
        return $this->taskController->delete($taskId);
    }

    public function findTaskById(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT t.*, t.project_id AS projet_id, p.title AS project_title, p.owner_id FROM tasks t INNER JOIN projects p ON p.id = t.project_id WHERE t.id = :id LIMIT 1');
        $stmt->execute(['id' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function tasksMapForProjects(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds), static fn(int $value): bool => $value > 0)));
        if ($projectIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $stmt = $this->pdo->prepare('SELECT *, project_id AS projet_id FROM tasks WHERE project_id IN (' . $placeholders . ') ORDER BY deadline ASC, id DESC');
        $stmt->execute($projectIds);
        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $task) {
            $projectId = (int) ($task['project_id'] ?? $task['projet_id'] ?? 0);
            $grouped[$projectId][] = $task;
        }
        return $grouped;
    }
}
