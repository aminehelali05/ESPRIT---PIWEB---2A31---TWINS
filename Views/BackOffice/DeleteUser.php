<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

if (isset($_GET['id'])) {
    $userController = new UserController();
    $userController->deleteUser($_GET['id']);
}

header('Location: ListUsers.php');
exit;
