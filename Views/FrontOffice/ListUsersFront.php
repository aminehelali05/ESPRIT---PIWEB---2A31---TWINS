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
    <title>Utilisateurs</title>

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
                    <h4 class="mb-2 mb-sm-0 ms-sm-3"><span class="fw-light">Front Office</span> Utilisateurs</h4>
                    <a href="../BackOffice/ListUsers.php" class="btn btn-sm btn-primary-soft mb-0 ms-auto flex-shrink-0">
                        <i class="bi bi-speedometer2 fa-fw me-2"></i>Back Office
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="pt-0">
        <div class="container vstack gap-4">
            <div class="row g-4">
                <?php if (count($users) > 0): ?>
                <?php foreach ($users as $user): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="card shadow h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge bg-primary bg-opacity-10 text-primary"><?= htmlspecialchars($user->getRole()) ?></span>
                                <span class="badge <?= $user->getStatus() ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $user->getStatus() ? 'text-success' : 'text-danger' ?>">
                                    <?= $user->getStatus() ? 'Actif' : 'Inactif' ?>
                                </span>
                            </div>
                            <h5 class="card-title"><a href="UserDetail.php?id=<?= $user->getId() ?>"><?= htmlspecialchars($user->getFullName()) ?></a></h5>
                            <p class="mb-1"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user->getEmail()) ?></p>
                            <p class="mb-0"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($user->getPhone()) ?></p>
                        </div>
                        <div class="card-footer text-center">
                            <a href="UserDetail.php?id=<?= $user->getId() ?>" class="btn btn-sm btn-primary-soft mb-0">
                                <i class="bi bi-eye me-1"></i>Voir Détails
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi bi-info-circle me-2"></i>Aucun utilisateur trouvé.
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script src="/Views/assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="/Views/assets/js/functions.js"></script>
</body>
</html>
