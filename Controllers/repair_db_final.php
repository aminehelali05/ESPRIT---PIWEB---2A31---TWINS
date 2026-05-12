<?php
include_once('config.php');

try {
    $db = config::getConnexion();
    echo "Starting Critical Database Repair...\n";

    // --- 1. Fix Events Table ---
    $q = $db->query("DESCRIBE events");
    $cols = $q->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('event_date', $cols) && !in_array('start_date', $cols)) {
        $db->exec("ALTER TABLE events CHANGE COLUMN event_date start_date DATETIME NOT NULL");
        echo "Renamed event_date to start_date.\n";
    }
    if (!in_array('end_date', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN end_date DATETIME NULL AFTER start_date");
        $db->exec("UPDATE events SET end_date = DATE_ADD(start_date, INTERVAL 2 HOUR)");
        $db->exec("ALTER TABLE events MODIFY COLUMN end_date DATETIME NOT NULL");
        echo "Added end_date to events.\n";
    }
    if (!in_array('capacite_max', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN capacite_max INT DEFAULT 20");
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

    // --- 2. Fix Resources Table ---
    // Drop and recreate to be absolutely sure
    $db->exec("DROP TABLE IF EXISTS resources");
    $sql_resources = "
    CREATE TABLE resources (
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
    echo "Resources table recreated correctly.\n";

    echo "Database Repair Successful!\n";
} catch (Exception $e) {
    echo "FATAL ERROR during database repair: " . $e->getMessage() . "\n";
}
