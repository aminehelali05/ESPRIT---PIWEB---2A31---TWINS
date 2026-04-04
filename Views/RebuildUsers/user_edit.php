<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$id = (int)($_GET['id'] ?? 0);
$user = $id > 0 ? $controller->getUserById($id) : null;
if (!$user) { header('Location: user_list.php'); exit; }

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
    $isBlocked = (int)($_POST['is_blocked'] ?? 0);

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = 'First name, last name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } elseif ($controller->emailExists($email, $id)) {
        $error = 'Another user already uses this email.';
    } else {
        $newPassword = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $user->getPassword();

        $updated = new User($firstName, $lastName, $email, $newPassword, $phone, $role, $status, $user->getCreatedAt());
        $updated->setAvatarUrl($avatarUrl ?: null);
        $updated->setBadge($badge ?: null);
        $updated->setCountry($country ?: null);
        $updated->setBio($bio ?: null);
        $updated->setTitle($title ?: null);
        $updated->setSkills($skills ?: null);
        $updated->setXp($xp);
        $updated->setIsBlocked($isBlocked);
        $updated->setFaceDescriptor($user->getFaceDescriptor());
        $updated->setFaceImagesPath($user->getFaceImagesPath());
        $updated->setFaceEnrolled($user->getFaceEnrolled());
        $updated->setFaceEnrolledAt($user->getFaceEnrolledAt());

        $controller->updateUser($updated, $id);
        header('Location: user_list.php?success=updated');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Voices Of Peace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user-system.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="user-system">
<div class="us-shell us-fade-up">
    <header class="us-topbar">
        <a class="us-brand" href="user_list.php"><span class="us-brand-dot">VoP</span> Edit User</a>
        <div class="us-row">
            <a class="us-btn us-btn-ghost" href="user_details.php?id=<?= $id ?>">Details</a>
            <a class="us-btn us-btn-ghost" href="user_list.php">Back</a>
        </div>
    </header>

    <?php if ($error !== ''): ?>
        <div class="us-card" style="border-color:rgba(251,113,133,.38); color:#fecdd3;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="us-card us-card-3d">
        <h1 class="us-card-title">Edit <?= htmlspecialchars($user->getFullName()) ?></h1>
        <p class="us-card-subtitle">All fields are connected to backend update logic and validated before submit.</p>

        <form id="editUserForm" method="post" style="margin-top:14px;">
            <div class="us-form-grid">
                <label class="us-field"><span class="us-label">First Name *</span><input class="us-input" name="first_name" data-validate="required" value="<?= htmlspecialchars($_POST['first_name'] ?? $user->getFirstName()) ?>"><span class="us-error" data-error-for="first_name">Required.</span></label>
                <label class="us-field"><span class="us-label">Last Name *</span><input class="us-input" name="last_name" data-validate="required" value="<?= htmlspecialchars($_POST['last_name'] ?? $user->getLastName()) ?>"><span class="us-error" data-error-for="last_name">Required.</span></label>
                <label class="us-field full"><span class="us-label">Email *</span><input class="us-input" name="email" data-validate="required|email" value="<?= htmlspecialchars($_POST['email'] ?? $user->getEmail()) ?>"><span class="us-error" data-error-for="email">Invalid email.</span></label>
                <label class="us-field"><span class="us-label">New Password</span><input class="us-input" name="password" type="password" placeholder="Leave blank to keep current"></label>
                <label class="us-field"><span class="us-label">Phone</span><input class="us-input" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user->getPhone()) ?>"></label>
                <label class="us-field"><span class="us-label">Role</span><select class="us-select" name="role"><?php $role = $_POST['role'] ?? $user->getRole(); ?><option value="user" <?= $role==='user'?'selected':'' ?>>User</option><option value="manager" <?= $role==='manager'?'selected':'' ?>>Manager</option><option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option></select></label>
                <label class="us-field"><span class="us-label">Status</span><select class="us-select" name="status"><?php $status=(string)($_POST['status'] ?? $user->getStatus()); ?><option value="1" <?= $status==='1'?'selected':'' ?>>Active</option><option value="0" <?= $status==='0'?'selected':'' ?>>Inactive</option></select></label>
                <label class="us-field"><span class="us-label">Blocked</span><select class="us-select" name="is_blocked"><?php $blocked=(string)($_POST['is_blocked'] ?? $user->getIsBlocked()); ?><option value="0" <?= $blocked==='0'?'selected':'' ?>>No</option><option value="1" <?= $blocked==='1'?'selected':'' ?>>Yes</option></select></label>
                <label class="us-field"><span class="us-label">Country</span><input class="us-input" name="country" value="<?= htmlspecialchars($_POST['country'] ?? $user->getCountry()) ?>"></label>
                <label class="us-field"><span class="us-label">Title</span><input class="us-input" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $user->getTitle()) ?>"></label>
                <label class="us-field"><span class="us-label">Badge</span><input class="us-input" name="badge" value="<?= htmlspecialchars($_POST['badge'] ?? $user->getBadge()) ?>"></label>
                <label class="us-field"><span class="us-label">XP</span><input class="us-input" type="number" min="0" name="xp" value="<?= htmlspecialchars((string)($_POST['xp'] ?? $user->getXp())) ?>"></label>
                <label class="us-field full"><span class="us-label">Skills</span><input class="us-input" name="skills" value="<?= htmlspecialchars($_POST['skills'] ?? $user->getSkills()) ?>"></label>
                <label class="us-field full"><span class="us-label">Avatar URL</span><input class="us-input" type="url" name="avatar_url" value="<?= htmlspecialchars($_POST['avatar_url'] ?? $user->getAvatarUrl()) ?>"></label>
                <label class="us-field full"><span class="us-label">Bio</span><textarea class="us-textarea" name="bio"><?= htmlspecialchars($_POST['bio'] ?? $user->getBio()) ?></textarea></label>
            </div>

            <div class="us-row" style="margin-top:16px;">
                <button class="us-btn us-btn-primary" type="submit"><i data-lucide="save"></i> Save Changes</button>
                <a class="us-btn us-btn-ghost" href="user_list.php">Cancel</a>
            </div>
        </form>
    </section>
</div>

<script src="../assets/js/user-system.js"></script>
<script>
  if (window.lucide) { window.lucide.createIcons(); }
  UserSystem.initValidation('#editUserForm');
</script>
</body>
</html>
