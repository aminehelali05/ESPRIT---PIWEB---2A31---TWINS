<?php
include_once('config.php');
try {
    $db = config::getConnexion();
    $db->exec("UPDATE resources SET user_id = 1 WHERE user_id NOT IN (SELECT id FROM users) OR user_id = 0");
    $db->exec("ALTER TABLE resources ADD CONSTRAINT fk_resource_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    echo "Constraint added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
