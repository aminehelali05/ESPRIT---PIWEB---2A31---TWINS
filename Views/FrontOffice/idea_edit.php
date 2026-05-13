<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/IdeaController.php');

if (!UserController::isAuthenticated()) { header('Location: auth.php'); exit; }

$id = $_GET['id'] ?? null;
$ideaController = new IdeaController();
$idea = $ideaController->getIdeaById($id);

if (!$idea || ($idea->getUserId() != UserController::currentUser()['id'] && !UserController::isAdmin())) {
    header('Location: brainstormings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Edit Idea — Diversity.is</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/projects.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
    <nav class="navbar">
        <div class="container">
            <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
        </div>
    </nav>

    <main class="profile-main">
        <div class="container" style="max-width: 800px;">
            <div class="glass-card" style="padding: 40px;">
                <h2 class="text-h2" style="margin-bottom: 30px;">Edit your <span style="color: var(--color-accent);">Contribution</span></h2>
                
                <?php if (isset($_SESSION['flash_error'])): ?>
                    <div style="background: rgba(225, 29, 72, 0.1); color: var(--color-accent-rose); border: 1px solid var(--color-accent-rose); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                        <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
                    </div>
                <?php endif; ?>

                <form id="editIdeaForm" action="../../index.php?action=update_idea" method="POST">
                    <input type="hidden" name="id" value="<?= $idea->getId() ?>">
                    <input type="hidden" name="brainstorming_id" value="<?= $idea->getBrainstormingId() ?>">

                    <div style="position: relative; margin-bottom: 20px;">
                        <i data-lucide="type" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; color: #94a3b8;"></i>
                        <input type="text" name="title" id="title" class="glass-input" style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px;" value="<?= htmlspecialchars($idea->getTitle()) ?>" placeholder="Titre de votre idée...">
                        <div id="error-title" style="color: var(--color-accent-rose); font-size: 0.8rem; margin-top: 5px; display: none;">Title is required.</div>
                    </div>

                    <div style="position: relative; margin-bottom: 20px;">
                        <i data-lucide="tag" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; color: #94a3b8;"></i>
                        <select name="type" id="type" class="glass-input" style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px; appearance: none; cursor: pointer;">
                            <option value="Feature" <?= $idea->getIdeaType() == 'Feature' ? 'selected' : '' ?>>Feature (Nouvelle fonctionnalité)</option>
                            <option value="Amélioration" <?= $idea->getIdeaType() == 'Amélioration' ? 'selected' : '' ?>>Amélioration (Optimisation)</option>
                            <option value="Bug" <?= $idea->getIdeaType() == 'Bug' ? 'selected' : '' ?>>Correction (Bug/Problème)</option>
                            <option value="Recherche" <?= $idea->getIdeaType() == 'Recherche' ? 'selected' : '' ?>>Recherche (Exploration)</option>
                        </select>
                        <i data-lucide="chevron-down" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 16px; color: #94a3b8; pointer-events: none;"></i>
                    </div>

                    <div style="position: relative; margin-bottom: 30px;">
                        <i data-lucide="align-left" style="position: absolute; left: 12px; top: 15px; width: 18px; color: #94a3b8;"></i>
                        <textarea name="content" id="content" rows="6" class="glass-input" style="width: 100%; padding: 12px 12px 12px 40px; border-radius: 12px;" placeholder="Détaillez le fonctionnement ici..."><?= htmlspecialchars($idea->getContent()) ?></textarea>
                        <div id="error-content" style="color: var(--color-accent-rose); font-size: 0.8rem; margin-top: 5px; display: none;">Content is required.</div>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                        <a href="brainstorming_details.php?id=<?= $idea->getBrainstormingId() ?>" class="btn btn-secondary" style="flex: 1;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        document.getElementById('editIdeaForm').addEventListener('submit', function(e) {
            let isValid = true;
            const title = document.getElementById('title');
            const content = document.getElementById('content');
            
            if (title.value.trim() === "") {
                document.getElementById('error-title').style.display = 'block';
                title.style.borderColor = 'var(--color-accent-rose)';
                isValid = false;
            } else {
                document.getElementById('error-title').style.display = 'none';
                title.style.borderColor = '';
            }

            if (content.value.trim() === "") {
                document.getElementById('error-content').style.display = 'block';
                content.style.borderColor = 'var(--color-accent-rose)';
                isValid = false;
            } else {
                document.getElementById('error-content').style.display = 'none';
                content.style.borderColor = '';
            }

            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
