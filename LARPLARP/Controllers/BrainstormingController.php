<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Brainstorming.php');

class BrainstormingController
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

        // Length checks
        if (empty($title) || strlen($title) < 10) {
            $errors[] = "Title must be at least 10 characters.";
        }
        if (empty($desc) || strlen($desc) < 20) {
            $errors[] = "Description must be at least 20 characters.";
        }

        // Letter requirement (No pure numeric entries)
        if (!preg_match('/[a-zA-Z]/', $title)) {
            $errors[] = "Title must contain at least some letters (cannot be only numbers).";
        }
        if (!preg_match('/[a-zA-Z]/', $desc)) {
            $errors[] = "Description must contain at least some letters.";
        }

        // Gibberish Detection
        if ($this->isGibberish($title)) {
            $errors[] = "Title seems to contain invalid character patterns (gibberish).";
        }
        if ($this->isGibberish($desc)) {
            $errors[] = "Description seems to contain invalid character patterns.";
        }

        // Profanity Filter
        if ($this->hasProfanity($title) || $this->hasProfanity($desc)) {
            $errors[] = "Input contains inappropriate language.";
        }

        // Duplicate Title Check
        if ($this->isDuplicateTitle($title, $id)) {
            $errors[] = "A brainstorming session with this title already exists.";
        }

        return $errors;
    }

    private function isGibberish($text)
    {
        // 1. Check for 5+ repeated characters (e.g., aaaaa)
        if (preg_match('/(.)\1{4,}/', $text)) return true;

        // 2. Check for 6+ consecutive consonants
        if (preg_match('/[^aeiouyAEIOUY\s\d\W]{6,}/', $text)) return true;

        return false;
    }

    private function hasProfanity($text)
    {
        $blacklist = ['badword1', 'badword2', 'merde', 'putain', 'salope', 'connard']; // Basic example list
        $text = strtolower($text);
        foreach ($blacklist as $word) {
            if (strpos($text, $word) !== false) return true;
        }
        return false;
    }

    public function isDuplicateTitle($title, $id = null)
    {
        $sql = "SELECT COUNT(*) FROM brainstormings WHERE title = :title";
        if ($id) $sql .= " AND id != :id";
        
        try {
            $q = $this->db->prepare($sql);
            $params = ['title' => $title];
            if ($id) $params['id'] = $id;
            $q->execute($params);
            return $q->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function addBrainstorming(Brainstorming $b)
    {
        $sql = "INSERT INTO brainstormings 
                    (user_id, title, description, status) 
                VALUES 
                    (:user_id, :title, :description, :status)";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'user_id'     => $b->getUserId(),
                'title'       => $b->getTitle(),
                'description' => $b->getDescription(),
                'status'      => $b->getStatus() ?? 'EN_ATTENTE'
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log('BrainstormingController::addBrainstorming — ' . $e->getMessage());
            return false;
        }
    }

    public function updateBrainstorming(Brainstorming $b, $id)
    {
        $sql = "UPDATE brainstormings SET 
                    title       = :title,
                    description = :description,
                    status      = :status
                WHERE id = :id";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'title'       => $b->getTitle(),
                'description' => $b->getDescription(),
                'status'      => $b->getStatus(),
                'id'          => $id
            ]);
            return true;
        } catch (Exception $e) {
            error_log('BrainstormingController::updateBrainstorming — ' . $e->getMessage());
            return false;
        }
    }

    public function deleteBrainstorming($id)
    {
        try {
            $q = $this->db->prepare("DELETE FROM brainstormings WHERE id = :id");
            return $q->execute(['id' => $id]);
        } catch (Exception $e) {
            error_log('BrainstormingController::deleteBrainstorming — ' . $e->getMessage());
            return false;
        }
    }

    // ── QUERIES ───────────────────────────────────────────────────────────

    public function listBrainstormings($search = '', $filters = [])
    {
        $sql = "SELECT b.*, u.first_name, u.last_name 
                FROM brainstormings b 
                JOIN users u ON b.user_id = u.id";
        
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(b.title LIKE :search OR b.description LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = "b.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY b.created_at DESC";

        try {
            $q = $this->db->prepare($sql);
            $q->execute($params);
            return $q->fetchAll();
        } catch (Exception $e) {
            error_log('BrainstormingController::listBrainstormings — ' . $e->getMessage());
            return [];
        }
    }

    public function getBrainstormingById($id)
    {
        try {
            $q = $this->db->prepare("SELECT * FROM brainstormings WHERE id = :id");
            $q->execute(['id' => $id]);
            $row = $q->fetch();
            if ($row) {
                return $this->hydrate($row);
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateStatus($id, $status)
    {
        try {
            $q = $this->db->prepare("UPDATE brainstormings SET status = :status WHERE id = :id");
            return $q->execute(['status' => $status, 'id' => $id]);
        } catch (Exception $e) {
            error_log('BrainstormingController::updateStatus — ' . $e->getMessage());
            return false;
        }
    }

    private function hydrate(array $row): Brainstorming
    {
        $b = new Brainstorming(
            $row['user_id'],
            $row['title'],
            $row['description'],
            $row['status']
        );
        $b->setId($row['id']);
        $b->setCreatedAt($row['created_at']);
        $b->setUpdatedAt($row['updated_at']);
        return $b;
    }
}
