<?php
include 'config.php';
$db = config::getConnexion();
$res = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($res);
