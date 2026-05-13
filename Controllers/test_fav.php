<?php
require 'Controllers/EventController.php';
require 'Controllers/UserController.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Mock user if needed
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = ['id' => 1]; // Assume ID 1 exists
}

$ec = new EventController();
$uid = $_SESSION['user']['id'];
$eid = 1; // Assume Event ID 1 exists

echo "Testing toggleFavorite for UID $uid, EID $eid...\n";
$res = $ec->toggleFavorite($uid, $eid);
print_r($res);

echo "\nSecond toggle (should be opposite):\n";
$res = $ec->toggleFavorite($uid, $eid);
print_r($res);

echo "\nisFavorite check:\n";
var_dump($ec->isFavorite($uid, $eid));
