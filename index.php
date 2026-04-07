<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/Controllers/AuthController.php');

$authController = new AuthController();

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? null;

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->login($_POST['email'] ?? '', $_POST['password'] ?? '');

    if ($result['success']) {
        header('Location: Views/BackOffice/dashboard.php');
        exit;
    }

    $_SESSION['flash_error'] = $result['message'];
    header('Location: Views/FrontOffice/auth.php');
    exit;
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->register([
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'country' => $_POST['country'] ?? '',
        'bio' => $_POST['bio'] ?? '',
        'title' => $_POST['title'] ?? '',
        'skills' => $_POST['skills'] ?? '',
        'avatar_url' => $_POST['avatar_url'] ?? '',
    ]);

    if ($result['success']) {
        $_SESSION['flash_success'] = 'Account created. Welcome!';
        header('Location: Views/BackOffice/dashboard.php');
        exit;
    }

    $_SESSION['flash_error'] = $result['message'];
    header('Location: Views/FrontOffice/auth.php#register');
    exit;
}

if ($action === 'logout') {
    $authController->logout();
    $_SESSION['flash_success'] = 'You are now signed out.';
    header('Location: Views/FrontOffice/auth.php');
    exit;
}

if ($page === 'dashboard') {
    header('Location: Views/BackOffice/dashboard.php');
    exit;
}

if ($page === 'auth') {
    header('Location: Views/FrontOffice/auth.php');
    exit;
}

if ($page === 'profile') {
    header('Location: Views/FrontOffice/profile.php');
    exit;
}

header('Location: Views/FrontOffice/home.php');
exit;
