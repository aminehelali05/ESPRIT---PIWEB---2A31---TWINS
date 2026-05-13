<?php
require_once 'config.php';
$pdo = config::getConnexion();

try {
    $pdo->exec("ALTER TABLE `marketplace_items` ADD `quantity` INT NOT NULL DEFAULT 1 AFTER `price` ");
    echo "Successfully added 'quantity' column to 'marketplace_items'.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'quantity' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
