<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$error = '';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: ListUsers.php');
    exit;
}

$id = (int) $_GET['id'];
$user = $userController->getUserById($id);
if (!$user) {
    header('Location: ListUsers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $role = trim($_POST['role'] ?? 'user');
    $status = isset($_POST['status']) ? 1 : 0;

    if ($firstName === '' || $lastName === '' || $email === '') {
        $error = 'Prénom, nom et email sont obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif ($userController->emailExists($email, $id)) {
        $error = 'Cet email existe déjà.';
    } else {
        $hashedPassword = $user->getPassword();
        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        }

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

        $userController->updateUser($updated, $id);
        header('Location: ListUsers.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>BackOffice - Modifier Utilisateur</title>
    <link rel="shortcut icon" href="/Views/assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="/Views/assets/css/style.css">
</head>
<body>
<main class="container py-4">
    <h1 class="h4 mb-3">Modifier Utilisateur #<?= $id ?></h1>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card shadow-sm"><div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Prénom *</label><input class="form-control" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? $user->getFirstName()) ?>"></div>
                <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? $user->getLastName()) ?>"></div>
                <div class="col-12"><label class="form-label">Email *</label><input class="form-control" name="email" type="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user->getEmail()) ?>"></div>
                <div class="col-12"><label class="form-label">Nouveau mot de passe (optionnel)</label><input class="form-control" name="password" type="password"></div>
                <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $user->getPhone()) ?>"></div>
                <div class="col-md-6"><label class="form-label">Rôle</label>
                    <?php $selectedRole = strtolower($_POST['role'] ?? $user->getRole()); ?>
                    <select class="form-select" name="role">
                        <option value="user" <?= $selectedRole === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="manager" <?= $selectedRole === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <?php $checked = isset($_POST['status']) || (!isset($_POST['status']) && (int)$user->getStatus() === 1); ?>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="status" id="status" <?= $checked ? 'checked' : '' ?>><label class="form-check-label" for="status">Actif</label></div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <a href="ListUsers.php" class="btn btn-secondary btn-sm">Retour</a>
                <button class="btn btn-primary btn-sm" type="submit">Enregistrer</button>
            </div>
        </form>
    </div></div>
</main>
</body>
</html>
