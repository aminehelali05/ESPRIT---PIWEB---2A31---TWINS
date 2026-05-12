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
$statusFilter = $_GET['st'] ?? '';

$filters = [];
if ($statusFilter) {
    $filters['status'] = $statusFilter;
}

$allEvents = $eventController->listEvents($search, $filters, $perPage, $offset);
$totalRecords = $eventController->countEvents($search, $filters);
$totalPages = ceil($totalRecords / $perPage);

$user = UserController::currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events — VoP Admin</title>
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
                    <h1>All <span style="color: var(--b-accent);">Events</span></h1>
                    <p>Browse and manage the complete event registry.</p>
                </div>
                <div class="header-actions">
                    <form action="" method="GET" class="search-bar">
                        <i data-lucide="search"></i>
                        <input type="text" name="s" placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="st" value="<?= htmlspecialchars($statusFilter) ?>">
                        <?php if($search): ?>
                            <a href="events_all.php?st=<?= urlencode($statusFilter) ?>" style="color: var(--b-text-light);"><i data-lucide="x-circle"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
            </header>

            <div class="card animate-enter">
                <div class="section-head">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <h2 style="margin: 0;">Event Registry</h2>
                        <div class="filter-pills" style="display: flex; gap: 8px;">
                            <a href="events_all.php?st=" class="pill <?= !$statusFilter ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">All</a>
                            <a href="events_all.php?st=ACCEPTE" class="pill <?= $statusFilter == 'ACCEPTE' ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">Accepted</a>
                            <a href="events_all.php?st=EN_ATTENTE" class="pill <?= $statusFilter == 'EN_ATTENTE' ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">Pending</a>
                            <a href="events_all.php?st=REFUSE" class="pill <?= $statusFilter == 'REFUSE' ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">Refused</a>
                        </div>
                    </div>
                    <span class="dm-kpi"><?= $totalRecords ?> Total</span>
                </div>

                <div class="table-container">
                    <table class="elegant-table">
                        <thead>
                            <tr>
                                <th>#ID</th>
                                <th>Creator</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allEvents as $e): ?>
                            <tr>
                                <td><span style="font-family: monospace; color: var(--b-text-light);">#<?= $e['id'] ?></span></td>
                                <td>
                                    <div class="u-cell">
                                        <div class="u-avatar"><?= strtoupper(substr($e['first_name'], 0, 1)) ?></div>
                                        <span class="u-name"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--b-text-main);"><?= htmlspecialchars($e['title']) ?></strong>
                                </td>
                                <td>
                                    <span class="pill pill-manager"><?= htmlspecialchars($e['category']) ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $st = strtolower($e['status']);
                                    if ($st == 'accepte'): ?>
                                        <span class="status st-active"><span class="st-dot"></span> Accepted</span>
                                    <?php elseif ($st == 'refuse'): ?>
                                        <span class="status st-offline" style="color: #ef4444;"><span class="st-dot" style="background: #ef4444;"></span> Refused</span>
                                    <?php else: ?>
                                        <span class="status" style="color: #f59e0b;"><span class="st-dot" style="background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.4);"></span> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="t-actions">
                                        <a href="../FrontOffice/event_details.php?id=<?= $e['id'] ?>" class="t-btn" title="View Details">
                                            <i data-lucide="eye"></i>
                                        </a>
                                        <?php if ($e['user_id'] == ($sidebarUser['id'] ?? -1)): ?>
                                        <a href="../FrontOffice/event_edit.php?id=<?= $e['id'] ?>" class="t-btn" title="Edit">
                                            <i data-lucide="pencil"></i>
                                        </a>
                                        <a href="#" class="t-btn t-btn-refuse" title="Delete" 
                                           onclick="event.preventDefault(); confirmDelete(<?= $e['id'] ?>);">
                                            <i data-lucide="trash-2"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($allEvents)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 4rem 0; color: var(--b-text-light);">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                                    <p>No events found.</p>
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
                        <a href="?p=1&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>" class="pg-num first <?= $page == 1 ? 'current' : '' ?>">1</a>
                        
                        <a href="?p=<?= max(1, $page - 1) ?>&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>" class="pg-arrow <?= $page <= 1 ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>

                        <?php if($page != 1 && $page != $totalPages): ?>
                            <span class="pg-num current"><?= $page ?></span>
                        <?php endif; ?>

                        <a href="?p=<?= min($totalPages, $page + 1) ?>&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>" class="pg-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>

                        <a href="?p=<?= $totalPages ?>&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>" class="pg-num last <?= $page == $totalPages ? 'current' : '' ?>"><?= $totalPages ?></a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();

        function confirmDelete(id) {
            Swal.fire({
                title: 'Delete this event?',
                text: 'This action is permanent and cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel',
                background: '#ffffff',
                color: '#0f172a',
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../../index.php?action=delete_event&id=' + id;
                }
            });
        }
    </script>
</body>
</html>
