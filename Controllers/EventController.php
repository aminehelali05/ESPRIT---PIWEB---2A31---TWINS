<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Event.php');

class EventController
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

        if (empty($title) || strlen($title) < 5) {
            $errors[] = "Title must be at least 5 characters.";
        }
        if (empty($desc) || strlen($desc) < 20) {
            $errors[] = "Description must be at least 20 characters.";
        }

        // Date validation
        if (empty($data['start_date'])) {
            $errors[] = "Start date is required.";
        } else if (strtotime($data['start_date']) < time()) {
            $errors[] = "Start date cannot be in the past.";
        }

        if (empty($data['end_date'])) {
            $errors[] = "End date is required.";
        } else if (!empty($data['start_date']) && strtotime($data['end_date']) <= strtotime($data['start_date'])) {
            $errors[] = "End date must be after the start date.";
        }

        if (empty($data['location'])) {
            $errors[] = "Location is required.";
        }

        // Letter requirement (No pure numeric entries)
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
            $errors[] = "An event with this title already exists.";
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
        $sql = "SELECT COUNT(*) FROM events WHERE title = :title";
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

    public function addEvent(Event $event)
    {
        $sql = "INSERT INTO events 
                    (user_id, title, description, start_date, end_date, location, category, status, capacite_max, nb_inscrits, statut_inscription) 
                VALUES 
                    (:user_id, :title, :description, :start_date, :end_date, :location, :category, :status, :capacite_max, :nb_inscrits, :statut_inscription)";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'user_id'            => $event->getUserId(),
                'title'              => $event->getTitle(),
                'description'        => $event->getDescription(),
                'start_date'         => $event->getStartDate(),
                'end_date'           => $event->getEndDate(),
                'location'           => $event->getLocation(),
                'category'           => $event->getCategory(),
                'status'             => $event->getStatus() ?? 'EN_ATTENTE',
                'capacite_max'       => $event->getCapaciteMax() ?? 0,
                'nb_inscrits'        => 0,
                'statut_inscription' => 'OUVERT'
            ]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log('EventController::addEvent — ' . $e->getMessage());
            return false;
        }
    }

    public function updateEvent(Event $event, $id)
    {
        $sql = "UPDATE events SET 
                    title       = :title,
                    description = :description,
                    start_date  = :start_date,
                    end_date    = :end_date,
                    location    = :location,
                    category    = :category,
                    status      = :status,
                    capacite_max = :capacite_max
                WHERE id = :id";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'title'        => $event->getTitle(),
                'description'  => $event->getDescription(),
                'start_date'   => $event->getStartDate(),
                'end_date'     => $event->getEndDate(),
                'location'     => $event->getLocation(),
                'category'     => $event->getCategory(),
                'status'       => $event->getStatus(),
                'capacite_max'  => $event->getCapaciteMax(),
                'id'           => $id
            ]);
            return true;
        } catch (Exception $e) {
            error_log('EventController::updateEvent — ' . $e->getMessage());
            return false;
        }
    }

    public function deleteEvent($id)
    {
        try {
            $q = $this->db->prepare("DELETE FROM events WHERE id = :id");
            return $q->execute(['id' => $id]);
        } catch (Exception $e) {
            error_log('EventController::deleteEvent — ' . $e->getMessage());
            return false;
        }
    }

    // ── QUERIES ───────────────────────────────────────────────────────────

    public function listEvents($search = '', $filters = [], $limit = null, $offset = null)
    {
        $sql = "SELECT e.*, u.first_name, u.last_name 
                FROM events e 
                JOIN users u ON e.user_id = u.id";
        
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = "e.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $conditions[] = "e.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY e.start_date DESC";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        try {
            $q = $this->db->prepare($sql);
            $q->execute($params);
            return $q->fetchAll();
        } catch (Exception $e) {
            error_log('EventController::listEvents — ' . $e->getMessage());
            return [];
        }
    }

    public function countEvents($search = '', $filters = [])
    {
        $sql = "SELECT COUNT(*) FROM events e";
        
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(e.title LIKE :search OR e.description LIKE :search OR e.location LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = "e.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $conditions[] = "e.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        try {
            $q = $this->db->prepare($sql);
            $q->execute($params);
            return (int)$q->fetchColumn();
        } catch (Exception $e) {
            error_log('EventController::countEvents — ' . $e->getMessage());
            return 0;
        }
    }

    public function getEventById($id)
    {
        try {
            $q = $this->db->prepare("SELECT * FROM events WHERE id = :id");
            $q->execute(['id' => $id]);
            $row = $q->fetch();
            if ($row) {
                return $this->hydrateEvent($row);
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function updateStatus($id, $status)
    {
        try {
            $q = $this->db->prepare("UPDATE events SET status = :status WHERE id = :id");
            $res = $q->execute(['status' => $status, 'id' => $id]);
            
            if ($status === 'ACCEPTE') {
                $this->generateAndStoreQrCode($id);
            }
            
            return $res;
        } catch (Exception $e) {
            error_log('EventController::updateStatus — ' . $e->getMessage());
            return false;
        }
    }

    public function registerToEvent($user_id, $event_id)
    {
        try {
            // Check if already registered
            $check = $this->db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id = :uid AND event_id = :eid");
            $check->execute(['uid' => $user_id, 'eid' => $event_id]);
            if ($check->fetchColumn() > 0) return ['success' => false, 'message' => 'Already registered.'];

            // Get event capacity
            $ev = $this->getEventById($event_id);
            if (!$ev) return ['success' => false, 'message' => 'Event not found.'];
            if ($ev->getStatutInscription() === 'COMPLET') return ['success' => false, 'message' => 'Event is full.'];

            $this->db->beginTransaction();
            
            // Increment nb_inscrits
            $inc = $this->db->prepare("UPDATE events SET nb_inscrits = nb_inscrits + 1 WHERE id = :id");
            $inc->execute(['id' => $event_id]);

            // Insert registration
            $reg = $this->db->prepare("INSERT INTO registrations (user_id, event_id) VALUES (:uid, :eid)");
            $reg->execute(['uid' => $user_id, 'eid' => $event_id]);

            // Check if now full
            $ev_updated = $this->getEventById($event_id);
            if ($ev_updated->getNbInscrits() >= $ev_updated->getCapaciteMax() && $ev_updated->getCapaciteMax() > 0) {
                $full = $this->db->prepare("UPDATE events SET statut_inscription = 'COMPLET' WHERE id = :id");
                $full->execute(['id' => $event_id]);
            }

            $this->db->commit();
            return ['success' => true];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('EventController::registerToEvent — ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggleFavorite($user_id, $event_id)
    {
        try {
            $check = $this->db->prepare("SELECT id FROM favoris WHERE user_id = :uid AND evenement_id = :eid");
            $check->execute(['uid' => $user_id, 'eid' => $event_id]);
            $fav = $check->fetch();
            
            if ($fav) {
                $q = $this->db->prepare("DELETE FROM favoris WHERE id = :id");
                $q->execute(['id' => $fav['id']]);
                return ['status' => 'removed'];
            } else {
                $q = $this->db->prepare("INSERT INTO favoris (user_id, evenement_id) VALUES (:uid, :eid)");
                $q->execute(['uid' => $user_id, 'eid' => $event_id]);
                return ['status' => 'added'];
            }
        } catch (Exception $e) { return ['success' => false, 'message' => $e->getMessage()]; }
    }

    public function listFavorites($user_id)
    {
        try {
            $sql = "SELECT e.*, u.first_name, u.last_name 
                    FROM favoris f 
                    JOIN events e ON f.evenement_id = e.id 
                    JOIN users u ON e.user_id = u.id 
                    WHERE f.user_id = :uid";
            $q = $this->db->prepare($sql);
            $q->execute(['uid' => $user_id]);
            return $q->fetchAll();
        } catch (Exception $e) { return []; }
    }

    public function isFavorite($user_id, $event_id)
    {
        try {
            $q = $this->db->prepare("SELECT id FROM favoris WHERE user_id = :uid AND evenement_id = :eid");
            $q->execute(['uid' => $user_id, 'eid' => $event_id]);
            return (bool)$q->fetch();
        } catch (Exception $e) { return false; }
    }

    public function generateAndStoreQrCode($id)
    {
        // Use QRServer API to get a QR code and store it as Base64
        $url = "https://diversity.is/Views/FrontOffice/event_details.php?id=" . $id;
        $apiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($url);
        
        try {
            $qrData = file_get_contents($apiUrl);
            if ($qrData) {
                $base64 = 'data:image/png;base64,' . base64_encode($qrData);
                $q = $this->db->prepare("UPDATE events SET qr_code = :qr WHERE id = :id");
                return $q->execute(['qr' => $base64, 'id' => $id]);
            }
        } catch (Exception $e) {}
        return false;
    }

    private function hydrateEvent(array $row): Event
    {
        $event = new Event(
            $row['user_id'],
            $row['title'],
            $row['description'],
            $row['start_date'],
            $row['end_date'],
            $row['location'],
            $row['category'],
            $row['status'],
            $row['capacite_max'] ?? 0,
            $row['nb_inscrits'] ?? 0,
            $row['statut_inscription'] ?? 'OUVERT',
            $row['qr_code'] ?? null
        );
        $event->setId($row['id']);
        $event->setCreatedAt($row['created_at']);
        $event->setUpdatedAt($row['updated_at']);
        return $event;
    }
}
