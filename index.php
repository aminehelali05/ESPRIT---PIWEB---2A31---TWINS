<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/config.php');
include_once(__DIR__ . '/Controllers/UserController.php');

$authController = new UserController();

$action = $_GET['action'] ?? null;
$page = $_GET['page'] ?? null;

function send_json_response(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_actions(): array
{
    return [
        'create_story',
        'delete_story',
        'get_stories',
        'get_story_users',
        'add_linked_account',
        'delete_linked_account',
        'get_linked_accounts',
        'create_group',
        'get_friends',
        'get_map_users',
        'update_location',
        'upload_message_media',
    ];
}

if (in_array($action, json_actions(), true)) {
    set_exception_handler(static function (Throwable $exception): void {
        error_log('JSON endpoint exception - ' . $exception->getMessage());
        send_json_response(['success' => false, 'message' => 'Server error.'], 500);
    });

    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        error_log(sprintf('JSON endpoint PHP error [%d] %s in %s:%d', $severity, $message, $file, $line));
        return true;
    });
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->login($_POST['email'] ?? '', $_POST['password'] ?? '');

    if ($result['success']) {
        if (UserController::isAdmin()) {
            header('Location: Views/BackOffice/dashboardUser.php');
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
    $registerCountry = trim((string)($_POST['country'] ?? ''));
    $registerCity = trim((string)($_POST['city'] ?? ''));
    $registerFullAddress = trim((string)($_POST['fullAddress'] ?? ''));
    $registerExactLocation = $registerFullAddress !== ''
        ? $registerFullAddress
        : ($registerCity !== '' ? $registerCity . ($registerCountry !== '' ? ', ' . $registerCountry : '') : $registerCountry);

    $result = $authController->register([
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'country' => $registerCountry,
        'exact_location' => $registerExactLocation,
        'latitude' => $_POST['latitude'] ?? null,
        'longitude' => $_POST['longitude'] ?? null,
        'account_type' => $_POST['account_type'] ?? 'freelancer',
        'bio' => $_POST['bio'] ?? '',
        'title' => $_POST['title'] ?? '',
        'skills' => $_POST['skills'] ?? '',
        'avatar_url' => $_POST['avatar_url'] ?? '',
        'referral_code' => $_POST['referral_code'] ?? '',
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

if ($action === 'forgot_password_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $authController->requestPasswordReset($_POST['email'] ?? '');
    $_SESSION['flash_' . ($result['success'] ? 'success' : 'error')] = $result['message'];
    header('Location: Views/FrontOffice/forgot-password.php');
    exit;
}

if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['token'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($password !== $confirmPassword) {
        $_SESSION['flash_error'] = 'Passwords do not match.';
        header('Location: Views/FrontOffice/reset-password.php?token=' . urlencode($token));
        exit;
    }

    $result = $authController->resetPasswordWithToken($token, $password);
    $_SESSION['flash_' . ($result['success'] ? 'success' : 'error')] = $result['message'];
    header('Location: ' . ($result['success']
        ? 'Views/FrontOffice/auth.php'
        : 'Views/FrontOffice/reset-password.php?token=' . urlencode($token)));
    exit;
}

if ($action === 'google_login') {
    $result = $authController->getGoogleAuthorizationUrl();
    if (!$result['success']) {
        $_SESSION['flash_error'] = $result['message'];
        header('Location: Views/FrontOffice/auth.php');
        exit;
    }

    header('Location: ' . $result['url']);
    exit;
}

if ($action === 'google_callback') {
    $result = $authController->handleGoogleOAuthCallback(
        (string) ($_GET['code'] ?? ''),
        (string) ($_GET['state'] ?? '')
    );

    $_SESSION['flash_' . ($result['success'] ? 'success' : 'error')] = $result['message'];
    header('Location: ' . ($result['success'] ? 'Views/FrontOffice/home.php' : 'Views/FrontOffice/auth.php'));
    exit;
}

if ($page === 'dashboard') {
    header('Location: Views/BackOffice/dashboardUser.php');
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

if ($page === 'forgot-password') {
    header('Location: Views/FrontOffice/forgot-password.php');
    exit;
}

// ═══════════════════════════════════════════════════════════════════════
//  API ENDPOINTS (JSON responses)
// ═══════════════════════════════════════════════════════════════════════

if ($action === 'create_story' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) {
        send_json_response(['success' => false, 'message' => 'Not authenticated'], 401);
    }
    $userId = (int)$_SESSION['auth_user']['id'];

    // Handle file upload if present
    $mediaUrl = null;
    if (!empty($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/assets/uploads/stories/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION);
        $filename = 'story_' . $userId . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $filename);
        $mediaUrl = 'assets/uploads/stories/' . $filename;
    }

    $storyData = [
        'story_type'     => $_POST['story_type'] ?? 'image',
        'media_url'      => $mediaUrl ?? ($_POST['media_url'] ?? null),
        'caption'        => $_POST['caption'] ?? null,
        'music_url'      => $_POST['music_url'] ?? null,
        'music_title'    => $_POST['music_title'] ?? null,
        'drawing_data'   => $_POST['drawing_data'] ?? null,
        'text_layers'    => isset($_POST['text_layers']) ? json_decode($_POST['text_layers'], true) : null,
        'sticker_layers' => isset($_POST['sticker_layers']) ? json_decode($_POST['sticker_layers'], true) : null,
        'filter_css'     => $_POST['filter_css'] ?? null,
        'gradient_bg'    => $_POST['gradient_bg'] ?? null,
        'duration'       => $_POST['duration'] ?? 5,
        'visibility'     => $_POST['visibility'] ?? 'public',
        'location_label' => $_POST['location_label'] ?? null,
    ];

    $storyId = $authController->createStory($userId, $storyData);
    send_json_response(['success' => (bool)$storyId, 'story_id' => $storyId], $storyId ? 200 : 500);
}

if ($action === 'delete_story' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) {
        send_json_response(['success' => false], 401);
    }
    $result = $authController->deleteStory(
        (int)($_POST['story_id'] ?? 0),
        (int)$_SESSION['auth_user']['id']
    );
    send_json_response(['success' => $result], $result ? 200 : 500);
}

if ($action === 'get_stories') {
    header('Content-Type: application/json');
    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId > 0) {
        echo json_encode($authController->getActiveStories($userId));
    } else {
        echo json_encode($authController->getAllActiveStories());
    }
    exit;
}

if ($action === 'get_story_users') {
    header('Content-Type: application/json');
    echo json_encode($authController->getUsersWithActiveStories());
    exit;
}

if ($action === 'add_linked_account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    $id = $authController->addLinkedAccount((int)$_SESSION['auth_user']['id'], $_POST);
    echo json_encode(['success' => (bool)$id, 'id' => $id]);
    exit;
}

if ($action === 'delete_linked_account' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) {
        echo json_encode(['success' => false]);
        exit;
    }
    $result = $authController->deleteLinkedAccount(
        (int)($_POST['account_id'] ?? 0),
        (int)$_SESSION['auth_user']['id']
    );
    echo json_encode(['success' => $result]);
    exit;
}

