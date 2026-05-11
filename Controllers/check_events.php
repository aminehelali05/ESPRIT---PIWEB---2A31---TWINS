<?php
include 'config.php';
$db = config::getConnexion();
$res = $db->query("SELECT id, title FROM events")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
