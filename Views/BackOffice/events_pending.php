<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

if (!UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$eventController = new EventController();

// Pagination Logic
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = $_GET['s'] ?? '';

$filters = ['status' => 'EN_ATTENTE'];
$pendingEvents = $eventController->listEvents($search, $filters, $perPage, $offset);
$totalRecords = $eventController->countEvents($search, $filters);
$totalPages = ceil($totalRecords / $perPage);

$user = UserController::currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Events — VoP Admin</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css?v=<?= time() ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="app-container">
        <?php include 'admin_sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <div class="page-title">
                    <h1>Pending <span style="color: var(--b-accent);">Events</span></h1>
                    <p>Review and validate community event submissions.</p>
                </div>
                <div class="header-actions">
                    <form action="" method="GET" class="search-bar">
                        <i data-lucide="search"></i>
                        <input type="text" name="s" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                        <?php if($search): ?>
                            <a href="events_pending.php" style="color: var(--b-text-light);"><i data-lucide="x-circle"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </header>

            <section class="card animate-enter">
                <div class="section-head">
                    <h2><i data-lucide="clock" style="color: #f59e0b; vertical-align: middle; margin-right: 8px;"></i> Awaiting Approval</h2>
                    <span class="dm-kpi"><?= $totalRecords ?> Total</span>
                </div>

                <div class="table-container">
                    <table class="elegant-table">
                        <thead>
                            <tr>
                                <th>Creator</th>
                                <th>Event Details</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingEvents as $e): ?>
                            <tr>
                                <td>
                                    <div class="u-cell">
                                        <div class="u-avatar"><?= strtoupper(substr($e['first_name'], 0, 1) . substr($e['last_name'], 0, 1)) ?></div>
                                        <div>
                                            <span class="u-name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></span>
                                            <span class="u-email">#U-<?= $e['user_id'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--b-text-main);"><?= htmlspecialchars($e['title']) ?></strong>
                                    <p style="font-size: 0.8rem; color: var(--b-text-muted); margin: 4px 0 0; line-height: 1.4;">
                                        <?= htmlspecialchars(strlen($e['description']) > 100 ? substr($e['description'], 0, 100) . '...' : $e['description']) ?>
                                    </p>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: var(--b-text-muted);">
                                        <?= date('M d, Y', strtotime($e['start_date'])) ?>
                                        <br><small><?= date('H:i', strtotime($e['start_date'])) ?> - <?= date('H:i', strtotime($e['end_date'])) ?></small>
                                    </span>
                                </td>
                                <td>
                                    <span class="pill pill-manager"><?= htmlspecialchars($e['category']) ?></span>
                                </td>
                                <td>
                                    <div class="t-actions">
                                        <a href="../../index.php?action=approve_event&id=<?= $e['id'] ?>" class="t-btn t-btn-approve" title="Approve">
                                            <i data-lucide="check-circle"></i> Approve
                                        </a>
                                        <a href="../../index.php?action=refuse_event&id=<?= $e['id'] ?>" class="t-btn t-btn-refuse" title="Refuse">
                                            <i data-lucide="x-circle"></i> Refuse
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($pendingEvents)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 4rem 0; color: var(--b-text-light);">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                                    <p>No pending events found.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Minimalist Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="minimal-pagination">
                        <a href="?p=1&s=<?= urlencode($search) ?>" class="pg-num first <?= $page == 1 ? 'current' : '' ?>">1</a>
                        
                        <a href="?p=<?= max(1, $page - 1) ?>&s=<?= urlencode($search) ?>" class="pg-arrow <?= $page <= 1 ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>

                        <?php if($page != 1 && $page != $totalPages): ?>
                            <span class="pg-num current"><?= $page ?></span>
                        <?php endif; ?>

                        <a href="?p=<?= min($totalPages, $page + 1) ?>&s=<?= urlencode($search) ?>" class="pg-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>

                        <a href="?p=<?= $totalPages ?>&s=<?= urlencode($search) ?>" class="pg-num last <?= $page == $totalPages ? 'current' : '' ?>"><?= $totalPages ?></a>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
