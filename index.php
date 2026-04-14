<?php
<<<<<<< Updated upstream
header('Location: Views/BackOffice/ListUsers.php');
exit;
=======
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/Controllers/UserController.php');
include_once(__DIR__ . '/Controllers/EventController.php');
include_once(__DIR__ . '/Controllers/ResourceController.php');
include_once(__DIR__ . '/Controllers/BrainstormingController.php');
include_once(__DIR__ . '/Controllers/IdeaController.php');

$authController = new UserController();
$eventController = new EventController();
$resourceController = new ResourceController();
$brainstormingController = new BrainstormingController();
$ideaController = new IdeaController();

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? null;

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->login($_POST['email'] ?? '', $_POST['password'] ?? '');

    if ($result['success']) {
        if (UserController::isAdmin()) {
            header('Location: Views/BackOffice/dashboard.php');
        } else {
            header('Location: Views/FrontOffice/home.php');
        }
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
        'account_type' => $_POST['account_type'] ?? 'freelancer',
        'bio' => $_POST['bio'] ?? '',
        'title' => $_POST['title'] ?? '',
        'skills' => $_POST['skills'] ?? '',
        'avatar_url' => $_POST['avatar_url'] ?? '',
    ]);

    if ($result['success']) {
        $_SESSION['flash_success'] = 'Account created. Welcome!';
        header('Location: Views/FrontOffice/home.php');
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

// ── Event Actions ───────────────────────────────────────────────────
if ($action === 'create_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    
    $errors = $eventController->validate($_POST);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: Views/FrontOffice/event_create.php');
        exit;
    }

    $user = UserController::currentUser();
    $event = new Event(
        $user['id'],
        $_POST['title'] ?? '',
        $_POST['description'] ?? '',
        $_POST['event_date'] ?? '',
        $_POST['location'] ?? '',
        $_POST['category'] ?? '',
        'EN_ATTENTE'
    );
    if ($eventController->addEvent($event)) {
        $_SESSION['flash_success'] = 'Event submitted for validation.';
        header('Location: Views/FrontOffice/events.php');
    } else {
        $_SESSION['flash_error'] = 'Failed to create event.';
        header('Location: Views/FrontOffice/event_create.php');
    }
    exit;
}

if ($action === 'update_event' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $id = $_POST['id'] ?? null;

    $errors = $eventController->validate($_POST);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: Views/FrontOffice/event_edit.php?id=' . $id);
        exit;
    }

    $existing = $eventController->getEventById($id);
    if ($existing && ($existing->getUserId() == UserController::currentUser()['id'] || UserController::isAdmin())) {
        $existing->setTitle($_POST['title'] ?? $existing->getTitle());
        $existing->setDescription($_POST['description'] ?? $existing->getDescription());
        $existing->setEventDate($_POST['event_date'] ?? $existing->getEventDate());
        $existing->setLocation($_POST['location'] ?? $existing->getLocation());
        $existing->setCategory($_POST['category'] ?? $existing->getCategory());
        if (UserController::isAdmin()) {
            $existing->setStatus($_POST['status'] ?? $existing->getStatus());
        }
        $eventController->updateEvent($existing, $id);
        $_SESSION['flash_success'] = 'Event updated.';
    }
    header('Location: Views/FrontOffice/events.php');
    exit;
}

if ($action === 'delete_event' && isset($_GET['id'])) {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $event = $eventController->getEventById($_GET['id']);
    if ($event && ($event->getUserId() == UserController::currentUser()['id'] || UserController::isAdmin())) {
        $eventController->deleteEvent($_GET['id']);
        $_SESSION['flash_success'] = 'Event deleted.';
    }
    header('Location: ' . (UserController::isAdmin() ? 'Views/BackOffice/events_admin.php' : 'Views/FrontOffice/events.php'));
    exit;
}

if ($action === 'approve_event' && UserController::isAdmin()) {
    $eventController->updateStatus($_GET['id'], 'ACCEPTE');
    $_SESSION['flash_success'] = 'Event approved.';
    header('Location: Views/BackOffice/events_admin.php');
    exit;
}

if ($action === 'refuse_event' && UserController::isAdmin()) {
    $eventController->updateStatus($_GET['id'], 'REFUSE');
    $_SESSION['flash_success'] = 'Event refused.';
    header('Location: Views/BackOffice/events_admin.php');
    exit;
}

