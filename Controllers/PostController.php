<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/Post.php');

class PostController
{
    public function addPost(Post $post)
    {
        $sql = "INSERT INTO post (user_id, contenu, media_url, created_at)
                VALUES (:user_id, :contenu, :media_url, :created_at)";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'user_id' => $post->getUserId(),
                'contenu' => $post->getContenu(),
                'media_url' => $post->getMediaUrl(),
                'created_at' => $post->getCreatedAt() ?: date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function updatePost(Post $post, $id)
    {
        $sql = "UPDATE post SET contenu=:contenu, media_url=:media_url WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'contenu' => $post->getContenu(),
                'media_url' => $post->getMediaUrl(),
                'id' => $id
            ]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function deletePost($id)
    {
        $sql = "DELETE FROM post WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function listPosts()
    {
        $sql = "SELECT p.*, u.first_name, u.last_name 
                FROM post p 
                JOIN user u ON p.user_id = u.id 
                ORDER BY p.created_at DESC";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            return $query->fetchAll();
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return [];
        }
    }

    public function getPostById($id)
    {
        $sql = "SELECT * FROM post WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();

            if ($row) {
                $post = new Post(
                    $row['user_id'],
                    $row['contenu'],
                    $row['media_url'],
                    $row['created_at'],
                    $row['updated_at']
                );
                $post->setId($row['id']);
                return $post;
            }
            return null;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }
}
