<?php
include 'config.php';
try {
    $db = config::getConnexion();
    
    echo "Checking tables...\n";
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(", ", $tables) . "\n\n";

    $required = ['events', 'registrations', 'favoris'];
    foreach ($required as $t) {
        if (!in_array($t, $tables)) {
            echo "[MISSING] Table: $t\n";
        } else {
            echo "[OK] Table: $t\n";
            $cols = $db->query("DESCRIBE $t")->fetchAll(PDO::FETCH_COLUMN);
            echo "  Cols: " . implode(", ", $cols) . "\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
