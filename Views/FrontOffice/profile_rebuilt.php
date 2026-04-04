<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$error = '';
$success = '';

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    $users = $controller->listUsers();
    if (!empty($users)) {
        $currentUserId = (int) $users[0]->getId();
    }
}

$user = $currentUserId > 0 ? $controller->getUserById($currentUserId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = 'First name, last name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($controller->emailExists($email, $user->getId())) {
        $error = 'Email is already used by another account.';
    } else {
        $hashedPassword = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $user->getPassword();
        $updated = new User(
            $firstName,
            $lastName,
            $email,
            $hashedPassword,
            $phone,
            $user->getRole(),
            $user->getStatus(),
            $user->getCreatedAt()
        );
        $updated->setAvatarUrl($user->getAvatarUrl());
        $updated->setBadge($user->getBadge());
        $updated->setCountry($country);
        $updated->setBio($bio);
        $updated->setTitle($title);
        $updated->setSkills($skills);
        $updated->setXp($user->getXp());
        $updated->setIsBlocked($user->getIsBlocked());
        $updated->setFaceDescriptor($user->getFaceDescriptor());
        $updated->setFaceImagesPath($user->getFaceImagesPath());
        $updated->setFaceEnrolled($user->getFaceEnrolled());
        $updated->setFaceEnrolledAt($user->getFaceEnrolledAt());
                $controller->updateUser($updated, $user->getId());
                $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                $_SESSION['user_email'] = $email;
                header('Location: profile.php?success=updated');
                exit;
        }
}

if (isset($_GET['success']) && $_GET['success'] === 'updated') {
        $success = 'Profile updated successfully.';
        $user = $controller->getUserById($currentUserId);
}

$displayName = $user ? $user->getFullName() : 'Guest User';
$displayEmail = $user ? $user->getEmail() : 'guest@diversity.is';
$displayRole = $user ? strtolower((string) $user->getRole()) : 'user';
$displayCountry = $user ? (string) $user->getCountry() : '';
$displayPhone = $user ? (string) $user->getPhone() : '';
$displayTitle = $user ? (string) $user->getTitle() : '';
$displayBio = $user ? (string) $user->getBio() : '';
$displaySkills = $user ? (string) $user->getSkills() : '';
$displayCreated = $user ? (string) $user->getCreatedAt() : '';
$initials = '';
foreach (explode(' ', $displayName) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials ?: 'GU', 0, 2);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Diversity.is</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .profile-wrap { padding-top: 96px; }
        .panel { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; padding: 1rem; }
        [data-theme="light"] .panel { background: #fff; border-color: rgba(0,0,0,.08); }
        .hero { display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem; }
        .avatar { width: 84px; height: 84px; border-radius: 16px; background: linear-gradient(135deg, var(--color-accent), var(--color-accent-secondary)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:1.5rem; }
        .meta { color: var(--color-text-secondary); font-size: .82rem; display: flex; gap: .7rem; flex-wrap: wrap; }
        .pill { display:inline-flex; padding:2px 10px; border-radius:999px; font-size:.68rem; font-weight:700; text-transform:capitalize; }
        .pill-admin { color:#c084fc; background: rgba(168,85,247,.16); }
        .pill-manager { color:#fbbf24; background: rgba(251,191,36,.16); }
        .pill-user { color:#818cf8; background: rgba(129,140,248,.16); }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 840px){ .grid { grid-template-columns: 1fr; } }
        .field { display:grid; gap:.3rem; }
        .field label { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); font-weight:600; }
        .field input, .field textarea { width:100%; background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); border-radius:10px; color: var(--color-text-primary); padding:.65rem .8rem; font: inherit; }
        .field textarea { min-height: 110px; resize: vertical; }
        .field input:focus, .field textarea:focus { outline:none; border-color: rgba(99,102,241,.55); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .alert-ok { margin-bottom: .8rem; padding: .7rem .9rem; border-radius: 10px; background: rgba(34,197,94,.14); border: 1px solid rgba(34,197,94,.25); color:#86efac; }
        .alert-bad { margin-bottom: .8rem; padding: .7rem .9rem; border-radius: 10px; background: rgba(239,68,68,.14); border: 1px solid rgba(239,68,68,.25); color:#fca5a5; }
    </style>
</head>
<body class="grid-dot-bg">
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
            <div class="navbar-nav" id="navbar-nav">
                <a href="home.php">Home</a>
                <a href="social.php">Feed</a>
                <a href="skills.php">Skills</a>
                <a href="projects.php">Projects</a>
            </div>
            <div class="navbar-actions">
                <a href="auth.php" class="btn btn-ghost btn-sm">Sign In</a>
                <a href="../BackOffice/user_list.php" class="btn btn-primary btn-sm">Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="profile-main profile-wrap">
        <div class="container" style="display:grid; gap:1rem;">
            <section class="panel">
                <div class="hero">
                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                        <h2 style="font-size:1.4rem;"><?= htmlspecialchars($displayName) ?></h2>
                        <div class="meta">
                            <span><i data-lucide="mail" class="w-4 h-4"></i> <?= htmlspecialchars($displayEmail) ?></span>
                            <span><i data-lucide="calendar" class="w-4 h-4"></i> Joined <?= htmlspecialchars(substr($displayCreated, 0, 10)) ?></span>
                        </div>
                        <?php $pillClass = $displayRole === 'admin' ? 'pill-admin' : ($displayRole === 'manager' ? 'pill-manager' : 'pill-user'); ?>
                        <span class="pill <?= $pillClass ?>" style="margin-top:.45rem;"><?= htmlspecialchars($displayRole) ?></span>
                    </div>
                </div>
            </section>

            <section class="panel">
                <h3 style="font-size:1rem; margin-bottom:.8rem;">Edit Profile</h3>
                <?php if ($success): ?><div class="alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert-bad"><?= htmlspecialchars($error) ?></div><?php endif; ?>

                <form method="post" style="display:grid; gap:1rem;">
                    <div class="grid">
                        <div class="field"><label>First Name</label><input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? ($user ? $user->getFirstName() : '')) ?>" required></div>
                        <div class="field"><label>Last Name</label><input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? ($user ? $user->getLastName() : '')) ?>" required></div>
                    </div>
                    <div class="grid">
                        <div class="field"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $displayEmail) ?>" required></div>
                        <div class="field"><label>New Password</label><input type="password" name="password" placeholder="Leave blank to keep current password"></div>
                    </div>
                    <div class="grid">
                        <div class="field"><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $displayPhone) ?>"></div>
                        <div class="field"><label>Country</label><input type="text" name="country" value="<?= htmlspecialchars($_POST['country'] ?? $displayCountry) ?>"></div>
                    </div>
                    <div class="field"><label>Professional Title</label><input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $displayTitle) ?>"></div>
                    <div class="field"><label>Skills (comma-separated)</label><input type="text" name="skills" value="<?= htmlspecialchars($_POST['skills'] ?? $displaySkills) ?>" placeholder="PHP, MySQL, UI/UX"></div>
                    <div class="field"><label>Bio</label><textarea name="bio"><?= htmlspecialchars($_POST['bio'] ?? $displayBio) ?></textarea></div>
                    <div style="display:flex; justify-content:flex-end; gap:.6rem;">
                        <a href="../BackOffice/user_list.php" class="btn btn-ghost">Open Dashboard</a>
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>if(window.lucide){window.lucide.createIcons();}</script>
</body>
</html>
