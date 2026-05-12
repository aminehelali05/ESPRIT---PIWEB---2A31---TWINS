<?php
include_once('config.php');
try {
    $db = config::getConnexion();
    $q = $db->query("DESCRIBE resources");
    $cols = $q->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('user_id', $cols)) {
        $db->exec("ALTER TABLE resources ADD COLUMN user_id INT NULL AFTER id");
        $db->exec("UPDATE resources SET user_id = 1 WHERE user_id IS NULL");
        $db->exec("ALTER TABLE resources MODIFY COLUMN user_id INT NOT NULL");
        $db->exec("ALTER TABLE resources ADD CONSTRAINT fk_resource_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        echo "Added user_id to resources successfully.\n";
    } else {
        echo "user_id already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
