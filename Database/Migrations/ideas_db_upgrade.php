<?php
require_once __DIR__ . '/../../Core/config.php';

try {
    $db = config::getConnexion();
    
    // 1. ALTER IDEAS TABLE
    $sql = "ALTER TABLE ideas 
            ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT 'Untitled Idea' AFTER user_id,
            ADD COLUMN status VARCHAR(50) DEFAULT 'PROPOSED' AFTER content";
    try {
        $db->exec($sql);
        echo "Table ideas modifiée.\n";
    } catch(Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Columns already exist in ideas.\n";
        } else {
            throw $e;
        }
    }

    // 2. CREATE VOTES TABLE
    $sqlVotes = "CREATE TABLE IF NOT EXISTS idea_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        idea_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type TINYINT NOT NULL COMMENT '1 for upvote, -1 for downvote',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (idea_id, user_id),
        CONSTRAINT fk_vote_idea FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
        CONSTRAINT fk_vote_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $db->exec($sqlVotes);
    echo "Table idea_votes prete.\n";

    // 3. SEEDING
    $db->exec("DELETE FROM idea_votes");
    $db->exec("DELETE FROM ideas"); // Clean to inject beautiful data

    // Insert 3 beautiful ideas for brainstorming 1
    $data = [
        [
            'uid' => 1, 'bid' => 1, 
            'title' => 'Green Citizen App — Gamifier l’impact local', 
            'content' => 'Une application mobile open-source où chaque citoyen peut signaler les infractions environnementales. Les municipalités voient les tickets en direct et les citoyens gagnent des "Eco-points" échangeables contre des remises dans le transport public local.',
            'status' => 'UNDER_REVIEW'
        ],
        [
            'uid' => 2, 'bid' => 1, 
            'title' => 'Comités Décisionnels par Tirage au Sort', 
            'content' => 'S\'inspirer de l\'agora athénienne : au lieu d\'élire des experts politiques pour les questions de pollution, tirer au sort des citoyens formés durant 3 semaines par des scientifiques indépendants pour voter les lois environnementales. La vraie démocratie en action.',
            'status' => 'PROPOSED'
        ],
        [
            'uid' => 3, 'bid' => 1, 
            'title' => 'Bilan Carbone lié au Vote Électronique', 
            'content' => 'Introduire le vote électronique utilisant une blockchain écologique (proof-of-stake) pour toutes les décisions locales liées à l\'écologie. Cela permet une délibération rapide et transparente, accessible depuis chez soi sans émettre de gaz à effet de serre via les déplacements.',
            'status' => 'PROPOSED'
        ]
    ];

    $stmt = $db->prepare("INSERT INTO ideas (brainstorming_id, user_id, title, content, status) VALUES (:bid, :uid, :title, :content, :status)");
    $ideaIds = [];
    foreach ($data as $i) {
        $stmt->execute($i);
        $ideaIds[] = $db->lastInsertId();
    }
    echo "Ideas seedées.\n";

    // Insert some votes
    // Idea 1 (id: $ideaIds[0]): 3 upvotes (Users 1,2,3)
    // Idea 2 (id: $ideaIds[1]): 2 upvotes, 1 downvote (Users 1 up, 2 up, 3 down) -> score 1
    // Idea 3 (id: $ideaIds[2]): 1 upvote (User 2) -> score 1

    $stmtVote = $db->prepare("INSERT INTO idea_votes (idea_id, user_id, vote_type) VALUES (?, ?, ?)");
    
    // Idea 1
    $stmtVote->execute([$ideaIds[0], 1, 1]);
    $stmtVote->execute([$ideaIds[0], 2, 1]);
    $stmtVote->execute([$ideaIds[0], 3, 1]);

    // Idea 2
    $stmtVote->execute([$ideaIds[1], 1, 1]);
    $stmtVote->execute([$ideaIds[1], 2, 1]);
    $stmtVote->execute([$ideaIds[1], 3, -1]);

    // Idea 3
    $stmtVote->execute([$ideaIds[2], 2, 1]);

    echo "Votes seedés.\nBase de données prête !";

} catch (Exception $e) {
    echo "Erreur absolue: " . $e->getMessage();
}
