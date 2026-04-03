<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$userController = new UserController();
$users = $userController->listUsers();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>BackOffice - Liste des Utilisateurs</title>
    <link rel="shortcut icon" href="/Views/assets/images/favicon.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap">
    <link rel="stylesheet" type="text/css" href="/Views/assets/vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" type="text/css" href="/Views/assets/css/style.css">
</head>
<body>
<main class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">Gestion des Utilisateurs</h1>
        <a href="AddUser.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Ajouter</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($users) === 0): ?>
                    <tr><td colspan="6" class="text-center py-3">Aucun utilisateur trouvé.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int) $user->getId() ?></td>
                            <td><?= htmlspecialchars($user->getFullName()) ?></td>
                            <td><?= htmlspecialchars($user->getEmail()) ?></td>
                            <td><?= htmlspecialchars($user->getRole()) ?></td>
                            <td><?= (int) $user->getStatus() === 1 ? 'Actif' : 'Inactif' ?></td>
                            <td class="text-end">
                                <a href="EditUser.php?id=<?= (int) $user->getId() ?>" class="btn btn-warning-soft btn-sm me-1"><i class="bi bi-pencil"></i></a>
                                <a href="DeleteUser.php?id=<?= (int) $user->getId() ?>" class="btn btn-danger-soft btn-sm" onclick="return confirm('Supprimer cet utilisateur ?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="/Views/assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