if ($action === 'get_linked_accounts') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) {
        echo json_encode([]);
        exit;
    }
    echo json_encode($authController->getLinkedAccountsForUser((int)$_SESSION['auth_user']['id']));
    exit;
}

if ($action === 'create_group' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    $memberIds = isset($_POST['member_ids']) ? json_decode($_POST['member_ids'], true) : [];
    $groupId = $authController->createGroupChat(
        (int)$_SESSION['auth_user']['id'],
        $_POST['name'] ?? '',
        $_POST['description'] ?? '',
        is_array($memberIds) ? $memberIds : []
    );
    echo json_encode(['success' => (bool)$groupId, 'group_id' => $groupId]);
    exit;
}

if ($action === 'get_friends') {
    header('Content-Type: application/json');
    if (!UserController::isAuthenticated()) {
        echo json_encode([]);
        exit;
    }
    echo json_encode($authController->getFriendsForUser((int)$_SESSION['auth_user']['id']));
    exit;
}

if ($action === 'get_map_users') {
    $allUsers = $authController->listUsers();
    $storyUsers = $authController->getUsersWithActiveStories();
    $storyUserIds = array_column($storyUsers, 'id');
    $mapData = [];
    foreach ($allUsers as $u) {
        $lat = $u->getLatitude();
        $lng = $u->getLongitude();
        if ($lat === null || $lng === null) continue;
        $mapData[] = [
            'id'         => $u->getId(),
            'name'       => $u->getFullName(),
            'avatar_url' => $u->getAvatarUrl(),
            'initials'   => $u->getInitials(),
            'country'    => $u->getCountry(),
            'location'   => $u->getExactLocation(),
            'lat'        => (float)$lat,
            'lng'        => (float)$lng,
            'role'       => $u->getRole(),
            'skills'     => $u->getSkillsArray(),
            'has_story'  => in_array($u->getId(), $storyUserIds),
        ];
    }
    send_json_response($mapData);
}

