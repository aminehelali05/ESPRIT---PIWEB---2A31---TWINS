<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Front Office - Users</title>

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
    <section class="pt-5 pb-5">
        <div class="container">
            <div class="card shadow p-4 p-md-5 text-center">
                <h1 class="mb-3">MVC User CRUD</h1>
                <p class="text-muted mb-4">Projet migré vers un CRUD Utilisateur avec architecture MVC.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="../BackOffice/ListUsers.php" class="btn btn-primary">
                        <i class="bi bi-people me-2"></i>Back Office Users
                    </a>
                    <a href="ListUsersFront.php" class="btn btn-outline-primary">
                        <i class="bi bi-list me-2"></i>Front Office Users
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="/Views/assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="/Views/assets/js/functions.js"></script>
</body>
</html>
