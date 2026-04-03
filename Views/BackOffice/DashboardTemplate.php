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
        header('Location: DashboardTemplate.php?success=deleted#users');
        exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'user');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($firstName == '' or $lastName == '' or $email == '') {
            $error = 'First name, last name, and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } elseif ($userController->emailExists($email, $action === 'update' ? $id : null)) {
            $error = 'Email already exists.';
        } else {
            if ($action === 'update') {
                $existingUser = $userController->getUserById($id);
                if ($existingUser === null) {
                    $error = 'User not found.';
                } else {
                    $hashedPassword = $existingUser->getPassword();
                    if ($password !== '') {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $user = new User(
                        $firstName,
                        $lastName,
                        $email,
                        $hashedPassword,
                        $phone,
                        $role,
                        $status,
                        $existingUser->getCreatedAt()
                    );
                    $userController->updateUser($user, $id);
                    header('Location: DashboardTemplate.php?success=updated#users');
                    exit;
                }
            } else {
                if ($password === '') {
                    $error = 'Password is required for new users.';
                } else {
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
                    $userController->addUser($user);
                    header('Location: DashboardTemplate.php?success=created#users');
                    exit;
                }
            }
        }
    }
}

if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editingUser = $userController->getUserById((int) $_GET['edit']);
    if ($editingUser === null) {
        $error = 'Selected user does not exist.';
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success = 'User created successfully.';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'User updated successfully.';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'User deleted successfully.';
    }
}

