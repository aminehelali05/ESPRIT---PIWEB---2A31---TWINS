<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/Controllers/UserController.php');
include_once(__DIR__ . '/Controllers/EventController.php');
include_once(__DIR__ . '/Controllers/ResourceController.php');
include_once(__DIR__ . '/Controllers/BrainstormingController.php');
include_once(__DIR__ . '/Controllers/IdeaController.php');
include_once(__DIR__ . '/Controllers/AiController.php');

$authController = new UserController();
$eventController = new EventController();
$resourceController = new ResourceController();
$brainstormingController = new BrainstormingController();
$ideaController = new IdeaController();
$aiController = new AiController();

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
        'EN_ATTENTE',
        (int)($_POST['capacite_max'] ?? 0)
    );
    if ($eventController->addEvent($event)) {
        $_SESSION['flash_success'] = 'Event submitted for validation.';
    } else {
        $_SESSION['flash_error'] = 'Failed to create event.';
    }
    header('Location: Views/FrontOffice/events.php');
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
    header('Location: ' . (UserController::isAdmin() ? 'Views/BackOffice/events_all.php' : 'Views/FrontOffice/events.php'));
    exit;
}

if ($action === 'approve_event' && UserController::isAdmin()) {
    $eventController->updateStatus($_GET['id'], 'ACCEPTE');
    $_SESSION['flash_success'] = 'Event approved.';
    header('Location: Views/BackOffice/events_all.php');
    exit;
}

if ($action === 'refuse_event' && UserController::isAdmin()) {
    $eventController->updateStatus($_GET['id'], 'REFUSE');
    $_SESSION['flash_success'] = 'Event refused.';
    header('Location: Views/BackOffice/events_all.php');
    exit;
}

if ($action === 'join_event') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $user = UserController::currentUser();
    $res = $eventController->registerToEvent($user['id'], $_GET['id']);
    if ($res['success']) {
        $_SESSION['flash_success'] = 'Registration successful!';
    } else {
        $_SESSION['flash_error'] = $res['message'];
    }
    header('Location: Views/FrontOffice/event_details.php?id=' . $_GET['id']);
    exit;
}

if ($action === 'toggle_favorite') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) { echo json_encode(['success' => false]); exit; }
    $user = UserController::currentUser();
    $res = $eventController->toggleFavorite($user['id'], $_POST['event_id']);
    echo json_encode($res);
    exit;
}

if ($action === 'get_calendar_events') {
    header('Content-Type: application/json');
    $events = $eventController->listEvents('', ['status' => 'ACCEPTE']);
    $formatted = [];
    foreach ($events as $e) {
        $formatted[] = [
            'id' => $e['id'],
            'title' => $e['title'],
            'start' => $e['event_date'],
            'extendedProps' => [
                'category' => $e['category'],
                'location' => $e['location']
            ]
        ];
    }
    echo json_encode($formatted);
    exit;
}

// ── Resource Actions ────────────────────────────────────────────────
if ($action === 'create_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $errors = $resourceController->validate($_POST);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        $redirect = UserController::isAdmin() ? 'Views/BackOffice/resources_admin.php' : 'Views/FrontOffice/resources.php';
        header('Location: ' . $redirect);
        exit;
    }
    $user = UserController::currentUser();
    $res = new ResourceItem($user['id'], $_POST['type'], $_POST['title'], $_POST['description'], $_POST['status'] ?? 'active');
    $resourceController->addResource($res);
    $_SESSION['flash_success'] = 'Resource created successfully.';
    $redirect = UserController::isAdmin() ? 'Views/BackOffice/resources_admin.php' : 'Views/FrontOffice/resources.php';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'update_resource' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $id = $_POST['id'] ?? null;
    $errors = $resourceController->validate($_POST, $id);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        $redirect = UserController::isAdmin() ? 'Views/BackOffice/resources_admin.php?edit_id=' . $id : 'Views/FrontOffice/resources.php?edit_id=' . $id;
        header('Location: ' . $redirect);
        exit;
    }
    $res = $resourceController->getResourceById($id);
    if ($res && ($res->getUserId() == UserController::currentUser()['id'] || UserController::isAdmin())) {
        $res->setType($_POST['type']);
        $res->setTitle($_POST['title']);
        $res->setDescription($_POST['description']);
        $res->setStatus($_POST['status'] ?? $res->getStatus());
        $resourceController->updateResource($res, $id);
        $_SESSION['flash_success'] = 'Resource updated successfully.';
    } else {
        $_SESSION['flash_error'] = 'Unauthorized action.';
    }
    $redirect = UserController::isAdmin() ? 'Views/BackOffice/resources_admin.php' : 'Views/FrontOffice/resources.php';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'delete_resource' && isset($_GET['id'])) {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $res = $resourceController->getResourceById($_GET['id']);
    if ($res && ($res->getUserId() == UserController::currentUser()['id'] || UserController::isAdmin())) {
        $resourceController->deleteResource($_GET['id']);
        $_SESSION['flash_success'] = 'Resource deleted.';
    } else {
        $_SESSION['flash_error'] = 'Unauthorized action.';
    }
    $redirect = UserController::isAdmin() ? 'Views/BackOffice/resources_admin.php' : 'Views/FrontOffice/resources.php';
    header('Location: ' . $redirect);
    exit;
}

