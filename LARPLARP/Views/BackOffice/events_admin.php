<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

if (!UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$eventController = new EventController();
$events = $eventController->listEvents('', ['status' => 'EN_ATTENTE']);
$allEvents = $eventController->listEvents();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Management — Admin Diversity</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .admin-table { width: 100%; border-collapse: collapse; margin-top: 20px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; overflow: hidden; }
        .admin-table th, .admin-table td { padding: 15px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); color: #e2e8f0; }
        .admin-table th { background: rgba(30, 41, 59, 0.8); font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.05em; }
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }
        .status-accepted { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .status-refused { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .action-btn { padding: 8px 12px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-approve { background: #22c55e; color: white; margin-right: 5px; }
        .btn-refuse { background: #ef4444; color: white; }
    </style>
</head>
<body style="background: #020617; color: #e2e8f0; font-family: Inter, sans-serif;">
    <div class="app-container" style="display: flex; min-height: 100vh;">
        <aside class="sidebar" style="width: 260px; border-right: 1px solid rgba(255,255,255,0.1); padding: 20px;">
            <div class="brand" style="margin-bottom: 40px;">
                <h2 style="color: #38bdf8;">VoP Admin</h2>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item" style="display: flex; align-items: center; gap: 10px; padding: 12px; color: #94a3b8; text-decoration: none;">
                    <i data-lucide="layout-dashboard"></i> Overview
                </a>
                <a href="events_admin.php" class="nav-item active" style="display: flex; align-items: center; gap: 10px; padding: 12px; background: rgba(56, 189, 248, 0.1); color: #38bdf8; text-decoration: none; border-radius: 8px;">
                    <i data-lucide="calendar"></i> Events
                </a>
                <a href="resources_admin.php" class="nav-item" style="display: flex; align-items: center; gap: 10px; padding: 12px; color: #94a3b8; text-decoration: none;">
                    <i data-lucide="library"></i> Resources
                </a>
            </nav>
        </aside>

        <main class="main-content" style="flex: 1; padding: 40px;">
            <header style="margin-bottom: 40px;">
                <h1 style="font-size: 2rem;">Event <span style="color: #38bdf8;">Validation</span></h1>
                <p style="color: #94a3b8;">Review and manage community event suggestions.</p>
            </header>

            <section>
                <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="clock" style="color: #f59e0b;"></i> Pending Requests
                </h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Event Details</th>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($e['title']) ?></strong><br>
                                <small style="color: #94a3b8;"><?= htmlspecialchars(substr($e['description'], 0, 50)) ?>...</small>
                            </td>
                            <td><?= date('M d, Y', strtotime($e['event_date'])) ?></td>
                            <td><?= htmlspecialchars($e['category']) ?></td>
                            <td>
                                <a href="../../index.php?action=approve_event&id=<?= $e['id'] ?>" class="action-btn btn-approve">Approve</a>
                                <a href="../../index.php?action=refuse_event&id=<?= $e['id'] ?>" class="action-btn btn-refuse">Refuse</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($events)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8;">No pending requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section style="margin-top: 60px;">
                <h2 style="margin-bottom: 20px;">All Events</h2>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allEvents as $e): ?>
                        <tr>
                            <td>#<?= $e['id'] ?></td>
                            <td><?= htmlspecialchars($e['title']) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($e['status']) == 'en_attente' ? 'pending' : (strtolower($e['status']) == 'accepte' ? 'accepted' : 'refused') ?>">
                                    <?= $e['status'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="../FrontOffice/event_edit.php?id=<?= $e['id'] ?>" class="action-btn" style="background: rgba(255,255,255,0.1); color: white;">Edit</a>
                                <a href="../../index.php?action=delete_event&id=<?= $e['id'] ?>" class="action-btn" style="color: #ef4444;" onclick="return confirm('Delete this event permanently?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