if ($action === 'update_location' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) {
        send_json_response(['success' => false, 'message' => 'Not authenticated'], 401);
    }

    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode($rawBody ?: '', true);
    $input = is_array($jsonBody) ? $jsonBody : $_POST;

    $latitude = $input['latitude'] ?? $input['lat'] ?? null;
    $longitude = $input['longitude'] ?? $input['lng'] ?? null;

    $result = $authController->updateProfile((int)$_SESSION['auth_user']['id'], [
        'country' => $input['country'] ?? '',
        'exact_location' => $input['exact_location'] ?? ($input['location'] ?? ''),
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]);

    if ($result) {
        $freshUser = $authController->getUserById((int) $_SESSION['auth_user']['id']);
        if ($freshUser) {
            $_SESSION['auth_user']['avatar_url'] = $freshUser->getAvatarUrl();
        }
    }

    send_json_response([
        'success' => $result,
        'latitude' => is_numeric($latitude) ? (float) $latitude : null,
        'longitude' => is_numeric($longitude) ? (float) $longitude : null,
    ], $result ? 200 : 500);
}

// Upload message media (images, audio)
if ($action === 'upload_message_media' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!UserController::isAuthenticated()) { send_json_response(['success' => false, 'message' => 'Not authenticated'], 401); }
    $userId = (int)($_SESSION['auth_user']['id'] ?? 0);

    if (empty($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
        send_json_response(['success' => false, 'message' => 'No file uploaded or upload error'], 400);
    }

    $file = $_FILES['media'];
    $maxBytes = 10 * 1024 * 1024; // 10 MB limit
    if ($file['size'] > $maxBytes) { send_json_response(['success' => false, 'message' => 'File too large'], 400); }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    $allowedImages = ['jpg','jpeg','png','gif','webp'];
    $allowedAudio = ['webm','mp3','wav','m4a','ogg','aac'];
    $allowedVideo = ['mp4','mov','webm','m4v','avi'];
    $allowedFiles = ['pdf','txt','rtf','doc','docx','xls','xlsx','ppt','pptx','zip','rar','7z','csv'];
    $type = null;
    if (in_array($ext, $allowedImages, true) || str_starts_with($mimeType, 'image/')) $type = 'image';
    if (in_array($ext, $allowedAudio, true) || str_starts_with($mimeType, 'audio/')) $type = 'audio';
    if ($type === null && (in_array($ext, $allowedVideo, true) || str_starts_with($mimeType, 'video/'))) $type = 'video';
    if ($type === null && (in_array($ext, $allowedFiles, true) || in_array($mimeType, [
        'application/pdf',
        'text/plain',
        'text/rtf',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip',
        'application/x-rar-compressed',
        'application/x-7z-compressed',
    ], true))) {
        $type = 'file';
    }
    if ($type === null) { send_json_response(['success' => false, 'message' => 'Unsupported file type'], 400); }

    $subDir = $type === 'audio' ? 'audio' : ($type === 'image' ? 'images' : ($type === 'video' ? 'video' : 'files'));
    $uploadDir = __DIR__ . '/assets/uploads/messages/' . $subDir . '/';
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $safeHash = bin2hex(random_bytes(6));
    $filename = sprintf('msg_%d_%d_%s.%s', $userId, time(), $safeHash, $ext);
    $dest = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        send_json_response(['success' => false, 'message' => 'Could not save uploaded file'], 500);
    }

    $mediaUrl = 'assets/uploads/messages/' . $subDir . '/' . $filename;
    send_json_response([
        'success' => true,
        'media_url' => $mediaUrl,
        'type' => $type,
        'size' => (int) $file['size'],
        'original_name' => (string) ($file['name'] ?? $filename),
        'mime_type' => $mimeType,
        'extension' => $ext,
    ]);
}

header('Location: Views/FrontOffice/home.php');
exit;
