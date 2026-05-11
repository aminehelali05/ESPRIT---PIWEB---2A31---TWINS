<?php
include 'config.php';
try {
    $db = config::getConnexion();
    echo "Starting Database Repair...\n";

    // 1. Ensure events columns exist
    $eventsCols = $db->query("DESCRIBE events")->fetchAll(PDO::FETCH_COLUMN);
    $newCols = [
        'capacite_max' => "INT DEFAULT 20",
        'nb_inscrits' => "INT DEFAULT 0",
        'statut_inscription' => "VARCHAR(20) DEFAULT 'OUVERT'",
        'qr_code' => "LONGTEXT"
    ];
    foreach ($newCols as $col => $def) {
        if (!in_array($col, $eventsCols)) {
            echo "Adding column $col to events...\n";
            $db->exec("ALTER TABLE events ADD COLUMN $col $def");
        }
    }

    // 2. Fix registrations table
    echo "Creating registrations table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS registrations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        event_id INT NOT NULL,
        registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, event_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    )");

    // 3. Fix favoris table
    echo "Handling favoris table...\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('favoris', $tables)) {
        echo "Creating favoris table...\n";
        $db->exec("CREATE TABLE favoris (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, event_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
        )");
    } else {
        $favCols = $db->query("DESCRIBE favoris")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('evenement_id', $favCols)) {
            echo "Renaming evenement_id to event_id in favoris...\n";
            $db->exec("ALTER TABLE favoris CHANGE evenement_id event_id INT NOT NULL");
        }
        if (!in_array('event_id', $favCols) && !in_array('evenement_id', $favCols)) {
             echo "Adding missing event_id to favoris...\n";
             $db->exec("ALTER TABLE favoris ADD COLUMN event_id INT NOT NULL AFTER user_id");
        }
    }

    echo "Database Repair Completed Successfully!\n";
} catch (Exception $e) {
    echo "ERROR during repair: " . $e->getMessage() . "\n";
}
