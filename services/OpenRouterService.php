<?php

// Lightweight OpenRouter API service used by controllers/services for AI features.
class OpenRouterService
{
    private PDO $pdo;
    private string $apiKey;
    private string $model;
    private int $cacheTtlSeconds = 60 * 60 * 24; // 24 hours default cache

    public function __construct(PDO $pdo, ?string $apiKey = null, ?string $model = null)
    {
        $this->pdo = $pdo;
        $this->apiKey = $apiKey ?? (string) config::get('OPENROUTER_API_KEY', '');
        $this->model = $model ?? (string) config::get('OPENROUTER_MODEL', 'openai/gpt-4o-mini');
    }

    public function callChat(array $messages, array $options = []): array
    {
        if (trim($this->apiKey) === '') {
            return ['error' => 'Missing OpenRouter API key'];
        }

        $payload = array_merge(["model" => $this->model, "messages" => $messages], $options);

        // Canonical OpenRouter endpoint
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $resp === null) {
            return ['error' => 'Empty response from OpenRouter: ' . $err];
        }

        $decoded = json_decode($resp, true);
        if ($decoded === null) {
            return ['error' => 'Invalid JSON response from API: ' . substr((string)$resp, 0, 500)];
        }

        return $decoded;
    }

    /**
     * Safely extract and decode JSON from AI response
     */
    public function safeJsonDecode(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') return null;

        // 1. Try direct decode
        $decoded = json_decode($content, true);
        if (is_array($decoded)) return $decoded;

        // 2. Try to extract content between first { and last }
        if (preg_match('/\{(?:.|\n)*\}/s', $content, $matches)) {
            $jsonStr = trim($matches[0]);
            $decoded = json_decode($jsonStr, true);
            if (is_array($decoded)) return $decoded;
            
            // 3. Try to clean markdown tags if still failing
            $clean = preg_replace('/^```(?:json)?\s*|```\s*$/im', '', $jsonStr);
            $decoded = json_decode(trim($clean), true);
            if (is_array($decoded)) return $decoded;
        }

        // 4. Try stripping all backticks as last resort
        $noBackticks = preg_replace('/```(?:json)?/i', '', $content);
        $decoded = json_decode(trim($noBackticks), true);
        if (is_array($decoded)) return $decoded;

        return null;
    }

    // Compose a match prompt and get structured output. Returns array with keys: match_percentage, explanation, raw
    public function matchJobToUser(array $job, array $user, bool $forceRefresh = false): array
    {
        // compute cache key
        $jobHash = md5(json_encode($job));
        $userHash = md5(json_encode($user));
        $cacheKey = 'job_user_match_' . $job['id'] . '_' . $user['id'] . '_' . $jobHash . '_' . $userHash;

        if (!$forceRefresh) {
            try {
                $stmt = $this->pdo->prepare('SELECT result_json, created_at FROM ai_results WHERE cache_key = :cache_key LIMIT 1');
                $stmt->execute(['cache_key' => $cacheKey]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $age = strtotime($row['created_at']) ?: 0;
                    if (time() - $age < $this->cacheTtlSeconds) {
                        $data = json_decode($row['result_json'], true);
                        if (is_array($data)) {
                            return $data;
                        }
                    }
                }
            } catch (Throwable $e) {
                // ignore cache errors
            }
        }

        $system = "You are an assistant that compares a job posting with a user profile and returns a JSON object with: {\"match_percentage\":0-100 integer, \"roi_score\":0-10 integer based on salary, skill match, market demand, location, and experience, \"explanation\":string explaining WHY this job is a good ROI for the user, \"highlights\": [strings]}. Respond with valid JSON only. RETURN ONLY JSON, NO TEXT, NO MARKDOWN. Do not wrap in ```json";

        $userProfile = json_encode([
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'skills' => $user['skills'] ?? '',
            'projects' => $user['projects_summary'] ?? '',
            'experience_years' => $user['experience_years'] ?? null,
            'role' => $user['role'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $jobPayload = json_encode([
            'title' => $job['title'] ?? '',
            'description' => $job['description'] ?? '',
            'skills_required' => $job['skills_required'] ?? '',
            'location' => $job['location'] ?? '',
            'budget' => $job['budget'] ?? null,
            'experience_level' => $job['experience_level'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Job: $jobPayload\n\nUser: $userProfile\n\nReturn only JSON as described. Be concise. RETURN ONLY JSON, NO TEXT, NO MARKDOWN. Do not wrap in ```json"],
        ];

        $resp = $this->callChat($messages, ['temperature' => 0.0, 'max_tokens' => 400]);

        $result = ['match_percentage' => 0, 'explanation' => '', 'highlights' => [], 'raw' => $resp];

        if (isset($resp['error'])) {
            $result['explanation'] = 'AI error: ' . $resp['error'];
        } elseif (isset($resp['choices'][0]['message']['content'])) {
            $content = $resp['choices'][0]['message']['content'];
            $decoded = $this->safeJsonDecode($content);
            if (is_array($decoded)) {
                $result = array_merge($result, $decoded);
            } else {
                // try to extract percentage and explanation heuristically
                if (preg_match('/(\d{1,3})\s*%/', $content, $m)) {
                    $result['match_percentage'] = min(100, max(0, (int)$m[1]));
                }
                // explanation fallback: first 400 chars, but strip tags if possible
                $clean = preg_replace('/```(?:json)?|```/i', '', $content);
                $result['explanation'] = trim(mb_substr($clean, 0, 800));
            }
        }

        // store cache
        try {
            $stmt = $this->pdo->prepare('INSERT INTO ai_results (cache_key, job_id, user_id, result_json, created_at) VALUES (:cache_key, :job_id, :user_id, :result_json, NOW()) ON DUPLICATE KEY UPDATE result_json = VALUES(result_json), created_at = NOW()');
            $stmt->execute([
                'cache_key' => $cacheKey,
                'job_id' => (int)$job['id'],
                'user_id' => (int)$user['id'],
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Throwable $e) {
            // ignore cache write errors
        }

        return $result;
    }

    // Analyze contract text and return structured findings. Caches directly into contracts table.
    public function analyzeContractText(string $contractText, ?int $contractId = null, ?int $analyzedBy = null, bool $forceRefresh = false): array
    {
        if (!$forceRefresh && $contractId) {
            try {
                $stmt = $this->pdo->prepare('SELECT analysis_json FROM contracts WHERE id = :contract_id LIMIT 1');
                $stmt->execute(['contract_id' => $contractId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['analysis_json'])) {
                    $data = json_decode($row['analysis_json'], true);
                    if (is_array($data)) {
                        return $this->normalizeAnalysis($data);
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }

        $system = "You are a legal expert analyzing contracts. Given a contract text, return a JSON object with: 
        risk_score (0-100), 
        red_flags (array of strings), 
        pros (array of strings), 
        cons (array of strings), 
        recommendation (string). 
        Respond with valid JSON only. No markdown, no conversational text. RETURN ONLY JSON, NO TEXT, NO MARKDOWN. Do not wrap in ```json";
        
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Analyze this contract:\n\n" . $contractText]
        ];

        $resp = $this->callChat($messages, ['temperature' => 0.0, 'max_tokens' => 1200]);

        // Check for API errors
        if (isset($resp['error'])) {
            throw new \Exception('OpenRouter API Error: ' . $resp['error']);
        }

        $decoded = null;
        if (isset($resp['choices'][0]['message']['content'])) {
            $content = $resp['choices'][0]['message']['content'];
            $decoded = $this->safeJsonDecode($content);
        }

        if (!is_array($decoded)) {
            $decoded = [
                'risk_score' => 0,
                'recommendation' => 'Unable to parse AI response. Raw: ' . trim(mb_substr($content ?? '', 0, 400))
            ];
        }

        return $this->normalizeAnalysis($decoded);
    }

    public function normalizeAnalysisOutput(array $data): array
    {
        return $this->normalizeAnalysis($data);
    }

    private function normalizeAnalysis(array $data): array
    {
        $normalized = [
            'risk_score' => max(0, min(100, (int)($data['risk_score'] ?? 0))),
            'recommendation' => (string)($data['recommendation'] ?? 'Review carefully.'),
            'pros' => $this->ensureStringArray($data['pros'] ?? []),
            'cons' => $this->ensureStringArray($data['cons'] ?? []),
            'red_flags' => $this->ensureStringArray($data['red_flags'] ?? []),
        ];
        return $normalized;
    }

    private function ensureStringArray($input): array
    {
        if (is_array($input)) {
            return array_values(array_map('strval', array_filter($input)));
        }
        if (is_string($input) && trim($input) !== '') {
            return array_values(array_filter(explode("\n", $input)));
        }
        return [];
    }
}
