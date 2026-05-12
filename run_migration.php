<?php
require_once 'config.php';
$sql = file_get_contents('migration_v2.sql');
$db = config::getConnexion();
try {
    $db->exec($sql);
    echo "Migration successful!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
unlink(__FILE__);
