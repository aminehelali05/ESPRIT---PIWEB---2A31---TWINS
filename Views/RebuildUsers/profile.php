<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$controller = new UserController();
$userId = (int)$_SESSION['user_id'];
$user = $controller->getUserById($userId);
if (!$user) {
    session_destroy();
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $payload = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'title' => trim($_POST['title'] ?? ''),
        'skills' => trim($_POST['skills'] ?? ''),
        'bio' => trim($_POST['bio'] ?? ''),
        'avatar_url' => trim($_POST['avatar_url'] ?? ''),
    ];

    if ($payload['first_name'] === '' || $payload['last_name'] === '' || $payload['email'] === '') {
        $error = 'First name, last name and email are required.';
    } elseif (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email format is invalid.';
    } elseif ($controller->emailExists($payload['email'], $userId)) {
        $error = 'Email already used by another account.';
    } else {
        if ($controller->updateProfile($userId, $payload)) {
            $_SESSION['user_name'] = trim($payload['first_name'] . ' ' . $payload['last_name']);
            $_SESSION['user_email'] = $payload['email'];
            $success = 'Profile updated successfully.';
            $user = $controller->getUserById($userId);
        } else {
            $error = 'Unable to update profile right now.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif ($controller->changePassword($userId, $newPassword)) {
        $success = 'Password changed successfully.';
    } else {
        $error = 'Password could not be changed.';
    }
}

$skillsArray = $user->getSkillsArray();
$displayName = $user->getFullName();
$initials = $user->getInitials();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Voices Of Peace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user-system.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="user-system">
<div class="us-shell us-fade-up">
    <header class="us-topbar">
        <a class="us-brand" href="home.php"><span class="us-brand-dot">VoP</span> Profile Center</a>
        <div class="us-row">
            <a class="us-btn us-btn-ghost" href="../BackOffice/user_list.php"><i data-lucide="layout-dashboard"></i> Dashboard</a>
            <a class="us-btn us-btn-danger" href="auth.php?logout=1"><i data-lucide="log-out"></i> Sign out</a>
        </div>
    </header>

    <?php if ($error !== ''): ?><div class="us-card" style="border-color:rgba(251,113,133,.38); color:#fecdd3;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="us-card" style="border-color:rgba(52,211,153,.34); color:#bbf7d0;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="us-main">
        <section class="us-card us-card-3d">
            <div class="us-row" style="justify-content:space-between; width:100%; align-items:center;">
                <div class="us-row" style="align-items:center;">
                    <?php if ($user->getAvatarUrl()): ?>
                        <img src="<?= htmlspecialchars($user->getAvatarUrl()) ?>" alt="avatar" style="width:74px;height:74px;border-radius:20px;border:1px solid rgba(255,255,255,.15);object-fit:cover;">
                    <?php else: ?>
                        <div class="us-user-avatar" style="width:74px;height:74px;border-radius:20px;font-size:24px;"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                    <div>
                        <h1 class="us-card-title" style="font-size:24px;"><?= htmlspecialchars($displayName) ?></h1>
                        <p class="us-card-subtitle"><?= htmlspecialchars($user->getEmail()) ?> · <?= htmlspecialchars($user->getCountry() ?: 'Global') ?></p>
                        <div class="us-row" style="margin-top:8px;">
                            <span class="us-badge active"><?= htmlspecialchars($user->getRole() ?: 'user') ?></span>
                            <?php if ($user->getBadge()): ?><span class="us-badge" style="background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.35);color:#fcd34d;"><?= htmlspecialchars($user->getBadge()) ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>
                <button class="us-btn us-btn-primary" id="openEditProfile"><i data-lucide="pencil"></i> Edit Profile</button>
            </div>
        </section>

        <section class="us-stat-grid">
            <article class="us-stat us-card-3d"><h4>XP</h4><p><?= (int)$user->getXp() ?></p></article>
            <article class="us-stat us-card-3d"><h4>Status</h4><p><?= (int)$user->getStatus() ? 'Active' : 'Inactive' ?></p></article>
            <article class="us-stat us-card-3d"><h4>Blocked</h4><p><?= (int)$user->getIsBlocked() ? 'Yes' : 'No' ?></p></article>
            <article class="us-stat us-card-3d"><h4>Skills</h4><p><?= count($skillsArray) ?></p></article>
        </section>

        <section class="us-card us-card-3d">
            <h2 class="us-card-title">About</h2>
            <p class="us-card-subtitle" style="margin-top:8px;"><?= nl2br(htmlspecialchars($user->getBio() ?: 'No biography configured yet.')) ?></p>
            <div class="us-row" style="margin-top:12px;">
                <?php if (count($skillsArray)): foreach ($skillsArray as $skill): ?>
                    <span class="us-badge active" style="background:rgba(195,230,203,.12); border-color:rgba(195,230,203,.33); color:#bbf7d0;"><?= htmlspecialchars(trim($skill)) ?></span>
                <?php endforeach; else: ?>
                    <span class="us-card-subtitle">No skills yet.</span>
                <?php endif; ?>
            </div>
        </section>

        <section class="us-card us-card-3d">
            <h2 class="us-card-title">Security</h2>
            <form id="passwordForm" method="post" style="margin-top:14px;">
                <input type="hidden" name="action" value="change_password">
                <div class="us-form-grid">
                    <label class="us-field"><span class="us-label">New Password *</span><input id="newPassword" class="us-input" type="password" name="new_password" data-validate="required|password"><span class="us-error" data-error-for="new_password">Minimum 6 characters.</span><span id="pwStrength" class="us-card-subtitle"></span></label>
                    <label class="us-field"><span class="us-label">Confirm Password *</span><input class="us-input" type="password" name="confirm_password" data-validate="required"><span class="us-error" data-error-for="confirm_password">Required.</span></label>
                </div>
                <div class="us-row" style="margin-top:14px;"><button class="us-btn us-btn-primary" type="submit"><i data-lucide="shield"></i> Change Password</button></div>
            </form>
        </section>
    </div>
