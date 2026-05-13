<?php
include 'config.php';
$db = config::getConnexion();
$users = $db->query("SELECT id, first_name, last_name, email, role, title FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($users, JSON_PRETTY_PRINT);
