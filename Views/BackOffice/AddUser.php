<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$error = "";
$userController = new UserController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['first_name']) && !empty($_POST['last_name']) && !empty($_POST['email']) && !empty($_POST['password'])) {
        if ($userController->emailExists($_POST['email'])) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $user = new User(
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                password_hash($_POST['password'], PASSWORD_DEFAULT),
                $_POST['phone'] ?? '',
                $_POST['role'] ?? 'user',
                isset($_POST['status']) ? 1 : 0,
                date('Y-m-d H:i:s')
            );
            $userController->addUser($user);
            header('Location: ListUsers.php');
            exit;
        }
    } else {
        $error = "Prénom, nom, email et mot de passe sont obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Ajouter un Utilisateur</title>
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
                </div>
            </div>
        </div>
    </section>

    <section class="pt-0">
        <div class="container vstack gap-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="fs-4 mb-0"><i class="bi bi-person-plus fa-fw me-1"></i>Ajouter un Utilisateur</h1>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informations Utilisateur</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Rôle</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="user">User</option>
                                        <option value="admin">Admin</option>
                                        <option value="manager">Manager</option>
                                    </select>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" checked>
                                    <label class="form-check-label" for="status">Actif</label>
                                </div>
                                <div class="text-center">
                                    <a href="ListUsers.php" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i>Retour</a>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Ajouter</button>
                                </div>
                            </form>
                        </div>
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