if ($action === 'generate_planning_ai' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $description = $_POST['description'] ?? '';
    $planning = $resourceController->generatePlanningByAI($description);
    
    if ($planning && !isset($planning['error'])) {
        echo json_encode(['success' => true, 'planning' => $planning]);
    } else {
        $msg = $planning['error'] ?? 'AI generation failed.';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
    exit;
}

// ── Brainstorming Actions ───────────────────────────────────────────
if ($action === 'create_brainstorming' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    
    $errors = $brainstormingController->validate($_POST);
    if (!empty($errors)) {
        $_SESSION['flash_error'] = implode(' ', $errors);
        header('Location: Views/FrontOffice/brainstormings.php');
        exit;
    }

    $user = UserController::currentUser();
    $b = new Brainstorming(
        $user['id'],
        $_POST['title'] ?? '',
        $_POST['description'] ?? '',
        $_POST['topic'] ?? 'General',
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
        $existing->setTopic($_POST['topic'] ?? $existing->getTopic());
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
    $title = $_POST['title'] ?? 'Untitled Idea';
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'Standard';
    
    if (empty($content)) {
        $_SESSION['flash_error'] = 'Idea content cannot be empty.';
        header('Location: Views/FrontOffice/brainstorming_details.php?id=' . $bid);
        exit;
    }

    $user = UserController::currentUser();
    $idea = new Idea($bid, $user['id'], $title, $content, 'PROPOSED', $type);
    if ($ideaController->addIdea($idea)) {
        $_SESSION['flash_success'] = 'Idea posted successfully.';
    } else {
        $_SESSION['flash_error'] = 'Failed to post idea.';
    }
    header('Location: Views/FrontOffice/brainstorming_details.php?id=' . $bid);
    exit;
}

if ($action === 'vote_idea' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $user = UserController::currentUser();
    $idea_id = $_POST['idea_id'] ?? null;
    $vote_type = (int)($_POST['vote_type'] ?? 0);
    
    if ($idea_id && in_array($vote_type, [1, -1])) {
        $stats = $ideaController->toggleVote($idea_id, $user['id'], $vote_type);
        if ($stats !== false) {
            echo json_encode(['success' => true, 'stats' => $stats]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB Error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
    exit;
}

if ($action === 'poll_ideas_stats' && isset($_GET['bid'])) {
    header('Content-Type: application/json');
    $stats = $ideaController->getIdeasStatsByBrainstorming($_GET['bid']);
    echo json_encode($stats);
    exit;
}

if ($action === 'delete_idea' && isset($_GET['id'])) {
    if (!UserController::isAdmin()) { header('Location: Views/FrontOffice/auth.php'); exit; }
    $ideaController->deleteIdea($_GET['id']);
    $_SESSION['flash_success'] = 'Idea deleted successfully.';
    header('Location: Views/BackOffice/ideas_all.php');
    exit;
}

if ($action === 'generate_ai' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = $input['prompt'] ?? '';
    $context = $input['context'] ?? 'Brainstorming Description';
    
    $result = $aiController->generateContent($prompt, $context);
    echo json_encode($result);
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
