<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/BrainstormingController.php');
include_once(__DIR__ . '/../../Controllers/stats_brainstorming.php');

if (!UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php');
    exit;
}

$brainstormingController = new BrainstormingController();
$statsController = new BrainstormingStats();
$globalStats = $statsController->getGlobalStats();
$kpis = $globalStats['kpis'];

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

            <!-- Global Stats Section [NEW] -->
            <div class="card animate-enter" style="margin-bottom: 30px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="section-head" style="border-bottom: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: var(--b-text-main); display: flex; align-items: center; gap: 10px;">
                        <i data-lucide="line-chart" style="color: var(--b-accent);"></i> Global Insights
                    </h2>
                    <button class="pill pill-admin" onclick="document.getElementById('globalStatsCharts').style.display = (document.getElementById('globalStatsCharts').style.display === 'none' ? 'grid' : 'none')" style="border:none; cursor:pointer;">Toggle Analytics</button>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px;">
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center;">
                        <span style="display: block; font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Sessions</span>
                        <span style="font-size: 1.8rem; font-weight: 800; color: var(--b-accent);"><?= $kpis['total_sessions'] ?></span>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center;">
                        <span style="display: block; font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Total Ideas</span>
                        <span style="font-size: 1.8rem; font-weight: 800; color: #10b981;"><?= $kpis['total_ideas'] ?></span>
                    </div>
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center;">
                        <span style="display: block; font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Engagement (Votes)</span>
                        <span style="font-size: 1.8rem; font-weight: 800; color: #f59e0b;"><?= $kpis['total_votes'] ?></span>
                    </div>
                </div>

                <div id="globalStatsCharts" style="display: none; grid-template-columns: repeat(2, 1fr); gap: 25px; padding-top: 10px;">
                    <div style="background: white; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <p style="font-weight: 700; margin-bottom: 15px; font-size: 0.9rem;">Distribution des Statuts (Radar)</p>
                        <canvas id="globalStatusRadar" style="max-height: 250px;"></canvas>
                    </div>
                    <div style="background: white; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <p style="font-weight: 700; margin-bottom: 15px; font-size: 0.9rem;">Top 5 Thèmes (Polar Area)</p>
                        <canvas id="globalTypesPolar" style="max-height: 250px;"></canvas>
                    </div>
                </div>
            </div>

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
                                        <a href="ideas_all.php?bid=<?= $b['id'] ?>" 
                                           class="t-btn" 
                                           title="Access Ideas"
                                           style="color: #f59e0b;">
                                            <i data-lucide="lightbulb"></i>
                                        </a>
                                        <a href="dashboard_brainstorming.php?id=<?= $b['id'] ?>" 
                                           class="t-btn" 
                                           title="View Analytics"
                                           style="color: #10b981;">
                                            <i data-lucide="bar-chart-2"></i>
                                        </a>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        lucide.createIcons();

        // Global Status Radar Chart
        const radarCtx = document.getElementById('globalStatusRadar').getContext('2d');
        new Chart(radarCtx, {
            type: 'radar',
            data: {
                labels: <?= json_encode(array_column($globalStats['status_dist'], 'status')) ?>,
                datasets: [{
                    label: 'Nombre de Sessions',
                    data: <?= json_encode(array_column($globalStats['status_dist'], 'count')) ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.2)',
                    borderColor: '#8b5cf6',
                    pointBackgroundColor: '#8b5cf6',
                    borderWidth: 2
                }]
            },
            options: {
                scales: { r: { beginAtZero: true, grid: { color: '#e2e8f0' } } },
                plugins: { legend: { display: false } }
            }
        });

        // Global Types Polar Area Chart
        const polarCtx = document.getElementById('globalTypesPolar').getContext('2d');
        new Chart(polarCtx, {
            type: 'polarArea',
            data: {
                labels: <?= json_encode(array_column($globalStats['top_types'], 'category')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($globalStats['top_types'], 'count')) ?>,
                    backgroundColor: [
                        '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ef4444'
                    ]
                }]
            },
            options: {
                plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 10 } } } }
            }
        });
    </script>
</body>
</html>
