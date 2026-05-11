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

$filters = ['status' => 'EN_ATTENTE'];
$sort = $_GET['sort'] ?? 'newest';
$pendingSessions = $brainstormingController->listBrainstormings($search, $filters, $sort, $perPage, $offset);
$totalRecords = $brainstormingController->countBrainstormings($search, $filters);
$totalPages = max(1, ceil($totalRecords / $perPage));
if ($page > $totalPages) $page = $totalPages;

$user = UserController::currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Brainstormings — VoP Admin</title>
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
                    <h1>Pending <span style="color: var(--b-accent);">Brainstormings</span></h1>
                    <p>Review and validate new session requests.</p>
                </div>
                <div class="header-actions" style="display:flex; gap:10px; align-items:center;">
                    <form action="" method="GET" class="search-bar" style="display:flex; gap:8px; align-items:center;">
                        <i data-lucide="search"></i>
                        <input type="text" name="s" placeholder="Search sessions..." value="<?= htmlspecialchars($search) ?>">
                        <select name="sort" onchange="this.form.submit()" style="padding:6px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:inherit; font-size:0.85rem; cursor:pointer;">
                            <option value="newest" <?= $sort=='newest'?'selected':'' ?>>Newest First</option>
                            <option value="oldest" <?= $sort=='oldest'?'selected':'' ?>>Oldest First</option>
                            <option value="title_az" <?= $sort=='title_az'?'selected':'' ?>>Title A–Z</option>
                            <option value="title_za" <?= $sort=='title_za'?'selected':'' ?>>Title Z–A</option>
                        </select>
                        <?php if($search): ?>
                            <a href="brainstorming_pending.php" style="color: var(--b-text-light);"><i data-lucide="x-circle"></i></a>
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
                                <th>Session Details</th>
                                <th>Date Submitted</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingSessions as $b): ?>
                            <tr>
                                <td>
                                    <div class="u-cell">
                                        <div class="u-avatar"><?= strtoupper(substr($b['first_name'], 0, 1) . substr($b['last_name'], 0, 1)) ?></div>
                                        <div>
                                            <span class="u-name"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></span>
                                            <span class="u-email">#U-<?= $b['user_id'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong style="color: var(--b-text-main);"><?= htmlspecialchars($b['title']) ?></strong>
                                    <p style="font-size: 0.8rem; color: var(--b-text-muted); margin: 4px 0 0; line-height: 1.4;">
                                        <?= htmlspecialchars(strlen($b['description']) > 100 ? substr($b['description'], 0, 100) . '...' : $b['description']) ?>
                                    </p>
                                </td>
                                <td>
                                    <span style="font-size: 0.85rem; color: var(--b-text-muted);">
                                        <?= date('M d, Y', strtotime($b['created_at'])) ?>
                                        <br><small><?= date('H:i', strtotime($b['created_at'])) ?></small>
                                    </span>
                                </td>
                                <td>
                                    <div class="t-actions">
                                        <a href="../../index.php?action=approve_brainstorming&id=<?= $b['id'] ?>" class="t-btn t-btn-approve" title="Approve">
                                            <i data-lucide="check-circle"></i> Approve
                                        </a>
                                        <a href="../../index.php?action=refuse_brainstorming&id=<?= $b['id'] ?>" class="t-btn t-btn-refuse" title="Refuse">
                                            <i data-lucide="x-circle"></i> Refuse
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($pendingSessions)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 4rem 0; color: var(--b-text-light);">
                                    <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 12px; opacity: 0.5;"></i>
                                    <p>No pending sessions found.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Minimalist Pagination (Ref: Image 1) -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <div class="minimal-pagination">
                        <a href="?p=1&s=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>" class="pg-num <?= $page == 1 ? 'current' : '' ?>">1</a>
                        <a href="?p=<?= max(1, $page - 1) ?>&s=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>" class="pg-arrow <?= $page <= 1 ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <?php if($page != 1 && $page != $totalPages): ?>
                            <span class="pg-num current"><?= $page ?></span>
                        <?php endif; ?>
                        <a href="?p=<?= min($totalPages, $page + 1) ?>&s=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>" class="pg-arrow <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <?php if($totalPages > 1): ?>
                        <a href="?p=<?= $totalPages ?>&s=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>" class="pg-num <?= $page == $totalPages ? 'current' : '' ?>"><?= $totalPages ?></a>
                        <?php endif; ?>
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
