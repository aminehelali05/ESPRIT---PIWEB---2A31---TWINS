<?php

include_once(__DIR__ . '/../config.php');

class ProjectController
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

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT p.*, u.first_name, u.last_name FROM projects p LEFT JOIN users u ON u.id = p.owner_id ORDER BY p.created_at DESC';
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

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO projects (title, description, cover_image, short_description, technologies, status, progress_percent, budget, due_date, owner_id, visibility, created_at, updated_at) VALUES (:title, :description, :cover_image, :short_description, :technologies, :status, :progress_percent, :budget, :due_date, :owner_id, :visibility, NOW(), NOW())');
        $stmt->execute([
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'cover_image' => trim((string) ($payload['cover_image'] ?? '')) ?: null,
            'short_description' => trim((string) ($payload['short_description'] ?? '')) ?: null,
            'technologies' => trim((string) ($payload['technologies'] ?? '')) ?: null,
            'status' => trim((string) ($payload['status'] ?? 'planning')),
            'progress_percent' => max(0, min(100, (int) ($payload['progress_percent'] ?? 0))),
            'budget' => max(0, (float) ($payload['budget'] ?? 0)),
            'due_date' => trim((string) ($payload['due_date'] ?? '')) ?: null,
            'owner_id' => (int) ($payload['owner_id'] ?? 0),
            'visibility' => trim((string) ($payload['visibility'] ?? 'team')),
        ]);
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

        $sql = 'SELECT p.*, u.first_name, u.last_name FROM projects p LEFT JOIN users u ON u.id = p.owner_id WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOwnedByUser(int $projectId, int $ownerId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE projects SET title = :title, description = :description, cover_image = :cover_image, short_description = :short_description, technologies = :technologies, status = :status, progress_percent = :progress_percent, budget = :budget, due_date = :due_date, visibility = :visibility, updated_at = NOW() WHERE id = :id AND owner_id = :owner_id');
        return $stmt->execute([
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'cover_image' => trim((string) ($payload['cover_image'] ?? '')) ?: null,
            'short_description' => trim((string) ($payload['short_description'] ?? '')) ?: null,
            'technologies' => trim((string) ($payload['technologies'] ?? '')) ?: null,
            'status' => trim((string) ($payload['status'] ?? 'planning')),
            'progress_percent' => max(0, min(100, (int) ($payload['progress_percent'] ?? 0))),
            'budget' => max(0, (float) ($payload['budget'] ?? 0)),
            'due_date' => trim((string) ($payload['due_date'] ?? '')) ?: null,
            'visibility' => trim((string) ($payload['visibility'] ?? 'team')),
            'id' => $projectId,
            'owner_id' => $ownerId,
        ]);
    }

    public function deleteOwnedByUser(int $projectId, int $ownerId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = :id AND owner_id = :owner_id');
        return $stmt->execute(['id' => $projectId, 'owner_id' => $ownerId]);
    }
}
