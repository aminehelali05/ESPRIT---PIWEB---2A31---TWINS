<?php
include 'config.php';
$db = config::getConnexion();
$res = $db->query("SELECT * FROM favoris")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
