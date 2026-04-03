<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$error = '';
$success = '';
$editingUser = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && !empty($_POST['id'])) {
        $userController->deleteUser((int) $_POST['id']);
        header('Location: profile.php?success=deleted#crud');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $error = 'First name, last name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } else {
            if ($action === 'update') {
                $current = $userController->getUserById($id);
                if (!$current) {
                    $error = 'User not found.';
                } elseif ($userController->emailExists($email, $id)) {
                    $error = 'Email already used by another account.';
                } else {
                    $hashedPassword = $current->getPassword();
                    if ($password !== '') {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    }
                    $updated = new User(
                        $firstName,
                        $lastName,
                        $email,
                        $hashedPassword,
                        $phone,
                        $current->getRole(),
                        $current->getStatus(),
                        $current->getCreatedAt()
                    );
                    $userController->updateUser($updated, $id);
                    header('Location: profile.php?success=updated#crud');
                    exit;
                }
            } else {
                if ($password === '') {
                    $error = 'Password is required for account creation.';
                } elseif ($userController->emailExists($email)) {
                    $error = 'Email already exists.';
                } else {
                    $newUser = new User(
                        $firstName,
                        $lastName,
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                        $phone,
                        'user',
                        1,
                        date('Y-m-d H:i:s')
                    );
                    $userController->addUser($newUser);
                    header('Location: profile.php?success=created#crud');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editingUser = $userController->getUserById((int) $_GET['edit']);
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') $success = 'Account created successfully.';
    if ($_GET['success'] === 'updated') $success = 'Profile updated successfully.';
    if ($_GET['success'] === 'deleted') $success = 'User deleted successfully.';
}

$users = $userController->listUsers();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Profile and unified front-office CRUD.">
  <title>Profile — Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
      <div class="navbar-nav" id="navbar-nav">
        <a href="home.php">Home</a>
        <a href="social.php">Feed</a>
        <a href="skills.php">Skills</a>
        <a href="projects.php">Projects</a>
        <a href="reviews.php">Reviews</a>
        <a href="challenges.php">Challenges</a>
      </div>
      <div class="navbar-actions">
        <a href="auth.php" class="btn btn-primary btn-sm">Sign In</a>
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu"><div class="nav-avatar">AD</div></button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header"><strong>Admin User</strong><span>admin@diversity.is</span></div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/UserDashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
          </div>
        </div>
      </div>
      <div class="nav-toggle" id="nav-toggle"><span></span><span></span><span></span></div>
    </div>
  </nav>

  <main class="profile-main" id="crud" style="padding-top:96px;">
    <div class="container">
      <div class="glass-card panel fade-in-section" style="margin-bottom:16px;">
        <h3 class="panel-title" style="margin-bottom:10px;">FrontOffice Profile CRUD</h3>
        <p class="text-body-md">All profile CRUD is unified here.</p>
      </div>

      <?php if ($success !== ''): ?><div class="glass-card panel" style="border:1px solid rgba(34,197,94,.35); margin-bottom:12px;"><strong><?= htmlspecialchars($success) ?></strong></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="glass-card panel" style="border:1px solid rgba(239,68,68,.35); margin-bottom:12px;"><strong><?= htmlspecialchars($error) ?></strong></div><?php endif; ?>

      <div class="profile-grid" style="grid-template-columns:1.2fr .8fr; gap:16px;">
        <div class="glass-card panel">
          <h4 class="panel-sub-title">Users</h4>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Action</th></tr></thead>
              <tbody>
                <?php if (count($users) === 0): ?>
                  <tr><td colspan="4">No users found.</td></tr>
                <?php else: ?>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td><?= (int) $u->getId() ?></td>
                      <td><?= htmlspecialchars($u->getFullName()) ?></td>
                      <td><?= htmlspecialchars($u->getEmail()) ?></td>
                      <td style="display:flex;gap:8px;">
                        <a href="profile.php?edit=<?= (int) $u->getId() ?>#crud" class="btn btn-secondary btn-sm">Modify</a>
                        <form method="POST" action="profile.php#crud" onsubmit="return confirm('Delete this user?');" style="margin:0;">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?= (int) $u->getId() ?>">
                          <button class="btn btn-secondary btn-sm" type="submit">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="glass-card panel">
          <h4 class="panel-sub-title"><?= $editingUser ? 'Modify User' : 'Create Account' ?></h4>
          <form method="POST" action="profile.php#crud">
            <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
            <?php if ($editingUser): ?><input type="hidden" name="id" value="<?= (int) $editingUser->getId() ?>"><?php endif; ?>
            <div class="form-group"><label>First name</label><input class="form-input" name="first_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getFirstName() : ($_POST['first_name'] ?? '')) ?>"></div>
            <div class="form-group"><label>Last name</label><input class="form-input" name="last_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getLastName() : ($_POST['last_name'] ?? '')) ?>"></div>
            <div class="form-group"><label>Email</label><input class="form-input" name="email" type="email" required value="<?= htmlspecialchars($editingUser ? $editingUser->getEmail() : ($_POST['email'] ?? '')) ?>"></div>
            <div class="form-group"><label>Password <?= $editingUser ? '(optional)' : '' ?></label><input class="form-input" name="password" type="password" <?= $editingUser ? '' : 'required' ?>></div>
            <div class="form-group"><label>Phone</label><input class="form-input" name="phone" value="<?= htmlspecialchars($editingUser ? $editingUser->getPhone() : ($_POST['phone'] ?? '')) ?>"></div>
            <div style="display:flex;gap:8px; margin-top:10px;">
              <button class="btn btn-primary btn-sm" type="submit"><?= $editingUser ? 'Save' : 'Create' ?></button>
              <?php if ($editingUser): ?><a href="profile.php#crud" class="btn btn-secondary btn-sm">Cancel</a><?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <script>
    if (window.lucide) {
      window.lucide.createIcons();
    }
  </script>
</body>
</html>