$users = $userController->listUsers();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard - Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg dashboard-body">
  <div class="dashboard-layout">
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-brand">
          <div class="brand-icon">⬡</div>
          <span class="sidebar-brand-text">Diversity.is</span>
        </div>
      </div>
      <nav class="sidebar-nav">
        <a href="#overview" class="sidebar-link active"><i data-lucide="layout-dashboard" class="w-4 h-4"></i><span>Dashboard</span></a>
        <a href="#users" class="sidebar-link"><i data-lucide="users" class="w-4 h-4"></i><span>User Management</span></a>
      </nav>
      <div class="sidebar-footer">
        <a href="../FrontOffice/TemplateHome.php" class="sidebar-link"><i data-lucide="home" class="w-4 h-4"></i><span>Front Office</span></a>
        <a href="../FrontOffice/auth.php" class="sidebar-link sidebar-logout"><i data-lucide="log-out" class="w-4 h-4"></i><span>Sign Out</span></a>
      </div>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header">
        <div class="header-left">
          <h2 class="page-title" style="margin:0;">User Dashboard</h2>
        </div>
      </header>

      <div class="dashboard-content">
        <section class="dash-page active" id="overview">
          <div class="page-header">
            <div>
              <h2 class="page-title">Overview</h2>
              <p class="page-subtitle">Integrated template + PHP MVC user CRUD.</p>
            </div>
          </div>
          <div class="stats-row">
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">Total Users</p><h3 class="dash-stat-value"><?= count($users) ?></h3></div><div class="dash-stat-icon icon-indigo"><i data-lucide="users" class="w-5 h-5 icon-gradient-primary"></i></div></div></div>
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">Active Users</p><h3 class="dash-stat-value"><?= count(array_filter($users, fn($u) => (int) $u->getStatus() === 1)) ?></h3></div><div class="dash-stat-icon icon-emerald"><i data-lucide="user-check" class="w-5 h-5 icon-gradient-success"></i></div></div></div>
            <div class="dash-stat-card"><div class="dash-stat-top"><div><p class="dash-stat-label">Admins</p><h3 class="dash-stat-value"><?= count(array_filter($users, fn($u) => strtolower($u->getRole()) === 'admin')) ?></h3></div><div class="dash-stat-icon icon-purple"><i data-lucide="shield" class="w-5 h-5 icon-gradient-purple"></i></div></div></div>
          </div>
        </section>

        <section class="dash-page active" id="users">
          <?php if ($success !== ''): ?>
            <div class="module-panel" style="border:1px solid rgba(34,197,94,.35); margin-bottom:16px;"><strong><?= htmlspecialchars($success) ?></strong></div>
          <?php endif; ?>
          <?php if ($error !== ''): ?>
            <div class="module-panel" style="border:1px solid rgba(239,68,68,.35); margin-bottom:16px;"><strong><?= htmlspecialchars($error) ?></strong></div>
          <?php endif; ?>

          <div class="module-rich-grid" style="grid-template-columns:1.2fr .8fr; align-items:start;">
            <article class="module-panel">
              <h3 style="margin-bottom:12px;">Users List</h3>
              <div class="table-wrap">
                <table class="dash-table">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($users) === 0): ?>
                      <tr><td colspan="6">No users found.</td></tr>
                    <?php else: ?>
                      <?php foreach ($users as $user): ?>
                        <tr>
                          <td><?= (int) $user->getId() ?></td>
                          <td><?= htmlspecialchars($user->getFullName()) ?></td>
                          <td><?= htmlspecialchars($user->getEmail()) ?></td>
                          <td><?= htmlspecialchars($user->getRole()) ?></td>
                          <td><?= (int) $user->getStatus() === 1 ? 'Active' : 'Inactive' ?></td>
                          <td style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">
                            <a class="btn btn-secondary btn-sm" href="DashboardTemplate.php?edit=<?= (int) $user->getId() ?>#users">Edit</a>
                            <form method="POST" action="DashboardTemplate.php#users" onsubmit="return confirm('Delete this user?');" style="margin:0;">
                              <input type="hidden" name="action" value="delete">
                              <input type="hidden" name="id" value="<?= (int) $user->getId() ?>">
                              <button type="submit" class="btn btn-secondary btn-sm">Delete</button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="module-panel">
              <h3 style="margin-bottom:12px;"><?= $editingUser ? 'Edit User' : 'Create User' ?></h3>
              <form method="POST" action="DashboardTemplate.php#users">
                <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
                <?php if ($editingUser): ?>
                  <input type="hidden" name="id" value="<?= (int) $editingUser->getId() ?>">
                <?php endif; ?>

                <div class="form-group"><label>First Name</label><input type="text" class="form-control" name="first_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getFirstName() : ($_POST['first_name'] ?? '')) ?>"></div>
                <div class="form-group"><label>Last Name</label><input type="text" class="form-control" name="last_name" required value="<?= htmlspecialchars($editingUser ? $editingUser->getLastName() : ($_POST['last_name'] ?? '')) ?>"></div>
                <div class="form-group"><label>Email</label><input type="email" class="form-control" name="email" required value="<?= htmlspecialchars($editingUser ? $editingUser->getEmail() : ($_POST['email'] ?? '')) ?>"></div>
                <div class="form-group"><label>Password <?= $editingUser ? '(leave empty to keep current)' : '' ?></label><input type="password" class="form-control" name="password" <?= $editingUser ? '' : 'required' ?>></div>
                <div class="form-group"><label>Phone</label><input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($editingUser ? $editingUser->getPhone() : ($_POST['phone'] ?? '')) ?>"></div>
                <div class="form-group">
                  <label>Role</label>
                  <select class="form-control" name="role">
                    <?php $selectedRole = strtolower($editingUser ? $editingUser->getRole() : ($_POST['role'] ?? 'user')); ?>
                    <option value="user" <?= $selectedRole === 'user' ? 'selected' : '' ?>>User</option>
                    <option value="manager" <?= $selectedRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                    <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                  </select>
                </div>
                <?php $checkedStatus = $editingUser ? ((int) $editingUser->getStatus() === 1) : isset($_POST['status']); ?>
                <div class="form-group" style="display:flex; gap:8px; align-items:center; margin:10px 0 14px;">
                  <input type="checkbox" id="status" name="status" <?= $checkedStatus ? 'checked' : '' ?>><label for="status" style="margin:0;">Active</label>
                </div>
                <div style="display:flex; gap:8px;">
                  <button type="submit" class="btn btn-primary btn-sm"><?= $editingUser ? 'Update User' : 'Create User' ?></button>
                  <?php if ($editingUser): ?>
                    <a href="DashboardTemplate.php#users" class="btn btn-secondary btn-sm">Cancel</a>
                  <?php endif; ?>
                </div>
              </form>
            </article>
          </div>
        </section>
      </div>
    </main>
  </div>

  <script>
    if (window.lucide) {
      window.lucide.createIcons();
    }
  </script>
</body>
</html>
