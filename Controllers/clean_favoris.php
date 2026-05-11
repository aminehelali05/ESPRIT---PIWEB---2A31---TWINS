<?php
include 'config.php';
try {
    $db = config::getConnexion();
    echo "Cleaning favoris table...\n";

    // 1. Remove duplicates
    $db->exec("DELETE f1 FROM favoris f1
               INNER JOIN favoris f2 
               WHERE f1.id > f2.id 
               AND f1.user_id = f2.user_id 
               AND f1.event_id = f2.event_id");
    
    echo "Adding UNIQUE constraint to favoris...\n";
    try {
        $db->exec("ALTER TABLE favoris ADD UNIQUE KEY user_event_unique (user_id, event_id)");
    } catch (Exception $e) {
        echo "Constraint might already exist or failed: " . $e->getMessage() . "\n";
    }

    echo "Favoris table cleaned and secured!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
