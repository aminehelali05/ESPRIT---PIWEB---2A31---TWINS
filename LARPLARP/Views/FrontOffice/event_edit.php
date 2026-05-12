<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

if (!UserController::isAuthenticated()) {
    header('Location: auth.php');
    exit;
}

$eventController = new EventController();
$event = $eventController->getEventById($_GET['id'] ?? 0);

if (!$event) {
    header('Location: events.php');
    exit;
}

$sidebarUser = UserController::currentUser();
$isOwner = $sidebarUser['id'] == $event->getUserId();
$isAdmin = UserController::isAdmin();

if (!$isOwner && !$isAdmin) {
    header('Location: events.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Event — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/projects.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar">
    <div class="container">
      <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
    </div>
  </nav>

  <main class="profile-main">
    <div class="container" style="max-width: 800px; margin: 0 auto;">
      <div class="glass-card" style="padding: 40px; margin-top: 40px;">
        <div class="section-header" style="text-align: left;">
          <h1 class="text-h1">Edit <span class="text-gradient">Event</span></h1>
          <p class="text-body-lg">Update the details of your suggested event.</p>
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

        <form id="eventEditForm" action="../../index.php?action=update_event" method="POST" class="fade-in-section">
          <input type="hidden" name="id" value="<?= $event->getId() ?>">
          
          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Event Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($event->getTitle()) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
            <div>
              <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Date & Time</label>
              <input type="datetime-local" name="event_date" value="<?= date('Y-m-d\TH:i', strtotime($event->getEventDate())) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
            </div>
            <div>
              <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Category</label>
              <select name="category" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
                <option value="Workshop" <?= $event->getCategory() == 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                <option value="Networking" <?= $event->getCategory() == 'Networking' ? 'selected' : '' ?>>Networking</option>
                <option value="Webinar" <?= $event->getCategory() == 'Webinar' ? 'selected' : '' ?>>Webinar</option>
                <option value="Conference" <?= $event->getCategory() == 'Conference' ? 'selected' : '' ?>>Conference</option>
              </select>
            </div>
          </div>

          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Location</label>
            <input type="text" name="location" value="<?= htmlspecialchars($event->getLocation()) ?>" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
          </div>

          <div style="margin-bottom: 20px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Description</label>
            <textarea name="description" rows="5" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;"><?= htmlspecialchars($event->getDescription()) ?></textarea>
          </div>

          <?php if ($isAdmin): ?>
          <div style="margin-bottom: 30px;">
            <label class="text-small" style="font-weight: 600; display: block; margin-bottom: 8px;">Status (Admin Only)</label>
            <select name="status" class="glass-input" style="width: 100%; padding: 12px; border-radius: 12px;">
                <option value="EN_ATTENTE" <?= $event->getStatus() == 'EN_ATTENTE' ? 'selected' : '' ?>>EN_ATTENTE</option>
                <option value="ACCEPTE" <?= $event->getStatus() == 'ACCEPTE' ? 'selected' : '' ?>>ACCEPTE</option>
                <option value="REFUSE" <?= $event->getStatus() == 'REFUSE' ? 'selected' : '' ?>>REFUSE</option>
            </select>
          </div>
          <?php endif; ?>

          <div style="display: flex; gap: 15px;">
            <button type="submit" class="btn btn-primary" style="flex: 2;">Save Changes</button>
            <a href="event_details.php?id=<?= $event->getId() ?>" class="btn btn-secondary" style="flex: 1; text-align: center;">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/event-resource-validation.js"></script>
  <script>lucide.createIcons();</script>
</body>
</html>
