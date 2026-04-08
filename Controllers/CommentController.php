<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Comment.php');

class CommentController
{
    public function addComment(Comment $comment)
    {
        $sql = "INSERT INTO comment (post_id, user_id, contenu, created_at)
                VALUES (:post_id, :user_id, :contenu, :created_at)";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'post_id' => $comment->getPostId(),
                'user_id' => $comment->getUserId(),
                'contenu' => $comment->getContenu(),
                'created_at' => $comment->getCreatedAt() ?: date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function updateComment($contenu, $id)
    {
        $sql = "UPDATE comment SET contenu=:contenu WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['contenu' => $contenu, 'id' => $id]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function deleteComment($id)
    {
        $sql = "DELETE FROM comment WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function listCommentsByPost($post_id)
    {
        $sql = "SELECT c.*, u.first_name, u.last_name 
                FROM comment c 
                JOIN user u ON c.user_id = u.id 
                WHERE c.post_id = :post_id 
                ORDER BY c.created_at ASC";
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
