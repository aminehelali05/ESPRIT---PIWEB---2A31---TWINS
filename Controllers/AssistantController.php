<?php

include_once(__DIR__ . '/../config.php');

class AssistantController
{
    private const DEFAULT_MODEL = 'openai/gpt-5.2';
    private const TASKS_FILE = 'tasks.json';
    private const MEMORY_FILE = 'memory.json';
    private const MAX_STEPS = 8;
    private const MAX_RECENT_TASKS = 12;
    private const MAX_TASK_EVENTS = 20;
    private const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    private string $storageDir;
    private string $tasksPath;
    private string $memoryPath;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'assistant';
        $this->tasksPath = $this->storageDir . DIRECTORY_SEPARATOR . self::TASKS_FILE;
        $this->memoryPath = $this->storageDir . DIRECTORY_SEPARATOR . self::MEMORY_FILE;
        $this->ensureStorage();
    }

    public static function bootstrapConfig(): array
    {
        $appUrl = rtrim(config::getBaseUrl(), '/');
        $model = trim((string) config::get('OPENROUTER_MODEL', self::DEFAULT_MODEL));
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }

        return [
            'provider' => 'OpenRouter',
            'model' => $model,
            'app_url' => $appUrl,
            'realtime' => [
                'enabled' => true,
                'url' => rtrim((string) config::get('ASSISTANT_WS_URL', 'ws://127.0.0.1:8787/assistant'), '/'),
            ],
            'api' => [
                'command' => $appUrl . '/index.php?action=assistant_command',
                'status' => $appUrl . '/index.php?action=assistant_status',
                'task' => $appUrl . '/index.php?action=assistant_task',
            ],
            'features' => [
                'voice_input' => true,
                'voice_feedback' => true,
                'whatsapp' => true,
                'browser_execution' => true,
                'live_polling' => true,
            ],
        ];
    }

    public function getBootstrapState(int $userId): array
    {
        $state = $this->readTasksState();
        $memory = $this->readMemoryState();
        $tasks = $this->normalizeTasks((array) ($state['tasks'] ?? []));
        $recent = $this->getRecentTasksFromList($tasks, $userId, self::MAX_RECENT_TASKS);
        $active = $this->getActiveTaskFromList($tasks, $userId);

        return [
            'success' => true,
            'configured' => trim((string) config::get('OPENROUTER_API_KEY', '')) !== '',
            'provider' => 'OpenRouter',
            'model' => self::bootstrapConfig()['model'],
            'queue' => [
                'pending' => $this->countTasksByStatus($tasks, ['queued', 'planning']),
                'running' => $this->countTasksByStatus($tasks, ['executing']),
                'done' => $this->countTasksByStatus($tasks, ['completed']),
                'failed' => $this->countTasksByStatus($tasks, ['failed']),
            ],
            'active_task' => $active,
            'recent_tasks' => $recent,
            'memory' => $this->normalizeMemoryForUser($memory, $userId),
            'last_active_user_id' => (int) ($memory['meta']['last_active_user_id'] ?? 0),
            'updated_at' => (string) ($state['updated_at'] ?? ''),
            'now' => gmdate('c'),
        ];
    }

    public function submitCommand(int $userId, string $message, array $context = []): array
    {
        $normalizedMessage = trim(preg_replace('/\s+/', ' ', (string) $message));
        if ($normalizedMessage === '') {
            throw new RuntimeException('Please enter a command first.');
        }

        $source = strtolower(trim((string) ($context['source'] ?? 'web')));
        if (!in_array($source, ['web', 'voice', 'whatsapp'], true)) {
            $source = 'web';
        }

        $effectiveUserId = $userId > 0 ? $userId : (int) ($context['user_id'] ?? 0);
        if ($effectiveUserId <= 0) {
            $effectiveUserId = (int) ($this->readMemoryState()['meta']['last_active_user_id'] ?? 0);
        }

        $planContext = array_merge([
            'user_id' => $effectiveUserId,
            'source' => $source,
            'page' => (string) ($context['page'] ?? 'profile'),
            'app_url' => rtrim(config::getBaseUrl(), '/'),
            'history' => $this->getRecentMemorySummary($effectiveUserId),
        ], is_array($context) ? $context : []);
        $planContext['user_id'] = $effectiveUserId;
        $planContext['source'] = $source;
        $planContext['page'] = (string) ($planContext['page'] ?? 'profile');
        $planContext['app_url'] = rtrim(config::getBaseUrl(), '/');
        $planContext['history'] = $this->getRecentMemorySummary($effectiveUserId);

        $plan = $this->planCommand($normalizedMessage, $planContext);
        $taskId = $this->createTaskId();
        $requiresBrowser = !empty($plan['requires_browser']);
        $requiresWhatsApp = !empty($plan['requires_whatsapp']);
        $steps = $this->normalizeSteps((array) ($plan['steps'] ?? []));

        if ($steps === []) {
            $steps = $this->buildFallbackSteps($normalizedMessage, $planContext);
        }
        $requiresBrowser = $requiresBrowser || $steps !== [];

        $task = [
            'id' => $taskId,
            'user_id' => $effectiveUserId,
            'source' => $source,
            'message' => $normalizedMessage,
            'goal' => (string) ($plan['goal'] ?? $normalizedMessage),
            'assistant_message' => (string) ($plan['assistant_message'] ?? $plan['voice_reply'] ?? 'Task queued.'),
            'status' => $requiresBrowser ? 'queued' : 'completed',
            'priority' => $this->normalizePriority((string) ($plan['priority'] ?? 'normal')),
            'plan' => [
                'goal' => (string) ($plan['goal'] ?? $normalizedMessage),
                'summary' => (string) ($plan['summary'] ?? $normalizedMessage),
                'assistant_message' => (string) ($plan['assistant_message'] ?? $plan['voice_reply'] ?? 'Task queued.'),
                'voice_reply' => (string) ($plan['voice_reply'] ?? $plan['assistant_message'] ?? 'Task queued.'),
                'requires_browser' => $requiresBrowser,
                'requires_whatsapp' => $requiresWhatsApp,
                'steps' => $steps,
            ],
            'context' => $planContext,
            'events' => [],
            'result' => null,
            'error' => null,
            'claimed_by' => null,
            'notifications' => [
                'whatsapp_sent' => false,
                'web_spoken' => false,
            ],
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'started_at' => null,
            'finished_at' => null,
        ];

        if ($requiresBrowser) {
            $task['events'][] = $this->buildEvent('queued', 'Task queued for browser execution.', 'queue');
        } else {
            $task['result'] = [
                'success' => true,
                'summary' => (string) ($plan['assistant_message'] ?? 'Completed.'),
            ];
            $task['finished_at'] = gmdate('c');
            $task['events'][] = $this->buildEvent('completed', 'Task completed immediately.', 'final');
        }

        $state = $this->readTasksState();
        $tasks = $this->normalizeTasks((array) ($state['tasks'] ?? []));
        $tasks[] = $task;
        $state['tasks'] = $tasks;
        $state['updated_at'] = gmdate('c');
        $this->writeTasksState($state);

        $this->updateMemory($effectiveUserId, [
            'last_active_user_id' => $effectiveUserId,
            'last_command' => $normalizedMessage,
            'last_source' => $source,
            'last_task_id' => $taskId,
            'last_plan_summary' => (string) ($plan['summary'] ?? $normalizedMessage),
        ]);

        return [
            'success' => true,
            'task' => $this->summarizeTask($task),
            'assistant_message' => (string) ($task['assistant_message'] ?? 'Task queued.'),
            'voice_reply' => (string) ($task['plan']['voice_reply'] ?? $task['assistant_message'] ?? 'Task queued.'),
            'queue_position' => $requiresBrowser ? $this->queuePositionForTask($tasks, $taskId, $effectiveUserId) : 0,
            'memory' => $this->normalizeMemoryForUser($this->readMemoryState(), $effectiveUserId),
        ];
    }

    public function getTaskById(string $taskId, int $userId = 0): ?array
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            return null;
        }

        $state = $this->readTasksState();
        foreach ($this->normalizeTasks((array) ($state['tasks'] ?? [])) as $task) {
            if ((string) ($task['id'] ?? '') !== $taskId) {
                continue;
            }
            if ($userId > 0 && (int) ($task['user_id'] ?? 0) !== $userId) {
                continue;
            }
            return $this->summarizeTask($task, true);
        }

        return null;
    }

    public function getTaskFeed(int $userId, int $limit = self::MAX_RECENT_TASKS): array
    {
        $state = $this->readTasksState();
        $tasks = $this->normalizeTasks((array) ($state['tasks'] ?? []));
        return $this->getRecentTasksFromList($tasks, $userId, $limit);
    }

    public function appendTaskEvent(string $taskId, array $event, ?string $expectedStatus = null): ?array
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            return null;
        }

        $state = $this->readTasksState();
        $tasks = $this->normalizeTasks((array) ($state['tasks'] ?? []));
        foreach ($tasks as $index => $task) {
            if ((string) ($task['id'] ?? '') !== $taskId) {
                continue;
            }
            if ($expectedStatus !== null && (string) ($task['status'] ?? '') !== $expectedStatus) {
                return $this->summarizeTask($task, true);
            }

            $task['events'] = $task['events'] ?? [];
            $task['events'][] = $this->buildEvent(
                (string) ($event['status'] ?? ($task['status'] ?? 'update')),
                (string) ($event['message'] ?? ''),
                (string) ($event['action'] ?? 'update'),
                $event
            );
            $task['events'] = array_slice((array) $task['events'], -self::MAX_TASK_EVENTS);

            if (isset($event['status'])) {
                $task['status'] = $this->normalizeStatus((string) $event['status']);
            }
            if (array_key_exists('result', $event)) {
                $task['result'] = $event['result'];
            }
            if (array_key_exists('error', $event)) {
                $task['error'] = $event['error'];
            }
            if (!empty($event['claimed_by'])) {
                $task['claimed_by'] = (string) $event['claimed_by'];
            }
            if (!empty($event['plan']) && is_array($event['plan'])) {
                $task['plan'] = $this->normalizePlan((array) $event['plan'], (string) ($task['message'] ?? ''));
            }
            if (!empty($event['notifications']) && is_array($event['notifications'])) {
                $task['notifications'] = array_merge((array) ($task['notifications'] ?? []), $event['notifications']);
            }

            $now = gmdate('c');
            $task['updated_at'] = $now;
            if (in_array($task['status'], ['executing'], true) && empty($task['started_at'])) {
                $task['started_at'] = $now;
            }
            if (in_array($task['status'], ['completed', 'failed', 'cancelled'], true) && empty($task['finished_at'])) {
                $task['finished_at'] = $now;
            }

            $tasks[$index] = $task;
            $state['tasks'] = $tasks;
            $state['updated_at'] = $now;
            $this->writeTasksState($state);
            return $this->summarizeTask($task, true);
        }

        return null;
    }

    private function ensureStorage(): void
    {
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw new RuntimeException('Unable to create assistant storage directory.');
        }

        if (!is_file($this->tasksPath)) {
            $this->writeTasksState([
                'version' => 1,
                'updated_at' => gmdate('c'),
                'tasks' => [],
            ]);
        }

        if (!is_file($this->memoryPath)) {
            $this->writeMemoryState([
                'version' => 1,
                'updated_at' => gmdate('c'),
                'meta' => [
                    'last_active_user_id' => 0,
                ],
                'users' => [],
            ]);
        }
    }

    private function readTasksState(): array
    {
        return $this->readJsonFile($this->tasksPath, [
            'version' => 1,
            'updated_at' => gmdate('c'),
            'tasks' => [],
        ]);
    }

    private function writeTasksState(array $state): bool
    {
        return $this->writeJsonFile($this->tasksPath, $state);
    }

    private function readMemoryState(): array
    {
        return $this->readJsonFile($this->memoryPath, [
            'version' => 1,
            'updated_at' => gmdate('c'),
            'meta' => [
                'last_active_user_id' => 0,
            ],
            'users' => [],
        ]);
    }

    private function writeMemoryState(array $state): bool
    {
        return $this->writeJsonFile($this->memoryPath, $state);
    }

    private function readJsonFile(string $path, array $fallback): array
    {
        if (!is_file($path)) {
            return $fallback;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return $fallback;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    private function writeJsonFile(string $path, array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        $tmpPath = $path . '.tmp';
        if (@file_put_contents($tmpPath, $json, LOCK_EX) === false) {
            return false;
        }

        if (is_file($path)) {
            @unlink($path);
        }

        $renamed = @rename($tmpPath, $path);
        if (!$renamed && is_file($tmpPath)) {
            @unlink($tmpPath);
        }

        return $renamed;
    }

    private function createTaskId(): string
    {
        return 'task_' . gmdate('Ymd_His') . '_' . substr(bin2hex(random_bytes(5)), 0, 10);
    }

    private function buildEvent(string $status, string $message = '', string $action = 'update', array $extra = []): array
    {
        return array_merge([
            'status' => $this->normalizeStatus($status),
            'action' => trim($action) !== '' ? trim($action) : 'update',
            'message' => trim($message),
            'timestamp' => gmdate('c'),
            'duration_ms' => (int) ($extra['duration_ms'] ?? 0),
        ], $extra);
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtolower(trim($status));
        $allowed = ['queued', 'planning', 'executing', 'completed', 'failed', 'cancelled', 'waiting', 'running', 'final'];
        return in_array($value, $allowed, true) ? $value : 'queued';
    }

    private function normalizePriority(string $priority): string
    {
        $value = strtolower(trim($priority));
        return in_array($value, ['low', 'normal', 'high', 'urgent'], true) ? $value : 'normal';
    }

    private function normalizeTasks(array $tasks): array
    {
        $normalized = [];
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $normalized[] = $this->normalizeTask($task);
        }
        usort($normalized, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            return $rightTime <=> $leftTime;
        });
        return $normalized;
    }

    private function normalizeTask(array $task): array
    {
        $task['id'] = (string) ($task['id'] ?? $this->createTaskId());
        $task['user_id'] = (int) ($task['user_id'] ?? 0);
        $task['source'] = strtolower(trim((string) ($task['source'] ?? 'web')));
        $task['message'] = trim((string) ($task['message'] ?? ''));
        $task['goal'] = trim((string) ($task['goal'] ?? $task['message']));
        $task['assistant_message'] = trim((string) ($task['assistant_message'] ?? ''));
        $task['status'] = $this->normalizeStatus((string) ($task['status'] ?? 'queued'));
        $task['priority'] = $this->normalizePriority((string) ($task['priority'] ?? 'normal'));
        $task['plan'] = $this->normalizePlan((array) ($task['plan'] ?? []), $task['message']);
        $task['context'] = is_array($task['context'] ?? null) ? $task['context'] : [];
        $task['events'] = $this->normalizeEvents((array) ($task['events'] ?? []));
        $task['notifications'] = is_array($task['notifications'] ?? null) ? array_merge([
            'whatsapp_sent' => false,
            'web_spoken' => false,
        ], $task['notifications']) : [
            'whatsapp_sent' => false,
            'web_spoken' => false,
        ];
        $task['result'] = $task['result'] ?? null;
        $task['error'] = $task['error'] ?? null;
        $task['claimed_by'] = $task['claimed_by'] ?? null;
        $task['created_at'] = (string) ($task['created_at'] ?? gmdate('c'));
        $task['updated_at'] = (string) ($task['updated_at'] ?? $task['created_at']);
        $task['started_at'] = $task['started_at'] ?? null;
        $task['finished_at'] = $task['finished_at'] ?? null;
        return $task;
    }

    private function normalizePlan(array $plan, string $message): array
    {
        $summary = trim((string) ($plan['summary'] ?? $plan['assistant_message'] ?? ''));
        $assistantMessage = trim((string) ($plan['assistant_message'] ?? $plan['voice_reply'] ?? $summary));
        $voiceReply = trim((string) ($plan['voice_reply'] ?? $assistantMessage));
        $steps = $this->normalizeSteps((array) ($plan['steps'] ?? []));
        $requiresBrowser = (bool) ($plan['requires_browser'] ?? (!empty($steps)));
        $requiresWhatsApp = (bool) ($plan['requires_whatsapp'] ?? false);

        return [
            'goal' => trim((string) ($plan['goal'] ?? $message)),
            'summary' => $summary !== '' ? $summary : $message,
            'assistant_message' => $assistantMessage !== '' ? $assistantMessage : $message,
            'voice_reply' => $voiceReply !== '' ? $voiceReply : $assistantMessage,
            'requires_browser' => $requiresBrowser,
            'requires_whatsapp' => $requiresWhatsApp,
            'priority' => $this->normalizePriority((string) ($plan['priority'] ?? 'normal')),
            'steps' => $steps,
        ];
    }

    private function normalizeSteps(array $steps): array
    {
        $normalized = [];
        foreach (array_slice($steps, 0, self::MAX_STEPS) as $index => $step) {
            if (!is_array($step)) {
                continue;
            }
            $action = strtolower(trim((string) ($step['action'] ?? '')));
            if ($action === '') {
                continue;
            }
            $normalized[] = [
                'id' => (string) ($step['id'] ?? ('step_' . ($index + 1))),
                'action' => $action,
                'target' => trim((string) ($step['target'] ?? '')),
                'value' => (string) ($step['value'] ?? ''),
                'selector' => trim((string) ($step['selector'] ?? '')),
                'notes' => trim((string) ($step['notes'] ?? '')),
                'status' => $this->normalizeStatus((string) ($step['status'] ?? 'queued')),
            ];
        }

        return $normalized;
    }

    private function normalizeEvents(array $events): array
    {
        $normalized = [];
        foreach (array_slice($events, -self::MAX_TASK_EVENTS) as $event) {
            if (!is_array($event)) {
                continue;
            }
            $normalized[] = [
                'status' => $this->normalizeStatus((string) ($event['status'] ?? 'queued')),
                'action' => trim((string) ($event['action'] ?? 'update')),
                'message' => trim((string) ($event['message'] ?? '')),
                'timestamp' => (string) ($event['timestamp'] ?? gmdate('c')),
                'duration_ms' => (int) ($event['duration_ms'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function summarizeTask(array $task, bool $includeEvents = false): array
    {
        $plan = (array) ($task['plan'] ?? []);
        $summary = [
            'id' => (string) ($task['id'] ?? ''),
            'user_id' => (int) ($task['user_id'] ?? 0),
            'source' => (string) ($task['source'] ?? 'web'),
            'message' => (string) ($task['message'] ?? ''),
            'goal' => (string) ($task['goal'] ?? ''),
            'assistant_message' => (string) ($task['assistant_message'] ?? ($plan['assistant_message'] ?? '')),
            'status' => (string) ($task['status'] ?? 'queued'),
            'priority' => (string) ($task['priority'] ?? 'normal'),
            'plan' => $plan,
            'result' => $task['result'] ?? null,
            'error' => $task['error'] ?? null,
            'claimed_by' => $task['claimed_by'] ?? null,
            'notifications' => (array) ($task['notifications'] ?? []),
            'created_at' => (string) ($task['created_at'] ?? ''),
            'updated_at' => (string) ($task['updated_at'] ?? ''),
            'started_at' => $task['started_at'] ?? null,
            'finished_at' => $task['finished_at'] ?? null,
            'progress' => $this->buildProgress($task),
        ];

        if ($includeEvents) {
            $summary['events'] = array_values((array) ($task['events'] ?? []));
        }

        return $summary;
    }

    private function buildProgress(array $task): array
    {
        $steps = (array) (($task['plan']['steps'] ?? []));
        $total = count($steps);
        $completed = 0;
        $active = null;

        foreach ($steps as $index => $step) {
            if (!is_array($step)) {
                continue;
            }
            $status = strtolower(trim((string) ($step['status'] ?? 'queued')));
            if (in_array($status, ['completed', 'done'], true)) {
                $completed++;
                continue;
            }
            if ($active === null && !in_array($status, ['queued', 'waiting'], true)) {
                $active = [
                    'index' => $index,
                    'action' => (string) ($step['action'] ?? ''),
                    'target' => (string) ($step['target'] ?? ''),
                    'status' => $status,
                ];
            }
        }

        return [
            'total_steps' => $total,
            'completed_steps' => $completed,
            'current_step' => $active,
        ];
    }

    private function getRecentTasksFromList(array $tasks, int $userId, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $filtered = array_values(array_filter($tasks, static function (array $task) use ($userId): bool {
            return $userId <= 0 ? true : (int) ($task['user_id'] ?? 0) === $userId;
        }));

        return array_map(
            fn (array $task): array => $this->summarizeTask($task, true),
            array_slice($filtered, 0, $limit)
        );
    }

    private function getActiveTaskFromList(array $tasks, int $userId): ?array
    {
        $claimable = array_values(array_filter($tasks, static function (array $task) use ($userId): bool {
            if ($userId > 0 && (int) ($task['user_id'] ?? 0) !== $userId) {
                return false;
            }
            return in_array((string) ($task['status'] ?? ''), ['queued', 'planning', 'executing'], true);
        }));

        usort($claimable, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            if ($leftTime === $rightTime) {
                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            }
            return $leftTime <=> $rightTime;
        });

        foreach ($claimable as $task) {
            return $this->summarizeTask($task, true);
        }

        return null;
    }

    private function countTasksByStatus(array $tasks, array $statusList): int
    {
        $count = 0;
        foreach ($tasks as $task) {
            if (in_array((string) ($task['status'] ?? ''), $statusList, true)) {
                $count++;
            }
        }
        return $count;
    }

    private function queuePositionForTask(array $tasks, string $taskId, int $userId): int
    {
        $position = 0;
        usort($tasks, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            if ($leftTime === $rightTime) {
                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            }
            return $leftTime <=> $rightTime;
        });

        foreach ($tasks as $task) {
            if ($userId > 0 && (int) ($task['user_id'] ?? 0) !== $userId) {
                continue;
            }
            if (!in_array((string) ($task['status'] ?? ''), ['queued', 'planning', 'executing'], true)) {
                continue;
            }
            $position++;
            if ((string) ($task['id'] ?? '') === $taskId) {
                return $position;
            }
        }
        return 0;
    }

    private function updateMemory(int $userId, array $updates): void
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        $state = $this->readMemoryState();
        $state['meta'] = is_array($state['meta'] ?? null) ? $state['meta'] : [];
        $state['users'] = is_array($state['users'] ?? null) ? $state['users'] : [];

        $current = is_array($state['users'][$userId] ?? null) ? $state['users'][$userId] : [];
        $current['updated_at'] = gmdate('c');
        $current['last_active_user_id'] = $userId;
        foreach ($updates as $key => $value) {
            $current[$key] = $value;
        }
        $current['recent_tasks'] = array_slice((array) ($current['recent_tasks'] ?? []), 0, 4);

        if (!empty($updates['last_task_id'])) {
            array_unshift($current['recent_tasks'], [
                'task_id' => (string) $updates['last_task_id'],
                'command' => (string) ($updates['last_command'] ?? ''),
                'summary' => (string) ($updates['last_plan_summary'] ?? ''),
                'timestamp' => gmdate('c'),
            ]);
            $current['recent_tasks'] = array_slice($current['recent_tasks'], 0, 6);
        }

        $state['users'][$userId] = $current;
        $state['meta']['last_active_user_id'] = $userId;
        $state['updated_at'] = gmdate('c');
        $this->writeMemoryState($state);
    }

    private function normalizeMemoryForUser(array $memoryState, int $userId): array
    {
        $users = (array) ($memoryState['users'] ?? []);
        $user = is_array($users[$userId] ?? null) ? $users[$userId] : [];

        return [
            'user_id' => $userId,
            'last_command' => (string) ($user['last_command'] ?? ''),
            'last_source' => (string) ($user['last_source'] ?? ''),
            'last_task_id' => (string) ($user['last_task_id'] ?? ''),
            'last_plan_summary' => (string) ($user['last_plan_summary'] ?? ''),
            'recent_tasks' => array_values((array) ($user['recent_tasks'] ?? [])),
            'updated_at' => (string) ($user['updated_at'] ?? ''),
        ];
    }

    private function getRecentMemorySummary(int $userId): array
    {
        $memory = $this->readMemoryState();
        $user = is_array(($memory['users'][$userId] ?? null)) ? $memory['users'][$userId] : [];
        return array_values((array) ($user['recent_tasks'] ?? []));
    }

    private function planCommand(string $message, array $context): array
    {
        $apiKey = trim((string) config::get('OPENROUTER_API_KEY', ''));
        if ($apiKey === '') {
            return $this->fallbackPlan($message, $context);
        }

        $model = trim((string) config::get('OPENROUTER_MODEL', self::DEFAULT_MODEL));
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }

        $systemPrompt = <<<PROMPT
You are the autonomous assistant for the Diversity.is profile page.
Return ONLY a JSON object. No markdown, no commentary.
Use this schema:
{
  "goal": "short goal string",
  "summary": "1-2 sentence plain-language summary",
  "assistant_message": "what the UI should say back to the user",
  "voice_reply": "short spoken response",
  "priority": "low|normal|high|urgent",
  "requires_browser": true|false,
  "requires_whatsapp": true|false,
  "steps": [
    {
      "action": "navigate|click|type|open_chat|upload_file|wait|press|focus",
      "target": "selector, page, or chat target",
      "value": "optional text or data",
      "selector": "optional stable selector",
      "notes": "optional execution note"
    }
  ]
}
Rules:
- Keep steps short, ordered, and realistic.
- If the task is informational or does not need website actions, set requires_browser to false.
- Prefer stable selectors and existing page identifiers when a browser action is needed.
- If the user wants a chat action, prefer open_chat + type + click/send.
- Keep the assistant_message concise and professional.
PROMPT;

        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'max_tokens' => 700,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'message' => $message,
                        'context' => $context,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];

        try {
            $result = $this->callOpenRouter($apiKey, $payload);
            if ($result !== []) {
                return $result;
            }
        } catch (Throwable $e) {
            error_log('AssistantController OpenRouter error: ' . $e->getMessage());
        }

        return $this->fallbackPlan($message, $context);
    }

    private function callOpenRouter(string $apiKey, array $payload): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL is required for OpenRouter requests.');
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'HTTP-Referer: ' . rtrim(config::getBaseUrl(), '/'),
            'X-OpenRouter-Title: Diversity.is Assistant',
        ];

        $ch = curl_init(self::OPENROUTER_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('OpenRouter request failed: ' . $error);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenRouter response was not valid JSON.');
        }

        if ($status >= 400) {
            $message = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'OpenRouter request failed.');
            throw new RuntimeException($message);
        }

        $content = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            return [];
        }

        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            $parsed = $this->extractJsonObject($content);
        }

        if (!is_array($parsed)) {
            return [];
        }

        return $this->normalizePlan($parsed, (string) ($parsed['goal'] ?? ''));
    }

    private function extractJsonObject(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches)) {
            $candidate = (string) ($matches[0] ?? '');
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function fallbackPlan(string $message, array $context): array
    {
        $normalized = strtolower($message);
        $steps = [];
        $assistantMessage = 'I have queued that request and will keep you updated.';
        $requiresBrowser = false;
        $requiresWhatsApp = false;

        if (preg_match('/\b(send|message|chat|reply)\b/', $normalized)) {
            $requiresBrowser = true;
            $assistantMessage = 'I will open the chat flow and prepare the message.';
            $target = $this->extractTargetFromMessage($message);
            $steps = [
                [
                    'action' => 'navigate',
                    'target' => 'messages.php',
                    'notes' => 'Open the messages workspace.',
                ],
                [
                    'action' => 'open_chat',
                    'target' => $target !== '' ? $target : 'latest conversation',
                    'notes' => 'Select the intended conversation.',
                ],
                [
                    'action' => 'type',
                    'target' => 'message composer',
                    'value' => $message,
                    'notes' => 'Populate the message composer.',
                ],
                [
                    'action' => 'click',
                    'target' => '#sendMessageBtn',
                    'selector' => '#sendMessageBtn',
                    'notes' => 'Send the message.',
                ],
            ];
        } elseif (preg_match('/\b(open|go to|navigate|visit)\b/', $normalized)) {
            $requiresBrowser = true;
            $assistantMessage = 'I will navigate to the requested page.';
            $target = $this->extractTargetFromMessage($message);
            $steps = [
                [
                    'action' => 'navigate',
                    'target' => $target !== '' ? $target : 'profile.php',
                    'notes' => 'Open the requested page.',
                ],
            ];
        }

        return $this->normalizePlan([
            'goal' => $message,
            'summary' => $assistantMessage,
            'assistant_message' => $assistantMessage,
            'voice_reply' => $assistantMessage,
            'priority' => 'normal',
            'requires_browser' => $requiresBrowser,
            'requires_whatsapp' => $requiresWhatsApp,
            'steps' => $steps,
        ], $message);
    }

    private function extractTargetFromMessage(string $message): string
    {
        if (preg_match('/\bto\s+([a-z0-9_.@ -]{2,})/i', $message, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }
        if (preg_match('/\bwith\s+([a-z0-9_.@ -]{2,})/i', $message, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }
        return '';
    }

    private function buildFallbackSteps(string $message, array $context): array
    {
        $fallback = $this->fallbackPlan($message, $context);
        return (array) ($fallback['steps'] ?? []);
    }
}
