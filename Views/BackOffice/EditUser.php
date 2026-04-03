<?php
include_once __DIR__ . '/../../Controllers/UserController.php';

$error = "";
$userController = new UserController();

if (!isset($_GET['id'])) {
    header('Location: ListUsers.php');
    exit;
}

$id = $_GET['id'];
$user = $userController->getUserById($id);

if (!$user) {
    header('Location: ListUsers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['first_name']) && !empty($_POST['last_name']) && !empty($_POST['email'])) {
        if ($userController->emailExists($_POST['email'], $id)) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : $user->getPassword();
            $updatedUser = new User(
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $password,
                $_POST['phone'] ?? '',
                $_POST['role'] ?? 'user',
                isset($_POST['status']) ? 1 : 0,
                $user->getCreatedAt()
            );
            $userController->updateUser($updatedUser, $id);
            header('Location: ListUsers.php');
            exit;
        }
    } else {
        $error = "Prénom, nom et email sont obligatoires.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Modifier un Utilisateur</title>
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
                    <h1 class="fs-4 mb-0"><i class="bi bi-pencil fa-fw me-1"></i>Modifier un Utilisateur</h1>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Modifier les informations</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <form action="EditUser.php?id=<?= $id ?>" method="POST">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user->getFirstName()) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user->getLastName()) ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3 mt-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user->getEmail()) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Mot de passe (laisser vide pour ne pas changer)</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Téléphone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user->getPhone()) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Rôle</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="user" <?= $user->getRole() === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user->getRole() === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="manager" <?= $user->getRole() === 'manager' ? 'selected' : '' ?>>Manager</option>
                                    </select>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" <?= $user->getStatus() ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="status">Actif</label>
                                </div>
                                <div class="text-center">
                                    <a href="ListUsers.php" class="btn btn-secondary me-2"><i class="bi bi-arrow-left me-1"></i>Retour</a>
                                    <button type="submit" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>Modifier</button>
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
