<?php
require 'config.php';
$db = config::getConnexion();
try {
    $q = $db->query('DESCRIBE favoris');
    print_r($q->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Table 'favoris' error: " . $e->getMessage() . "\n";
    // Check if it exists with another name
    $q = $db->query('SHOW TABLES');
    print_r($q->fetchAll(PDO::FETCH_COLUMN));
}
