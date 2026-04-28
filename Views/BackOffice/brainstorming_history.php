<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/BrainstormingController.php');

if (!UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$brainstormingController = new BrainstormingController();

// Pagination Logic
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = $_GET['s'] ?? '';
$statusFilter = $_GET['st'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build filters — default: exclude pending (show history only)
$filters = [];
if ($statusFilter) {
    $filters['status'] = $statusFilter;
} else {
    $filters['not_status'] = 'EN_ATTENTE';
}

$allSessions = $brainstormingController->listBrainstormings($search, $filters, $sort, $perPage, $offset);
$totalRecords = $brainstormingController->countBrainstormings($search, $filters);
$totalPages = max(1, ceil($totalRecords / $perPage));
if ($page > $totalPages) $page = $totalPages;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessions History — VoP Admin</title>
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
                    <h1>Platform <span style="color: var(--b-accent);">History</span></h1>
                    <p>Track all brainstorming sessions and their outcomes.</p>
                </div>
                <div class="header-actions" style="display:flex; gap:10px; align-items:center;">
                    <form action="" method="GET" class="search-bar" style="display:flex; gap:8px; align-items:center;">
                        <i data-lucide="search"></i>
                        <input type="text" name="s" placeholder="Search history..." value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="st" value="<?= htmlspecialchars($statusFilter) ?>">
                        <select name="sort" onchange="this.form.submit()" style="padding:6px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:inherit; font-size:0.85rem; cursor:pointer;">
                            <option value="newest" <?= $sort=='newest'?'selected':'' ?>>Newest First</option>
                            <option value="oldest" <?= $sort=='oldest'?'selected':'' ?>>Oldest First</option>
                            <option value="title_az" <?= $sort=='title_az'?'selected':'' ?>>Title A–Z</option>
                            <option value="title_za" <?= $sort=='title_za'?'selected':'' ?>>Title Z–A</option>
                        </select>
                    </form>
                </div>
            </header>

            <div class="card animate-enter">
                <div class="section-head">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <h2 style="margin: 0;">History Records</h2>
                        <div class="filter-pills" style="display: flex; gap: 8px;">
                            <a href="brainstorming_history.php?st=" class="pill <?= !$statusFilter ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">All</a>
                            <a href="brainstorming_history.php?st=ACCEPTE" class="pill <?= $statusFilter == 'ACCEPTE' ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">Accepted</a>
                            <a href="brainstorming_history.php?st=REFUSE" class="pill <?= $statusFilter == 'REFUSE' ? 'pill-admin' : 'pill-user' ?>" style="text-decoration: none;">Refused</a>
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
                                <th>Status</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allSessions as $b): ?>
                            <tr>
                                <td><span style="font-family: monospace; color: var(--b-text-light);">#<?= $b['id'] ?></span></td>
                                <td>
                                    <div class="u-cell">
                                        <div class="u-avatar"><?= strtoupper(substr($b['first_name'], 0, 1)) ?></div>
                                        <span class="u-name"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--b-text-main);"><?= htmlspecialchars($b['title']) ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $st = strtolower($b['status']);
                                    if ($st == 'accepte'): ?>
                                        <span class="status st-active"><span class="st-dot"></span> Accepted</span>
                                    <?php elseif ($st == 'refuse'): ?>
                                        <span class="status st-offline" style="color: #ef4444;"><span class="st-dot" style="background: #ef4444;"></span> Refused</span>
                                    <?php else: ?>
                                        <span class="status status-pending"><span class="st-dot" style="background: #f59e0b;"></span> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="t-actions">
                                        <?php 
                                        $adminUser = UserController::currentUser();
                                        if ($b['user_id'] == $adminUser['id']): ?>
                                        <a href="../FrontOffice/brainstorming_edit.php?id=<?= $b['id'] ?>" 
                                           class="t-btn" 
                                           title="Edit Session"
                                           style="color: var(--b-accent);">
                                            <i data-lucide="edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="../../index.php?action=delete_brainstorming&id=<?= $b['id'] ?>" 
                                           class="t-btn t-btn-refuse" 
                                           onclick="return confirm('Permanently delete this record?')"
                                           title="Delete Permanently">
                                            <i data-lucide="trash-2"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="minimal-pagination">
                        <a href="?p=1&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>&sort=<?= urlencode($sort) ?>" class="pg-num <?= $page == 1 ? 'current' : '' ?>">1</a>
                        <a href="?p=<?= max(1, $page - 1) ?>&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>&sort=<?= urlencode($sort) ?>" class="pg-arrow <?= $page <= 1 ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <?php if($page != 1 && $page != $totalPages): ?>
                            <span class="pg-num current"><?= $page ?></span>
                        <?php endif; ?>
                        <a href="?p=<?= min($totalPages, $page + 1) ?>&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>&sort=<?= urlencode($sort) ?>" class="pg-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <?php if($totalPages > 1): ?>
                        <a href="?p=<?= $totalPages ?>&s=<?= urlencode($search) ?>&st=<?= urlencode($statusFilter) ?>&sort=<?= urlencode($sort) ?>" class="pg-num <?= $page == $totalPages ? 'current' : '' ?>"><?= $totalPages ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
