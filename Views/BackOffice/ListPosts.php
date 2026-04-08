<?php
include_once __DIR__ . '/../../Controllers/PostController.php';

$postController = new PostController();
$posts = $postController->listPosts();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Gestion des Posts - Diversity</title>

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
                    <h4 class="mb-2 mb-sm-0 ms-sm-3"><span class="fw-light">Back Office</span> Gestion du Contenu Social</h4>
                </div>
                <div class="offcanvas-xl offcanvas-end mt-xl-3" tabindex="-1" id="dashboardMenu">
                    <div class="offcanvas-body p-3 p-xl-0">
                        <div class="navbar navbar-expand-xl">
                            <ul class="navbar-nav navbar-offcanvas-menu">
                                <li class="nav-item"> <a class="nav-link" href="ListUsers.php"><i class="bi bi-people fa-fw me-1"></i>Utilisateurs</a> </li>
                                <li class="nav-item"> <a class="nav-link active" href="ListPosts.php"><i class="bi bi-grid fa-fw me-1"></i>Posts</a> </li>
                                <li class="nav-item"> <a class="nav-link" href="../FrontOffice/SocialFeed.php"><i class="bi bi-house fa-fw me-1"></i>Voir le Flux Social</a> </li>
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
                    <h1 class="fs-4 mb-0"><i class="bi bi-grid fa-fw me-1"></i>Liste des Posts</h1>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Auteur</th>
                                    <th>Contenu</th>
                                    <th>Media</th>
                                    <th>Date de création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($posts) > 0): ?>
                                <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td><?= htmlspecialchars($post['id']) ?></td>
                                    <td><strong><?= htmlspecialchars($post['first_name'] . ' ' . $post['last_name']) ?></strong></td>
                                    <td>
                                        <div style="max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= htmlspecialchars($post['contenu']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($post['media_url']): ?>
                                            <span class="badge bg-info bg-opacity-10 text-info">Image/Lien</span>
                                        <?php else: ?>
                                            <span class="text-muted">Aucun</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($post['created_at']) ?></td>
                                    <td>
                                        <a href="DeletePost.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-danger-soft" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce post ? Cela supprimera aussi les commentaires et réactions associés.');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="bi bi-info-circle me-2"></i>Aucun post trouvé.
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
