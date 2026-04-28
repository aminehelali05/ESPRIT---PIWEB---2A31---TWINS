<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Resource.php');

class ResourceController
{
    private $db;

    public function __construct()
    {
        $this->db = config::getConnexion();
    }

    // ── VALIDATION ────────────────────────────────────────────────────────

    // ── VALIDATION ────────────────────────────────────────────────────────

    public function validate(array $data, $id = null)
    {
        $errors = [];
        $title = $data['title'] ?? '';
        $desc = $data['description'] ?? '';

        if (empty($title) || strlen($title) < 3) {
            $errors[] = "Resource title must be at least 3 characters.";
        }
        if (empty($desc) || strlen($desc) < 10) {
            $errors[] = "Resource description must be at least 10 characters.";
        }

        // Letter requirement
        if (!preg_match('/[a-zA-Z]/', $title)) {
            $errors[] = "Title must contain at least some letters.";
        }
        if (!preg_match('/[a-zA-Z]/', $desc)) {
            $errors[] = "Description must contain at least some letters.";
        }

        // Gibberish Detection
        if ($this->isGibberish($title) || $this->isGibberish($desc)) {
            $errors[] = "Input contains invalid character patterns (gibberish).";
        }

        // Profanity Filter
        if ($this->hasProfanity($title) || $this->hasProfanity($desc)) {
            $errors[] = "Input contains inappropriate language.";
        }

        // Duplicate Title Check
        if ($this->isDuplicateTitle($title, $id)) {
            $errors[] = "A resource with this title already exists.";
        }

        return $errors;
    }

    private function isGibberish($text)
    {
        if (preg_match('/(.)\1{4,}/', $text)) return true;
        if (preg_match('/[^aeiouyAEIOUY\s\d\W]{6,}/', $text)) return true;
        return false;
    }

    private function hasProfanity($text)
    {
        $blacklist = ['badword1', 'badword2', 'merde', 'putain', 'salope', 'connard'];
        $text = strtolower($text);
        foreach ($blacklist as $word) {
            if (strpos($text, $word) !== false) return true;
        }
        return false;
    }

    public function isDuplicateTitle($title, $id = null)
    {
        $sql = "SELECT COUNT(*) FROM resources WHERE title = :title";
        if ($id) $sql .= " AND id != :id";
        try {
            $q = $this->db->prepare($sql);
            $params = ['title' => $title];
            if ($id) $params['id'] = $id;
            $q->execute($params);
            return $q->fetchColumn() > 0;
        } catch (Exception $e) { return false; }
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function addResource(ResourceItem $resource)
    {
        $sql = "INSERT INTO resources 
                    (user_id, event_id, type, title, description, status) 
                VALUES 
                    (:user_id, :event_id, :type, :title, :description, :status)";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'user_id'     => $resource->getUserId(),
                'event_id'    => $resource->getEventId(),
                'type'        => $resource->getType(),
                'title'       => $resource->getTitle(),
                'description' => $resource->getDescription(),
                'status'      => $resource->getStatus() ?? 'active'
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log('ResourceController::addResource — ' . $e->getMessage());
            return false;
        }
    }

    public function updateResource(ResourceItem $resource, $id)
    {
        $sql = "UPDATE resources SET 
                    event_id    = :event_id,
                    type        = :type,
                    title       = :title,
                    description = :description,
                    status      = :status
                WHERE id = :id";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'event_id'    => $resource->getEventId(),
                'type'        => $resource->getType(),
                'title'       => $resource->getTitle(),
                'description' => $resource->getDescription(),
                'status'      => $resource->getStatus(),
                'id'          => $id
            ]);
            return true;
        } catch (Exception $e) {
            error_log('ResourceController::updateResource — ' . $e->getMessage());
            return false;
        }
    }

    public function deleteResource($id)
    {
        try {
            $q = $this->db->prepare("DELETE FROM resources WHERE id = :id");
            return $q->execute(['id' => $id]);
        } catch (Exception $e) {
            error_log('ResourceController::deleteResource — ' . $e->getMessage());
            return false;
        }
    }

    // ── QUERIES ───────────────────────────────────────────────────────────

    public function listResources($type = null, $event_id = null)
    {
        $sql = "SELECT * FROM resources WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }
        if ($event_id) {
            $sql .= " AND event_id = :event_id";
            $params['event_id'] = $event_id;
        }

        $sql .= " ORDER BY created_at DESC";

        try {
            $q = $this->db->prepare($sql);
            $q->execute($params);
            return $q->fetchAll();
        } catch (Exception $e) {
            error_log('ResourceController::listResources — ' . $e->getMessage());
            return [];
        }
    }

    public function getResourcesByEvent($event_id)
    {
        return $this->listResources(null, $event_id);
    }

    public function getResourceById($id)
    {
        try {
            $q = $this->db->prepare("SELECT * FROM resources WHERE id = :id");
            $q->execute(['id' => $id]);
            $row = $q->fetch();
            if ($row) {
                return $this->hydrateResource($row);
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function generatePlanningByAI($description)
    {
        $apiKey = "AIzaSyAXvL-IXQaSXcRZaY4vsWqfdnJ7IAwvY0A";
        $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

        $prompt = "Tu es un assistant de gestion de projet freelance. L'utilisateur te décrit son projet en quelques mots. " .
                  "Génère un planning structuré en JSON (un tableau d'objets) avec les champs suivants pour chaque étape : titre, description_courte, durée_estimée_en_jours, ordre. " .
                  "Retourne uniquement le JSON, sans texte autour. Projet : " . $description;

        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ];

        $payload = json_encode($data);

        // Use cURL
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError) {
            return ['error' => 'Connection failed: ' . $curlError];
        }

        if ($httpCode !== 200) {
            $errJson = json_decode($response, true);
            $errMsg = $errJson['error']['message'] ?? "HTTP $httpCode";
            return ['error' => $errMsg];
        }

        $json = json_decode($response, true);
        $textResponse = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Clean markdown
        $textResponse = preg_replace('/```json|```/', '', $textResponse);
        $textResponse = trim($textResponse);

        $planning = json_decode($textResponse, true);
        if (!$planning) {
            return ['error' => 'Invalid AI response format.'];
        }

        return $planning;
    }

    private function hydrateResource(array $row): ResourceItem
    {
        $resource = new ResourceItem(
            $row['user_id'],
            $row['type'],
            $row['title'],
            $row['description'],
            $row['status'],
            $row['event_id']
        );
        $resource->setId($row['id']);
        $resource->setCreatedAt($row['created_at']);
        $resource->setUpdatedAt($row['updated_at']);
        return $resource;
    }
}
