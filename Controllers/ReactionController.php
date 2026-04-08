<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Reaction.php');

class ReactionController
{
    public function toggleReaction(Reaction $reaction)
    {
        $db = config::getConnexion();
        
        // Check if reaction already exists
        $sqlCheck = "SELECT id, type FROM reaction WHERE user_id = :user_id AND post_id = :post_id";
        $queryCheck = $db->prepare($sqlCheck);
        $queryCheck->execute([
            'user_id' => $reaction->getUserId(),
            'post_id' => $reaction->getPostId()
        ]);
        $existing = $queryCheck->fetch();

        if ($existing) {
            if ($existing['type'] === $reaction->getType()) {
                // Remove reaction if same type (Toggle off)
                $sqlDelete = "DELETE FROM reaction WHERE id = :id";
                $queryDelete = $db->prepare($sqlDelete);
                $queryDelete->execute(['id' => $existing['id']]);
            } else {
                // Update type if different
                $sqlUpdate = "UPDATE reaction SET type = :type WHERE id = :id";
                $queryUpdate = $db->prepare($sqlUpdate);
                $queryUpdate->execute([
                    'type' => $reaction->getType(),
                    'id' => $existing['id']
                ]);
            }
        } else {
            // Add new reaction
            $sqlAdd = "INSERT INTO reaction (user_id, post_id, type, created_at)
                       VALUES (:user_id, :post_id, :type, :created_at)";
            $queryAdd = $db->prepare($sqlAdd);
            $queryAdd->execute([
                'user_id' => $reaction->getUserId(),
                'post_id' => $reaction->getPostId(),
                'type' => $reaction->getType(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function getReactionsByPost($post_id)
    {
        $sql = "SELECT type, COUNT(*) as count 
                FROM reaction 
                WHERE post_id = :post_id 
                GROUP BY type";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['post_id' => $post_id]);
            return $query->fetchAll();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function listReactionsWithUsers($post_id)
    {
        $sql = "SELECT r.*, u.first_name, u.last_name 
                FROM reaction r 
                JOIN user u ON r.user_id = u.id 
                WHERE r.post_id = :post_id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['post_id' => $post_id]);
            return $query->fetchAll();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }
}
