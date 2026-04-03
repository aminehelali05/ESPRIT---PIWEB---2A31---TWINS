<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$error = '';
$success = '';
$user = null;

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $error = 'Missing user id.';
} else {
    $id = (int) $_GET['id'];
    $user = $userController->getUserById($id);

    if (!$user) {
        $error = 'User not found.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif ($userController->emailExists($email, $id)) {
            $error = 'Email already used by another account.';
        } else {
            $updated = new User(
                $firstName,
                $lastName,
                $email,
                $user->getPassword(),
                $phone,
                $user->getRole(),
                $user->getStatus(),
                $user->getCreatedAt()
            );
            $userController->updateUser($updated, $id);
            $success = 'Profile updated successfully.';
            $user = $userController->getUserById($id);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modify Profile - Diversity.is</title>
  <link rel="stylesheet" href="../assets/css/global.css">
  <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body class="grid-dot-bg">
<section class="profile-main" style="padding-top:90px;">
  <div class="container">
    <div class="glass-card p-4">
      <h1 class="text-h3 mb-3">Modify User</h1>
      <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <?php if ($user): ?>
      <form method="POST" class="row g-3">
        <div class="col-md-6"><label class="form-label">First name</label><input class="form-input" name="first_name" value="<?= htmlspecialchars($user->getFirstName()) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Last name</label><input class="form-input" name="last_name" value="<?= htmlspecialchars($user->getLastName()) ?>" required></div>
        <div class="col-12"><label class="form-label">Email</label><input class="form-input" name="email" type="email" value="<?= htmlspecialchars($user->getEmail()) ?>" required></div>
        <div class="col-12"><label class="form-label">Phone</label><input class="form-input" name="phone" value="<?= htmlspecialchars($user->getPhone()) ?>"></div>
        <div class="col-12 d-flex gap-2">
          <a href="profile.php" class="btn btn-secondary">Back</a>
          <button class="btn btn-primary" type="submit">Save changes</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</section>
</body>
</html>

