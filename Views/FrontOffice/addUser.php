<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($userController->emailExists($email)) {
        $error = 'Email already used.';
    } else {
        $user = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            trim($_POST['phone'] ?? ''),
            'user',
            1,
            date('Y-m-d H:i:s')
        );
        $userController->addUser($user);
        header('Location: auth.php?registered=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>
  <section class="section auth-section">
    <div class="container flex-center">
      <div class="auth-wrapper fade-in-section" style="max-width:640px;">
        <div class="auth-face auth-front glass-card" style="position:relative;transform:none;">
          <div class="auth-header">
            <h1 class="text-h2">Create <span class="text-gradient">Account</span></h1>
            <p class="text-body-lg">Sign up to start using Diversity.is</p>
          </div>
          <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="auth.php">Sign In</a></div><?php endif; ?>
          <form method="POST" class="auth-form">
            <div class="form-row">
              <div class="form-group"><label class="form-label">First name</label><input class="form-input" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"></div>
              <div class="form-group"><label class="form-label">Last name</label><input class="form-input" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Password</label><input class="form-input" type="password" name="password" required></div>
            <div class="form-group"><label class="form-label">Phone (optional)</label><input class="form-input" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"></div>
            <button class="btn btn-primary btn-lg auth-submit" type="submit">Create account</button>
          </form>
          <p class="auth-toggle-text">Already have an account? <a href="auth.php">Sign In</a></p>
        </div>
      </div>
    </div>
  </section>
</body>
</html>

