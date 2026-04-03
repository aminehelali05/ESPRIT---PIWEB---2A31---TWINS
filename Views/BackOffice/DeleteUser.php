<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $controller = new UserController();
    $controller->deleteUser((int) $_GET['id']);
}

header('Location: ListUsers.php');
exit;
