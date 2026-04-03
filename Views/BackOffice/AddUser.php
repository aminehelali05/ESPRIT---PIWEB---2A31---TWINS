<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
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
        $error = 'Tous les champs obligatoires doivent être remplis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } elseif ($userController->emailExists($email)) {
        $error = 'Cet email existe déjà.';
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
    <title>BackOffice - Ajouter Utilisateur</title>
    <link rel="shortcut icon" href="/Views/assets/images/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap">
    <link rel="stylesheet" type="text/css" href="/Views/assets/css/style.css">
</head>
<body>
<main class="container py-4">
    <h1 class="h4 mb-3">Ajouter un Utilisateur</h1>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card shadow-sm"><div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Prénom *</label><input class="form-control" name="first_name" required></div>
                <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="last_name" required></div>
                <div class="col-12"><label class="form-label">Email *</label><input class="form-control" name="email" type="email" required></div>
                <div class="col-12"><label class="form-label">Mot de passe *</label><input class="form-control" name="password" type="password" required></div>
                <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="phone"></div>
                <div class="col-md-6"><label class="form-label">Rôle</label><select class="form-select" name="role"><option value="user">User</option><option value="manager">Manager</option><option value="admin">Admin</option></select></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="status" id="status" checked><label class="form-check-label" for="status">Actif</label></div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <a href="ListUsers.php" class="btn btn-secondary btn-sm">Retour</a>
                <button class="btn btn-primary btn-sm" type="submit">Créer</button>
            </div>
        </form>
    </div></div>
</main>
</body>
</html>
