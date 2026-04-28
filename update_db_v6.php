<?php
include_once('config.php');
try {
    $db = config::getConnexion();
    // Rename event_date to start_date if not already renamed
    $q = $db->query("DESCRIBE events");
    $cols = $q->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('event_date', $cols)) {
        $db->exec("ALTER TABLE events CHANGE COLUMN event_date start_date DATETIME NOT NULL");
        echo "Renamed event_date to start_date.\n";
    }

    if (!in_array('end_date', $cols)) {
        $db->exec("ALTER TABLE events ADD COLUMN end_date DATETIME NULL AFTER start_date");
        $db->exec("UPDATE events SET end_date = DATE_ADD(start_date, INTERVAL 2 HOUR)");
        $db->exec("ALTER TABLE events MODIFY COLUMN end_date DATETIME NOT NULL");
        echo "Added end_date column.\n";
    }
    
    echo "Database update completed.\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
