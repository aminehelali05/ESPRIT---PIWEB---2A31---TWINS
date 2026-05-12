<?php
include_once('config.php');
try {
    $db = config::getConnexion();
    $db->exec("ALTER TABLE resources ADD COLUMN event_id INT NULL");
    $db->exec("ALTER TABLE resources ADD CONSTRAINT fk_resource_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
    echo "Database updated successfully: Added event_id to resources table.\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
