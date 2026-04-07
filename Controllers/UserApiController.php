<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

include_once(__DIR__ . '/UserController.php');
include_once(__DIR__ . '/../Models/User.php');

if (!isset($_SESSION['auth_user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userController = new UserController();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function parseFormBody(): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
        return $_POST;
    }
    return parseJsonBody();
}

function userToArray(User $user): array
{
    $statusText = (int) $user->getStatus() === 1 ? 'active' : 'offline';

    return [
        'id' => (int) $user->getId(),
        'first_name' => (string) $user->getFirstName(),
        'last_name' => (string) $user->getLastName(),
        'email' => (string) $user->getEmail(),
        'phone' => (string) ($user->getPhone() ?? ''),
        'role' => (string) $user->getRole(),
        'status' => (int) $user->getStatus(),
        'status_text' => $statusText,
        'avatar_url' => $user->getAvatarUrl(),
        'badge' => $user->getBadge(),
        'country' => $user->getCountry(),
        'bio' => $user->getBio(),
        'title' => $user->getTitle(),
        'skills' => $user->getSkills(),
        'xp' => (int) $user->getXp(),
        'is_blocked' => (int) $user->getIsBlocked(),
        'last_seen' => $user->getLastSeen(),
        'face_descriptor' => $user->getFaceDescriptor(),
        'face_images_path' => $user->getFaceImagesPath(),
        'face_enrolled' => (int) $user->getFaceEnrolled(),
        'face_enrolled_at' => $user->getFaceEnrolledAt(),
        'created_at' => (string) ($user->getCreatedAt() ?? ''),
        'updated_at' => (string) ($user->getUpdatedAt() ?? ''),

        'firstName' => (string) $user->getFirstName(),
        'lastName' => (string) $user->getLastName(),
        'location' => (string) ($user->getCountry() ?? ''),
        'createdAt' => (string) ($user->getCreatedAt() ?? ''),
        'lastActive' => (string) ($user->getLastSeen() ?? ''),
    ];
}

function normalizeRole(string $role): string
{
    $role = strtolower(trim($role));
    $allowed = ['admin', 'manager', 'member', 'user'];
    return in_array($role, $allowed, true) ? $role : 'user';
}

function normalizeStatus($status): int
{
    if (is_numeric($status)) {
        return (int) $status === 1 ? 1 : 0;
    }
    return strtolower(trim((string) $status)) === 'active' ? 1 : 0;
}

function payloadValue(array $payload, string $snakeCase, string $camelCase = null, $default = null)
{
    if (array_key_exists($snakeCase, $payload)) {
        return $payload[$snakeCase];
    }
    if ($camelCase !== null && array_key_exists($camelCase, $payload)) {
        return $payload[$camelCase];
    }
    return $default;
}

try {
    if ($method === 'GET' && $action === 'me') {
        $id = (int) ($_SESSION['auth_user']['id'] ?? 0);
        $user = $id > 0 ? $userController->getUserById($id) : null;

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Authenticated user not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'user' => userToArray($user)]);
        exit;
    }

    if ($method === 'GET' && $action === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        $user = $id > 0 ? $userController->getUserById($id) : null;

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        echo json_encode(['success' => true, 'user' => userToArray($user)]);
        exit;
    }

    if ($method === 'GET' && $action === 'list') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $role = strtolower(trim((string) ($_GET['role'] ?? 'all')));
        $status = strtolower(trim((string) ($_GET['status'] ?? 'all')));

        $users = $userController->listUsers($search);
        $mapped = [];
        foreach ($users as $user) {
            $row = userToArray($user);

            if ($role !== 'all' && strtolower($row['role']) !== $role) {
                continue;
            }
            if ($status !== 'all' && strtolower($row['status_text']) !== $status) {
                continue;
            }
            $mapped[] = $row;
        }

        echo json_encode(['success' => true, 'users' => $mapped]);
        exit;
    }

    if ($method === 'GET' && $action === 'stats') {
        $allUsers = $userController->listUsers();
        $total = count($allUsers);
        $active = 0;
        $admins = 0;
        $newThisMonth = 0;
        $nowMonth = date('Y-m');

        foreach ($allUsers as $u) {
            if ((int) $u->getStatus() === 1) {
                $active++;
            }
            if (strtolower((string) $u->getRole()) === 'admin') {
                $admins++;
            }
            $createdAt = (string) ($u->getCreatedAt() ?? '');
            if ($createdAt !== '' && strpos($createdAt, $nowMonth) === 0) {
                $newThisMonth++;
            }
        }

        echo json_encode([
            'success' => true,
            'stats' => [
                'total' => $total,
                'active' => $active,
                'admins' => $admins,
                'newThisMonth' => $newThisMonth,
            ],
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'create') {
        $payload = parseFormBody();
        $firstName = trim((string) payloadValue($payload, 'first_name', 'firstName', ''));
        $lastName = trim((string) payloadValue($payload, 'last_name', 'lastName', ''));
        $email = trim((string) payloadValue($payload, 'email', null, ''));
        $phone = trim((string) payloadValue($payload, 'phone', null, ''));
        $role = normalizeRole((string) payloadValue($payload, 'role', null, 'user'));
        $status = normalizeStatus(payloadValue($payload, 'status', null, 1));
        $country = trim((string) payloadValue($payload, 'country', 'location', ''));
        $avatarUrl = trim((string) payloadValue($payload, 'avatar_url', 'avatarUrl', ''));
        $badge = trim((string) payloadValue($payload, 'badge', null, ''));
        $bio = trim((string) payloadValue($payload, 'bio', null, ''));
        $title = trim((string) payloadValue($payload, 'title', null, ''));
        $skills = trim((string) payloadValue($payload, 'skills', null, ''));
        $xp = (int) payloadValue($payload, 'xp', null, 0);
        $isBlocked = (int) payloadValue($payload, 'is_blocked', 'isBlocked', 0);
        $faceDescriptor = payloadValue($payload, 'face_descriptor', 'faceDescriptor', null);
        $faceImagesPath = payloadValue($payload, 'face_images_path', 'faceImagesPath', null);
        $faceEnrolled = (int) payloadValue($payload, 'face_enrolled', 'faceEnrolled', 0);
        $faceEnrolledAt = payloadValue($payload, 'face_enrolled_at', 'faceEnrolledAt', null);
        $lastSeen = payloadValue($payload, 'last_seen', 'lastSeen', null);
        $password = (string) ($payload['password'] ?? 'ChangeMe123!');

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Please fill all required fields: first name, last name, email, password.',
                'errors' => [
                    'first_name' => $firstName === '' ? 'First name is required.' : null,
                    'last_name' => $lastName === '' ? 'Last name is required.' : null,
                    'email' => $email === '' ? 'Email is required.' : null,
                    'password' => $password === '' ? 'Password is required.' : null,
                ],
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if ($userController->emailExists($email)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Email already exists.']);
            exit;
        }

        $user = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone,
            $role,
            $status,
            date('Y-m-d H:i:s')
        );
        $user->setAvatarUrl($avatarUrl !== '' ? $avatarUrl : null);
        $user->setBadge($badge !== '' ? $badge : null);
        $user->setCountry($country !== '' ? $country : null);
        $user->setBio($bio !== '' ? $bio : null);
        $user->setTitle($title !== '' ? $title : null);
        $user->setSkills($skills !== '' ? $skills : null);
        $user->setXp(max(0, $xp));
        $user->setIsBlocked($isBlocked === 1 ? 1 : 0);
        $user->setFaceDescriptor($faceDescriptor ?: null);
        $user->setFaceImagesPath($faceImagesPath ?: null);
        $user->setFaceEnrolled($faceEnrolled === 1 ? 1 : 0);
        $user->setFaceEnrolledAt($faceEnrolledAt ?: null);
        $user->setLastSeen($lastSeen ?: null);

        $id = $userController->addUser($user);
        if (!$id) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Could not create user: ' . ((string) $userController->getLastError() ?: 'database error'),
            ]);
            exit;
        }

        $newUser = $userController->getUserById((int) $id);
        echo json_encode(['success' => true, 'user' => $newUser ? userToArray($newUser) : null]);
        exit;
    }

    if ($method === 'POST' && $action === 'update') {
        $payload = parseFormBody();
        $id = (int) ($payload['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid id.']);
            exit;
        }

        $existing = $userController->getUserById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $firstName = trim((string) payloadValue($payload, 'first_name', 'firstName', $existing->getFirstName()));
        $lastName = trim((string) payloadValue($payload, 'last_name', 'lastName', $existing->getLastName()));
        $email = trim((string) payloadValue($payload, 'email', null, $existing->getEmail()));
        $phone = trim((string) payloadValue($payload, 'phone', null, $existing->getPhone()));
        $role = normalizeRole((string) payloadValue($payload, 'role', null, $existing->getRole()));
        $status = normalizeStatus(payloadValue($payload, 'status', null, $existing->getStatus()));
        $country = trim((string) payloadValue($payload, 'country', 'location', $existing->getCountry()));
        $avatarUrl = trim((string) payloadValue($payload, 'avatar_url', 'avatarUrl', (string) ($existing->getAvatarUrl() ?? '')));
        $badge = trim((string) payloadValue($payload, 'badge', null, (string) ($existing->getBadge() ?? '')));
        $bio = trim((string) payloadValue($payload, 'bio', null, (string) ($existing->getBio() ?? '')));
        $title = trim((string) payloadValue($payload, 'title', null, (string) ($existing->getTitle() ?? '')));
        $skills = trim((string) payloadValue($payload, 'skills', null, (string) ($existing->getSkills() ?? '')));
        $xp = (int) payloadValue($payload, 'xp', null, $existing->getXp());
        $isBlocked = (int) payloadValue($payload, 'is_blocked', 'isBlocked', $existing->getIsBlocked());
        $faceDescriptor = payloadValue($payload, 'face_descriptor', 'faceDescriptor', $existing->getFaceDescriptor());
        $faceImagesPath = payloadValue($payload, 'face_images_path', 'faceImagesPath', $existing->getFaceImagesPath());
        $faceEnrolled = (int) payloadValue($payload, 'face_enrolled', 'faceEnrolled', $existing->getFaceEnrolled());
        $faceEnrolledAt = payloadValue($payload, 'face_enrolled_at', 'faceEnrolledAt', $existing->getFaceEnrolledAt());
        $lastSeen = payloadValue($payload, 'last_seen', 'lastSeen', $existing->getLastSeen());
        $newPassword = (string) payloadValue($payload, 'password', null, '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Please fill all required fields: first name, last name, email.',
                'errors' => [
                    'first_name' => $firstName === '' ? 'First name is required.' : null,
                    'last_name' => $lastName === '' ? 'Last name is required.' : null,
                    'email' => $email === '' ? 'Email is required.' : null,
                ],
            ]);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if ($userController->emailExists($email, $id)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Email already in use.']);
            exit;
        }

        $existing->setFirstName($firstName);
        $existing->setLastName($lastName);
        $existing->setEmail($email);
        $existing->setPhone($phone);
        $existing->setRole($role);
        $existing->setStatus($status);
        $existing->setCountry($country !== '' ? $country : null);
        $existing->setAvatarUrl($avatarUrl !== '' ? $avatarUrl : null);
        $existing->setBadge($badge !== '' ? $badge : null);
        $existing->setBio($bio !== '' ? $bio : null);
        $existing->setTitle($title !== '' ? $title : null);
        $existing->setSkills($skills !== '' ? $skills : null);
        $existing->setXp(max(0, $xp));
        $existing->setIsBlocked($isBlocked === 1 ? 1 : 0);
        $existing->setFaceDescriptor($faceDescriptor ?: null);
        $existing->setFaceImagesPath($faceImagesPath ?: null);
        $existing->setFaceEnrolled($faceEnrolled === 1 ? 1 : 0);
        $existing->setFaceEnrolledAt($faceEnrolledAt ?: null);
        $existing->setLastSeen($lastSeen ?: null);

        if ($newPassword !== '') {
            if (strlen($newPassword) < 6) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
                exit;
            }
            $existing->setPassword(password_hash($newPassword, PASSWORD_DEFAULT));
        }

        $ok = $userController->updateUser($existing, $id);
        if (!$ok) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Could not update user: ' . ((string) $userController->getLastError() ?: 'database error'),
            ]);
            exit;
        }

        $updated = $userController->getUserById($id);
        if ((int) ($_SESSION['auth_user']['id'] ?? 0) === $id && $updated) {
            $_SESSION['auth_user']['first_name'] = $updated->getFirstName();
            $_SESSION['auth_user']['last_name'] = $updated->getLastName();
            $_SESSION['auth_user']['email'] = $updated->getEmail();
            $_SESSION['auth_user']['role'] = $updated->getRole();
            $_SESSION['auth_user']['avatar_url'] = $updated->getAvatarUrl();
        }

        echo json_encode(['success' => true, 'user' => $updated ? userToArray($updated) : null]);
        exit;
    }

    if ($method === 'POST' && $action === 'profile_update') {
        $payload = parseFormBody();
        $id = (int) ($_SESSION['auth_user']['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }

        $existing = $userController->getUserById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $firstName = trim((string) payloadValue($payload, 'first_name', null, $existing->getFirstName()));
        $lastName = trim((string) payloadValue($payload, 'last_name', null, $existing->getLastName()));
        $email = trim((string) payloadValue($payload, 'email', null, $existing->getEmail()));
        $phone = trim((string) payloadValue($payload, 'phone', null, $existing->getPhone()));
        $country = trim((string) payloadValue($payload, 'country', null, (string) $existing->getCountry()));
        $bio = trim((string) payloadValue($payload, 'bio', null, (string) $existing->getBio()));
        $title = trim((string) payloadValue($payload, 'title', null, (string) $existing->getTitle()));
        $skills = trim((string) payloadValue($payload, 'skills', null, (string) $existing->getSkills()));
        $avatarUrl = trim((string) payloadValue($payload, 'avatar_url', null, (string) $existing->getAvatarUrl()));

        if ($firstName === '' || $lastName === '' || $email === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'First name, last name and email are required.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        if ($userController->emailExists($email, $id)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Email already in use.']);
            exit;
        }

        $ok = $userController->updateProfile($id, [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'country' => $country,
            'bio' => $bio,
            'title' => $title,
            'skills' => $skills,
            'avatar_url' => $avatarUrl,
        ]);

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Could not update profile.']);
            exit;
        }

        $updated = $userController->getUserById($id);
        $_SESSION['auth_user']['first_name'] = $firstName;
        $_SESSION['auth_user']['last_name'] = $lastName;
        $_SESSION['auth_user']['email'] = $email;
        $_SESSION['auth_user']['avatar_url'] = $avatarUrl !== '' ? $avatarUrl : null;

        echo json_encode(['success' => true, 'user' => $updated ? userToArray($updated) : null]);
        exit;
    }

    if ($method === 'POST' && $action === 'delete') {
        $payload = parseFormBody();
        $id = (int) ($payload['id'] ?? 0);

        if ($id === (int) ($_SESSION['auth_user']['id'] ?? -1)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own active session user.']);
            exit;
        }

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid id.']);
            exit;
        }

        $ok = $userController->deleteUser($id);
        if (!$ok) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Could not delete user: ' . ((string) $userController->getLastError() ?: 'database error'),
            ]);
            exit;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'POST' && $action === 'toggle') {
        $payload = parseFormBody();
        $id = (int) ($payload['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid id.']);
            exit;
        }

        $next = $userController->toggleBlock($id);
        if ($next === false) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }

        $user = $userController->getUserById($id);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found after update.']);
            exit;
        }

        $user->setStatus($next === 1 ? 0 : 1);
        $userController->updateUser($user, $id);

        $fresh = $userController->getUserById($id);
        echo json_encode(['success' => true, 'user' => $fresh ? userToArray($fresh) : null]);
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
