<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$controller = new UserController();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please provide your email and password.';
    } else {
        $user = $controller->login($email, $password);
        if ($user) {
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_name'] = $user->getFullName();
            $_SESSION['user_email'] = $user->getEmail();
            $_SESSION['user_role'] = $user->getRole();
            if (strtolower($user->getRole()) === 'admin') {
                header('Location: ../BackOffice/user_list.php');
                exit;
            }
            header('Location: profile.php');
            exit;
        }
        $error = 'Invalid credentials or blocked account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Voices of Peace</title>
  <link rel="stylesheet" href="../assets/css/user-module.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
  <div class="um-page">
    <header class="um-topbar">
      <a class="um-brand" href="home.php"><span class="um-brand-dot">VoP</span> Voices of Peace</a>
      <button class="um-theme-toggle" type="button" data-theme-toggle><i data-lucide="moon-star" class="w-4 h-4"></i></button>
    </header>

    <div class="um-grid-auth">
      <section class="um-card um-auth-hero tilt-hover">
        <h1>Welcome Back</h1>
        <p>Sign in to continue your journey with a modern and secure user space.</p>
      </section>

      <section class="um-card um-auth-panel">
        <h2>Sign In</h2>
        <p class="um-subtext">Reference-aligned auth flow migrated from Projet-2Aref.</p>
        <?php if ($error): ?>
          <div class="um-alert um-alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="um-form">
          <div>
            <label>Email</label>
            <input class="um-input" type="email" name="email" placeholder="you@example.com" required>
          </div>
          <div>
            <label>Password</label>
            <input class="um-input" type="password" name="password" placeholder="••••••••" required>
          </div>
          <button class="um-btn um-btn-primary" type="submit">Sign In</button>
          <a class="um-btn um-btn-soft" href="register.php">Create Account</a>
        </form>
      </section>
    </div>
  </div>

  <script src="../assets/js/user-module.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>