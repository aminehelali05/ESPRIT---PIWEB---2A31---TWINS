<?php
include_once('config.php');
try {
    $db = config::getConnexion();
    $q = $db->query("DESCRIBE resources");
    $cols = $q->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
