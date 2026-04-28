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
        $sql = "INSERT INTO ideas (brainstorming_id, user_id, title, content, idea_type, status) VALUES (:bid, :uid, :title, :content, :type, :status)";
        try {
            $q = $this->db->prepare($sql);
            return $q->execute([
                'bid'     => $i->getBrainstormingId(),
                'uid'     => $i->getUserId(),
                'title'   => $i->getTitle() ?? 'Untitled Idea',
                'content' => $i->getContent(),
                'type'    => $i->getIdeaType() ?? 'Feature',
                'status'  => $i->getStatus() ?? 'PROPOSED'
            ]);
        } catch (Exception $e) {
            error_log('IdeaController::addIdea — ' . $e->getMessage());
            return false;
        }
    }

    public function listIdeasByBrainstorming($bid, $currentUserId = null)
    {
        $sql = "SELECT i.*, u.first_name, u.last_name, u.avatar_url, u.karma,
                       MAX(CASE WHEN v.user_id = :uid THEN v.vote_type ELSE 0 END) as user_vote,
                       SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END) as upvotes,
                       SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END) as downvotes
                FROM ideas i 
                JOIN users u ON i.user_id = u.id 
                LEFT JOIN idea_votes v ON i.id = v.idea_id
                WHERE i.brainstorming_id = :bid 
                GROUP BY i.id
                ORDER BY i.score DESC, i.created_at ASC";
        try {
            $q = $this->db->prepare($sql);
            $q->execute(['bid' => $bid, 'uid' => $currentUserId ?? 0]);
            return $q->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getIdeasStatsByBrainstorming($bid)
    {
        $sql = "SELECT i.id, i.score, 
                       SUM(CASE WHEN v.vote_type = 1 THEN 1 ELSE 0 END) as upvotes,
                       SUM(CASE WHEN v.vote_type = -1 THEN 1 ELSE 0 END) as downvotes
                FROM ideas i
                LEFT JOIN idea_votes v ON i.id = v.idea_id
                WHERE i.brainstorming_id = ?
                GROUP BY i.id";
        try {
            $q = $this->db->prepare($sql);
            $q->execute([$bid]);
            return $q->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    public function getScoreExtremes()
    {
        $sql = "SELECT MIN(score) as min_score, MAX(score) as max_score FROM ideas";
        try {
            $q = $this->db->query($sql);
            $res = $q->fetch(PDO::FETCH_ASSOC);
            return $res ?: ['min_score' => 0, 'max_score' => 0];
        } catch (Exception $e) {
            return ['min_score' => 0, 'max_score' => 0];
        }
    }

    public function listAllIdeas($search = '', $sort = 'date_desc', $minScore = '')
    {
        $sql = "SELECT i.*, u.first_name, u.last_name, b.title as brainstorming_title
                FROM ideas i
                JOIN users u ON i.user_id = u.id
                JOIN brainstormings b ON i.brainstorming_id = b.id
                WHERE (i.title LIKE :search OR i.content LIKE :search)";
        
        $params = ['search' => "%$search%"];

        if ($minScore !== '') {
            $sql .= " AND i.score >= :minScore";
            $params['minScore'] = (int)$minScore;
        }

        switch ($sort) {
            case 'score_desc':
                $sql .= " ORDER BY i.score DESC, i.created_at DESC";
                break;
            case 'score_asc':
                $sql .= " ORDER BY i.score ASC, i.created_at DESC";
                break;
            case 'date_asc':
                $sql .= " ORDER BY i.created_at ASC";
                break;
            case 'date_desc':
            default:
                $sql .= " ORDER BY i.created_at DESC";
                break;
        }

        try {
            $q = $this->db->prepare($sql);
            $q->execute($params);
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

    public function updateIdea(Idea $i, $id)
    {
        $sql = "UPDATE ideas SET title = :title, content = :content, idea_type = :type, status = :status WHERE id = :id";
        try {
            $q = $this->db->prepare($sql);
            return $q->execute([
                'title'   => $i->getTitle(),
                'content' => $i->getContent(),
                'type'    => $i->getIdeaType(),
                'status'  => $i->getStatus(),
                'id'      => $id
            ]);
        } catch (Exception $e) {
            error_log('IdeaController::updateIdea — ' . $e->getMessage());
            return false;
        }
    }

    public function getIdeaById($id)
    {
        try {
            $q = $this->db->prepare("SELECT * FROM ideas WHERE id = ?");
            $q->execute([$id]);
            $res = $q->fetch();
            if ($res) {
                $idea = new Idea($res['brainstorming_id'], $res['user_id'], $res['title'], $res['content'], $res['status'], $res['idea_type']);
                $idea->setId($res['id']);
                return $idea;
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function toggleVote($idea_id, $user_id, $vote_type)
    {
        try {
            $stmt = $this->db->prepare("SELECT vote_type FROM idea_votes WHERE idea_id = ? AND user_id = ?");
            $stmt->execute([$idea_id, $user_id]);
            $existing = $stmt->fetchColumn();

            if ($existing !== false) {
                if ($existing == $vote_type) {
                    $del = $this->db->prepare("DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?");
                    $del->execute([$idea_id, $user_id]);
                } else {
                    $upd = $this->db->prepare("UPDATE idea_votes SET vote_type = ? WHERE idea_id = ? AND user_id = ?");
                    $upd->execute([$vote_type, $idea_id, $user_id]);
                }
            } else {
                $ins = $this->db->prepare("INSERT INTO idea_votes (idea_id, user_id, vote_type) VALUES (?, ?, ?)");
                $ins->execute([$idea_id, $user_id, $vote_type]);
            }

            // Recalculate Idea Score
            $this->db->prepare("UPDATE ideas SET score = COALESCE((SELECT SUM(vote_type) FROM idea_votes WHERE idea_id = ?), 0) WHERE id = ?")->execute([$idea_id, $idea_id]);
            
            // Recalculate Owner Karma
            $ownerId = $this->db->prepare("SELECT user_id FROM ideas WHERE id = ?");
            $ownerId->execute([$idea_id]);
            $ownerId = $ownerId->fetchColumn();
            
            if ($ownerId) {
                $this->db->prepare("UPDATE users SET karma = COALESCE((SELECT SUM(vote_type) FROM idea_votes v JOIN ideas i ON v.idea_id = i.id WHERE i.user_id = ?), 0) WHERE id = ?")->execute([$ownerId, $ownerId]);
            }

            $statStmt = $this->db->prepare("SELECT score, 
                        (SELECT COUNT(*) FROM idea_votes WHERE idea_id = ? AND vote_type = 1) as upvotes,
                        (SELECT COUNT(*) FROM idea_votes WHERE idea_id = ? AND vote_type = -1) as downvotes
                        FROM ideas WHERE id = ?");
            $statStmt->execute([$idea_id, $idea_id, $idea_id]);
            return $statStmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("IdeaController::toggleVote error: " . $e->getMessage());
            return false;
        }
    }
}
