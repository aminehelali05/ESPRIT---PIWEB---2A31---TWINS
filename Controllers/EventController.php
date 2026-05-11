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

    public function validate(array $data)
    {
        $errors = [];
        if (empty($data['title']) || strlen($data['title']) < 5) {
            $errors[] = "Title must be at least 5 characters.";
        }
        if (empty($data['event_date'])) {
            $errors[] = "Event date is required.";
        } else {
            if (strtotime($data['event_date']) < time()) {
                $errors[] = "Event date cannot be in the past.";
            }
        }
        if (empty($data['location'])) {
            $errors[] = "Location is required.";
        }
        if (empty($data['description']) || strlen($data['description']) < 20) {
            $errors[] = "Description must be at least 20 characters.";
        }
        return $errors;
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function addEvent(Event $event)
    {
        $sql = "INSERT INTO events 
                    (user_id, title, description, event_date, location, category, status) 
                VALUES 
                    (:user_id, :title, :description, :event_date, :location, :category, :status)";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'user_id'     => $event->getUserId(),
                'title'       => $event->getTitle(),
                'description' => $event->getDescription(),
                'event_date'  => $event->getEventDate(),
                'location'    => $event->getLocation(),
                'category'    => $event->getCategory(),
                'status'      => $event->getStatus() ?? 'EN_ATTENTE'
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
                    event_date  = :event_date,
                    location    = :location,
                    category    = :category,
                    status      = :status
                WHERE id = :id";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
                'title'       => $event->getTitle(),
                'description' => $event->getDescription(),
                'event_date'  => $event->getEventDate(),
                'location'    => $event->getLocation(),
                'category'    => $event->getCategory(),
                'status'      => $event->getStatus(),
                'id'          => $id
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

    public function listEvents($search = '', $filters = [])
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

        $sql .= " ORDER BY e.event_date DESC";

        try {
            $q = $this->db->prepare($sql);
            $q->execute($params);
            return $q->fetchAll();
        } catch (Exception $e) {
            error_log('EventController::listEvents — ' . $e->getMessage());
            return [];
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
            return $q->execute(['status' => $status, 'id' => $id]);
        } catch (Exception $e) {
            error_log('EventController::updateStatus — ' . $e->getMessage());
            return false;
        }
    }

    private function hydrateEvent(array $row): Event
    {
        $event = new Event(
            $row['user_id'],
            $row['title'],
            $row['description'],
            $row['event_date'],
            $row['location'],
            $row['category'],
            $row['status']
        );
        $event->setId($row['id']);
        $event->setCreatedAt($row['created_at']);
        $event->setUpdatedAt($row['updated_at']);
        return $event;
    }
}
