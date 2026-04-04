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
    $status = isset($_POST['status']) ? 1 : 0;

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $error = 'First name, last name, email and password are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($controller->emailExists($email)) {
        $error = 'Email already exists.';
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
        $controller->addUser($user);
        header('Location: user_list.php?success=created');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add User - Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="sidebar-header"><div class="sidebar-brand"><div class="brand-icon">⬡</div><span class="sidebar-brand-text">Diversity.is</span></div></div>
      <nav class="sidebar-nav">
        <div class="sidebar-section-label">BackOffice</div>
        <a class="sidebar-link" href="user_list.php">Users</a>
        <a class="sidebar-link active" href="user_form.php">Add User</a>
      </nav>
    </aside>

    <main class="dashboard-main">
      <header class="dashboard-header"><h3 style="font-size:.95rem;">Create User</h3></header>
      <section class="dashboard-content" style="padding:1.2rem; overflow:auto;">
        <div class="module-panel" style="max-width:760px; margin:0 auto; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:1rem;">
          <?php if ($error): ?>
            <div style="margin-bottom:1rem; background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.25); color:#fca5a5; border-radius:10px; padding:.75rem 1rem;"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <form method="post" style="display:grid; gap:.9rem;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem;">
              <div><label>First Name</label><input class="form-input" type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"></div>
              <div><label>Last Name</label><input class="form-input" type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem;">
              <div><label>Email</label><input class="form-input" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
              <div><label>Password</label><input class="form-input" type="password" name="password" required></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.9rem;">
              <div><label>Phone</label><input class="form-input" type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
              <div><label>Role</label>
                <select class="form-input" name="role">
                  <option value="user">User</option>
                  <option value="manager">Manager</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
            </div>
            <label><input type="checkbox" name="status" checked> Active account</label>
            <div style="display:flex; gap:.7rem; justify-content:flex-end;">
              <a href="user_list.php" class="btn btn-ghost">Cancel</a>
              <button type="submit" class="btn btn-primary">Create User</button>
            </div>
          </form>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
