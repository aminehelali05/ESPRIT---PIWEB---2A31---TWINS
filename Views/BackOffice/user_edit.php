<?php
session_start();
include_once __DIR__ . '/../../Controllers/UserController.php';

$controller = new UserController();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$user = $id > 0 ? $controller->getUserById($id) : null;

if (!$user) {
    header('Location: user_list.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $status = isset($_POST['status']) ? 1 : 0;

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = 'First name, last name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($controller->emailExists($email, $id)) {
        $error = 'Email already used by another account.';
    } else {
        $hashedPassword = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : $user->getPassword();
        $updated = new User(
            $firstName,
            $lastName,
            $email,
            $hashedPassword,
            $phone,
            $role,
            $status,
            $user->getCreatedAt()
        );
        $updated->setAvatarUrl($user->getAvatarUrl());
        $updated->setBadge($user->getBadge());
        $updated->setCountry($user->getCountry());
        $updated->setBio($user->getBio());
        $updated->setTitle($user->getTitle());
        $updated->setSkills($user->getSkills());
        $updated->setXp($user->getXp());
        $updated->setIsBlocked($user->getIsBlocked());
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
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit User - Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="sidebar-header"><div class="sidebar-brand"><div class="brand-icon">⬡</div><span class="sidebar-brand-text">Diversity.is</span></div></div>
      <nav class="sidebar-nav">
        <div class="sidebar-section-label">BackOffice</div>
        <a class="sidebar-link active" href="user_list.php">Users</a>
        <a class="sidebar-link" href="user_form.php">Add User</a>
      </nav>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header"><h3 style="font-size:.95rem;">Edit User #<?= (int) $user->getId() ?></h3></header>
      <section class="dashboard-content" style="padding:1.2rem; overflow:auto;">
        <div class="module-panel" style="max-width:760px; margin:0 auto; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1rem;">
          <?php if ($error): ?>
            <div style="margin-bottom:1rem; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.25); color:#fca5a5; border-radius:10px; padding:.75rem 1rem;"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <form method="post" style="display:grid; gap:.9rem;">
            <input type="hidden" name="id" value="<?= (int) $user->getId() ?>">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem;">
              <div><label>First Name</label><input class="form-input" type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? $user->getFirstName()) ?>"></div>
              <div><label>Last Name</label><input class="form-input" type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? $user->getLastName()) ?>"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem;">
              <div><label>Email</label><input class="form-input" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user->getEmail()) ?>"></div>
              <div><label>New Password (optional)</label><input class="form-input" type="password" name="password" placeholder="Leave blank to keep current"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem;">
              <div><label>Phone</label><input class="form-input" type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user->getPhone()) ?>"></div>
              <div><label>Role</label>
                <?php $selectedRole = strtolower($_POST['role'] ?? $user->getRole() ?? 'user'); ?>
                <select class="form-input" name="role">
                  <option value="user" <?= $selectedRole === 'user' ? 'selected' : '' ?>>User</option>
                  <option value="manager" <?= $selectedRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                  <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
            </div>
            <?php $isActive = isset($_POST['status']) ? true : ((int) $user->getStatus() === 1); ?>
            <label><input type="checkbox" name="status" <?= $isActive ? 'checked' : '' ?>> Active account</label>
            <div style="display:flex; gap:.7rem; justify-content:flex-end;">
              <a href="user_list.php" class="btn btn-ghost">Cancel</a>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
