<?php

include_once(__DIR__ . '/../config.php');

class ContractController
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

    public function parseDateTimeLocal(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value) ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    public function listOffers(): array
    {
        return $this->pdo->query('SELECT id, title FROM job_offers ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT c.*, o.title AS offer_title,
                cu.first_name AS client_first, cu.last_name AS client_last,
                fu.first_name AS freelancer_first, fu.last_name AS freelancer_last
                FROM contracts c
                LEFT JOIN job_offers o ON o.id = c.job_offer_id
                LEFT JOIN users cu ON cu.id = c.client_id
                LEFT JOIN users fu ON fu.id = c.freelancer_id
                ORDER BY c.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buildBackofficeStats(array $rows): array
    {
        $active = 0;
        $draft = 0;
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'draft');
            if ($status === 'active') {
                $active++;
            }
            if ($status === 'draft') {
                $draft++;
            }
        }
        return ['total' => count($rows), 'active' => $active, 'draft' => $draft];
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO contracts (job_offer_id, freelancer_id, client_id, terms, status, amount, signed_at, starts_at, ends_at, created_by_client_id, created_at, updated_at) VALUES (:job_offer_id, :freelancer_id, :client_id, :terms, :status, :amount, :signed_at, :starts_at, :ends_at, :created_by_client_id, NOW(), NOW())');
        $stmt->execute([
            'job_offer_id' => (int) ($payload['job_offer_id'] ?? 0),
            'freelancer_id' => (int) ($payload['freelancer_id'] ?? 0),
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'terms' => trim((string) ($payload['terms'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'draft')),
            'amount' => max(0, (float) ($payload['amount'] ?? 0)),
            'signed_at' => $payload['signed_at'] ?? null,
            'starts_at' => $payload['starts_at'] ?? null,
            'ends_at' => $payload['ends_at'] ?? null,
            'created_by_client_id' => (int) ($payload['created_by_client_id'] ?? 0),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $contractId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $contractId]);
    }

    public function deleteById(int $contractId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts WHERE id = :id');
        return $stmt->execute(['id' => $contractId]);
    }

    public function listUserContracts(int $userId): array
    {
        $sql = 'SELECT c.*, o.title AS offer_title,
                cu.first_name AS client_first, cu.last_name AS client_last,
                fu.first_name AS freelancer_first, fu.last_name AS freelancer_last
                FROM contracts c
                INNER JOIN job_offers o ON o.id = c.job_offer_id
                INNER JOIN users cu ON cu.id = c.client_id
                INNER JOIN users fu ON fu.id = c.freelancer_id
                WHERE c.client_id = :uid OR c.freelancer_id = :uid
                ORDER BY c.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listClientOffers(int $clientId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, title FROM job_offers WHERE client_id = :client_id ORDER BY created_at DESC');
        $stmt->execute(['client_id' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listFreelancers(): array
    {
        $sql = 'SELECT id, first_name, last_name FROM users WHERE role = "freelancer" ORDER BY first_name ASC, last_name ASC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientOwnsOffer(int $offerId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM job_offers WHERE id = :id AND client_id = :client_id LIMIT 1');
        $stmt->execute(['id' => $offerId, 'client_id' => $clientId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateByClient(int $contractId, int $clientId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts SET terms = :terms, status = :status, amount = :amount, starts_at = :starts_at, ends_at = :ends_at, signed_at = CASE WHEN :status = "active" AND signed_at IS NULL THEN NOW() ELSE signed_at END, updated_at = NOW() WHERE id = :id AND client_id = :client_id');
        return $stmt->execute([
            'terms' => trim((string) ($payload['terms'] ?? '')),
            'status' => trim((string) ($payload['status'] ?? 'draft')),
            'amount' => max(0, (float) ($payload['amount'] ?? 0)),
            'starts_at' => trim((string) ($payload['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($payload['ends_at'] ?? '')) ?: null,
            'id' => $contractId,
            'client_id' => $clientId,
        ]);
    }

    public function deleteByClient(int $contractId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts WHERE id = :id AND client_id = :client_id');
        return $stmt->execute(['id' => $contractId, 'client_id' => $clientId]);
    }

    public function signByFreelancer(int $contractId, int $freelancerId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts SET status = :status, signed_at = NOW(), updated_at = NOW() WHERE id = :id AND freelancer_id = :freelancer_id AND status IN ("draft", "active")');
        return $stmt->execute(['status' => 'active', 'id' => $contractId, 'freelancer_id' => $freelancerId]);
    }

    public function listClientAcceptedApplicationsWithoutContract(int $clientId): array
    {
        $sql = 'SELECT a.job_offer_id, a.freelancer_id,
                       o.title AS offer_title,
                       u.first_name, u.last_name, u.email,
                       o.budget
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN users u ON u.id = a.freelancer_id
                LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                WHERE o.client_id = :client_id
                  AND a.status = "accepted"
                  AND c.id IS NULL
                ORDER BY a.decided_at DESC, a.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['client_id' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function canClientCreateContractForPair(int $clientId, int $offerId, int $freelancerId): bool
    {
        $sql = 'SELECT a.id
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                WHERE a.job_offer_id = :offer_id
                  AND a.freelancer_id = :freelancer_id
                  AND a.status = "accepted"
                  AND o.client_id = :client_id
                  AND c.id IS NULL
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'offer_id' => $offerId,
            'freelancer_id' => $freelancerId,
            'client_id' => $clientId,
        ]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
