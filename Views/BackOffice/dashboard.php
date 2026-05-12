<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Models/User.php');

if (!UserController::isAuthenticated()) {
    $_SESSION['flash_error'] = 'Please sign in to access the dashboard.';
    header('Location: ../FrontOffice/auth.php?mode=login');
    exit;
}

$currentSessionUser = UserController::currentUser() ?? [];
$currentSessionRole = strtolower((string) ($currentSessionUser['role'] ?? 'user'));
$isAdminSession = in_array($currentSessionRole, ['admin', 'administrator'], true) && UserController::isAdmin();

if (!$isAdminSession) {
    http_response_code(403);
    $safeName = htmlspecialchars(trim((string) ($currentSessionUser['first_name'] ?? 'Visitor')), ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied</title>
        <style>
            :root {
                color-scheme: dark;
                --bg: #050816;
                --panel: rgba(15, 23, 42, 0.72);
                --panel-border: rgba(148, 163, 184, 0.24);
                --text: #e2e8f0;
                --muted: #94a3b8;
                --accent: #38bdf8;
                --accent-2: #8b5cf6;
                --danger: #fb7185;
            }

            * { box-sizing: border-box; }
            html, body { height: 100%; }
            body {
                margin: 0;
                font-family: Inter, Poppins, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(56, 189, 248, 0.22), transparent 34%),
                    radial-gradient(circle at bottom right, rgba(139, 92, 246, 0.20), transparent 36%),
                    linear-gradient(135deg, #020617 0%, #0f172a 45%, #020617 100%);
                color: var(--text);
                overflow: hidden;
            }

            .deny-shell {
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 24px;
                position: relative;
                isolation: isolate;
            }

            .deny-orb {
                position: absolute;
                border-radius: 999px;
                filter: blur(18px);
                opacity: 0.7;
                animation: drift 10s ease-in-out infinite alternate;
            }
            .deny-orb.one { width: 180px; height: 180px; top: 10%; left: 8%; background: rgba(56, 189, 248, 0.22); }
            .deny-orb.two { width: 240px; height: 240px; right: 8%; bottom: 10%; background: rgba(139, 92, 246, 0.18); animation-duration: 12s; }

            .deny-card {
                width: min(720px, 100%);
                position: relative;
                z-index: 1;
                padding: 32px;
                border-radius: 28px;
                background: linear-gradient(180deg, rgba(15, 23, 42, 0.88), rgba(2, 6, 23, 0.82));
                border: 1px solid var(--panel-border);
                box-shadow: 0 24px 80px rgba(2, 6, 23, 0.7);
                backdrop-filter: blur(18px);
                transform: translateY(18px) scale(0.98);
                animation: popIn 700ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            .deny-badge {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 8px 14px;
                border-radius: 999px;
                background: rgba(248, 113, 113, 0.12);
                color: #fecaca;
                border: 1px solid rgba(248, 113, 113, 0.2);
                font-size: 0.78rem;
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .deny-grid {
                display: grid;
                grid-template-columns: 1.1fr 0.9fr;
                gap: 28px;
                align-items: center;
                margin-top: 22px;
            }

            .deny-title {
                margin: 0;
                font-size: clamp(2rem, 4vw, 3.6rem);
                line-height: 1.02;
                letter-spacing: -0.04em;
            }

            .deny-title span { color: var(--accent); }

            .deny-copy {
                margin: 14px 0 0;
                color: var(--muted);
                font-size: 1rem;
                line-height: 1.7;
                max-width: 52ch;
            }

            .deny-meta {
                margin-top: 18px;
                display: grid;
                gap: 12px;
                color: #cbd5e1;
                font-size: 0.92rem;
            }

            .deny-meta strong { color: #fff; }

            .deny-actions {
                margin-top: 28px;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .deny-btn {
                appearance: none;
                border: 0;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                padding: 14px 18px;
                border-radius: 14px;
                font-weight: 600;
                transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease, opacity 180ms ease;
            }

            .deny-btn:hover { transform: translateY(-2px); }
            .deny-btn.primary {
                background: linear-gradient(135deg, var(--accent), var(--accent-2));
                color: white;
                box-shadow: 0 16px 32px rgba(56, 189, 248, 0.22);
            }
            .deny-btn.secondary {
                background: rgba(148, 163, 184, 0.12);
                color: #e2e8f0;
                border: 1px solid rgba(148, 163, 184, 0.16);
            }

            .deny-illustration {
                display: grid;
                place-items: center;
                min-height: 320px;
                position: relative;
            }

            .lock-wrap {
                width: min(260px, 100%);
                aspect-ratio: 1;
                display: grid;
                place-items: center;
                position: relative;
            }

            .lock-ring {
                position: absolute;
                inset: 6%;
                border-radius: 50%;
                border: 1px solid rgba(148, 163, 184, 0.18);
                animation: spin 16s linear infinite;
            }

            .lock-core {
                width: 66%;
                height: 66%;
                border-radius: 28px;
                background: linear-gradient(180deg, rgba(30, 41, 59, 0.96), rgba(15, 23, 42, 0.96));
                border: 1px solid rgba(148, 163, 184, 0.18);
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), 0 18px 40px rgba(2, 6, 23, 0.45);
                display: grid;
                place-items: center;
                position: relative;
                animation: float 5s ease-in-out infinite;
            }

            .lock-core svg { width: 68%; height: 68%; color: #60a5fa; }

            .lock-glow {
                position: absolute;
                width: 140px;
                height: 140px;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(56, 189, 248, 0.28), transparent 70%);
                filter: blur(8px);
                animation: pulse 2.8s ease-in-out infinite;
            }

            .deny-foot {
                margin-top: 20px;
                color: #94a3b8;
                font-size: 0.82rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .deny-foot .dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background: #fb7185;
                box-shadow: 0 0 18px rgba(251, 113, 133, 0.8);
            }

            @keyframes popIn {
                from { opacity: 0; transform: translateY(18px) scale(0.98); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            @keyframes float {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            @keyframes pulse {
                0%, 100% { transform: scale(0.95); opacity: 0.7; }
                50% { transform: scale(1.08); opacity: 1; }
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            @keyframes drift {
                from { transform: translate3d(0, 0, 0) scale(1); }
                to { transform: translate3d(18px, -22px, 0) scale(1.08); }
            }

            @media (max-width: 860px) {
                .deny-card { padding: 24px; }
                .deny-grid { grid-template-columns: 1fr; }
                .deny-illustration { min-height: 220px; }
            }

            @media (max-width: 560px) {
                body { overflow-y: auto; }
                .deny-shell { padding: 16px; }
                .deny-actions { flex-direction: column; }
                .deny-btn { width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class="deny-shell">
            <div class="deny-orb one"></div>
            <div class="deny-orb two"></div>

            <main class="deny-card" role="alert" aria-live="polite">
                <span class="deny-badge">403 · Admin only</span>
                <div class="deny-grid">
                    <section>
                        <h1 class="deny-title">Access <span>denied</span></h1>
                        <p class="deny-copy">
                            Sorry <?= $safeName ?>, this dashboard is reserved for administrators only.
                            Your session does not have permission to open back-office tools.
                        </p>
                        <div class="deny-meta">
                            <div><strong>Required role:</strong> Administrator</div>
                            <div><strong>Current access:</strong> Restricted</div>
                            <div><strong>Next step:</strong> Return to the front office or sign in with an admin account.</div>
                        </div>
                        <div class="deny-actions">
                            <a class="deny-btn primary" href="../FrontOffice/home.php">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/><path d="M9 12h12"/></svg>
                                Go to home
                            </a>
                            <a class="deny-btn secondary" href="../FrontOffice/auth.php?mode=login">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18"/></svg>
                                Admin login
                            </a>
                        </div>
                        <div class="deny-foot"><span class="dot"></span> Back-office entry is protected.</div>
                    </section>

                    <aside class="deny-illustration" aria-hidden="true">
                        <div class="lock-wrap">
                            <div class="lock-glow"></div>
                            <div class="lock-ring"></div>
                            <div class="lock-core">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="5" y="10" width="14" height="10" rx="3"></rect>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                    <circle cx="12" cy="15" r="1.2" fill="currentColor" stroke="none"></circle>
                                </svg>
                            </div>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const card = document.querySelector('.deny-card');
                const buttons = document.querySelectorAll('.deny-btn');
                if (card) {
                    card.animate([
                        { transform: 'translateY(18px) scale(0.98)', opacity: 0 },
                        { transform: 'translateY(0) scale(1)', opacity: 1 }
                    ], { duration: 700, easing: 'cubic-bezier(0.16, 1, 0.3, 1)', fill: 'forwards' });
                }

                buttons.forEach((button, index) => {
                    button.style.transitionDelay = `${index * 60}ms`;
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

$currentSessionUser = UserController::currentUser() ?? [];
$userController = new UserController();
$currentSessionUserId = (int) ($currentSessionUser['id'] ?? 0);

if ($isAdminSession && $currentSessionUserId <= 0) {
    $adminUser = $userController->getUserByEmail('admin@diversity.is');
    $currentSessionUserId = $adminUser ? (int) $adminUser->getId() : 1;
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $jsonInput = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($jsonInput)) {
        $jsonInput = [];
    }

    $respond = static function (array $payload, int $code = 200): void {
        http_response_code($code);
        echo json_encode($payload);
        exit;
    };

    $userToArray = static function (User $u): array {
        return [
            'id' => (int) $u->getId(),
            'first_name' => (string) $u->getFirstName(),
            'last_name' => (string) $u->getLastName(),
            'email' => (string) $u->getEmail(),
            'phone' => (string) ($u->getPhone() ?? ''),
            'role' => (string) ($u->getRole() ?? 'user'),
            'status' => (int) ($u->getStatus() ?? 1),
            'is_blocked' => (int) ($u->getIsBlocked() ?? 0),
            'avatar_url' => (string) ($u->getAvatarUrl() ?? ''),
            'country' => (string) ($u->getCountry() ?? ''),
            'title' => (string) ($u->getTitle() ?? ''),
            'skills' => (string) ($u->getSkills() ?? ''),
            'bio' => (string) ($u->getBio() ?? ''),
            'xp' => (int) ($u->getXp() ?? 0),
            'face_enrolled' => (int) ($u->getFaceEnrolled() ?? 0),
            'face_images_path' => (string) ($u->getFaceImagesPath() ?? ''),
            'face_descriptor' => (string) ($u->getFaceDescriptor() ?? ''),
            'face_enrolled_at' => (string) ($u->getFaceEnrolledAt() ?? ''),
            'last_seen' => (string) ($u->getLastSeen() ?? ''),
            'created_at' => (string) ($u->getCreatedAt() ?? ''),
        ];
    };

    $clean = static function ($value): string {
        return trim((string) ($value ?? ''));
    };
    $normalizePhone = static function ($value): string {
        return preg_replace('/[^\d+]/', '', trim((string) ($value ?? '')));
    };

    $normalizeRole = static function ($value): string {
        $role = strtolower(trim((string) ($value ?? '')));
        if ($role === 'freelancer' || $role === 'client') {
            return $role;
        }
        return 'client';
    };

    $sanitizeFileName = static function (string $fileName): string {
        $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
        return trim((string) $normalized, '._-') ?: ('avatar_' . date('Ymd_His'));
    };

    $buildGeneratedAvatarUrl = static function (string $firstName, string $lastName, string $email = ''): string {
        $seed = trim($firstName . '-' . $lastName . '-' . $email);
        if ($seed === '') {
            $seed = 'diversity-user-' . date('YmdHis');
        }
        return 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . rawurlencode($seed);
    };

    $storeAvatarImage = static function ($rawImageData, string $preferredFileName = '') use ($sanitizeFileName): ?string {
        $raw = trim((string) ($rawImageData ?? ''));
        if ($raw === '') {
            return null;
        }

        $mime = 'image/png';
        $binary = '';
        if (preg_match('/^data:(image\/(png|jpeg|jpg|webp|svg\+xml));base64,(.+)$/i', $raw, $matches)) {
            $mime = strtolower((string) ($matches[1] ?? 'image/png'));
            $binary = base64_decode((string) ($matches[3] ?? ''), true) ?: '';
        } else {
            $binary = base64_decode($raw, true) ?: '';
        }

        if ($binary === '') {
            return null;
        }

        $ext = 'png';
        if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
            $ext = 'jpg';
        } elseif (strpos($mime, 'webp') !== false) {
            $ext = 'webp';
        } elseif (strpos($mime, 'svg+xml') !== false) {
            $ext = 'svg';
        }

        $baseName = $sanitizeFileName($preferredFileName);
        if (stripos($baseName, '.'.$ext) === false) {
            $baseName .= '.' . $ext;
        }

        $facesDir = realpath(__DIR__ . '/../../assets') . DIRECTORY_SEPARATOR . 'faces';
        if (!is_dir($facesDir)) {
            @mkdir($facesDir, 0775, true);
        }
        if (!is_dir($facesDir) || !is_writable($facesDir)) {
            return null;
        }

        $finalName = uniqid('face_', true) . '_' . $baseName;
        $absolutePath = $facesDir . DIRECTORY_SEPARATOR . $finalName;
        $written = @file_put_contents($absolutePath, $binary);
        if ($written === false) {
            return null;
        }

        return '../../assets/faces/' . $finalName;
    };

    $action = strtolower((string) ($_GET['action'] ?? ''));

    if ($action === 'list') {
        $users = $userController->listUsers();
        $payload = [];
        foreach ($users as $u) {
            if ($u instanceof User) {
                $payload[] = $userToArray($u);
            }
        }
        $respond(['success' => true, 'users' => $payload]);
    }

    if ($action === 'stats') {
        $users = $userController->listUsers();
        $thisMonth = date('Y-m');
        $newThisMonth = 0;
        foreach ($users as $u) {
            if (!($u instanceof User)) {
                continue;
            }
            $created = (string) ($u->getCreatedAt() ?? '');
            if ($created !== '' && strpos($created, $thisMonth) === 0) {
                $newThisMonth++;
            }
        }
        $respond([
            'success' => true,
            'stats' => [
                'total' => $userController->countUsers(),
                'admins' => $userController->countByRole('admin'),
                'newThisMonth' => $newThisMonth,
            ],
        ]);
    }

    if ($action === 'create') {
        $firstName = $clean($jsonInput['first_name'] ?? '');
        $lastName = $clean($jsonInput['last_name'] ?? '');
        $email = $clean($jsonInput['email'] ?? '');
        $password = (string) ($jsonInput['password'] ?? '');
        $phone = $normalizePhone($jsonInput['phone'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $phone === '') {
            $respond(['success' => false, 'message' => 'First name, last name, email, password, and phone are required.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $respond(['success' => false, 'message' => 'Invalid email format.'], 400);
        }

        if ($userController->emailExists($email)) {
            $respond(['success' => false, 'message' => 'Email already exists.'], 400);
        }

        if (!preg_match('/^\+\d{8,15}$/', $phone)) {
            $respond(['success' => false, 'message' => 'Phone must start with +country code and contain 8 to 15 digits.'], 400);
        }

        $newUser = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone,
            $normalizeRole($jsonInput['role'] ?? 'client'),
            (int) ($jsonInput['status'] ?? 1),
            date('Y-m-d H:i:s')
        );

        $newUser->setIsBlocked((int) ($jsonInput['is_blocked'] ?? 0));
        $incomingAvatar = $clean($jsonInput['avatar_url'] ?? '');
        $newUser->setAvatarUrl($incomingAvatar !== '' ? $incomingAvatar : $buildGeneratedAvatarUrl($firstName, $lastName, $email));
        $incomingCountry = $clean($jsonInput['country'] ?? '');
        $newUser->setCountry($incomingCountry !== '' ? $incomingCountry : 'Unknown');
        $newUser->setSkills(null);
        $newUser->setBio($clean($jsonInput['bio'] ?? '') ?: null);
        $newUser->setFaceEnrolled((int) ($jsonInput['face_enrolled'] ?? 0));
        $newUser->setFaceImagesPath($clean($jsonInput['face_images_path'] ?? '') ?: null);
        $newUser->setFaceDescriptor($clean($jsonInput['face_descriptor'] ?? '') ?: null);
        $newUser->setFaceEnrolledAt($clean($jsonInput['face_enrolled_at'] ?? '') ?: null);
        $newUser->setLastSeen(null);

        $newId = $userController->addUser($newUser);
        if (!$newId) {
            $respond(['success' => false, 'message' => 'Could not create user.'], 500);
        }

        $saved = $userController->getUserById((int) $newId);
        $respond(['success' => true, 'user' => $saved instanceof User ? $userToArray($saved) : ['id' => (int) $newId]]);
    }

    if ($action === 'upload_avatar') {
        $imageData = (string) ($jsonInput['image_data'] ?? '');
        $fileName = $clean($jsonInput['file_name'] ?? 'avatar');
        $savedPath = $storeAvatarImage($imageData, $fileName);

        if (!$savedPath) {
            $respond(['success' => false, 'message' => 'Could not save avatar image.'], 500);
        }

        $respond(['success' => true, 'avatar_url' => $savedPath]);
    }

    if ($action === 'update') {
        $id = (int) ($jsonInput['id'] ?? 0);
        if ($id <= 0) {
            $respond(['success' => false, 'message' => 'Invalid user id.'], 400);
        }

        if ($id !== $currentSessionUserId) {
            $respond(['success' => false, 'message' => 'Direct editing of other users is not allowed.'], 403);
        }

        $existing = $userController->getUserById($id);
        if (!($existing instanceof User)) {
            $respond(['success' => false, 'message' => 'User not found.'], 404);
        }

        $email = $clean($jsonInput['email'] ?? $existing->getEmail());
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $respond(['success' => false, 'message' => 'Invalid email format.'], 400);
        }

        if ($userController->emailExists($email, $id)) {
            $respond(['success' => false, 'message' => 'Email already exists.'], 400);
        }

        $phone = $normalizePhone($jsonInput['phone'] ?? $existing->getPhone());
        if ($phone === '' || !preg_match('/^\+\d{8,15}$/', $phone)) {
            $respond(['success' => false, 'message' => 'Phone must start with +country code and contain 8 to 15 digits.'], 400);
        }

        $existing->setFirstName($clean($jsonInput['first_name'] ?? $existing->getFirstName()));
        $existing->setLastName($clean($jsonInput['last_name'] ?? $existing->getLastName()));
        $existing->setEmail($email);
        $existing->setPhone($phone);
        $existing->setRole($normalizeRole($jsonInput['role'] ?? $existing->getRole()));
        $existing->setStatus((int) ($jsonInput['status'] ?? $existing->getStatus()));
        $existing->setIsBlocked((int) ($jsonInput['is_blocked'] ?? $existing->getIsBlocked()));
        $incomingAvatar = $clean($jsonInput['avatar_url'] ?? $existing->getAvatarUrl());
        $existing->setAvatarUrl($incomingAvatar !== '' ? $incomingAvatar : $buildGeneratedAvatarUrl((string) $existing->getFirstName(), (string) $existing->getLastName(), (string) $existing->getEmail()));
        $incomingCountry = $clean($jsonInput['country'] ?? $existing->getCountry());
        $existing->setCountry($incomingCountry !== '' ? $incomingCountry : 'Unknown');
        $existing->setSkills($existing->getSkills());
        $existing->setBio($clean($jsonInput['bio'] ?? $existing->getBio()) ?: null);
        $existing->setFaceEnrolled((int) ($jsonInput['face_enrolled'] ?? $existing->getFaceEnrolled()));
        $existing->setFaceImagesPath($clean($jsonInput['face_images_path'] ?? $existing->getFaceImagesPath()) ?: null);
        $existing->setFaceDescriptor($clean($jsonInput['face_descriptor'] ?? $existing->getFaceDescriptor()) ?: null);
        $existing->setFaceEnrolledAt($clean($jsonInput['face_enrolled_at'] ?? $existing->getFaceEnrolledAt()) ?: null);
        $existing->setLastSeen($existing->getLastSeen());

        $newPassword = (string) ($jsonInput['password'] ?? '');
        if (trim($newPassword) !== '') {
            $existing->setPassword(password_hash($newPassword, PASSWORD_DEFAULT));
        }

        if (!$userController->updateUser($existing, $id)) {
            $respond(['success' => false, 'message' => 'Could not update user.'], 500);
        }

        $saved = $userController->getUserById($id);
        $respond(['success' => true, 'user' => $saved instanceof User ? $userToArray($saved) : ['id' => $id]]);
    }

    if ($action === 'toggle') {
        $id = (int) ($jsonInput['id'] ?? 0);
        if ($id <= 0) {
            $respond(['success' => false, 'message' => 'Invalid user id.'], 400);
        }

        $existing = $userController->getUserById($id);
        if (!($existing instanceof User)) {
            $respond(['success' => false, 'message' => 'User not found.'], 404);
        }

        $nextBlocked = (int) ($existing->getIsBlocked() ?? 0) === 1 ? 0 : 1;
        $existing->setIsBlocked($nextBlocked);
        if ($nextBlocked === 1) {
            $existing->setStatus(0);
        } else {
            $existing->setStatus(1);
        }

        if (!$userController->updateUser($existing, $id)) {
            $respond(['success' => false, 'message' => 'Could not toggle user status.'], 500);
        }

        $respond(['success' => true, 'is_blocked' => $nextBlocked, 'status' => (int) $existing->getStatus()]);
    }

    if ($action === 'delete') {
        $respond(['success' => false, 'message' => 'Direct deletion is disabled. Use delete requests workflow.'], 403);
    }

    if ($action === 'list_delete_requests') {
        if (!$isAdminSession) {
            $respond(['success' => false, 'message' => 'Only admins can view delete requests.'], 403);
        }

        $status = $clean($_GET['status'] ?? 'pending');
        $rows = $userController->getDeleteRequests($status !== '' ? $status : 'pending');
        $payload = array_map(static function ($row) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'requested_by' => (int) ($row['requested_by'] ?? 0),
                'reason' => (string) ($row['reason'] ?? ''),
                'status' => (string) ($row['status'] ?? 'pending'),
                'admin_note' => (string) ($row['admin_note'] ?? ''),
                'reviewed_by' => (int) ($row['reviewed_by'] ?? 0),
                'reviewed_at' => (string) ($row['reviewed_at'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }, $rows);

        $respond(['success' => true, 'requests' => $payload]);
    }

    if ($action === 'approve_delete_request') {
        if (!$isAdminSession) {
            $respond(['success' => false, 'message' => 'Only admins can approve delete requests.'], 403);
        }

        $requestId = (int) ($jsonInput['request_id'] ?? 0);
        if ($requestId <= 0) {
            $respond(['success' => false, 'message' => 'Invalid request id.'], 400);
        }

        if (!$userController->approveDeleteRequest($requestId, $currentSessionUserId)) {
            $respond(['success' => false, 'message' => 'Could not approve delete request.'], 500);
        }

        $respond(['success' => true]);
    }

    if ($action === 'reject_delete_request') {
        if (!$isAdminSession) {
            $respond(['success' => false, 'message' => 'Only admins can reject delete requests.'], 403);
        }

        $requestId = (int) ($jsonInput['request_id'] ?? 0);
        $note = $clean($jsonInput['admin_note'] ?? '');
        if ($requestId <= 0) {
            $respond(['success' => false, 'message' => 'Invalid request id.'], 400);
        }

        if (!$userController->rejectDeleteRequest($requestId, $currentSessionUserId, $note)) {
            $respond(['success' => false, 'message' => 'Could not reject delete request.'], 500);
        }

        $respond(['success' => true]);
    }

    $respond(['success' => false, 'message' => 'Unsupported action.'], 400);
}

$user = UserController::currentUser() ?? [];
$displayName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$displayName = $displayName !== '' ? $displayName : (string) ($user['username'] ?? 'Member User');
$displayEmail = (string) ($user['email'] ?? '');
$initials = strtoupper(substr((string) ($user['first_name'] ?? 'M'), 0, 1) . substr((string) ($user['last_name'] ?? 'U'), 0, 1));

// Premium, Minimal SVGs for Modules
$moduleShortcuts = [
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'label' => 'Users', 'kpi' => '132 Total', 'desc' => 'Manage accounts, roles, & access levels.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>', 
        'label' => 'Social feed', 'kpi' => '24 New', 'desc' => 'Moderate discussions and comments.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>', 
        'label' => 'Skills & Certs', 'kpi' => '12 Pending', 'desc' => 'Review and validate user skill sets.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>', 
        'label' => 'Projects', 'kpi' => '7 Active', 'desc' => 'Monitor platform-wide collaborations.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>', 
        'label' => 'Reviews', 'kpi' => '4.9 Avg', 'desc' => 'Analyze reputation metrics & feedback.'
    ],
    [
        'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>', 
        'label' => 'Challenges', 'kpi' => 'Daily Live', 'desc' => 'Configure daily quizzes and sprints.'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VoP Backend Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-form.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="../../assets/js/brainstorming-validation.js"></script>
</head>
<body data-current-user-id="<?= (int) ($user['id'] ?? 0) ?>">
    <div class="app-container">
        
        <!-- Elegant Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">VoP</div>
                <div class="brand-text">Diversity</div>
            </div>

            <nav class="nav-menu">
                <a href="#overview" class="nav-item active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Overview
                </a>
                <a href="#users" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    User
                </a>
                <a href="brainstorming_admin.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"/><path d="M12 7v5l3 3"/></svg>
                    Brainstorming
                </a>
                <a href="#modules" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    All Modules
                </a>
                <a href="#settings" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    Settings
                </a>
                <a href="../FrontOffice/home.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" x2="19" y1="12" y2="12"></line><polyline points="12 5 5 12 12 19"></polyline></svg>
                    Exit Backend
                </a>
            </nav>

            <div class="user-widget">
                <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($displayName) ?></strong>
                    <span>System Admin</span>
                </div>
                <a href="../../index.php?action=logout" class="logout-icon" aria-label="Sign out">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
                </a>
            </div>
        </aside>

        <!-- Main Content View -->
        <main class="main-content">
            
            <header class="top-header animate-enter">
                <div class="page-title">
                    <h1>Dashboard Overview</h1>
                    <p>Mon, April 7 — Welcome to your workspace.</p>
                </div>
                <div class="header-actions">
                    <div class="search-bar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                        <input type="text" placeholder="Search system...">
                    </div>
                </div>
            </header>

            <div class="bento-grid">
                
                <!-- KPI 1 -->
                <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.05s;">
                    <div>
                        <div class="kpi-h">Total Active Users</div>
                        <div class="kpi-v" id="kpiTotalUsers" data-counter="0">0</div>
                        <div class="kpi-badge">+12.5% this month</div>
                    </div>
                    <div class="icon-box icon-blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>

                <!-- KPI 2 -->
                <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.08s;">
                    <div>
                        <div class="kpi-h">Platform Reputation</div>
                        <div class="kpi-v" id="kpiAdmins" data-counter="0">0</div>
                        <div class="kpi-badge" style="color: #6366f1; background: rgba(99,102,241,0.1);">Stable growth</div>
                    </div>
                    <div class="icon-box icon-indigo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                </div>

                <!-- KPI 3 -->
                <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.11s;">
                    <div>
                        <div class="kpi-h">Completed Projects</div>
                        <div class="kpi-v" id="kpiNewThisMonth" data-counter="0">0</div>
                        <div class="kpi-badge" style="color: #8b5cf6; background: rgba(139,92,246,0.1);">+4 this week</div>
                    </div>
                    <div class="icon-box icon-purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                </div>

                <!-- Main User Table -->
                <div class="card span-8 animate-enter" style="animation-delay: 0.15s;" id="users">
                    <div class="section-head">
                        <h2>Users Management</h2>
                        <div class="section-actions">
                            <button class="btn-primary" id="addUserBtn" style="padding: 6px 14px; font-size: 0.8rem;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                                Add User
                            </button>
                        </div>
                    </div>
                    <p class="users-policy-note">Policy: admin can create users and review delete requests, but cannot directly edit/delete other profiles.</p>
                    <div style="overflow-x: auto;">
                        <table class="elegant-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card span-4 animate-enter" style="animation-delay: 0.16s;" id="deleteRequests">
                    <div class="section-head">
                        <h2>Delete Requests</h2>
                        <button class="btn-primary" id="refreshDeleteRequestsBtn" style="padding: 6px 14px; font-size: 0.8rem;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-2.64-6.36"></path><polyline points="21 3 21 9 15 9"></polyline></svg>
                            Refresh
                        </button>
                    </div>
                    <div class="delete-requests-list" id="deleteRequestsList">
                        <div class="empty-requests">No pending delete requests.</div>
                    </div>
                </div>

                <!-- Feed side column -->
                <div class="card span-4 animate-enter" style="animation-delay: 0.18s;">
                    <div class="section-head">
                        <h2>Activity Feed</h2>
                    </div>
                    <div class="feed-list">
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                            <div class="f-content"><p>Skill Verified</p><span>Sarah approved "Full-Stack Dev"</span></div>
                            <div class="f-time">2m</div>
                        </div>
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" x2="12" y1="8" y2="12"></line><line x1="12" x2="12.01" y1="16" y2="16"></line></svg></div>
                            <div class="f-content"><p>Report Filed</p><span>Automated system flagged comment</span></div>
                            <div class="f-time">1h</div>
                        </div>
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                            <div class="f-content"><p>New Enrollment</p><span>Alex joined the workspace</span></div>
                            <div class="f-time">3h</div>
                        </div>
                        <div class="feed-item">
                            <div class="f-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg></div>
                            <div class="f-content"><p>Global Post</p><span>Admin published an announcement</span></div>
                            <div class="f-time">5h</div>
                        </div>
                    </div>
                </div>

                <!-- Module Access Grid -->
                <div class="card span-12 animate-enter" style="animation-delay: 0.2s;" id="modules">
                    <div class="section-head">
                        <h2>System Modules</h2>
                        <span style="font-size:0.8rem; color:var(--b-text-muted);">Quick access to all CRUD panels</span>
                    </div>
                    <div class="module-list" style="grid-template-columns: repeat(3, 1fr);">
                        <?php foreach ($moduleShortcuts as $mod): ?>
                            <a href="#" class="dash-module">
                                <div class="dm-head">
                                    <div class="dm-icon"><?= $mod['svg'] ?></div>
                                    <span class="dm-kpi"><?= $mod['kpi'] ?></span>
                                </div>
                                <div>
                                    <h3 class="dm-title"><?= $mod['label'] ?></h3>
                                    <p class="dm-desc"><?= $mod['desc'] ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>    <!-- ══════════════════════════════════════════════════────
         NEW CLEAN USER MODAL — Add User / Edit User
         Uses .uf-* classes from user-form.css
    ─────────────────────────────────────────────────────── -->
    <div class="uf-overlay" id="userModal" role="dialog" aria-modal="true" aria-labelledby="ufModalTitle">
        <div class="uf-card">

            <!-- Header -->
            <div class="uf-header">
                <div class="uf-header-left">
                    <p class="uf-title" id="ufModalTitle">Create User</p>
                    <p class="uf-subtitle" id="ufModalSubtitle">Welcome to Diversity.is — your profile grows over time. <strong class="uf-member-since">Member since <?= date('F d, Y') ?></strong>.</p>
                </div>
                <button type="button" id="closeUserModal" class="uf-close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18"/><path d="M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <!-- Scrollable body -->
            <div class="uf-body">
                <form id="userForm" autocomplete="off">
                    <input type="hidden" id="formId" name="id">
                    <input type="hidden" id="formAvatarUrl" name="avatar_url">

                    <!-- Avatar upload -->
                    <div class="uf-avatar-row">
                        <div class="uf-avatar-shell">
                            <img id="formAvatarPreview" class="uf-avatar-img" src="../../assets/images/avatars/default-avatar.jpg" alt="Avatar preview">
                            <div id="formAvatarFallback" class="uf-avatar-fallback" aria-hidden="true">
                                <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Default avatar">
                                    <defs>
                                        <linearGradient id="avatarGradDashboard" x1="0" y1="0" x2="1" y2="1">
                                            <stop offset="0%" stop-color="#60a5fa"/>
                                            <stop offset="100%" stop-color="#6366f1"/>
                                        </linearGradient>
                                    </defs>
                                    <circle cx="40" cy="40" r="38" fill="url(#avatarGradDashboard)" opacity="0.22"/>
                                    <circle cx="40" cy="30" r="13" fill="#1e3a8a" opacity="0.9"/>
                                    <path d="M18 63c4-10 13-16 22-16s18 6 22 16" fill="#1e3a8a" opacity="0.9"/>
                                </svg>
                            </div>
                            <div class="uf-avatar-overlay">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                    <circle cx="12" cy="13" r="4"/>
                                </svg>
                            </div>
                            <div class="uf-avatar-shine"></div>
                        </div>
                        <div class="uf-avatar-info">
                            <span class="uf-avatar-label">Profile photo</span>
                            <span class="uf-avatar-hint">Upload a file or take a photo.</span>
                            <div class="uf-avatar-btns">
                                <input id="formAvatarFile" type="file" accept="image/*" hidden>
                                <button type="button" class="uf-btn uf-btn-ghost" id="pickAvatarFromFileBtn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17 8 12 3 7 8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    Upload
                                </button>
                                <button type="button" class="uf-btn uf-btn-accent" id="openAvatarCameraBtn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                        <circle cx="12" cy="13" r="4"/>
                                    </svg>
                                    Camera
                                </button>
                                <button type="button" class="uf-btn uf-btn-ghost" id="captureAvatarBtn" disabled>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M3 9a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 10.07 4h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 18.07 7H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/>
                                    </svg>
                                    Capture
                                </button>
                                <button type="button" class="uf-btn uf-btn-ghost" id="randomAvatarBtn" title="Random Cool Guy">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                      <path d="M16 3h5v5"/><path d="M4 20L21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/>
                                    </svg>
                                    Random
                                </button>
                            </div>
                            <video id="avatarCameraVideo" class="uf-camera-feed" autoplay playsinline muted></video>
                            <canvas id="avatarCaptureCanvas" style="display:none"></canvas>
                        </div>
                    </div>

                    <!-- Core fields 2-column grid -->
                    <div class="uf-grid">

                        <div class="uf-group">
                            <label class="uf-label" for="formFirstName">First name</label>
                            <input class="uf-input" id="formFirstName" name="first_name" type="text" placeholder=" " autocomplete="off">
                        </div>

                        <div class="uf-group">
                            <label class="uf-label" for="formLastName">Last name</label>
                            <input class="uf-input" id="formLastName" name="last_name" type="text" placeholder=" " autocomplete="off">
                        </div>

                        <div class="uf-group uf-span-2">
                            <label class="uf-label" for="formEmail">Email address</label>
                            <input class="uf-input" id="formEmail" name="email" type="email" placeholder=" " autocomplete="off">
                        </div>

                        <div class="uf-group" id="userFormPasswordGroup">
                            <label class="uf-label" for="formPassword">Password</label>
                            <input class="uf-input" id="formPassword" name="password" type="password" placeholder=" " autocomplete="new-password">
                        </div>

                        <div class="uf-group">
                            <label class="uf-label" for="formPhone">Phone</label>
                            <input class="uf-input" id="formPhone" name="phone" type="tel" placeholder=" " required inputmode="tel" autocomplete="tel" minlength="9" maxlength="20">
                        </div>

                        <!-- Section: Account -->
                        <div class="uf-section-sep uf-span-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>
                            </svg>
                            Account
                        </div>

                        <div class="uf-group">
                            <label class="uf-label" for="formRole">Role</label>
                            <select class="uf-input uf-select" id="formRole" name="role">
                                <option value="client">Client</option>
                                <option value="freelancer">Freelancer</option>
                            </select>
                        </div>

                        <div class="uf-group">
                            <label class="uf-label" for="formStatus">Status</label>
                            <select class="uf-input uf-select" id="formStatus" name="status">
                                <option value="1">Active</option>
                                <option value="0">Offline</option>
                            </select>
                        </div>


                        <div class="uf-group">
                            <label class="uf-label" for="formCountry">Country</label>
                            <input class="uf-input" id="formCountry" name="country" type="text" placeholder=" " readonly>
                            <div class="uf-country-meta" aria-live="polite">
                                <span class="uf-country-flag">🌍</span>
                                <span class="uf-country-prefix">No prefix detected</span>
                            </div>
                        </div>

                        <div class="uf-span-2 uf-globe-wrap" id="dbGlobeWrap">
                            <label class="uf-map-title" for="userGlobeMap">Pick country on globe</label>
                            <div class="uf-globe" id="userGlobeMap" aria-label="Interactive country picker map"></div>
                        </div>

                        <!-- Section: Profile -->
                        <div class="uf-section-sep uf-span-2">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                            </svg>
                            Profile
                        </div>

                        <div class="uf-group uf-span-2 is-textarea">
                            <label class="uf-label" for="formBio">Bio</label>
                            <textarea class="uf-input uf-textarea" id="formBio" name="bio" rows="3" placeholder=" "></textarea>
                        </div>

                        <!-- Hidden fields -->
                        <input type="hidden" id="formFaceEnrolled" name="face_enrolled" value="0">
                        <input type="hidden" id="formLatitude">
                        <input type="hidden" id="formLongitude">
                        <input type="hidden" id="formMapAddress">
                        <input type="hidden" id="formFaceImagesPath" name="face_images_path">
                        <input type="hidden" id="formFaceDescriptor" name="face_descriptor">

                    </div><!-- /uf-grid -->

                </form>
            </div><!-- /uf-body -->

            <!-- Actions footer -->
            <div class="uf-actions">
                <button type="button" class="uf-btn uf-btn-ghost" id="cancelUserModal">Cancel</button>
                <button type="submit" form="userForm" class="uf-btn uf-btn-primary" id="saveUserBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <path d="M17 21v-8H7v8M7 3v5h8"/>
                    </svg>
                    Save User
                </button>
            </div>

        </div>
    </div>

    <!-- Minimal JS execution -->
    <script src="../../assets/js/globe-explorer.js"></script>
    <script src="../../assets/js/user.js"></script>
    <script src="../../assets/js/skilluser.js"></script>
    <script src="../../assets/js/backoffice-dashboard.js"></script>
</body>
</html>
