<?php

include_once(__DIR__ . '/../config.php');

class RulesController
{
    private PDO $pdo;

    private const RULE_TYPES = ['payment', 'deadline', 'scope', 'penalties', 'other'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        $this->backfillLegacyRules();
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

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
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

    private function normalizeRuleType(string $value): string
    {
        $type = strtolower($this->sanitizeText($value));
        $legacyMap = [
            'delivery' => 'scope',
            'communication' => 'scope',
            'quality' => 'scope',
            'revision' => 'scope',
            'legal' => 'penalties',
            'penalty' => 'penalties',
            'terms' => 'scope',
        ];

        $type = $legacyMap[$type] ?? $type;
        return $type !== '' ? $type : 'other';
    }

    private function normalizeRulePayload(array $payload, int $index = 0): array
    {
        $type = $this->normalizeRuleType((string) ($payload['rule_type'] ?? $payload['type'] ?? 'scope'));
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? $payload['terms'] ?? ''));
        $dueDateRaw = trim((string) ($payload['due_date'] ?? $payload['deadline'] ?? ''));
        $dueDate = $this->normalizeDate($dueDateRaw);
        $penalty = $this->sanitizeText((string) ($payload['penalty'] ?? $payload['penalties'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Rule #' . ($index + 1) . ' title is required.');
        }
        if (mb_strlen($title) < 5 || mb_strlen($title) > 160) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' title must be between 5 and 160 characters.');
        }
        if (!$this->startsWithUppercase($title)) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' title must start with an uppercase letter.');
        }
        if ($description === '') {
            throw new RuntimeException('Rule #' . ($index + 1) . ' description is required.');
        }
        if (mb_strlen($description) < 20 || mb_strlen($description) > 4000) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' description must be between 20 and 4000 characters.');
        }
        if (!$this->startsWithUppercase($description)) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' description must start with an uppercase letter.');
        }
        if ($dueDateRaw !== '' && $dueDate === null) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' due date is invalid.');
        }
        if ($dueDate !== null && new DateTimeImmutable($dueDate) <= new DateTimeImmutable('today')) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' due date must be after today.');
        }
        if ($penalty !== '' && (mb_strlen($penalty) < 5 || mb_strlen($penalty) > 2000)) {
            throw new RuntimeException('Rule #' . ($index + 1) . ' penalty must be between 5 and 2000 characters.');
        }

        return [
            'rule_type' => $type,
            'title' => $title,
            'description' => $description,
            'due_date' => $dueDate,
            'penalty' => $penalty !== '' ? $penalty : null,
            'sort_order' => (int) ($payload['sort_order'] ?? $index),
        ];
    }

    public function normalizeRulesPayload(array $payload): array
    {
        $rules = [];

        if (isset($payload['rules']) && is_array($payload['rules'])) {
            foreach ($payload['rules'] as $index => $rule) {
                if (is_array($rule)) {
                    $rules[] = $this->normalizeRulePayload($rule, (int) $index);
                }
            }
        } else {
            $titles = $payload['rule_titles'] ?? $payload['rule_title'] ?? [];
            $types = $payload['rule_types'] ?? $payload['rule_type'] ?? [];
            $descriptions = $payload['rule_descriptions'] ?? $payload['rule_description'] ?? [];
            $dueDates = $payload['rule_due_dates'] ?? $payload['rule_due_date'] ?? [];
            $penalties = $payload['rule_penalties'] ?? $payload['rule_penalty'] ?? [];

            if (!is_array($titles)) {
                $titles = [$titles];
            }
            if (!is_array($types)) {
                $types = [$types];
            }
            if (!is_array($descriptions)) {
                $descriptions = [$descriptions];
            }
            if (!is_array($dueDates)) {
                $dueDates = [$dueDates];
            }
            if (!is_array($penalties)) {
                $penalties = [$penalties];
            }

            foreach ($titles as $index => $title) {
                $hasAnyValue = trim((string) $title) !== ''
                    || trim((string) ($descriptions[$index] ?? '')) !== ''
                    || trim((string) ($dueDates[$index] ?? '')) !== ''
                    || trim((string) ($penalties[$index] ?? '')) !== '';

                if (!$hasAnyValue) {
                    continue;
                }

                $rules[] = $this->normalizeRulePayload([
                    'title' => (string) $title,
                    'rule_type' => (string) ($types[$index] ?? 'scope'),
                    'description' => (string) ($descriptions[$index] ?? ''),
                    'due_date' => (string) ($dueDates[$index] ?? ''),
                    'penalty' => (string) ($penalties[$index] ?? ''),
                    'sort_order' => $index,
                ], (int) $index);
            }
        }

        if ($rules === [] && trim((string) ($payload['rules_terms'] ?? '')) !== '') {
            $rules[] = $this->normalizeRulePayload([
                'title' => 'Contract terms',
                'rule_type' => 'scope',
                'description' => (string) ($payload['rules_terms'] ?? ''),
                'due_date' => (string) ($payload['rules_deadline'] ?? ''),
                'penalty' => trim((string) ($payload['rules_penalties'] ?? '')),
                'sort_order' => 0,
            ], 0);

            $paymentTerms = trim((string) ($payload['rules_payment_terms'] ?? ''));
            if ($paymentTerms !== '') {
                $rules[] = $this->normalizeRulePayload([
                    'title' => 'Payment terms',
                    'rule_type' => 'payment',
                    'description' => $paymentTerms,
                    'due_date' => '',
                    'penalty' => '',
                    'sort_order' => 1,
                ], 1);
            }
        }

        if ($rules === []) {
            throw new RuntimeException('Add at least one contract rule.');
        }

        return $rules;
    }

    private function backfillLegacyRules(): void
    {
        if (!$this->tableExists('contract_rules')) {
            return;
        }

        try {
            $sql = 'INSERT INTO rules (id, contract_id, rule_type, title, description, due_date, penalty, sort_order, created_at, updated_at)
                    SELECT cr.id,
                           cr.contract_id,
                           CASE
                               WHEN LOWER(cr.rule_type) IN ("delivery", "communication", "quality", "revision") THEN "scope"
                               WHEN LOWER(cr.rule_type) IN ("legal", "penalty") THEN "penalties"
                               WHEN LOWER(cr.rule_type) IN ("payment", "deadline", "scope", "penalties", "other") THEN LOWER(cr.rule_type)
                               ELSE "other"
                           END,
                           cr.title,
                           cr.description,
                           cr.due_date,
                           cr.penalty,
                           cr.sort_order,
                           COALESCE(cr.created_at, NOW()),
                           COALESCE(cr.updated_at, NOW())
                    FROM contract_rules cr
                    LEFT JOIN rules r ON r.id = cr.id
                    WHERE r.id IS NULL';
            $this->pdo->exec($sql);
        } catch (Throwable $exception) {
            error_log('RulesController::backfillLegacyRules - ' . $exception->getMessage());
        }
    }

    public function listWithContracts(): array
    {
        $sql = 'SELECT r.*, c.title AS contract_title, c.client_id, c.freelancer_id, c.created_at AS contract_created_at
                FROM rules r
                INNER JOIN contracts c ON c.id = r.contract_id
                ORDER BY c.created_at DESC, r.sort_order ASC, r.id ASC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByContractId(int $contractId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rules WHERE contract_id = :contract_id ORDER BY sort_order ASC, id ASC');
        $stmt->execute(['contract_id' => $contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listGroupedByContractIds(array $contractIds): array
    {
        $contractIds = array_values(array_unique(array_filter(array_map('intval', $contractIds), static fn(int $id): bool => $id > 0)));
        if ($contractIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
        $stmt = $this->pdo->prepare('SELECT * FROM rules WHERE contract_id IN (' . $placeholders . ') ORDER BY contract_id ASC, sort_order ASC, id ASC');
        $stmt->execute($contractIds);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rule) {
            $contractId = (int) ($rule['contract_id'] ?? 0);
            $grouped[$contractId][] = $rule;
        }

        return $grouped;
    }

    public function findByContractId(int $contractId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rules WHERE contract_id = :contract_id ORDER BY sort_order ASC, id ASC LIMIT 1');
        $stmt->execute(['contract_id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countByContractId(int $contractId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rules WHERE contract_id = :contract_id');
        $stmt->execute(['contract_id' => $contractId]);
        return (int) $stmt->fetchColumn();
    }

    public function createForContract(int $contractId, array $payload, int $sortOrder = 0): int
    {
        $clean = $this->normalizeRulePayload($payload + ['sort_order' => $sortOrder], $sortOrder);
        $stmt = $this->pdo->prepare('INSERT INTO rules (contract_id, rule_type, title, description, due_date, penalty, sort_order, created_at, updated_at)
            VALUES (:contract_id, :rule_type, :title, :description, :due_date, :penalty, :sort_order, NOW(), NOW())');
        $stmt->execute($clean + ['contract_id' => $contractId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function replaceForContract(int $contractId, array $payload): int
    {
        $rules = $this->normalizeRulesPayload($payload);

        $this->pdo->prepare('DELETE FROM rules WHERE contract_id = :contract_id')
            ->execute(['contract_id' => $contractId]);

        foreach ($rules as $index => $rule) {
            $this->createForContract($contractId, $rule, $index);
        }

        return count($rules);
    }

    public function update(int $ruleId, array $payload): bool
    {
        $clean = $this->normalizeRulePayload($payload);
        $stmt = $this->pdo->prepare('UPDATE rules
            SET rule_type = :rule_type,
                title = :title,
                description = :description,
                due_date = :due_date,
                penalty = :penalty,
                sort_order = :sort_order,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute($clean + ['id' => $ruleId]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $ruleId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM rules WHERE id = :id');
        $stmt->execute(['id' => $ruleId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByContractId(int $contractId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM rules WHERE contract_id = :contract_id');
        $stmt->execute(['contract_id' => $contractId]);
        return $stmt->rowCount() > 0;
    }
}
