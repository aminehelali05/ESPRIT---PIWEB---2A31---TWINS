<?php

include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/CandidatureController.php');
include_once(__DIR__ . '/MessageController.php');
include_once(__DIR__ . '/../services/OpenRouterService.php');

class JobOfferController
{
    private PDO $pdo;
    private CandidatureController $candidatureController;
    private MessageController $messageController;
    private OpenRouterService $openRouterService;
    private array $columnCache = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? config::getConnexion();
        // initialize OpenRouter service (uses OPENROUTER_API_KEY from config)
        $this->openRouterService = new OpenRouterService($this->pdo);
        $this->candidatureController = new CandidatureController($this->pdo);
        $this->messageController = new MessageController($this->pdo);
    }

    private function normalizeOfferStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $map = [
            'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'open' => 'approved',
            'in_progress' => 'approved',
            'closed' => 'rejected',
            'archived' => 'rejected',
        ];

        return $map[$status] ?? 'pending';
    }

    private function offerIsVisible(array $offer, int $viewerId): bool
    {
        if ((int) ($offer['client_id'] ?? 0) === $viewerId) {
            return true;
        }

        return $this->normalizeOfferStatus((string) ($offer['status'] ?? '')) === 'approved';
    }

    private function offerStatusOptions(): array
    {
        return ['pending', 'approved', 'rejected'];
    }

    private function storeAiResult(string $cacheKey, array $result, ?int $jobId = null, ?int $userId = null): void
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO ai_results (cache_key, job_id, user_id, result_json, created_at) VALUES (:cache_key, :job_id, :user_id, :result_json, NOW())
                ON DUPLICATE KEY UPDATE job_id = VALUES(job_id), user_id = VALUES(user_id), result_json = VALUES(result_json), created_at = VALUES(created_at)');
            $stmt->execute([
                'cache_key' => $cacheKey,
                'job_id' => $jobId,
                'user_id' => $userId,
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $exception) {
            error_log('JobOfferController::storeAiResult - ' . $exception->getMessage());
        }
    }

    private function candidateSummary(array $user): string
    {
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        $skills = trim((string) ($user['skills'] ?? ''));
        $projects = trim((string) ($user['projects_summary'] ?? ''));
        return trim($name . ' | skills: ' . $skills . ' | projects: ' . $projects);
    }

    // Public helper: return AI match between a job offer and a user profile.
    // Returns array: match_percentage (int), explanation (string), highlights (array), raw (mixed)
    public function aiMatchOfferForUser(int $offerId, int $userId, bool $forceRefresh = false): array
    {
        $offer = $this->findById($offerId);
        if (!$offer) {
            throw new RuntimeException('Offer not found');
        }

        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, skills, bio, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new RuntimeException('User not found');
        }

        // normalize user fields used by AI
        $user['skills'] = trim((string)($user['skills'] ?? ''));
        // attach lightweight project/experience summary if available
        $projects = $this->pdo->prepare('SELECT id, title, progress_percent FROM projects WHERE owner_id = :id ORDER BY updated_at DESC LIMIT 6');
        $projects->execute(['id' => $userId]);
        $projRows = $projects->fetchAll(PDO::FETCH_ASSOC);
        $user['projects_summary'] = [];
        foreach ($projRows as $p) {
            $user['projects_summary'][] = $p['title'] . ' (' . ($p['progress_percent'] ?? 0) . '%)';
        }
        $user['projects_summary'] = implode('; ', $user['projects_summary']);
        $user['experience_years'] = null; // could be extended from profile

        // call service
        $result = $this->openRouterService->matchJobToUser($offer, array_merge($user, ['id' => $userId]), $forceRefresh);

        return $result;
    }

    public function analyzeSalarySuggestion(array $payload, bool $forceRefresh = false): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $skills = $this->sanitizeText((string) ($payload['skills_required'] ?? ''));
        $location = $this->sanitizeText((string) ($payload['location'] ?? ''));
        $experience = $this->sanitizeText((string) ($payload['experience_level'] ?? ''));
        $projectType = $this->sanitizeText((string) ($payload['project_type'] ?? ''));
        $budget = trim((string) ($payload['budget'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Job title is required.');
        }
        if ($description === '') {
            throw new RuntimeException('Job description is required.');
        }

        $cacheKey = 'salary_suggestion_' . md5(json_encode([$title, $description, $skills, $location, $experience, $projectType, $budget], JSON_UNESCAPED_UNICODE));
        if (!$forceRefresh) {
            try {
                $stmt = $this->pdo->prepare('SELECT result_json, created_at FROM ai_results WHERE cache_key = :cache_key LIMIT 1');
                $stmt->execute(['cache_key' => $cacheKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $data = json_decode((string) ($row['result_json'] ?? ''), true);
                    if (is_array($data)) {
                        return $data;
                    }
                }
            } catch (Throwable $exception) {
                error_log('JobOfferController::analyzeSalarySuggestion cache - ' . $exception->getMessage());
            }
        }

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are an expert job market analyst. Return valid JSON only with these keys: salary (string, e.g. "3500 TND"), currency (string), explanation (string explaining the reasoning), range_min (number), range_max (number), roi_score (integer 0-10 rating how competitive this offer is), country_analysis (array of objects with keys: country, salary, currency, comparison — compare the salary to at least 3 countries for the same role). Be concise and precise. RETURN ONLY JSON, NO TEXT, NO MARKDOWN. Do not wrap in ```json'
            ],
            [
                'role' => 'user',
                'content' => "Title: {$title}\nDescription: {$description}\nSkills: {$skills}\nLocation: {$location}\nExperience: {$experience}\nProject type: {$projectType}\nBudget: {$budget}\nReturn salary recommendation with country comparisons and ROI score."
            ],
        ];

        $resp = $this->openRouterService->callChat($messages, ['temperature' => 0.1, 'max_tokens' => 600]);
        $result = ['salary' => null, 'currency' => 'TND', 'explanation' => '', 'range_min' => null, 'range_max' => null, 'roi_score' => 0, 'country_analysis' => [], 'raw' => $resp];

        if (isset($resp['choices'][0]['message']['content'])) {
            $content = (string) $resp['choices'][0]['message']['content'];
            $decoded = $this->openRouterService->safeJsonDecode($content);

            if (is_array($decoded)) {
                $decoded['roi_score'] = isset($decoded['roi_score']) && is_numeric($decoded['roi_score']) ? (int) $decoded['roi_score'] : 0;
                $decoded['salary'] = isset($decoded['salary']) && (is_string($decoded['salary']) || is_numeric($decoded['salary'])) ? (string) $decoded['salary'] : null;
                $decoded['country_analysis'] = isset($decoded['country_analysis']) && is_array($decoded['country_analysis']) ? $decoded['country_analysis'] : [];
                if (!isset($decoded['explanation']) || trim($decoded['explanation']) === '') {
                    $decoded['explanation'] = 'AI response parsed successfully.';
                }
                $result = array_merge($result, $decoded);
            } else {
                $result['explanation'] = 'AI response invalid';
                $result['roi_score'] = 0;
                $result['salary'] = null;
                $result['country_analysis'] = [];
            }
        } elseif (isset($resp['raw']) && is_string($resp['raw'])) {
            $result['explanation'] = trim($resp['raw']);
        } elseif (isset($resp['error'])) {
            $result['explanation'] = 'AI error: ' . $resp['error'];
        }

        if (($result['salary'] ?? '') === '' && isset($result['range_min'], $result['range_max']) && is_numeric($result['range_min']) && is_numeric($result['range_max'])) {
            $result['salary'] = number_format((float) $result['range_min'], 0) . ' - ' . number_format((float) $result['range_max'], 0) . ' ' . strtoupper((string) ($result['currency'] ?? 'TND'));
        }
        $result['currency'] = strtoupper(trim((string) ($result['currency'] ?? 'TND'))) ?: 'TND';
        $this->storeAiResult($cacheKey, $result);
        return $result;
    }

    public function smartMatchCandidates(int $offerId, int $actorUserId, bool $forceRefresh = false, int $limit = 5): array
    {
        $offer = $this->findById($offerId);
        if (!$offer) {
            throw new RuntimeException('Offer not found');
        }

        $offerPayload = [
            'title' => (string) ($offer['title'] ?? ''),
            'description' => (string) ($offer['description'] ?? ''),
            'skills_required' => (string) ($offer['skills_required'] ?? ''),
            'location' => (string) ($offer['location'] ?? ''),
            'budget' => (string) ($offer['budget'] ?? ''),
            'salary' => (string) ($offer['salary'] ?? ''),
        ];

        $candidates = $this->pdo->query('SELECT u.id, u.first_name, u.last_name, u.skills, u.bio, u.role,
                GROUP_CONCAT(DISTINCT p.title ORDER BY p.updated_at DESC SEPARATOR " | ") AS projects_summary
            FROM users u
            LEFT JOIN projects p ON p.owner_id = u.id
            WHERE u.role IN ("freelancer", "user")
            GROUP BY u.id
            ORDER BY u.updated_at DESC
            LIMIT 25')->fetchAll(PDO::FETCH_ASSOC);

        if ($candidates === []) {
            return ['matches' => [], 'raw' => [], 'message' => 'No candidates available.'];
        }

        $cacheKey = 'offer_matches_' . $offerId . '_' . md5(json_encode($candidates, JSON_UNESCAPED_UNICODE));
        if (!$forceRefresh) {
            try {
                $stmt = $this->pdo->prepare('SELECT result_json FROM ai_results WHERE cache_key = :cache_key LIMIT 1');
                $stmt->execute(['cache_key' => $cacheKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $data = json_decode((string) ($row['result_json'] ?? ''), true);
                    if (is_array($data)) {
                        return $data;
                    }
                }
            } catch (Throwable $exception) {
                error_log('JobOfferController::smartMatchCandidates cache - ' . $exception->getMessage());
            }
        }

        $messages = [
            ['role' => 'system', 'content' => 'You rank freelancer candidates for a job offer. Return valid JSON only with key matches as an array of {user_id, match_percentage, reason}. RETURN ONLY JSON, NO TEXT, NO MARKDOWN. Do not wrap in ```json'],
            ['role' => 'user', 'content' => 'Job offer: ' . json_encode($offerPayload, JSON_UNESCAPED_UNICODE) . "\nCandidates: " . json_encode($candidates, JSON_UNESCAPED_UNICODE) . "\nReturn only JSON."],
        ];

        $resp = $this->openRouterService->callChat($messages, ['temperature' => 0.1, 'max_tokens' => 800]);
        $result = ['matches' => [], 'raw' => $resp];
        $content = $resp['choices'][0]['message']['content'] ?? ($resp['raw'] ?? null);
        if (is_string($content)) {
            $decoded = $this->openRouterService->safeJsonDecode($content);
            if (is_array($decoded)) {
                $result = array_merge($result, $decoded);
            }
        }

        $matches = is_array($result['matches'] ?? null) ? array_values(array_filter($result['matches'], 'is_array')) : [];
        usort($matches, static fn(array $a, array $b): int => (int) ($b['match_percentage'] ?? 0) <=> (int) ($a['match_percentage'] ?? 0));
        $matches = array_slice($matches, 0, max(1, min(10, $limit)));

        $normalizedMatches = [];
        foreach ($matches as $match) {
            $candidateId = (int) ($match['user_id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }
            $normalizedMatches[] = [
                'user_id' => $candidateId,
                'match_percentage' => max(0, min(100, (int) ($match['match_percentage'] ?? 0))),
                'reason' => trim((string) ($match['reason'] ?? '')),
            ];
        }

        $result['matches'] = $normalizedMatches;
        $this->storeAiResult($cacheKey, $result, $offerId, $actorUserId > 0 ? $actorUserId : null);

        if ($actorUserId > 0 && $normalizedMatches !== []) {
            $sender = $actorUserId;
            foreach (array_slice($normalizedMatches, 0, 3) as $match) {
                $targetUserId = (int) ($match['user_id'] ?? 0);
                if ($targetUserId <= 0) {
                    continue;
                }
                $conversationId = $this->messageController->ensurePrivateConversationForPair($sender, $targetUserId);
                if ($conversationId > 0) {
                    $this->messageController->insertSystemMessage($conversationId, $sender, 'You were matched with a job offer: ' . (string) ($offer['title'] ?? 'Untitled offer'));
                }
            }
        }

        return $result;
    }

    private function jobOfferHasColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnCache)) {
            return $this->columnCache[$column];
        }

        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM job_offers LIKE " . $this->pdo->quote($column));
            $this->columnCache[$column] = (bool) ($stmt && $stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Throwable $exception) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    private function ensureJobOffersSchema(): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    private function ensureAiFeatureSchema(): void
    {
        // Schema is owned by Diversity.sql (single source of truth).
        return;
    }

    private function normalizeDateTimeString(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $value);
        return $dt instanceof DateTimeInterface ? $dt->format('Y-m-d H:i:s') : null;
    }



    public function upsertSalaryInsight(int $offerId, ?float $salaryMin, ?float $salaryMax, string $currency = 'TND'): bool
    {
        $salary = trim((string) $salaryMin);
        if ($salary === '' && $salaryMax !== null) {
            $salary = number_format($salaryMax, 0);
        }

        $stmt = $this->pdo->prepare('UPDATE job_offers SET salary = :salary, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'salary' => $salary !== '' ? $salary . ' ' . strtoupper(trim($currency) !== '' ? trim($currency) : 'TND') : null,
            'id' => $offerId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function buildOfferInsights(int $offerId): array
    {
        $offer = $this->findById($offerId);
        if (!$offer) {
            throw new RuntimeException('Offer not found.');
        }

        return [
            'salary' => trim((string) ($offer['salary'] ?? '')),
            'company_rating' => ['average_rating' => null, 'reviews_count' => 0],
        ];
    }

    private function normalizedTitle(array $payload): string
    {
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            return 'Untitled Offer';
        }

        $snippet = preg_replace('/\s+/', ' ', $description);
        return mb_substr((string) $snippet, 0, 120);
    }

    private function sanitizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? $value);
    }

    private function normalizeDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value) ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d H:i:s') : null;
    }

    private function validateOfferPayload(array $payload): array
    {
        $title = $this->sanitizeText((string) ($payload['title'] ?? ''));
        $description = $this->sanitizeText((string) ($payload['description'] ?? ''));
        $skills = $this->sanitizeText((string) ($payload['skills_required'] ?? ''));
        $location = $this->sanitizeText((string) ($payload['location'] ?? ''));
        $budget = max(0, (float) ($payload['budget'] ?? 0));
        $deadline = $this->normalizeDateTime((string) ($payload['deadline_at'] ?? ''));
        $rawStatus = (string) ($payload['status'] ?? 'pending');
        $status = in_array($rawStatus, ['pending', 'approved', 'rejected', 'open', 'closed', 'in_progress'], true) ? $rawStatus : 'pending';
        $experience = $this->sanitizeText((string) ($payload['experience_level'] ?? 'Mid'));
        $projectType = $this->sanitizeText((string) ($payload['project_type'] ?? 'Fixed Price'));
        $salary = $this->sanitizeText((string) ($payload['salary'] ?? ''));
        $clientId = (int) ($payload['client_id'] ?? 0);

        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }
        if (mb_strlen($title) < 10 || mb_strlen($title) > 140) {
            throw new RuntimeException('Title must be between 10 and 140 characters.');
        }
        if ($description === '') {
            throw new RuntimeException('Description is required.');
        }
        if (mb_strlen($description) < 40 || mb_strlen($description) > 3500) {
            throw new RuntimeException('Description must be between 40 and 3500 characters.');
        }
        if ($skills !== '' && (mb_strlen($skills) < 3 || mb_strlen($skills) > 255)) {
            throw new RuntimeException('Skills must be between 3 and 255 characters.');
        }
        if ($location !== '' && (mb_strlen($location) < 2 || mb_strlen($location) > 120)) {
            throw new RuntimeException('Location must be between 2 and 120 characters.');
        }
        if ($budget <= 0) {
            throw new RuntimeException('Budget must be greater than 0.');
        }
        if ($budget > 10000000) {
            throw new RuntimeException('Budget is too high.');
        }
        if ($salary !== '' && mb_strlen($salary) > 255) {
            throw new RuntimeException('Salary is too long.');
        }
        if (!in_array($experience, ['Junior', 'Mid', 'Senior', 'Expert'], true)) {
            throw new RuntimeException('Invalid experience level.');
        }
        if (!in_array($projectType, ['Fixed Price', 'Hourly', 'Retainer', 'Long-term'], true)) {
            throw new RuntimeException('Invalid project type.');
        }
        if ($clientId <= 0) {
            throw new RuntimeException('Client is required.');
        }

        return [
            'title' => $title,
            'description' => $description,
            'skills_required' => $skills,
            'location' => $location,
            'budget' => $budget,
            'deadline_at' => $deadline,
            'status' => $status,
            'salary' => $salary !== '' ? $salary : null,
            'experience_level' => $experience,
            'project_type' => $projectType,
            'client_id' => $clientId,
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
        $email = trim((string) ($row['email'] ?? ''));
        return $email !== '' ? $email : ('User #' . (int) ($row['id'] ?? 0));
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

    public function listBackofficeRows(): array
    {
        $sql = 'SELECT o.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM job_offer_applications a WHERE a.job_offer_id = o.id) AS applications_count
                FROM job_offers o
                LEFT JOIN users u ON u.id = o.client_id
                ORDER BY o.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buildBackofficeStats(array $rows): array
    {
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        $apps = 0;
        foreach ($rows as $row) {
            $status = $this->normalizeOfferStatus((string) ($row['status'] ?? ''));
            if ($status === 'pending') {
                $pending++;
            } elseif ($status === 'approved') {
                $approved++;
            } elseif ($status === 'rejected') {
                $rejected++;
            }
            $apps += (int) ($row['applications_count'] ?? 0);
        }
        return ['total' => count($rows), 'pending' => $pending, 'approved' => $approved, 'rejected' => $rejected, 'applications' => $apps];
    }

    public function buildBackofficeDashboard(array $filters = []): array
    {
        $fromDate = trim((string) ($filters['from'] ?? ''));
        $toDate = trim((string) ($filters['to'] ?? ''));
        $query = strtolower(trim((string) ($filters['q'] ?? '')));

        $rows = $this->listBackofficeRows();
        $candidatures = $this->listAllCandidatures();
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
        $filteredCandidatures = array_values(array_filter($candidatures, static function (array $row) use ($query, $inDateRange): bool {
            $createdAt = (string) ($row['candidature_created_at'] ?? $row['applied_at'] ?? $row['created_at'] ?? '');
            if (!$inDateRange($createdAt)) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            return str_contains(strtolower((string) json_encode($row)), $query);
        }));

        $applicationTrend = [];
        $statusDistribution = ['pending' => 0, 'accepted' => 0, 'rejected' => 0];
        $skillsCount = [];
        $locationCount = [];
        $acceptedByOffer = [];
        $timeToHireByMonth = [];

        foreach ($filteredRows as $offerRow) {
            $location = trim((string) ($offerRow['location'] ?? 'Unknown'));
            $locationKey = $location !== '' ? $location : 'Unknown';
            $locationCount[$locationKey] = ($locationCount[$locationKey] ?? 0) + 1;
            $skills = preg_split('/[,\|;]/', (string) ($offerRow['skills_required'] ?? '')) ?: [];
            foreach ($skills as $skillRaw) {
                $skill = strtolower(trim($skillRaw));
                if ($skill === '') {
                    continue;
                }
                $skillsCount[$skill] = ($skillsCount[$skill] ?? 0) + 1;
            }
        }

        foreach ($filteredCandidatures as $applicationRow) {
            $createdAt = (string) ($applicationRow['candidature_created_at'] ?? $applicationRow['applied_at'] ?? $applicationRow['created_at'] ?? '');
            $monthKey = $createdAt !== '' ? date('Y-m', strtotime($createdAt)) : 'unknown';
            $applicationTrend[$monthKey] = ($applicationTrend[$monthKey] ?? 0) + 1;
            $status = strtolower((string) ($applicationRow['status'] ?? 'pending'));
            if (!isset($statusDistribution[$status])) {
                $statusDistribution[$status] = 0;
            }
            $statusDistribution[$status]++;
            if ($status === 'accepted') {
                $offerId = (int) ($applicationRow['job_offer_id'] ?? 0);
                if ($offerId > 0) {
                    $acceptedByOffer[$offerId] = $createdAt;
                }
            }
        }

        foreach ($filteredRows as $offerRow) {
            $offerId = (int) ($offerRow['id'] ?? 0);
            $createdAt = (string) ($offerRow['created_at'] ?? '');
            if ($offerId <= 0 || $createdAt === '' || !isset($acceptedByOffer[$offerId])) {
                continue;
            }
            $days = (int) floor((strtotime($acceptedByOffer[$offerId]) - strtotime($createdAt)) / 86400);
            $monthKey = date('Y-m', strtotime($acceptedByOffer[$offerId]));
            if (!isset($timeToHireByMonth[$monthKey])) {
                $timeToHireByMonth[$monthKey] = ['sum' => 0, 'count' => 0];
            }
            $timeToHireByMonth[$monthKey]['sum'] += max(0, $days);
            $timeToHireByMonth[$monthKey]['count']++;
        }

        ksort($applicationTrend);
        arsort($skillsCount);
        arsort($locationCount);
        ksort($timeToHireByMonth);

        $applicationsTotal = count($filteredCandidatures);
        $acceptedTotal = (int) ($statusDistribution['accepted'] ?? 0);
        $conversionRate = $applicationsTotal > 0 ? round(($acceptedTotal / $applicationsTotal) * 100, 1) : 0.0;
        $topSkills = array_slice($skillsCount, 0, 6, true);
        $topLocations = array_slice($locationCount, 0, 6, true);
        $avgHireTrend = [];
        foreach ($timeToHireByMonth as $month => $bucket) {
            $avgHireTrend[$month] = $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 1) : 0;
        }

        return [
            'filters' => ['from' => $fromDate, 'to' => $toDate, 'q' => $query],
            'rows' => $filteredRows,
            'candidatures' => $filteredCandidatures,
            'stats' => [
                'total' => count($filteredRows),
                'open' => count(array_filter($filteredRows, static fn(array $row): bool => in_array((string) ($row['status'] ?? ''), ['approved', 'open', 'in_progress'], true))),
                'applications' => count($filteredCandidatures),
                'conversion_rate' => $conversionRate,
            ],
            'charts' => [
                'application_trend' => $applicationTrend,
                'status_distribution' => $statusDistribution,
                'top_skills' => $topSkills,
                'top_locations' => $topLocations,
                'time_to_hire' => $avgHireTrend,
            ],
        ];
    }

    public function createFromBackoffice(array $payload): int
    {
        $clean = $this->validateOfferPayload($payload);
        $stmt = $this->pdo->prepare('INSERT INTO job_offers (title, description, budget, salary, skills_required, location, experience_level, project_type, status, deadline_at, client_id, created_at, updated_at) VALUES (:title, :description, :budget, :salary, :skills_required, :location, :experience_level, :project_type, :status, :deadline_at, :client_id, NOW(), NOW())');
        $stmt->execute([
            'title' => $clean['title'],
            'description' => $clean['description'],
            'budget' => $clean['budget'],
            'salary' => $clean['salary'],
            'skills_required' => $clean['skills_required'] ?: null,
            'location' => $clean['location'] ?: null,
            'experience_level' => $clean['experience_level'],
            'project_type' => $clean['project_type'],
            'status' => $clean['status'] ?: 'pending',
            'deadline_at' => $clean['deadline_at'],
            'client_id' => $clean['client_id'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $offerId, string $status): bool
    {
        $allowed = ['pending', 'approved', 'rejected', 'open', 'in_progress', 'closed', 'archived'];
        $status = strtolower(trim($status));
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $stmt = $this->pdo->prepare('UPDATE job_offers SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute(['status' => $status, 'id' => $offerId]);
    }

    public function deleteCascade(int $offerId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM candidatures WHERE job_offer_id = :id')->execute(['id' => $offerId]);
            $this->pdo->prepare('DELETE FROM job_offer_applications WHERE job_offer_id = :id')->execute(['id' => $offerId]);
            $this->pdo->prepare('DELETE FROM contracts WHERE job_offer_id = :id')->execute(['id' => $offerId]);
            $this->pdo->prepare('DELETE FROM job_offers WHERE id = :id')->execute(['id' => $offerId]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function listFrontofficeRows(array $filters, ?int $viewerId = null): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? 'all')));
        $type = trim((string) ($filters['type'] ?? 'all'));
        $experience = trim((string) ($filters['experience'] ?? 'all'));
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'newest')));

        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(o.title LIKE :q OR o.description LIKE :q OR o.skills_required LIKE :q OR o.location LIKE :q OR o.project_type LIKE :q OR o.experience_level LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            if ($status === 'approved') {
                $where[] = 'o.status IN ("approved","open","in_progress")';
            } else {
                $where[] = 'o.status = :status';
                $params['status'] = $status;
            }
        }
        if (in_array($type, ['Fixed Price', 'Hourly', 'Retainer', 'Long-term'], true)) {
            $where[] = 'o.project_type = :project_type';
            $params['project_type'] = $type;
        }
        if (in_array($experience, ['Junior', 'Mid', 'Senior', 'Expert'], true)) {
            $where[] = 'o.experience_level = :experience_level';
            $params['experience_level'] = $experience;
        }
        if ($viewerId !== null && $viewerId > 0) {
            $where[] = '(o.status IN ("approved","open","in_progress") OR o.client_id = :viewer_id)';
            $params['viewer_id'] = $viewerId;
        } else {
            $where[] = 'o.status IN ("approved","open","in_progress")';
        }

        $order = 'o.created_at DESC';
        if ($sort === 'budget_asc') {
            $order = 'o.budget ASC, o.created_at DESC';
        } elseif ($sort === 'budget_desc') {
            $order = 'o.budget DESC, o.created_at DESC';
        } elseif ($sort === 'deadline') {
            $order = 'o.deadline_at IS NULL, o.deadline_at ASC, o.created_at DESC';
        }

        $sql = 'SELECT o.*, u.first_name, u.last_name, u.avatar_url,
                (SELECT COUNT(*) FROM job_offer_applications a WHERE a.job_offer_id = o.id) AS applications_count,
                (SELECT COUNT(*) FROM job_offer_applications a WHERE a.job_offer_id = o.id AND a.status = "pending") AS pending_count
                FROM job_offers o
                INNER JOIN users u ON u.id = o.client_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isOwnedByClient(int $offerId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM job_offers WHERE id = :id AND client_id = :client_id LIMIT 1');
        $stmt->execute(['id' => $offerId, 'client_id' => $clientId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createByClient(array $payload): int
    {
        $payload['status'] = 'pending';
        return $this->createFromBackoffice($payload);
    }

    public function findById(int $offerId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM job_offers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $offerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateOwnedByClient(int $offerId, int $clientId, array $payload): bool
    {
        $clean = $this->validateOfferPayload($payload);
        $stmt = $this->pdo->prepare('UPDATE job_offers SET title = :title, description = :description, budget = :budget, salary = :salary, skills_required = :skills_required, location = :location, experience_level = :experience_level, project_type = :project_type, status = :status, deadline_at = :deadline_at, updated_at = NOW() WHERE id = :id AND client_id = :client_id');
        return $stmt->execute([
            'title' => $clean['title'],
            'description' => $clean['description'],
            'budget' => $clean['budget'],
            'salary' => $clean['salary'],
            'skills_required' => $clean['skills_required'] ?: null,
            'location' => $clean['location'] ?: null,
            'experience_level' => $clean['experience_level'],
            'project_type' => $clean['project_type'],
            'status' => 'pending', // Client edits require re-approval
            'deadline_at' => $clean['deadline_at'],
            'id' => $offerId,
            'client_id' => $clientId,
        ]);
    }

    public function deleteOwnedByClient(int $offerId, int $clientId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM job_offers WHERE id = :id AND client_id = :client_id');
        return $stmt->execute(['id' => $offerId, 'client_id' => $clientId]);
    }

    public function freelancerAlreadyApplied(int $offerId, int $freelancerId): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM job_offer_applications WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1');
        $stmt->execute(['job_offer_id' => $offerId, 'freelancer_id' => $freelancerId]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function applyToOffer(int $offerId, int $freelancerId, array $payload = [], array $files = []): int
    {
        return $this->saveCandidatureForFreelancer($offerId, $freelancerId, null, $payload, $files);
    }

    public function saveCandidatureForFreelancer(int $offerId, int $freelancerId, ?int $candidatureId, array $payload, array $files = []): int
    {
        $clean = $this->candidatureController->normalizeApplicationPayload($payload);

        $this->pdo->beginTransaction();
        try {
            $applicationStmt = $this->pdo->prepare('SELECT * FROM job_offer_applications WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1 FOR UPDATE');
            $applicationStmt->execute([
                'job_offer_id' => $offerId,
                'freelancer_id' => $freelancerId,
            ]);
            $applicationRow = $applicationStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($candidatureId > 0) {
                $candidatureStmt = $this->pdo->prepare('SELECT * FROM candidatures WHERE id = :id AND job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1 FOR UPDATE');
                $candidatureStmt->execute([
                    'id' => $candidatureId,
                    'job_offer_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                ]);
            } else {
                $candidatureStmt = $this->pdo->prepare('SELECT * FROM candidatures WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id LIMIT 1 FOR UPDATE');
                $candidatureStmt->execute([
                    'job_offer_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                ]);
            }
            $candidatureRow = $candidatureStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $attachmentsJson = $this->candidatureController->storeAttachmentsFromFiles($files, $freelancerId);
            if ($attachmentsJson === null && $candidatureRow) {
                $attachmentsJson = (string) ($candidatureRow['attachments_json'] ?? '') ?: null;
            }
            $proposedBudget = $clean['proposed_budget'];
            if ($proposedBudget === null && $candidatureRow) {
                $existingBudget = trim((string) ($candidatureRow['proposed_budget'] ?? ''));
                $proposedBudget = $existingBudget !== '' ? (float) $existingBudget : null;
            }
            $estimatedDeliveryDays = $clean['estimated_delivery_days'];
            if ($estimatedDeliveryDays === null && $candidatureRow) {
                $existingDays = trim((string) ($candidatureRow['estimated_delivery_days'] ?? ''));
                $estimatedDeliveryDays = $existingDays !== '' ? (int) $existingDays : null;
            }
            $skillsExperience = $clean['skills_experience'];
            if ($skillsExperience === null && $candidatureRow) {
                $existingSkills = trim((string) ($candidatureRow['skills_experience'] ?? ''));
                $skillsExperience = $existingSkills !== '' ? $existingSkills : null;
            }
            $sourceStatus = (string) ($applicationRow['status'] ?? ($candidatureRow['status'] ?? 'pending'));
            $candidatureStatus = in_array($sourceStatus, ['accepted', 'rejected'], true) ? $sourceStatus : 'pending';

            if ($applicationRow) {
                $updateApp = $this->pdo->prepare('UPDATE job_offer_applications SET cover_letter = :cover_letter, updated_at = NOW() WHERE id = :id');
                $updateApp->execute([
                    'cover_letter' => $clean['message'],
                    'id' => (int) ($applicationRow['id'] ?? 0),
                ]);
                $applicationId = (int) ($applicationRow['id'] ?? 0);
            } else {
                $insertApp = $this->pdo->prepare('INSERT INTO job_offer_applications (job_offer_id, freelancer_id, cover_letter, status, applied_at, created_at, updated_at) VALUES (:job_offer_id, :freelancer_id, :cover_letter, :status, NOW(), NOW(), NOW())');
                $insertApp->execute([
                    'job_offer_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                    'cover_letter' => $clean['message'],
                    'status' => 'pending',
                ]);
                $applicationId = (int) $this->pdo->lastInsertId();
            }

            if ($candidatureRow) {
                $updateCandidature = $this->pdo->prepare('UPDATE candidatures
                    SET message = :message,
                        proposed_budget = :proposed_budget,
                        estimated_delivery_days = :estimated_delivery_days,
                        skills_experience = :skills_experience,
                        attachments_json = :attachments_json,
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id');
                $updateCandidature->execute([
                    'message' => $clean['message'],
                    'proposed_budget' => $proposedBudget,
                    'estimated_delivery_days' => $estimatedDeliveryDays,
                    'skills_experience' => $skillsExperience,
                    'attachments_json' => $attachmentsJson,
                    'status' => $candidatureStatus,
                    'id' => (int) ($candidatureRow['id'] ?? 0),
                ]);
            } else {
                $insertCandidature = $this->pdo->prepare('INSERT INTO candidatures (
                    job_offer_id, freelancer_id, message, proposed_budget, estimated_delivery_days, skills_experience, attachments_json, status, created_at, updated_at
                ) VALUES (
                    :job_offer_id, :freelancer_id, :message, :proposed_budget, :estimated_delivery_days, :skills_experience, :attachments_json, :status, NOW(), NOW()
                )');
                $insertCandidature->execute([
                    'job_offer_id' => $offerId,
                    'freelancer_id' => $freelancerId,
                    'message' => $clean['message'],
                    'proposed_budget' => $proposedBudget,
                    'estimated_delivery_days' => $estimatedDeliveryDays,
                    'skills_experience' => $skillsExperience,
                    'attachments_json' => $attachmentsJson,
                    'status' => $candidatureStatus,
                ]);
            }

            $this->pdo->commit();
            return $applicationId;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteCandidatureForFreelancer(int $offerId, int $freelancerId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt1 = $this->pdo->prepare('DELETE FROM job_offer_applications WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id');
            $stmt1->execute(['job_offer_id' => $offerId, 'freelancer_id' => $freelancerId]);
            $stmt2 = $this->pdo->prepare('DELETE FROM candidatures WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id');
            $stmt2->execute(['job_offer_id' => $offerId, 'freelancer_id' => $freelancerId]);
            $this->pdo->commit();
            return ($stmt1->rowCount() + $stmt2->rowCount()) > 0;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deleteCandidatureById(int $applicationId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare('SELECT job_offer_id, freelancer_id FROM job_offer_applications WHERE id = :id LIMIT 1');
            $lookup->execute(['id' => $applicationId]);
            $row = $lookup->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }

            $this->pdo->prepare('DELETE FROM job_offer_applications WHERE id = :id')->execute(['id' => $applicationId]);
            $this->pdo->prepare('DELETE FROM candidatures WHERE job_offer_id = :job_offer_id AND freelancer_id = :freelancer_id')->execute([
                'job_offer_id' => (int) ($row['job_offer_id'] ?? 0),
                'freelancer_id' => (int) ($row['freelancer_id'] ?? 0),
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

    public function listAllCandidatures(): array
    {
        $sql = 'SELECT c.id AS candidature_id, c.job_offer_id, c.job_offer_id AS offre_id, c.freelancer_id, c.message, c.proposed_budget, c.estimated_delivery_days,
                       c.skills_experience, c.attachments_json, c.created_at AS candidature_created_at,
                       a.id AS application_id, a.status, a.applied_at,
                       o.title AS offer_title, o.client_id, u.first_name, u.last_name, u.email
                FROM candidatures c
                INNER JOIN job_offers o ON o.id = c.job_offer_id
                INNER JOIN users u ON u.id = c.freelancer_id
                LEFT JOIN job_offer_applications a ON a.job_offer_id = c.job_offer_id AND a.freelancer_id = c.freelancer_id
                ORDER BY c.created_at DESC';
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function freelancerApplicationsMap(int $freelancerId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id AS application_id, c.id AS candidature_id, a.job_offer_id, a.status, a.cover_letter, a.applied_at,
                c.message, c.proposed_budget, c.estimated_delivery_days, c.skills_experience, c.attachments_json
            FROM job_offer_applications a
            INNER JOIN candidatures c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
            WHERE a.freelancer_id = :freelancer_id');
        $stmt->execute(['freelancer_id' => $freelancerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $app) {
            $map[(int) ($app['job_offer_id'] ?? 0)] = $app;
        }
        return $map;
    }

    public function contractsMapForFreelancer(int $freelancerId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_offer_id, client_signed, freelancer_signed, freelancer_refused_at
            FROM contracts
            WHERE freelancer_id = :freelancer_id');
        $stmt->execute(['freelancer_id' => $freelancerId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offerId = (int) ($row['job_offer_id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $clientSigned = (int) ($row['client_signed'] ?? 0) === 1;
            $freelancerSigned = (int) ($row['freelancer_signed'] ?? 0) === 1;
            $refusedAt = trim((string) ($row['freelancer_refused_at'] ?? ''));
            $map[$offerId] = [
                'id' => (int) ($row['id'] ?? 0),
                'client_signed' => $clientSigned,
                'freelancer_signed' => $freelancerSigned,
                'workflow_state' => $freelancerSigned ? 'completed' : ($refusedAt !== '' ? 'refused' : ($clientSigned ? 'waiting_freelancer' : 'waiting_client')),
            ];
        }
        return $map;
    }

    public function contractsMapForClient(int $clientId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, job_offer_id, client_signed, freelancer_signed, freelancer_refused_at
            FROM contracts
            WHERE client_id = :client_id');
        $stmt->execute(['client_id' => $clientId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $offerId = (int) ($row['job_offer_id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }
            $clientSigned = (int) ($row['client_signed'] ?? 0) === 1;
            $freelancerSigned = (int) ($row['freelancer_signed'] ?? 0) === 1;
            $refusedAt = trim((string) ($row['freelancer_refused_at'] ?? ''));
            $map[$offerId] = [
                'id' => (int) ($row['id'] ?? 0),
                'client_signed' => $clientSigned,
                'freelancer_signed' => $freelancerSigned,
                'workflow_state' => $freelancerSigned ? 'completed' : ($refusedAt !== '' ? 'refused' : ($clientSigned ? 'waiting_freelancer' : 'waiting_client')),
            ];
        }
        return $map;
    }

    public function clientApplicationsByOffer(int $clientId): array
    {
        $sql = 'SELECT a.*, c.id AS candidature_id, c.message, c.proposed_budget, c.estimated_delivery_days,
                       c.skills_experience, c.attachments_json, u.first_name, u.last_name, u.email
                FROM job_offer_applications a
                INNER JOIN job_offers o ON o.id = a.job_offer_id
                INNER JOIN candidatures c ON c.job_offer_id = a.job_offer_id AND c.freelancer_id = a.freelancer_id
                INNER JOIN users u ON u.id = a.freelancer_id
                WHERE o.client_id = :client_id
                ORDER BY a.applied_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['client_id' => $clientId]);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $app) {
            $offerId = (int) ($app['job_offer_id'] ?? 0);
            if (!isset($grouped[$offerId])) {
                $grouped[$offerId] = [];
            }
            $grouped[$offerId][] = $app;
        }
        return $grouped;
    }

    public function decideApplication(int $clientId, int $applicationId, string $decision): void
    {
        $this->pdo->beginTransaction();
        try {
            $appQuery = $this->pdo->prepare('SELECT a.*, o.client_id, o.budget, o.id AS offer_id, o.title AS offer_title FROM job_offer_applications a INNER JOIN job_offers o ON o.id = a.job_offer_id WHERE a.id = :id LIMIT 1 FOR UPDATE');
            $appQuery->execute(['id' => $applicationId]);
            $application = $appQuery->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                throw new RuntimeException('Application not found.');
            }
            if ((int) ($application['client_id'] ?? 0) !== $clientId) {
                throw new RuntimeException('You can only manage candidates for your own offers.');
            }

            $update = $this->pdo->prepare('UPDATE job_offer_applications SET status = :status, updated_at = NOW() WHERE id = :id');
            $update->execute(['status' => $decision, 'id' => $applicationId]);

            $this->pdo->prepare('UPDATE candidatures
                SET status = :status,
                    updated_at = NOW()
                WHERE job_offer_id = :job_offer_id
                  AND freelancer_id = :freelancer_id')
                ->execute([
                    'status' => $decision,
                    'job_offer_id' => (int) ($application['job_offer_id'] ?? 0),
                    'freelancer_id' => (int) ($application['freelancer_id'] ?? 0),
                ]);

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
