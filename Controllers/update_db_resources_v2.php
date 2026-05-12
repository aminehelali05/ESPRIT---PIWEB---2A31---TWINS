<?php
include_once('config.php');

try {
    $db = config::getConnexion();
    echo "Starting Database Update...\n";

    // --- 1. Update Events Table ---
    $q = $db->query("DESCRIBE events");
    $cols = $q->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('capacite_max', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN capacite_max INT DEFAULT 0");
        echo "Added capacite_max to events.\n";
    }
    if (!in_array('nb_inscrits', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN nb_inscrits INT DEFAULT 0");
        echo "Added nb_inscrits to events.\n";
    }
    if (!in_array('statut_inscription', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN statut_inscription VARCHAR(50) DEFAULT 'OUVERT'");
        echo "Added statut_inscription to events.\n";
    }
    if (!in_array('qr_code', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN qr_code TEXT NULL");
        echo "Added qr_code to events.\n";
    }

    // --- 2. Create/Update Resources Table ---
    $sql_resources = "
    CREATE TABLE IF NOT EXISTS resources (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        event_id INT NULL,
        type ENUM('planning', 'regles', 'materiel') NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(50) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_resource_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_resource_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $db->exec($sql_resources);
    echo "Resources table ensured.\n";

    echo "Database Update Successful!\n";
} catch (Exception $e) {
    echo "FATAL ERROR during database update: " . $e->getMessage() . "\n";
}