// ── Resource Actions ────────────────────────────────────────────────
if (UserController::isAdmin()) {
    if ($action === 'create_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors = $resourceController->validate($_POST);
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: Views/BackOffice/resources_admin.php');
            exit;
        }
        $res = new ResourceItem($_POST['type'], $_POST['title'], $_POST['description'], $_POST['status']);
        $resourceController->addResource($res);
        header('Location: Views/BackOffice/resources_admin.php');
        exit;
    }
    if ($action === 'update_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors = $resourceController->validate($_POST);
        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            header('Location: Views/BackOffice/resources_admin.php?edit_id=' . $_POST['id']);
            exit;
        }
        $res = $resourceController->getResourceById($_POST['id']);
        if ($res) {
            $res->setType($_POST['type']);
            $res->setTitle($_POST['title']);
            $res->setDescription($_POST['description']);
            $res->setStatus($_POST['status']);
            $resourceController->updateResource($res, $_POST['id']);
        }
        header('Location: Views/BackOffice/resources_admin.php');
        exit;
    }
    if ($action === 'delete_resource' && isset($_GET['id'])) {
        $resourceController->deleteResource($_GET['id']);
        header('Location: Views/BackOffice/resources_admin.php');
        exit;
    }
}

// ── Brainstorming Actions ───────────────────────────────────────────
if ($action === 'create_brainstorming' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    
    $errors = $brainstormingController->validate($_POST);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: Views/FrontOffice/brainstorming_create.php');
        exit;
    }

    $user = UserController::currentUser();
    $b = new Brainstorming(
        $user['id'],
        $_POST['title'] ?? '',
        $_POST['description'] ?? '',
        'EN_ATTENTE'
    );
    if ($brainstormingController->addBrainstorming($b)) {
        $_SESSION['flash_success'] = 'Brainstorming session submitted for validation.';
    } else {
        $_SESSION['flash_error'] = 'Failed to create brainstorming.';
    }
    header('Location: Views/FrontOffice/brainstormings.php');
    exit;
}

if ($action === 'update_brainstorming' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $id = $_POST['id'] ?? null;

    $errors = $brainstormingController->validate($_POST);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: Views/FrontOffice/brainstorming_edit.php?id=' . $id);
        exit;
    }

    $existing = $brainstormingController->getBrainstormingById($id);
    if ($existing && ($existing->getUserId() == UserController::currentUser()['id'] || UserController::isAdmin())) {
        $existing->setTitle($_POST['title'] ?? $existing->getTitle());
        $existing->setDescription($_POST['description'] ?? $existing->getDescription());
        if (UserController::isAdmin()) {
            $existing->setStatus($_POST['status'] ?? $existing->getStatus());
        }
        $brainstormingController->updateBrainstorming($existing, $id);
        $_SESSION['flash_success'] = 'Brainstorming updated.';
    }
    header('Location: Views/FrontOffice/brainstormings.php');
    exit;
}

if ($action === 'delete_brainstorming' && isset($_GET['id'])) {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $b = $brainstormingController->getBrainstormingById($_GET['id']);
    if ($b && ($b->getUserId() == UserController::currentUser()['id'] || UserController::isAdmin())) {
        $brainstormingController->deleteBrainstorming($_GET['id']);
        $_SESSION['flash_success'] = 'Brainstorming deleted.';
    }
    header('Location: ' . (UserController::isAdmin() ? 'Views/BackOffice/brainstorming_admin.php' : 'Views/FrontOffice/brainstormings.php'));
    exit;
}

if ($action === 'approve_brainstorming' && UserController::isAdmin()) {
    $brainstormingController->updateStatus($_GET['id'], 'ACCEPTE');
    $_SESSION['flash_success'] = 'Brainstorming approved.';
    header('Location: Views/BackOffice/brainstorming_admin.php');
    exit;
}

if ($action === 'refuse_brainstorming' && UserController::isAdmin()) {
    $brainstormingController->updateStatus($_GET['id'], 'REFUSE');
    $_SESSION['flash_success'] = 'Brainstorming refused.';
    header('Location: Views/BackOffice/brainstorming_admin.php');
    exit;
}

// ── Idea Actions ───────────────────────────────────────────────────
if ($action === 'add_idea' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    
    $bid = $_POST['brainstorming_id'] ?? null;
    $content = $_POST['content'] ?? '';
    
    if (empty($content)) {
        $_SESSION['flash_error'] = 'Idea content cannot be empty.';
        header('Location: Views/FrontOffice/brainstorming_details.php?id=' . $bid);
        exit;
    }

    $user = UserController::currentUser();
    $idea = new Idea($bid, $user['id'], $content);
    if ($ideaController->addIdea($idea)) {
        $_SESSION['flash_success'] = 'Idea posted successfully.';
    } else {
        $_SESSION['flash_error'] = 'Failed to post idea.';
    }
    header('Location: Views/FrontOffice/brainstorming_details.php?id=' . $bid);
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

if ($page === 'events') {
    header('Location: Views/FrontOffice/events.php');
    exit;
}

if ($page === 'resources') {
    header('Location: Views/FrontOffice/resources.php');
    exit;
}

if ($page === 'brainstormings') {
    header('Location: Views/FrontOffice/brainstormings.php');
    exit;
}

header('Location: Views/FrontOffice/home.php');
exit;
>>>>>>> Stashed changes
