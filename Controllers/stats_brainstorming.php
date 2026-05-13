<?php
include_once(__DIR__ . '/../Core/config.php');

class BrainstormingStats
{
    private $db;
    private $apiKey = 'AIzaSyAHF2IlIa99kZKu1SA2V_q6oz4BGcTAuHY';

    public function __construct()
    {
        $this->db = config::getConnexion();
    }

    /**
     * Récupère les statistiques globales d'une session
     */
    public function getStats($brainstormingId)
    {
        $stats = [];

        // 1. Distribution par type (Catégorie dans le dashboard)
        $q = $this->db->prepare("SELECT type as category, COUNT(*) as count FROM ideas WHERE brainstorming_id = ? GROUP BY type");
        $q->execute([$brainstormingId]);
        $stats['categories'] = $q->fetchAll();

        // 2. Tendance des votes par jour
        $q = $this->db->prepare("SELECT DATE(created_at) as date, SUM(CASE WHEN vote_type = 1 THEN 1 ELSE 0 END) as upvotes, SUM(CASE WHEN vote_type = -1 THEN 1 ELSE 0 END) as downvotes FROM idea_votes WHERE idea_id IN (SELECT id FROM ideas WHERE brainstorming_id = ?) GROUP BY DATE(created_at) ORDER BY date ASC");
        $q->execute([$brainstormingId]);
        $stats['vote_trends'] = $q->fetchAll();

        // 3. Top 3 idées
        $q = $this->db->prepare("SELECT i.title, i.score, COUNT(v.id) as total_voters 
                                 FROM ideas i 
                                 LEFT JOIN idea_votes v ON i.id = v.idea_id 
                                 WHERE i.brainstorming_id = ? 
                                 GROUP BY i.id 
                                 ORDER BY i.score DESC LIMIT 3");
        $q->execute([$brainstormingId]);
        $stats['top_ideas'] = $q->fetchAll();

        // 4. Stats générales
        $q = $this->db->prepare("SELECT COUNT(DISTINCT id) as total_ideas, COUNT(DISTINCT user_id) as total_contributors FROM ideas WHERE brainstorming_id = ?");
        $q->execute([$brainstormingId]);
        $stats['general'] = $q->fetch();

        return $stats;
    }

    /**
     * Génère un résumé enrichi via l'API Gemini
     */
    public function generateEnhancedSummary($brainstormingId, $categoryFilter = null)
    {
        $stats = $this->getStats($brainstormingId);
        
        // Récupération des idées
        $sql = "SELECT title, content, type FROM ideas WHERE brainstorming_id = ?";
        $params = [$brainstormingId];
        if ($categoryFilter) {
            $sql .= " AND type = ?";
            $params[] = $categoryFilter;
        }
        $q = $this->db->prepare($sql);
        $q->execute($params);
        $ideas = $q->fetchAll();

        if (empty($ideas)) return "Aucune idée trouvée pour générer un résumé.";

        // Construction du prompt
        $prompt = "En tant qu'expert en innovation, analyse cette session de brainstorming :\n";
        $prompt .= "- Nombre d'idées : " . $stats['general']['total_ideas'] . "\n";
        $prompt .= "- Nombre de contributeurs : " . $stats['general']['total_contributors'] . "\n";
        
        if ($categoryFilter) {
            $prompt .= "- Filtre actuel : Type/Catégorie " . $categoryFilter . "\n";
        }

        $prompt .= "\nTop 3 des idées les plus votées :\n";
        foreach ($stats['top_ideas'] as $idx => $idea) {
            $prompt .= ($idx+1) . ". " . $idea['title'] . " (Score: " . $idea['score'] . ")\n";
        }

        $prompt .= "\nListe complète des contributions (Titres et descriptions) :\n";
        foreach ($ideas as $idea) {
            $prompt .= "- [" . $idea['type'] . "] " . $idea['title'] . " : " . $idea['content'] . "\n";
        }

        $prompt .= "\nInstructions :\n";
        $prompt .= "1. Identifie 3 thèmes principaux qui ressortent.\n";
        $prompt .= "2. Met en avant 2 idées particulièrement originales ou prometteuses.\n";
        $prompt .= "3. Donne 1 suggestion stratégique basée sur les statistiques de votes et la distribution.\n";
        $prompt .= "Formatte ta réponse en HTML propre (pas de markdown, utilise <h3>, <ul>, <li>, <p>).";

        return $this->callGemini($prompt);
    }

    private function callGemini($prompt)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $this->apiKey;

        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return "<div class='error'>Désolé, impossible de générer le résumé IA pour le moment. (Erreur API)</div>";
        }

        $responseData = json_decode($response, true);
        return $responseData['candidates'][0]['content']['parts'][0]['text'] ?? "Échec de l'analyse.";
    }

    /**
     * Récupère les statistiques globales de toutes les sessions
     */
    public function getGlobalStats()
    {
        $stats = [];

        // 1. Sessions par statut
        $q = $this->db->query("SELECT status, COUNT(*) as count FROM brainstormings GROUP BY status");
        $stats['status_dist'] = $q->fetchAll();

        // 2. Top 5 thèmes les plus populaires (basé sur le sujet des sessions)
        $q = $this->db->query("SELECT topic as category, COUNT(*) as count FROM brainstormings WHERE topic IS NOT NULL AND topic != '' GROUP BY topic ORDER BY count DESC LIMIT 5");
        $stats['top_types'] = $q->fetchAll();

        // 3. Activité de création de sessions par mois
        $q = $this->db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM brainstormings GROUP BY month ORDER BY month DESC LIMIT 6");
        $stats['creation_trend'] = array_reverse($q->fetchAll());

        // 4. KPI globaux
        $q = $this->db->query("SELECT 
                                (SELECT COUNT(*) FROM brainstormings) as total_sessions,
                                (SELECT COUNT(*) FROM ideas) as total_ideas,
                                (SELECT COUNT(*) FROM idea_votes) as total_votes");
        $stats['kpis'] = $q->fetch();

        return $stats;
    }
}
