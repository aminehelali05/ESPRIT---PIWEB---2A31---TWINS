<?php
require 'config.php';
$db = config::getConnexion();

echo "--- EVENTS TABLE ---\n";
try {
    $q = $db->query('DESCRIBE events');
    print_r($q->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage() . "\n"; }

echo "\n--- RESOURCES TABLE ---\n";
try {
    $q = $db->query('DESCRIBE resources');
    print_r($q->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage() . "\n"; }
