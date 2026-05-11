<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

$eventController = new EventController();
$event = $eventController->getEventById($_GET['id'] ?? 0);

if (!$event) {
    header('Location: events.php');
    exit;
}

$sidebarUser = UserController::currentUser() ?? [];
$isOwner = isset($sidebarUser['id']) && $sidebarUser['id'] == $event->getUserId();
$isAdmin = UserController::isAdmin();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event->getTitle()) ?> — Diversity.is</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/projects.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="grid-dot-bg">
    <canvas id="gradient-canvas"></canvas>

    <nav class="navbar">
        <div class="container">
            <a href="home.php" class="navbar-brand"><div class="brand-icon">⬡</div>Diversity.is</a>
            <div class="navbar-nav">
                <a href="events.php">Back to Events</a>
            </div>
        </div>
    </nav>

    <main class="profile-main">
        <div class="container" style="max-width: 900px;">
            <div class="glass-card" style="padding: 40px; margin-top: 40px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div>
                        <span class="project-status status-active"><?= htmlspecialchars($event->getCategory()) ?></span>
                        <h1 class="text-h1" style="margin-top: 10px;"><?= htmlspecialchars($event->getTitle()) ?></h1>
                    </div>
                    <?php if ($isOwner || $isAdmin): ?>
                    <div style="display: flex; gap: 10px;">
                        <a href="event_edit.php?id=<?= $event->getId() ?>" class="btn btn-secondary btn-sm"><i data-lucide="edit-3"></i> Edit</a>
                        <a href="../../index.php?action=delete_event&id=<?= $event->getId() ?>" class="btn btn-secondary btn-sm" style="color: var(--color-error);" onclick="return confirm('Are you sure?')"><i data-lucide="trash-2"></i> Delete</a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-2" style="gap: 30px; margin-bottom: 30px;">
                    <div class="glass-card" style="padding: 20px; background: rgba(255,255,255,0.05);">
                        <p class="text-small" style="font-weight: 600; color: var(--color-accent); margin-bottom: 10px;"><i data-lucide="calendar"></i> DATE & TIME</p>
                        <p class="text-body-lg"><?= date('F j, Y — H:i', strtotime($event->getEventDate())) ?></p>
                    </div>
                    <div class="glass-card" style="padding: 20px; background: rgba(255,255,255,0.05);">
                        <p class="text-small" style="font-weight: 600; color: var(--color-accent); margin-bottom: 10px;"><i data-lucide="map-pin"></i> LOCATION</p>
                        <p class="text-body-lg"><?= htmlspecialchars($event->getLocation()) ?></p>
                    </div>
                </div>

                <div style="margin-bottom: 40px;">
                    <h3 class="text-h3" style="margin-bottom: 15px;">About this event</h3>
                    <p class="text-body-lg" style="white-space: pre-wrap;"><?= htmlspecialchars($event->getDescription()) ?></p>
                </div>

                <div class="glass-card" style="padding: 20px; display: flex; align-items: center; gap: 15px;">
                    <div class="avatar" style="width: 50px; height: 50px; background: var(--color-gradient);"><span>?</span></div>
                    <div>
                        <p class="text-small">Organized by</p>
                        <p class="text-body" style="font-weight: 600;">Community Member</p>
                    </div>
                </div>
                
                <div style="margin-top: 40px; text-align: center;">
                    <p class="text-small" style="margin-bottom: 15px;">Current Status: <strong><?= $event->getStatus() ?></strong></p>
                    <?php if ($event->getStatus() === 'ACCEPTE'): ?>
                        <button class="btn btn-primary" style="padding: 15px 40px;">Attend Event</button>
                    <?php else: ?>
                        <p class="text-body" style="color: var(--color-accent-secondary);">This event is pending validation.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>lucide.createIcons();</script>
</body>
</html>
