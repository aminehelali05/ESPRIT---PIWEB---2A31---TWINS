<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $status = (int)($_POST['status'] ?? 1);
    $country = trim($_POST['country'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $badge = trim($_POST['badge'] ?? '');
    $xp = max(0, (int)($_POST['xp'] ?? 0));
    $avatarUrl = trim($_POST['avatar_url'] ?? '');

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must contain at least 6 characters.';
    } elseif ($controller->emailExists($email)) {
        $error = 'Email already exists.';
    } else {
        $user = new User($firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $role, $status, date('Y-m-d H:i:s'));
        $user->setCountry($country ?: null);
        $user->setTitle($title ?: null);
        $user->setSkills($skills ?: null);
        $user->setBio($bio ?: null);
        $user->setBadge($badge ?: null);
        $user->setXp($xp);
        $user->setAvatarUrl($avatarUrl ?: null);

        if ($controller->addUser($user) !== false) {
            header('Location: user_list.php?success=created');
            exit;
        }

        $error = 'Unable to create user now. Try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User | Voices Of Peace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user-system.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="user-system">
<div class="us-shell us-fade-up">
    <header class="us-topbar">
        <a class="us-brand" href="user_list.php"><span class="us-brand-dot">VoP</span> User Creation</a>
        <a class="us-btn us-btn-ghost" href="user_list.php"><i data-lucide="arrow-left"></i> Back to list</a>
    </header>

    <?php if ($error !== ''): ?>
        <div class="us-card" style="border-color:rgba(251,113,133,.38); color:#fecdd3;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="us-card us-card-3d">
        <h1 class="us-card-title">Create New User</h1>
        <p class="us-card-subtitle">Reconstructed from scratch with strict form validation and clean MVC binding.</p>

        <form id="createUserForm" method="post" style="margin-top:14px;">
            <div class="us-form-grid">
                <label class="us-field">
                    <span class="us-label">First Name *</span>
                    <input class="us-input" name="first_name" data-validate="required" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                    <span class="us-error" data-error-for="first_name">First name is required.</span>
                </label>
                <label class="us-field">
                    <span class="us-label">Last Name *</span>
                    <input class="us-input" name="last_name" data-validate="required" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                    <span class="us-error" data-error-for="last_name">Last name is required.</span>
                </label>
                <label class="us-field full">
                    <span class="us-label">Email *</span>
                    <input class="us-input" name="email" type="email" data-validate="required|email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    <span class="us-error" data-error-for="email">Email must be valid.</span>
                </label>
                <label class="us-field">
                    <span class="us-label">Password *</span>
                    <input class="us-input" id="passwordInput" name="password" type="password" data-validate="required|password">
                    <span id="passwordMeter" class="us-card-subtitle"></span>
                    <span class="us-error" data-error-for="password">Password must be at least 6 characters.</span>
                </label>
                <label class="us-field">
                    <span class="us-label">Phone</span>
                    <input class="us-input" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </label>
                <label class="us-field">
                    <span class="us-label">Role</span>
                    <select class="us-select" name="role">
                        <option value="user">User</option>
                        <option value="manager" <?= (($_POST['role'] ?? '') === 'manager') ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </label>
                <label class="us-field">
                    <span class="us-label">Status</span>
                    <select class="us-select" name="status">
                        <option value="1">Active</option>
                        <option value="0" <?= (($_POST['status'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </label>
                <label class="us-field">
                    <span class="us-label">Country</span>
                    <input class="us-input" name="country" value="<?= htmlspecialchars($_POST['country'] ?? '') ?>">
                </label>
                <label class="us-field">
                    <span class="us-label">Title</span>
                    <input class="us-input" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                </label>
                <label class="us-field">
                    <span class="us-label">Badge</span>
                    <input class="us-input" name="badge" value="<?= htmlspecialchars($_POST['badge'] ?? '') ?>">
                </label>
                <label class="us-field">
                    <span class="us-label">XP</span>
                    <input class="us-input" name="xp" type="number" min="0" value="<?= htmlspecialchars($_POST['xp'] ?? '0') ?>">
                </label>
                <label class="us-field full">
                    <span class="us-label">Skills (comma-separated)</span>
                    <input class="us-input" name="skills" value="<?= htmlspecialchars($_POST['skills'] ?? '') ?>">
                </label>
                <label class="us-field full">
                    <span class="us-label">Avatar URL</span>
                    <input class="us-input" name="avatar_url" type="url" value="<?= htmlspecialchars($_POST['avatar_url'] ?? '') ?>">
                </label>
                <label class="us-field full">
                    <span class="us-label">Bio</span>
                    <textarea class="us-textarea" name="bio"><?= htmlspecialchars($_POST['bio'] ?? '') ?></textarea>
                </label>
            </div>

            <div class="us-row" style="margin-top:16px;">
                <button class="us-btn us-btn-primary" type="submit"><i data-lucide="save"></i> Create User</button>
                <a class="us-btn us-btn-ghost" href="user_list.php">Cancel</a>
            </div>
        </form>
    </section>
</div>

<script src="../assets/js/user-system.js"></script>
<script>
  if (window.lucide) { window.lucide.createIcons(); }
  UserSystem.initValidation('#createUserForm');
  UserSystem.initPasswordMeter('#passwordInput', '#passwordMeter');
</script>
</body>
</html>
