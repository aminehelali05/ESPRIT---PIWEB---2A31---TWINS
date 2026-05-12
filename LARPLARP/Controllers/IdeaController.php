<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Idea.php');

class IdeaController
{
    private $db;

    public function __construct()
    {
        $this->db = config::getConnexion();
    }

    public function addIdea(Idea $i)
    {
        $sql = "INSERT INTO ideas (brainstorming_id, user_id, content) VALUES (:bid, :uid, :content)";
        try {
            $q = $this->db->prepare($sql);
            return $q->execute([
                'bid'     => $i->getBrainstormingId(),
                'uid'     => $i->getUserId(),
                'content' => $i->getContent()
            ]);
        } catch (Exception $e) {
            error_log('IdeaController::addIdea — ' . $e->getMessage());
            return false;
        }
    }

    public function listIdeasByBrainstorming($bid)
    {
        $sql = "SELECT i.*, u.first_name, u.last_name, u.avatar_url 
                FROM ideas i 
                JOIN users u ON i.user_id = u.id 
                WHERE i.brainstorming_id = :bid 
                ORDER BY i.created_at ASC";
        try {
            $q = $this->db->prepare($sql);
            $q->execute(['bid' => $bid]);
            return $q->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function deleteIdea($id)
    {
        try {
            $q = $this->db->prepare("DELETE FROM ideas WHERE id = :id");
            return $q->execute(['id' => $id]);
        } catch (Exception $e) {
            return false;
        }
    }
}
