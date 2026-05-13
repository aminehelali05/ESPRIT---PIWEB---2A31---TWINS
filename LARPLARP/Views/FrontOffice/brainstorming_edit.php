<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/BrainstormingController.php');

if (!UserController::isAuthenticated()) {
    header('Location: auth.php');
    exit;
}

$brainstormingController = new BrainstormingController();
$session = $brainstormingController->getBrainstormingById($_GET['id'] ?? 0);

if (!$session) {
    header('Location: brainstormings.php');
    exit;
}

$currentUser = UserController::currentUser();
$isOwner = $currentUser && ($currentUser['id'] == $session->getUserId());
$isAdmin = UserController::isAdmin();

if (!$isOwner && !$isAdmin) {
    header('Location: brainstormings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Brainstorming — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <nav class="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container" style="max-width: 800px; margin: 0 auto;">
      <div class="glass-card" style="padding: 40px; margin-top: 40px;">
        <div class="section-header" style="text-align: left;">
          <span class="section-tag">Edit Session</span>
          <h1 class="text-h1">Update <span class="text-gradient">Brainstorming</span></h1>
          <p class="text-body-lg">Refine the details of your brainstorming session.</p>
        </div>

        <?php if (isset($_SESSION['flash_error'])): ?>
          <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
          </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_success'])): ?>
          <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #22c55e; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
          </div>
        <?php endif; ?>

        <form id="brainstormingForm" action="../../index.php?action=update_brainstorming" method="POST">
          <input type="hidden" name="id" value="<?= $session->getId() ?>">

          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Session Title</label>
            <input type="text" name="title" id="formTitle" value="<?= htmlspecialchars($session->getTitle()) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;" placeholder="What are we brainstorming about?">
          </div>

          <div style="margin-bottom: 30px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Description & Goals</label>
            <textarea name="description" id="formDescription" rows="6" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;" placeholder="Describe the challenges, context, and what kind of ideas you're looking for."><?= htmlspecialchars($session->getDescription()) ?></textarea>
          </div>

          <?php if ($isAdmin): ?>
          <div style="margin-bottom: 30px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Status (Admin Only)</label>
            <select name="status" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
              <option value="EN_ATTENTE" <?= $session->getStatus() === 'EN_ATTENTE' ? 'selected' : '' ?>>EN_ATTENTE</option>
              <option value="ACCEPTE" <?= $session->getStatus() === 'ACCEPTE' ? 'selected' : '' ?>>ACCEPTE</option>
              <option value="REFUSE" <?= $session->getStatus() === 'REFUSE' ? 'selected' : '' ?>>REFUSE</option>
            </select>
          </div>
          <?php endif; ?>

          <div style="display: flex; gap: 15px;">
            <button type="submit" class="btn btn-primary" style="flex: 2;">Save Changes</button>
            <a href="brainstorming_details.php?id=<?= $session->getId() ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/brainstorming-validation.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
