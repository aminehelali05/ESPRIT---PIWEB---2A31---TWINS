<?php
require_once __DIR__ . '/../../Core/config.php';

try {
    $db = config::getConnexion();
    
    try {
        $db->exec("ALTER TABLE ideas ADD COLUMN score INT DEFAULT 0");
        echo "Colonne 'score' ajoutee a ideas.\n";
    } catch (Exception $e) { echo "score existe.\n"; }

    try {
        $db->exec("ALTER TABLE users ADD COLUMN karma INT DEFAULT 0");
        echo "Colonne 'karma' ajoutee a users.\n";
    } catch (Exception $e) { echo "karma existe.\n"; }

    // Sync
    $db->exec("UPDATE ideas i SET score = COALESCE((SELECT SUM(vote_type) FROM idea_votes WHERE idea_id = i.id), 0)");
    $db->exec("UPDATE users u SET karma = COALESCE((SELECT SUM(vote_type) FROM idea_votes v JOIN ideas i ON v.idea_id = i.id WHERE i.user_id = u.id), 0)");

    echo "Base de donnees synchronisee pour le Karma et le Score !";

} catch (Exception $e) {
    echo "Erreur absolue: " . $e->getMessage();
}
