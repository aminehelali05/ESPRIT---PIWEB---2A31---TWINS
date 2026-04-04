<?php
session_start();
require_once __DIR__ . '/../../Controllers/UserController.php';

if (isset($_SESSION['user_id'])) {
    header('Location: profile.php');
    exit;
}

$controller = new UserController();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif ($controller->emailExists($email)) {
        $error = 'Email already exists.';
    } else {
        $user = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            '',
            'user',
            1,
            date('Y-m-d H:i:s')
        );
        $user->setCountry($country);
        $createdId = $controller->addUser($user);
        if ($createdId) {
            $success = 'Registration successful. You can now sign in.';
        } else {
            $error = 'Unable to create account at this time.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - Voices of Peace</title>
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
        <h1>Join the Community</h1>
        <p>Create your account with the migrated premium onboarding interface.</p>
      </section>

      <section class="um-card um-auth-panel">
        <h2>Create Account</h2>
        <p class="um-subtext">Modern registration adapted to your existing MVC schema.</p>
        <?php if ($error): ?>
          <div class="um-alert um-alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="um-alert um-alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="um-form">
          <div class="um-grid-2">
            <div>
              <label>First Name</label>
              <input class="um-input" type="text" name="first_name" required>
            </div>
            <div>
              <label>Last Name</label>
              <input class="um-input" type="text" name="last_name" required>
            </div>
          </div>
          <div>
            <label>Email</label>
            <input class="um-input" type="email" name="email" required>
          </div>
          <div>
            <label>Country</label>
            <input class="um-input" type="text" name="country" placeholder="Optional">
          </div>
          <div>
            <label>Password</label>
            <input class="um-input" type="password" id="password" name="password" required>
            <div class="um-progress" style="margin-top:8px;"><span id="passwordMeter" data-animate-progress data-progress="0"></span></div>
          </div>
          <div>
            <label>Confirm Password</label>
            <input class="um-input" type="password" id="confirmPassword" name="confirm_password" required>
          </div>
          <button class="um-btn um-btn-primary" type="submit">Create Account</button>
          <a class="um-btn um-btn-soft" href="login.php">Already have an account? Sign In</a>
        </form>
      </section>
    </div>
  </div>

  <script src="../assets/js/user-module.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
