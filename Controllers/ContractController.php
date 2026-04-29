<?php

include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/RulesController.php');

class ContractController
{
    private PDO $pdo;
    private RulesController $ruleController;
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureContractSchema();
        $this->ruleController = new RulesController($this->pdo);
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

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function startsWithUppercase(string $value): bool
    {
        return (bool) preg_match('/^\p{Lu}/u', $value);
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

    private function normalizeContractPayload(array $payload): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $terms = $this->sanitizeText((string) ($payload['terms'] ?? ''));
        $paymentDetails = $this->sanitizeText((string) ($payload['payment_details'] ?? ''));
        $amountRaw = trim((string) ($payload['amount'] ?? ''));
        $amount = is_numeric($amountRaw) ? round((float) $amountRaw, 2) : null;

        if ($title === '') {
            $title = 'Contract';
        }
        if ($terms === '') {
            throw new RuntimeException('Contract terms are required.');
        }
        if (mb_strlen($terms) < 20 || mb_strlen($terms) > 4000) {
            throw new RuntimeException('Contract terms must be between 20 and 4000 characters.');
        }
        if (!$this->startsWithUppercase($terms)) {
            throw new RuntimeException('Contract terms must start with an uppercase letter.');
        }
        if ($paymentDetails === '') {
            throw new RuntimeException('Payment details are required.');
        }
        if (mb_strlen($paymentDetails) < 5 || mb_strlen($paymentDetails) > 2000) {
            throw new RuntimeException('Payment details must be between 5 and 2000 characters.');
        }
        if ($amount === null || $amount <= 0) {
            throw new RuntimeException('Amount must be greater than 0.');
        }
        if ($amount > 10000000) {
            throw new RuntimeException('Amount is too high.');
        }

        $startsAtRaw = trim((string) ($payload['starts_at'] ?? ''));
        $endsAtRaw = trim((string) ($payload['ends_at'] ?? ''));
        $deadlineAtRaw = trim((string) ($payload['deadline_at'] ?? ''));
        $startsAt = $this->parseDateTimeLocal($startsAtRaw);
        $endsAt = $this->parseDateTimeLocal($endsAtRaw);
        $deadlineAt = $this->parseDateTimeLocal($deadlineAtRaw);

        if ($startsAtRaw !== '' && $startsAt === null) {
            throw new RuntimeException('Start date is invalid.');
        }
        if ($endsAtRaw !== '' && $endsAt === null) {
            throw new RuntimeException('End date is invalid.');
        }
        if ($deadlineAtRaw !== '' && $deadlineAt === null) {
            throw new RuntimeException('Deadline is invalid.');
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            throw new RuntimeException('End date must be after the start date.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'terms' => $terms,
            'payment_details' => $paymentDetails,
            'amount' => $amount,
            'deadline_at' => $deadlineAt,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'created_by_client_id' => (int) ($payload['created_by_client_id'] ?? 0),
        ];
    }

    private function fetchContractRowForUpdate(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function withDerivedStatus(array $row): array
    {
        $row['status'] = $this->derivedStatus($row);
        return $row;
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

    private function enrichRowsWithRules(array $rows): array
    {
        $rulesByContract = $this->ruleController->listGroupedByContractIds(array_column($rows, 'id'));

        foreach ($rows as &$row) {
            $contractId = (int) ($row['id'] ?? 0);
            $rules = $rulesByContract[$contractId] ?? [];
            $firstRule = $rules[0] ?? [];

            $row['rules'] = $rules;
            $row['rules_count'] = count($rules);
            $row['rules_terms'] = '';
            $row['rules_deadline'] = '';
            $row['rules_payment_terms'] = '';
            $row['rules_penalties'] = '';

            foreach ($rules as $rule) {
                $ruleType = strtolower((string) ($rule['rule_type'] ?? ''));
                $description = (string) ($rule['description'] ?? '');
                $dueDate = (string) ($rule['due_date'] ?? '');
                $penalty = (string) ($rule['penalty'] ?? '');

                if ($row['rules_terms'] === '' && in_array($ruleType, ['scope', 'delivery', 'terms'], true) && trim($description) !== '') {
                    $row['rules_terms'] = $description;
                }
                if ($row['rules_deadline'] === '' && $ruleType === 'deadline' && trim($dueDate) !== '') {
                    $row['rules_deadline'] = $dueDate;
                }
                if ($row['rules_payment_terms'] === '' && $ruleType === 'payment' && trim($description) !== '') {
                    $row['rules_payment_terms'] = $description;
                }
                if ($row['rules_penalties'] === '' && in_array($ruleType, ['penalties', 'legal', 'penalty'], true) && trim($penalty) !== '') {
                    $row['rules_penalties'] = $penalty;
                }
            }

            if ($row['rules_terms'] === '') {
                $row['rules_terms'] = (string) ($firstRule['description'] ?? '');
            }
            if ($row['rules_deadline'] === '') {
                $row['rules_deadline'] = (string) ($firstRule['due_date'] ?? '');
            }
            if ($row['rules_penalties'] === '') {
                $row['rules_penalties'] = (string) ($firstRule['penalty'] ?? '');
            }
            if ($row['rules_payment_terms'] === '' && ($firstRule['rule_type'] ?? '') === 'payment') {
                $row['rules_payment_terms'] = (string) ($firstRule['description'] ?? '');
            }
        }
        unset($row);

        return $rows;
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

        $rows = $this->enrichRowsWithRules($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC));
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
        $rows = $this->enrichRowsWithRules($stmt->fetchAll(PDO::FETCH_ASSOC));
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
                           o.description AS offer_description
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

            $contractData = $this->normalizeContractPayload($payload + ['created_by_client_id' => $clientId]);
            if ($contractData['title'] === 'Contract') {
                $contractData['title'] = 'Contract for ' . trim((string) ($pair['offer_title'] ?? 'this project'));
            }
            if ($contractData['description'] === '') {
                $contractData['description'] = trim((string) ($pair['offer_description'] ?? ''));
            }

            $insert = $this->pdo->prepare('INSERT INTO contracts (
                    job_offer_id, freelancer_id, client_id, title, description, terms, amount,
                    payment_details, deadline_at, starts_at, ends_at, created_by_client_id,
                    client_signed, freelancer_signed, client_signature, freelancer_signature,
                    signed_at, created_at, updated_at
                ) VALUES (
                    :job_offer_id, :freelancer_id, :client_id, :title, :description, :terms, :amount,
                    :payment_details, :deadline_at, :starts_at, :ends_at, :created_by_client_id,
                    0, 0, NULL, NULL,
                    NULL, NOW(), NOW()
                )');

            $insert->execute([
                'job_offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
                'client_id' => $clientId,
                'title' => $contractData['title'],
                'description' => $contractData['description'] !== '' ? $contractData['description'] : null,
                'terms' => $contractData['terms'],
                'amount' => $contractData['amount'],
                'payment_details' => $contractData['payment_details'],
                'deadline_at' => $contractData['deadline_at'],
                'starts_at' => $contractData['starts_at'],
                'ends_at' => $contractData['ends_at'],
                'created_by_client_id' => $clientId,
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
            'title' => (string) ($payload['title'] ?? ''),
            'description' => (string) ($payload['description'] ?? ''),
            'terms' => (string) ($payload['terms'] ?? ''),
            'amount' => (string) ($payload['amount'] ?? ''),
            'payment_details' => trim((string) ($payload['payment_details'] ?? '')) !== ''
                ? (string) ($payload['payment_details'] ?? '')
                : ('Payment amount: ' . trim((string) ($payload['amount'] ?? '0')) . ' TND'),
            'deadline_at' => (string) ($payload['deadline_at'] ?? ''),
            'starts_at' => (string) ($payload['starts_at'] ?? ''),
            'ends_at' => (string) ($payload['ends_at'] ?? ''),
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
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['client_id'] ?? 0) !== $clientId) {
                return false;
            }
            if ((int) ($contract['client_signed'] ?? 0) === 1 || (int) ($contract['freelancer_signed'] ?? 0) === 1) {
                return false;
            }

            $clean = $this->normalizeContractPayload($payload + [
                'title' => (string) ($contract['title'] ?? ''),
                'description' => (string) ($contract['description'] ?? ''),
                'created_by_client_id' => (int) ($contract['created_by_client_id'] ?? $clientId),
            ]);

            $stmt = $this->pdo->prepare('UPDATE contracts
                SET terms = :terms,
                    amount = :amount,
                    payment_details = :payment_details,
                    starts_at = :starts_at,
                    ends_at = :ends_at,
                    deadline_at = :deadline_at,
                    updated_at = NOW()
                WHERE id = :id
                  AND client_id = :client_id
                  AND client_signed = 0
                  AND freelancer_signed = 0');
            $stmt->execute([
                'terms' => $clean['terms'],
                'amount' => $clean['amount'],
                'payment_details' => $clean['payment_details'],
                'starts_at' => $clean['starts_at'],
                'ends_at' => $clean['ends_at'],
                'deadline_at' => $clean['deadline_at'],
                'id' => $contractId,
                'client_id' => $clientId,
            ]);

            $this->pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function saveRulesByClient(int $contractId, int $clientId, array $payload): int
    {
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['client_id'] ?? 0) !== $clientId) {
                throw new RuntimeException('You can only manage rules for your own contracts.');
            }
            if ((int) ($contract['client_signed'] ?? 0) === 1 || (int) ($contract['freelancer_signed'] ?? 0) === 1) {
                throw new RuntimeException('Rules can no longer be changed after signing starts.');
            }

            $rulesCount = $this->ruleController->replaceForContract($contractId, $payload);

            $this->pdo->prepare('UPDATE contracts SET updated_at = NOW() WHERE id = :id')->execute(['id' => $contractId]);
            $this->pdo->commit();
            return $rulesCount;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function signByClient(int $contractId, int $clientId, string $signature): bool
    {
        $signature = $this->normalizeSignature($signature);
        $this->pdo->beginTransaction();

        try {
            $contract = $this->fetchContractRowForUpdate($contractId);
            if (!$contract || (int) ($contract['client_id'] ?? 0) !== $clientId) {
                throw new RuntimeException('You cannot sign this contract.');
            }
            if ((int) ($contract['client_signed'] ?? 0) === 1) {
                throw new RuntimeException('This contract has already been signed by the client.');
            }
            if ((int) ($contract['freelancer_signed'] ?? 0) === 1) {
                throw new RuntimeException('This contract has already been finalized.');
            }
            if (trim((string) ($contract['freelancer_refused_at'] ?? '')) !== '') {
                throw new RuntimeException('This contract has already been refused.');
            }
            if ($this->ruleController->countByContractId($contractId) === 0) {
                throw new RuntimeException('Add the contract rules before signing.');
            }

            $update = $this->pdo->prepare('UPDATE contracts
                SET client_signed = 1,
                    client_signature = :signature,
                    updated_at = NOW()
                WHERE id = :id');
            $update->execute([
                'signature' => $signature,
                'id' => $contractId,
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
            if ($this->ruleController->countByContractId($contractId) === 0) {
                throw new RuntimeException('The contract rules must be completed before signing.');
            }

            $update = $this->pdo->prepare('UPDATE contracts
                SET freelancer_signed = 1,
                    freelancer_signature = :signature,
                    signed_at = NOW(),
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
              AND client_signed = 0
              AND freelancer_signed = 0');

        $stmt->execute([
            'id' => $contractId,
            'client_id' => $clientId,
        ]);

        return $stmt->rowCount() > 0;
    }
}
