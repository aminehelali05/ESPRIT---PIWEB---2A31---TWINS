<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

if (!isset($_GET['id'])) {
    header('Location: ListUsersFront.php');
    exit;
}

$userController = new UserController();
$user = $userController->getUserById($_GET['id']);

if (!$user) {
    header('Location: ListUsersFront.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= htmlspecialchars($user->getFullName()) ?> - Détail Utilisateur</title>
    <link rel="shortcut icon" href="/Views/assets/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com/">
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Poppins:wght@400;500;700&display=swap">
    <link rel="stylesheet" type="text/css" href="/Views/assets/vendor/font-awesome/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="/Views/assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="/Views/assets/css/style.css">
</head>
<body>
<main>
    <section class="pt-5 pb-5">
        <div class="container">
            <div class="card shadow">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Détail Utilisateur</h5>
                    <a href="ListUsersFront.php" class="btn btn-sm btn-primary-soft"><i class="bi bi-arrow-left me-1"></i>Retour</a>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tbody>
                            <tr><th>ID</th><td><?= htmlspecialchars($user->getId()) ?></td></tr>
                            <tr><th>Nom</th><td><?= htmlspecialchars($user->getFullName()) ?></td></tr>
                            <tr><th>Email</th><td><?= htmlspecialchars($user->getEmail()) ?></td></tr>
                            <tr><th>Téléphone</th><td><?= htmlspecialchars($user->getPhone()) ?></td></tr>
                            <tr><th>Rôle</th><td><?= htmlspecialchars($user->getRole()) ?></td></tr>
                            <tr><th>Statut</th><td><?= $user->getStatus() ? 'Actif' : 'Inactif' ?></td></tr>
                            <tr><th>Créé le</th><td><?= htmlspecialchars($user->getCreatedAt()) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="/Views/assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="/Views/assets/js/functions.js"></script>
</body>
</html>
