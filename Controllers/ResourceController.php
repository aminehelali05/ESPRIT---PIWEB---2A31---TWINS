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

    public function validate(array $data)
    {
        $errors = [];
        if (empty($data['title']) || strlen($data['title']) < 3) {
            $errors[] = "Resource title must be at least 3 characters.";
        }
        if (empty($data['description']) || strlen($data['description']) < 10) {
            $errors[] = "Resource description must be at least 10 characters.";
        }
        return $errors;
    }

    // ── CRUD ──────────────────────────────────────────────────────────────

    public function addResource(ResourceItem $resource)
    {
        $sql = "INSERT INTO resources 
                    (type, title, description, status) 
                VALUES 
                    (:type, :title, :description, :status)";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
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
                    type        = :type,
                    title       = :title,
                    description = :description,
                    status      = :status
                WHERE id = :id";
        
        try {
            $q = $this->db->prepare($sql);
            $q->execute([
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

    public function listResources($type = null)
    {
        $sql = "SELECT * FROM resources";
        $params = [];

        if ($type) {
            $sql .= " WHERE type = :type";
            $params['type'] = $type;
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

    private function hydrateResource(array $row): ResourceItem
    {
        $resource = new ResourceItem(
            $row['type'],
            $row['title'],
            $row['description'],
            $row['status']
        );
        $resource->setId($row['id']);
        $resource->setCreatedAt($row['created_at']);
        $resource->setUpdatedAt($row['updated_at']);
        return $resource;
    }
}
