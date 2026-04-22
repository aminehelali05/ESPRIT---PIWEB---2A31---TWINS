<?php

include_once(__DIR__ . '/../config.php');

class ContractController
{
    private PDO $pdo;
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureContractSchema();
    }

    private function contractHasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM contracts LIKE " . $this->pdo->quote($column));
            $this->columnCache[$column] = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    private function ensureColumn(string $column, string $definition): void
    {
        if ($this->contractHasColumn($column)) {
            return;
        }

        try {
            $this->pdo->exec("ALTER TABLE contracts ADD COLUMN {$column} {$definition}");
            $this->columnCache[$column] = true;
        } catch (Throwable $exception) {
            error_log('ContractController::ensureColumn(' . $column . ') - ' . $exception->getMessage());
        }
    }

    private function ensureContractSchema(): void
    {
        $this->ensureColumn('client_signed', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureColumn('freelancer_signed', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureColumn('client_signature', 'LONGTEXT NULL');
        $this->ensureColumn('freelancer_signature', 'LONGTEXT NULL');
        $this->ensureColumn('title', 'VARCHAR(255) NULL');
        $this->ensureColumn('description', 'TEXT NULL');
        $this->ensureColumn('payment_details', 'TEXT NULL');
        $this->ensureColumn('deadline_at', 'DATETIME NULL');
        $this->ensureColumn('freelancer_refused_at', 'DATETIME NULL');
    }

    private function normalizeSignature(string $signature): string
    {
        $signature = trim($signature);
        if ($signature === '') {
            throw new RuntimeException('Signature is required.');
        }

        if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\s]+$#', $signature)) {
            throw new RuntimeException('Invalid signature format.');
        }

        return preg_replace('/\s+/', '', $signature) ?? $signature;
    }

    private function fetchContractRowForUpdate(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function derivedStatus(array $row): string
    {
        $state = $this->workflowState($row);
        if ($state === 'completed') {
            return 'finalized';
        }
        if ($state === 'refused') {
            return 'cancelled';
        }
        if ($state === 'waiting_freelancer') {
            return 'waiting';
        }
        return 'draft';
    }

    private function withDerivedStatus(array $row): array
    {
        $row['status'] = $this->derivedStatus($row);
        return $row;
    }

    public function workflowState(array $row): string
    {
        $clientSigned = (int) ($row['client_signed'] ?? 0) === 1;
        $freelancerSigned = (int) ($row['freelancer_signed'] ?? 0) === 1;
        $freelancerRefusedAt = trim((string) ($row['freelancer_refused_at'] ?? ''));

        if ($clientSigned && $freelancerSigned) {
            return 'completed';
        }

        if ($freelancerRefusedAt !== '') {
            return 'refused';
        }

        if ($clientSigned) {
            return 'waiting_freelancer';
        }

        return 'waiting_client';
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

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);

        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    public function listOffers(): array
    {
        return $this->pdo->query('SELECT id, title FROM job_offers ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT c.*,
                       COALESCE(c.title, o.title, CONCAT("Contract #", c.id)) AS contract_title,
                       o.title AS offer_title,
                       cu.first_name AS client_first, cu.last_name AS client_last,
                       fu.first_name AS freelancer_first, fu.last_name AS freelancer_last
                FROM contracts c
                LEFT JOIN job_offers o ON o.id = c.job_offer_id
                LEFT JOIN users cu ON cu.id = c.client_id
                LEFT JOIN users fu ON fu.id = c.freelancer_id
                ORDER BY c.created_at DESC';

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->withDerivedStatus($row), $rows);
    }

    public function buildBackofficeStats(array $rows): array
    {
        $active = 0;
        $draft = 0;

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? $this->derivedStatus($row));
            if ($status === 'waiting') {
                $active++;
            }
            if ($status === 'draft') {
                $draft++;
            }
        }

        return [
            'total' => count($rows),
            'active' => $active,
            'draft' => $draft,
        ];
    }

    public function listUserContracts(int $userId): array
    {
        $sql = 'SELECT c.*,
                       COALESCE(c.title, o.title, CONCAT("Contract #", c.id)) AS contract_title,
                       o.title AS offer_title,
                       o.description AS offer_description,
                       cu.first_name AS client_first, cu.last_name AS client_last,
                       fu.first_name AS freelancer_first, fu.last_name AS freelancer_last
                FROM contracts c
                LEFT JOIN job_offers o ON o.id = c.job_offer_id
                INNER JOIN users cu ON cu.id = c.client_id
                INNER JOIN users fu ON fu.id = c.freelancer_id
                WHERE c.client_id = :uid OR c.freelancer_id = :uid
                ORDER BY c.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): array => $this->withDerivedStatus($row), $rows);
    }

    public function listClientAcceptedApplicationsWithoutContract(int $clientId): array
    {
        $sql = 'SELECT a.job_offer_id, a.freelancer_id,
                       o.title AS offer_title,
                       o.description AS offer_description,
                       o.budget,
                       o.deadline_at,
                       u.first_name, u.last_name, u.email
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

    public function getAcceptedApplicationPrefill(int $clientId, int $offerId, int $freelancerId): ?array
    {
        $sql = 'SELECT a.job_offer_id, a.freelancer_id, a.cover_letter, a.applied_at,
                       o.title AS offer_title, o.description AS offer_description, o.budget, o.deadline_at,
                       u.first_name, u.last_name, u.email
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN users u ON u.id = a.freelancer_id
                LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                WHERE o.client_id = :client_id
                  AND a.job_offer_id = :offer_id
                  AND a.freelancer_id = :freelancer_id
                  AND a.status = "accepted"
                  AND c.id IS NULL
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'client_id' => $clientId,
            'offer_id' => $offerId,
            'freelancer_id' => $freelancerId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createFromAcceptedApplication(int $clientId, int $offerId, int $freelancerId, array $payload): int
    {
        $this->pdo->beginTransaction();

        try {
            $sql = 'SELECT a.id,
                           o.client_id,
                           o.title AS offer_title,
                           o.description AS offer_description,
                           o.budget,
                           o.deadline_at
                    FROM job_offer_applications a
                    INNER JOIN job_offers o ON o.id = a.job_offer_id
                    LEFT JOIN contracts c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                    WHERE a.job_offer_id = :offer_id
                      AND a.freelancer_id = :freelancer_id
                      AND a.status = "accepted"
                      AND o.client_id = :client_id
                      AND c.id IS NULL
                    LIMIT 1
                    FOR UPDATE';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
                'client_id' => $clientId,
            ]);
            $pair = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pair) {
                throw new RuntimeException('This accepted application is no longer available for contract creation.');
            }

            $title = trim((string) ($payload['title'] ?? ''));
            $description = trim((string) ($payload['description'] ?? ''));
            $terms = trim((string) ($payload['terms'] ?? ''));
            $paymentDetails = trim((string) ($payload['payment_details'] ?? ''));
            $signature = $this->normalizeSignature((string) ($payload['client_signature'] ?? ''));
            $deadlineAt = $payload['deadline_at'] ?? null;

            if ($title === '') {
                $title = 'Contract for ' . trim((string) ($pair['offer_title'] ?? 'this project'));
            }
            if ($description === '') {
                $description = trim((string) ($pair['offer_description'] ?? ''));
            }
            if ($terms === '') {
                throw new RuntimeException('Terms are required.');
            }
            if ($paymentDetails === '') {
                throw new RuntimeException('Payment details are required.');
            }

            $insert = $this->pdo->prepare('INSERT INTO contracts (
                    job_offer_id, freelancer_id, client_id, title, description, terms, amount,
                    payment_details, deadline_at, starts_at, ends_at, created_by_client_id,
                    client_signed, freelancer_signed, client_signature, freelancer_signature,
                    created_at, updated_at
                ) VALUES (
                    :job_offer_id, :freelancer_id, :client_id, :title, :description, :terms, :amount,
                    :payment_details, :deadline_at, :starts_at, :ends_at, :created_by_client_id,
                    1, 0, :client_signature, NULL,
                    NOW(), NOW()
                )');

            $insert->execute([
                'job_offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
                'client_id' => $clientId,
                'title' => $title,
                'description' => $description ?: null,
                'terms' => $terms,
                'amount' => max(0, (float) ($payload['amount'] ?? 0)),
                'payment_details' => $paymentDetails,
                'deadline_at' => $deadlineAt,
                'starts_at' => $payload['starts_at'] ?? null,
                'ends_at' => $payload['ends_at'] ?? null,
                'created_by_client_id' => $clientId,
                'client_signature' => $signature,
            ]);

            $contractId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $contractId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function create(array $payload): int
    {
        $offerId = (int) ($payload['job_offer_id'] ?? 0);
        $clientId = (int) ($payload['client_id'] ?? 0);
        $freelancerId = (int) ($payload['freelancer_id'] ?? 0);

        if ($offerId <= 0 || $clientId <= 0 || $freelancerId <= 0) {
            throw new RuntimeException('Offer, client, and freelancer are required.');
        }

        return $this->createFromAcceptedApplication($clientId, $offerId, $freelancerId, [
            'title' => trim((string) ($payload['title'] ?? '')),
            'description' => trim((string) ($payload['description'] ?? '')),
            'terms' => trim((string) ($payload['terms'] ?? '')),
            'amount' => max(0, (float) ($payload['amount'] ?? 0)),
            'payment_details' => trim((string) ($payload['payment_details'] ?? '')) !== ''
                ? trim((string) ($payload['payment_details'] ?? ''))
                : ('Payment amount: ' . max(0, (float) ($payload['amount'] ?? 0)) . ' TND'),
            'deadline_at' => $payload['deadline_at'] ?? null,
            'starts_at' => $payload['starts_at'] ?? null,
            'ends_at' => $payload['ends_at'] ?? null,
            'client_signature' => trim((string) ($payload['client_signature'] ?? '')),
        ]);
    }

    public function updateStatus(int $contractId, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts SET updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $contractId]);
        return $stmt->rowCount() > 0;
    }

    public function updateByClient(int $contractId, int $clientId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts
            SET terms = :terms,
                amount = :amount,
                starts_at = :starts_at,
                ends_at = :ends_at,
                updated_at = NOW()
            WHERE id = :id
              AND client_id = :client_id
              AND freelancer_signed = 0');
        $stmt->execute([
            'terms' => trim((string) ($payload['terms'] ?? '')),
            'amount' => max(0, (float) ($payload['amount'] ?? 0)),
            'starts_at' => trim((string) ($payload['starts_at'] ?? '')) ?: null,
            'ends_at' => trim((string) ($payload['ends_at'] ?? '')) ?: null,
            'id' => $contractId,
            'client_id' => $clientId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function signByFreelancer(int $contractId, int $freelancerId, string $signature = ''): bool
    {
        $signature = $this->normalizeSignature($signature);
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['freelancer_id'] ?? 0) !== $freelancerId) {
                throw new RuntimeException('You cannot sign this contract.');
            }

            if ((int) ($contract['client_signed'] ?? 0) !== 1) {
                throw new RuntimeException('The client must sign first.');
            }

            if ((int) ($contract['freelancer_signed'] ?? 0) === 1) {
                throw new RuntimeException('This contract has already been signed.');
            }

            if (trim((string) ($contract['freelancer_refused_at'] ?? '')) !== '') {
                throw new RuntimeException('This contract has been refused and cannot be signed.');
            }

            $update = $this->pdo->prepare('UPDATE contracts
                SET freelancer_signed = 1,
                    freelancer_signature = :signature,
                    updated_at = NOW()
                WHERE id = :id');
            $update->execute([
                'signature' => $signature,
                'id' => $contractId,
            ]);

            if ((int) ($contract['job_offer_id'] ?? 0) > 0) {
                $offerId = (int) $contract['job_offer_id'];
                $this->pdo->prepare('UPDATE job_offers SET status = :status, updated_at = NOW() WHERE id = :offer_id')
                    ->execute([
                        'status' => 'closed',
                        'offer_id' => $offerId,
                    ]);

                $this->pdo->prepare('UPDATE job_offer_applications
                    SET status = :status, decided_at = NOW(), updated_at = NOW()
                    WHERE job_offer_id = :offer_id
                      AND freelancer_id <> :freelancer_id
                      AND status = :pending')
                    ->execute([
                        'status' => 'rejected',
                        'offer_id' => $offerId,
                        'freelancer_id' => $freelancerId,
                        'pending' => 'pending',
                    ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function refuseByFreelancer(int $contractId, int $freelancerId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE contracts
            SET freelancer_refused_at = NOW(), updated_at = NOW()
            WHERE id = :id
              AND freelancer_id = :freelancer_id
              AND client_signed = 1
              AND freelancer_signed = 0
              AND freelancer_refused_at IS NULL');

        $stmt->execute([
            'id' => $contractId,
            'freelancer_id' => $freelancerId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteById(int $contractId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts WHERE id = :id');
        $stmt->execute(['id' => $contractId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByClient(int $contractId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts
            WHERE id = :id
              AND client_id = :client_id
              AND freelancer_signed = 0');

        $stmt->execute([
            'id' => $contractId,
            'client_id' => $clientId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