</div>

<div class="us-modal" id="profileModal">
    <div class="us-modal-panel us-fade-up">
        <div class="us-toolbar" style="margin-bottom:10px;">
            <h2 class="us-card-title">Edit Profile</h2>
            <button class="us-btn us-btn-ghost" id="closeProfileModal">Close</button>
        </div>
        <form id="profileForm" method="post">
            <input type="hidden" name="action" value="update_profile">
            <div class="us-form-grid">
                <label class="us-field"><span class="us-label">First Name *</span><input class="us-input" name="first_name" data-validate="required" value="<?= htmlspecialchars($user->getFirstName()) ?>"><span class="us-error" data-error-for="first_name">Required.</span></label>
                <label class="us-field"><span class="us-label">Last Name *</span><input class="us-input" name="last_name" data-validate="required" value="<?= htmlspecialchars($user->getLastName()) ?>"><span class="us-error" data-error-for="last_name">Required.</span></label>
                <label class="us-field full"><span class="us-label">Email *</span><input class="us-input" type="email" name="email" data-validate="required|email" value="<?= htmlspecialchars($user->getEmail()) ?>"><span class="us-error" data-error-for="email">Invalid email.</span></label>
                <label class="us-field"><span class="us-label">Phone</span><input class="us-input" name="phone" value="<?= htmlspecialchars($user->getPhone()) ?>"></label>
                <label class="us-field"><span class="us-label">Country</span><input class="us-input" name="country" value="<?= htmlspecialchars($user->getCountry()) ?>"></label>
                <label class="us-field"><span class="us-label">Title</span><input class="us-input" name="title" value="<?= htmlspecialchars($user->getTitle()) ?>"></label>
                <label class="us-field"><span class="us-label">Avatar URL</span><input class="us-input" type="url" name="avatar_url" value="<?= htmlspecialchars($user->getAvatarUrl()) ?>"></label>
                <label class="us-field full"><span class="us-label">Skills</span><input class="us-input" name="skills" value="<?= htmlspecialchars($user->getSkills()) ?>"></label>
                <label class="us-field full"><span class="us-label">Bio</span><textarea class="us-textarea" name="bio"><?= htmlspecialchars($user->getBio()) ?></textarea></label>
            </div>
            <div class="us-row" style="margin-top:14px;"><button class="us-btn us-btn-primary" type="submit"><i data-lucide="save"></i> Save Profile</button></div>
        </form>
    </div>
</div>

<script src="../assets/js/user-system.js"></script>
<script>
  if (window.lucide) { window.lucide.createIcons(); }
  UserSystem.initValidation('#profileForm');
  UserSystem.initValidation('#passwordForm');
  UserSystem.initPasswordMeter('#newPassword', '#pwStrength');
  UserSystem.initModal('#openEditProfile', '#profileModal', '#closeProfileModal');
</script>
</body>
</html>
