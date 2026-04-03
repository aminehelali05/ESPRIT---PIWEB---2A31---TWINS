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
    <title>Liste des Utilisateurs</title>

    <script>
        const storedTheme = localStorage.getItem('theme')
        const getPreferredTheme = () => {
            if (storedTheme) { return storedTheme }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
        }
        const setTheme = function (theme) {
            if (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-bs-theme', 'dark')
            } else {
                document.documentElement.setAttribute('data-bs-theme', theme)
            }
        }
        setTheme(getPreferredTheme())
    </script>

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
    <section class="pt-4">
        <div class="container">
            <div class="card rounded-3 border p-3 pb-2">
                <div class="d-sm-flex align-items-center">
                    <div class="avatar avatar-xl mb-2 mb-sm-0">
                        <img class="avatar-img rounded-circle" src="/Views/assets/images/avatar/01.jpg" alt="">
                    </div>
                    <h4 class="mb-2 mb-sm-0 ms-sm-3"><span class="fw-light">Back Office</span> Gestion des Utilisateurs</h4>
                    <a href="AddUser.php" class="btn btn-sm btn-primary-soft mb-0 ms-auto flex-shrink-0">
                        <i class="bi bi-plus-lg fa-fw me-2"></i>Ajouter un Utilisateur
                    </a>
                </div>
                <div class="offcanvas-xl offcanvas-end mt-xl-3" tabindex="-1" id="dashboardMenu">
                    <div class="offcanvas-body p-3 p-xl-0">
                        <div class="navbar navbar-expand-xl">
                            <ul class="navbar-nav navbar-offcanvas-menu">
                                <li class="nav-item"> <a class="nav-link active" href="ListUsers.php"><i class="bi bi-people fa-fw me-1"></i>Liste Utilisateurs</a> </li>
                                <li class="nav-item"> <a class="nav-link" href="AddUser.php"><i class="bi bi-person-plus fa-fw me-1"></i>Ajouter Utilisateur</a> </li>
                                <li class="nav-item"> <a class="nav-link" href="../FrontOffice/Home.php"><i class="bi bi-house fa-fw me-1"></i>Front Office</a> </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pt-0">
        <div class="container vstack gap-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="fs-4 mb-0"><i class="bi bi-people fa-fw me-1"></i>Liste des Utilisateurs</h1>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Nom Complet</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Rôle</th>
                                    <th>Statut</th>
                                    <th>Créé le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user->getId()) ?></td>
                                    <td><strong><?= htmlspecialchars($user->getFullName()) ?></strong></td>
                                    <td><?= htmlspecialchars($user->getEmail()) ?></td>
                                    <td><?= htmlspecialchars($user->getPhone()) ?></td>
                                    <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($user->getRole()) ?></span></td>
                                    <td>
                                        <span class="badge <?= $user->getStatus() ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $user->getStatus() ? 'text-success' : 'text-danger' ?>">
                                            <?= $user->getStatus() ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($user->getCreatedAt()) ?></td>
                                    <td>
                                        <a href="EditUser.php?id=<?= $user->getId() ?>" class="btn btn-sm btn-warning-soft me-1" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="DeleteUser.php?id=<?= $user->getId() ?>" class="btn btn-sm btn-danger-soft" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-info-circle me-2"></i>Aucun utilisateur trouvé.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="back-top"></div>
<script src="/Views/assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="/Views/assets/js/functions.js"></script>
</body>
</html>
