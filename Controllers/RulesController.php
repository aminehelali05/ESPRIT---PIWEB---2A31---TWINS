<?php

include_once(__DIR__ . '/../config.php');

class RulesController
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                contract_id INT NOT NULL UNIQUE,
                terms TEXT NOT NULL,
                deadline DATE NOT NULL,
                payment_terms TEXT NOT NULL,
                penalties TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_rules_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');
        } catch (Throwable $exception) {
            error_log('RulesController::ensureSchema - ' . $exception->getMessage());
        }
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

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function validatePayload(array $payload): array
    {
        $terms = $this->sanitizeText((string) ($payload['terms'] ?? ''));
        $paymentTerms = $this->sanitizeText((string) ($payload['payment_terms'] ?? ''));
        $penalties = $this->sanitizeText((string) ($payload['penalties'] ?? ''));
        $deadline = $this->normalizeDate((string) ($payload['deadline'] ?? ''));

        if ($terms === '') {
            throw new RuntimeException('Rules terms are required.');
        }
        if (mb_strlen($terms) < 20 || mb_strlen($terms) > 4000) {
            throw new RuntimeException('Rules terms must be between 20 and 4000 characters.');
        }
        if ($paymentTerms === '') {
            throw new RuntimeException('Payment terms are required.');
        }
        if (mb_strlen($paymentTerms) < 5 || mb_strlen($paymentTerms) > 2000) {
            throw new RuntimeException('Payment terms must be between 5 and 2000 characters.');
        }
        if ($penalties === '') {
            throw new RuntimeException('Penalties are required.');
        }
        if (mb_strlen($penalties) < 5 || mb_strlen($penalties) > 2000) {
            throw new RuntimeException('Penalties must be between 5 and 2000 characters.');
        }
        if ($deadline === null) {
            throw new RuntimeException('Rules deadline is invalid.');
        }
        if (new DateTimeImmutable($deadline) <= new DateTimeImmutable('today')) {
            throw new RuntimeException('Rules deadline must be after today.');
        }

        return [
            'terms' => $terms,
            'deadline' => $deadline,
            'payment_terms' => $paymentTerms,
            'penalties' => $penalties,
        ];
    }

    private function backfillMissingRules(): void
    {
        try {
            $stmt = $this->pdo->query('SELECT c.id, c.terms, c.deadline_at, c.payment_details
                FROM contracts c
                LEFT JOIN rules r ON r.contract_id = c.id
                WHERE r.id IS NULL
                  AND ((c.client_signed = 1) OR (c.freelancer_signed = 1))');
            $missing = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($missing === []) {
                return;
            }

            $insert = $this->pdo->prepare('INSERT INTO rules (contract_id, terms, deadline, payment_terms, penalties, created_at, updated_at)
                VALUES (:contract_id, :terms, :deadline, :payment_terms, :penalties, NOW(), NOW())');
            foreach ($missing as $row) {
                $deadline = (string) ($row['deadline_at'] ?? '');
                $deadlineDate = $deadline !== ''
                    ? (new DateTimeImmutable($deadline))->format('Y-m-d')
                    : (new DateTimeImmutable('+7 days'))->format('Y-m-d');
                $terms = trim((string) ($row['terms'] ?? ''));
                if ($terms === '') {
                    $terms = 'Contract rules generated from the existing contract.';
                }
                $payment = trim((string) ($row['payment_details'] ?? ''));
                if ($payment === '') {
                    $payment = 'Payment according to the linked contract.';
                }
                $insert->execute([
                    'contract_id' => (int) ($row['id'] ?? 0),
                    'terms' => $terms,
                    'deadline' => $deadlineDate,
                    'payment_terms' => $payment,
                    'penalties' => 'Standard penalties apply for breach of contract rules.',
                ]);
            }
        } catch (Throwable $exception) {
            error_log('RulesController::backfillMissingRules - ' . $exception->getMessage());
        }
    }

    public function listWithContracts(): array
    {
        $sql = 'SELECT r.*, c.title AS contract_title, c.client_id, c.freelancer_id, c.created_at AS contract_created_at
                FROM rules r
                INNER JOIN contracts c ON c.id = r.contract_id
                ORDER BY c.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByContractId(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rules WHERE contract_id = :contract_id LIMIT 1');
        $stmt->execute(['contract_id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertForContract(int $contractId, array $payload): int
    {
        $clean = $this->validatePayload($payload);
        $existing = $this->findByContractId($contractId);

        if ($existing) {
            $stmt = $this->pdo->prepare('UPDATE rules
                SET terms = :terms,
                    deadline = :deadline,
                    payment_terms = :payment_terms,
                    penalties = :penalties,
                    updated_at = NOW()
                WHERE contract_id = :contract_id');
            $stmt->execute($clean + ['contract_id' => $contractId]);
            return (int) ($existing['id'] ?? 0);
        }

        $stmt = $this->pdo->prepare('INSERT INTO rules (contract_id, terms, deadline, payment_terms, penalties, created_at, updated_at)
            VALUES (:contract_id, :terms, :deadline, :payment_terms, :penalties, NOW(), NOW())');
        $stmt->execute($clean + ['contract_id' => $contractId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteByContractId(int $contractId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM rules WHERE contract_id = :contract_id');
        $stmt->execute(['contract_id' => $contractId]);
        return $stmt->rowCount() > 0;
    }
}
