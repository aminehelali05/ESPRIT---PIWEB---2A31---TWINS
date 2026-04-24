<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/JobOfferController.php');
include_once(__DIR__ . '/../../config.php');

if (!UserController::isAuthenticated() || !UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php?mode=login');
    exit;
}

$pdo = config::getConnexion();
$controller = new JobOfferController($pdo);

if (empty($_SESSION['csrf_token_back'])) {
    $_SESSION['csrf_token_back'] = bin2hex(random_bytes(24));
}
$csrfToken = (string) $_SESSION['csrf_token_back'];
$notice = ['type' => '', 'message' => ''];
$clean = static fn($value): string => trim((string) ($value ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $token)) {
        $notice = ['type' => 'error', 'message' => 'Invalid token.'];
    } else {
        $action = strtolower($clean($_POST['action'] ?? ''));
        try {
            if ($action === 'create_offer') {
                $payload = [
                    'title' => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'budget' => max(0, (float) ($_POST['budget'] ?? 0)),
                    'skills_required' => $clean($_POST['skills_required'] ?? ''),
                    'location' => $clean($_POST['location'] ?? ''),
                    'experience_level' => $clean($_POST['experience_level'] ?? 'Mid'),
                    'project_type' => $clean($_POST['project_type'] ?? 'Fixed Price'),
                    'status' => strtolower($clean($_POST['status'] ?? 'open')),
                    'deadline_at' => $controller->parseDateTimeLocal($clean($_POST['deadline_at'] ?? '')),
                    'client_id' => (int) ($_POST['client_id'] ?? 0),
                ];
                if ($payload['title'] === '' || $payload['description'] === '' || $payload['client_id'] <= 0) {
                    throw new RuntimeException('Title, description and client are required.');
                }
                $controller->createFromBackoffice($payload);
                $notice = ['type' => 'success', 'message' => 'Offer created.'];
            }

            if ($action === 'update_offer') {
                $offerId = (int) ($_POST['offer_id'] ?? 0);
                $status = strtolower($clean($_POST['status'] ?? 'open'));
                if (!in_array($status, ['open', 'in_progress', 'closed', 'archived'], true)) {
                    $status = 'open';
                }
                $controller->updateStatus($offerId, $status);
                $notice = ['type' => 'success', 'message' => 'Offer updated.'];
            }

            if ($action === 'delete_offer') {
                $offerId = (int) ($_POST['offer_id'] ?? 0);
                $controller->deleteCascade($offerId);
                $notice = ['type' => 'success', 'message' => 'Offer deleted.'];
            }

            if ($action === 'delete_candidature') {
                $applicationId = (int) ($_POST['application_id'] ?? 0);
                if ($controller->deleteCandidatureById($applicationId)) {
                    $notice = ['type' => 'success', 'message' => 'Application deleted.'];
                } else {
                    $notice = ['type' => 'error', 'message' => 'Application not found.'];
                }
            }
        } catch (Throwable $exception) {
            $notice = ['type' => 'error', 'message' => $exception->getMessage()];
        }
    }
}

$users = $controller->listUsers();
$rows = $controller->listBackofficeRows();
$candidatures = $controller->listAllCandidatures();
$stats = $controller->buildBackofficeStats($rows);

$sessionUser = UserController::currentUser() ?? [];
$displayName = trim((string) (($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? '')));
$displayName = $displayName !== '' ? $displayName : 'System Admin';
$displayEmail = (string) ($sessionUser['email'] ?? 'admin@diversity.is');
$initials = strtoupper(substr((string) ($sessionUser['first_name'] ?? 'A'), 0, 1) . substr((string) ($sessionUser['last_name'] ?? 'D'), 0, 1));

$navItems = [
    ['label' => 'Dashboard', 'href' => 'dashboardUser.php'],
    ['label' => 'Job Offers', 'href' => 'JobOffer.php', 'active' => true],
    ['label' => 'Projects', 'href' => 'Projects.php'],
    ['label' => 'Contracts', 'href' => 'Contracts.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice · Job Offers</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-form.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        :root {
            color-scheme: light;
            --accent: #4f46e5;
            --accent-2: #06b6d4;
            --accent-hover: #4338ca;
        }
        body {
            margin: 0;
            font-family: Inter, Poppins, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #1f2a44;
            background: linear-gradient(180deg, #eef3ff 0%, #f7f9ff 100%);
        }
        .app-container { min-height: 100vh; background: transparent; }
        .sidebar {
            background: linear-gradient(180deg, #f7f8ff 0%, #f1f4ff 100%);
            border-right: 1px solid #dbe3ff;
            box-shadow: 8px 0 24px rgba(91, 104, 178, 0.08);
        }
        .main-content { background: transparent; }
        .top-header {
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid #dfe6ff;
            border-radius: 24px;
            padding: 1.15rem 1.4rem;
            box-shadow: 0 12px 30px rgba(66, 84, 160, 0.09);
            backdrop-filter: blur(6px);
        }
        .bento-grid { display: grid; gap: 1rem; grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .card {
            background: #ffffff;
            border: 1px solid #dfe6ff;
            border-radius: 22px;
            box-shadow: 0 14px 30px rgba(66, 84, 160, 0.08);
            color: #1f2a44;
        }
        .kpi-card { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:126px; }
        .section-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem; }
        .elegant-table thead th { background: #f5f7ff; color: #6b7693; }
        .elegant-table tbody tr { background: #ffffff; }
        .elegant-table tbody tr:hover { background: #f7f9ff; }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            border-radius: 12px;
            padding: .72rem 1.05rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            color: #ffffff;
            font-weight: 700;
            letter-spacing: .01em;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(79, 70, 229, 0.30);
            transition: transform .16s ease, box-shadow .2s ease, filter .2s ease;
        }
        .btn-primary:hover { transform: translateY(-1px); filter: brightness(1.03); box-shadow: 0 18px 32px rgba(79, 70, 229, 0.33); }
        .btn-primary:active { transform: translateY(0); }
        .field-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.9rem; }
        .field-grid .full { grid-column:1 / -1; }
        .form-panel { padding:1.15rem; border:1px solid #dfe6ff; border-radius:18px; background:#fbfcff; box-shadow:inset 0 0 0 1px #eef2ff; }
        .form-panel label { display:block; margin:0 0 .35rem; color:#5e6b8f; font-size:.82rem; font-weight:600; }
        .form-panel input,
        .form-panel select,
        .form-panel textarea { width:100%; border:1px solid #d6def9; border-radius:14px; background:#fff; color:#25324b; padding:.8rem .95rem; outline:none; transition:border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .form-panel textarea { min-height:120px; resize:vertical; }
        .form-panel input:focus,
        .form-panel select:focus,
        .form-panel textarea:focus { border-color:rgba(96,165,250,.65); box-shadow:0 0 0 4px rgba(59,130,246,.15); }
        .form-actions { display:flex; justify-content:flex-end; margin-top:1rem; }
        .main-content label { color:#5e6b8f; font-weight:600; font-size:.82rem; }
        .main-content input:not([type="hidden"]),
        .main-content select,
        .main-content textarea {
            border:1px solid #d6def9;
            border-radius:14px;
            background:#fff;
            color:#25324b;
        }
        .main-content input:not([type="hidden"]):focus,
        .main-content select:focus,
        .main-content textarea:focus {
            border-color:rgba(96,165,250,.65);
            box-shadow:0 0 0 4px rgba(59,130,246,.15);
            outline:none;
        }
        .action-stack select,
        .action-stack input:not([type="hidden"]) {
            min-height: 40px;
            padding: .55rem .75rem;
            border-radius: 12px;
            border: 1px solid #b7c9fb;
            background: #f7f9ff;
            color: #2b3959;
            font-weight: 600;
            font-family: 'Poppins', Inter, sans-serif;
        }
        .action-select {
            min-width: 136px;
            appearance: none;
            background-image: linear-gradient(45deg, transparent 50%, #566a95 50%), linear-gradient(135deg, #566a95 50%, transparent 50%);
            background-position: calc(100% - 18px) calc(50% - 3px), calc(100% - 12px) calc(50% - 3px);
            background-size: 6px 6px, 6px 6px;
            background-repeat: no-repeat;
            padding-right: 2rem !important;
        }
        .action-select:focus,
        .action-stack input:not([type="hidden"]):focus {
            outline: none;
            border-color: #7d95ff;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.16);
        }
        .notice { margin-bottom:1rem; padding:.9rem 1rem; border-radius:14px; }
        .notice.success { background:rgba(16,185,129,.14); border:1px solid rgba(16,185,129,.28); }
        .notice.error { background:rgba(239,68,68,.14); border:1px solid rgba(239,68,68,.28); }
        .action-stack { display:flex; gap:.55rem; flex-wrap:nowrap; align-items:center; }
        .action-stack form { display:flex; gap:.55rem; align-items:center; }
        .btn-mini {
            border: 1px solid #4f6df7;
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            border-radius: 12px;
            padding: .58rem .95rem;
            cursor: pointer;
            font-weight: 700;
            font-family: 'Poppins', Inter, sans-serif;
            transition: all .18s ease;
        }
        .btn-mini:hover {
            transform: translateY(-1px);
            border-color: #3659ef;
            box-shadow: 0 12px 24px rgba(59, 130, 246, 0.26);
        }
        .btn-mini.delete {
            border-color: #f17f98;
            color: #ffffff;
            background: linear-gradient(135deg, #e11d48 0%, #f43f5e 100%);
        }
        .btn-mini.delete:hover { box-shadow: 0 12px 24px rgba(225, 29, 72, 0.30); }
        @media (max-width:1200px) { .action-stack { flex-wrap: wrap; } }
        .module-actions { margin: 0 0 1rem; display:flex; justify-content:flex-end; }
        .bo-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 21, 42, .42);
            backdrop-filter: blur(5px);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .bo-modal-backdrop.open { display: flex; animation: boFadeIn .2s ease; }
        @keyframes boFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .bo-modal {
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #d2dbef;
            border-radius: 20px;
            box-shadow: 0 26px 60px rgba(30, 41, 59, 0.28);
            padding: 26px;
            animation: boModalIn .26s cubic-bezier(.16,1,.3,1);
        }
        @keyframes boModalIn { from { opacity: 0; transform: scale(.96) translateY(10px); } to { opacity: 1; transform: none; } }
        .bo-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px; }
        .bo-modal-head h3 { margin:0; font-size:1.05rem; font-weight:700; color:#1f2a44; }
        .bo-modal-close { background:none; border:none; cursor:pointer; padding:4px; color:#8a9ab8; border-radius:6px; }
        .bo-modal-close:hover { background:#eef2ff; color:#5e6b8f; }
        .bo-modal .form-panel { padding:0; border:0; border-radius:0; background:transparent; box-shadow:none; }
        .bo-modal .form-panel label { font-size:.77rem; font-weight:600; color:#5e6b8f; margin:0 0 4px; }
        .bo-modal .form-panel input,
        .bo-modal .form-panel select,
        .bo-modal .form-panel textarea {
            padding:8px 11px;
            background:#eef2fa;
            border:1px solid #d2dbef;
            border-radius:10px;
            font-size:.84rem;
            color:#25324b;
        }
        .bo-modal .form-panel input:focus,
        .bo-modal .form-panel select:focus,
        .bo-modal .form-panel textarea:focus { border-color:#818cf8; box-shadow:0 0 0 3px rgba(99,102,241,.15); }
        .bo-modal .form-panel textarea { min-height:88px; resize:vertical; }
        .bo-modal-actions { display:flex; gap:8px; margin-top:18px; justify-content:flex-end; }
        .bo-modal-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px; font-size:.81rem; font-weight:700; border:1px solid transparent; cursor:pointer; }
        .bo-modal-btn.ghost { background:#eef2fa; border-color:#d4ddef; color:#607092; }
        .bo-modal-btn.primary { background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#fff; box-shadow:0 12px 26px rgba(79,70,229,.25); }
        .bo-modal-btn.primary:hover { filter:brightness(1.03); }
        @media (max-width:900px){ .field-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="app-container">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">VoP</div>
            <div class="brand-text">Diversity</div>
        </div>
        <nav class="nav-menu">
            <a href="dashboardUser.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Overview
            </a>
            <a href="dashboardUser.php#users" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                User
            </a>
            <a href="JobOffer.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                Job Offers
            </a>
            <a href="Projects.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                Projects
            </a>
            <a href="Contracts.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg>
                Contracts
            </a>
            <a href="dashboardUser.php#settings" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Settings
            </a>
            <a href="../FrontOffice/home.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" x2="19" y1="12" y2="12"></line><polyline points="12 5 5 12 12 19"></polyline></svg>
                Exit Backend
            </a>
        </nav>
        <div class="user-widget">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars($displayName) ?></strong>
                <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="../../index.php?action=logout" class="logout-icon" aria-label="Sign out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header animate-enter">
            <div class="page-title">
                <h1>Job Offers Dashboard</h1>
                <p>Create and monitor offers with application insights.</p>
            </div>
            <div class="header-actions">
                <div class="search-bar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                    <input type="text" id="jobOfferSearch" placeholder="Search offers or applications..." aria-label="Search offers or applications">
                </div>
            </div>
        </header>

        <?php if ($notice['message'] !== ''): ?><div class="notice <?= htmlspecialchars($notice['type']) ?>"><?= htmlspecialchars($notice['message']) ?></div><?php endif; ?>

        <div class="module-actions">
            <button class="btn-primary" type="button" onclick="openCreateOfferModal()" style="display:inline-flex;align-items:center;gap:8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                Create offer
            </button>
        </div>

        <div class="bento-grid">
            <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.05s;">
                <div><div class="kpi-h">Total offers</div><div class="kpi-v"><?= (int) $stats['total'] ?></div></div>
                <div class="icon-box icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg></div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.08s;">
                <div><div class="kpi-h">Open offers</div><div class="kpi-v"><?= (int) $stats['open'] ?></div></div>
                <div class="icon-box icon-indigo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.11s;">
                <div><div class="kpi-h">Applications</div><div class="kpi-v"><?= (int) $stats['applications'] ?></div></div>
                <div class="icon-box icon-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg></div>
            </div>

            <section class="card span-12 animate-enter" style="animation-delay: 0.18s;" id="offers-list">
                <div class="section-head"><h2>Offers list</h2><span style="font-size:0.8rem;color:var(--b-text-muted);"><?= count($rows) ?> rows</span></div>
                <div style="overflow-x: auto;">
                    <table class="elegant-table">
                        <thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Status</th><th>Applications</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int) ($row['id'] ?? 0) ?></td>
                                <td><strong><?= htmlspecialchars((string) ($row['title'] ?? '')) ?></strong><br><span>Budget: <?= number_format((float) ($row['budget'] ?? 0), 2) ?> TND</span></td>
                                <td><?= htmlspecialchars(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')) ?: 'Unknown') ?></td>
                                <td><?= htmlspecialchars((string) ($row['status'] ?? 'open')) ?></td>
                                <td><?= (int) ($row['applications_count'] ?? 0) ?></td>
                                <td style="text-align:right;">
                                    <div class="action-stack" style="justify-content:flex-end;">
                                        <form method="post" class="action-stack">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_offer"><input type="hidden" name="offer_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                            <select name="status" class="action-select"><?php foreach (['open', 'in_progress', 'closed', 'archived'] as $status): ?><option value="<?= $status ?>" <?= (string) ($row['status'] ?? '') === $status ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select>
                                            <button class="btn-mini" type="submit">Save</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this offer?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="delete_offer"><input type="hidden" name="offer_id" value="<?= (int) ($row['id'] ?? 0) ?>"><button class="btn-mini delete" type="submit">Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="6">No offers yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card span-12 animate-enter" style="animation-delay: 0.22s;" id="candidatures-list">
                <div class="section-head"><h2>Candidatures</h2><span style="font-size:0.8rem;color:var(--b-text-muted);"><?= count($candidatures) ?> rows</span></div>
                <div style="overflow-x: auto;">
                    <table class="elegant-table">
                        <thead><tr><th>ID</th><th>Offer</th><th>Freelancer</th><th>Message</th><th>Status</th><th>Created</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($candidatures as $candidature): ?>
                            <tr>
                                <td><?= (int) ($candidature['application_id'] ?? $candidature['candidature_id'] ?? 0) ?></td>
                                <td><strong><?= htmlspecialchars((string) ($candidature['offer_title'] ?? '')) ?></strong></td>
                                <td><?= htmlspecialchars(trim((string) ($candidature['first_name'] ?? '') . ' ' . (string) ($candidature['last_name'] ?? ''))) ?></td>
                                <td style="max-width:360px;"><?= htmlspecialchars(mb_strimwidth(trim((string) ($candidature['message'] ?? '')), 0, 120, '…')) ?></td>
                                <td><?= htmlspecialchars((string) ($candidature['status'] ?? 'pending')) ?></td>
                                <td><?= htmlspecialchars((string) ($candidature['candidature_created_at'] ?? $candidature['applied_at'] ?? '')) ?></td>
                                <td style="text-align:right;">
                                    <form method="post" onsubmit="return confirm('Delete this application?');" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_candidature">
                                        <input type="hidden" name="application_id" value="<?= (int) ($candidature['application_id'] ?? 0) ?>">
                                        <button class="btn-mini delete" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$candidatures): ?><tr><td colspan="7">No candidatures yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="bo-modal-backdrop" id="createOfferModal" onclick="if(event.target===this) closeCreateOfferModal()">
            <div class="bo-modal">
                <div class="bo-modal-head">
                    <h3>Post a Job Offer</h3>
                    <button type="button" class="bo-modal-close" onclick="closeCreateOfferModal()" aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="createOfferForm" method="post" class="form-panel" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="create_offer">
                    <div class="field-grid">
                        <div><label>Title</label><input type="text" name="title" required></div>
                        <div><label>Budget</label><input type="number" name="budget" min="0" step="0.01" value="0"></div>
                        <div class="full"><label>Description</label><textarea name="description" required></textarea></div>
                        <div><label>Project Type</label><select name="project_type"><option>Fixed Price</option><option>Hourly</option><option>Retainer</option><option>Long-term</option></select></div>
                        <div><label>Experience Level</label><select name="experience_level"><option>Junior</option><option selected>Mid</option><option>Senior</option><option>Expert</option></select></div>
                        <div><label>Location</label><input type="text" name="location"></div>
                        <div><label>Status</label><select name="status"><option value="open">Open</option><option value="in_progress">In progress</option><option value="closed">Closed</option><option value="archived">Archived</option></select></div>
                        <div><label>Deadline</label><input type="datetime-local" name="deadline_at"></div>
                        <div class="full"><label>Skills</label><input type="text" name="skills_required"></div>
                        <div class="full"><label>Client</label><select name="client_id" required><option value="">Select a client</option><?php foreach ($users as $user): ?><option value="<?= (int) ($user['id'] ?? 0) ?>\"><?= htmlspecialchars($controller->userLabel($user)) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="bo-modal-actions">
                        <button type="button" class="bo-modal-btn ghost" onclick="closeCreateOfferModal()">Cancel</button>
                        <button class="bo-modal-btn primary" type="submit">Publish Offer</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

    <script>
    function openCreateOfferModal(){
        document.getElementById('createOfferModal')?.classList.add('open');
    }
    function closeCreateOfferModal(){
        document.getElementById('createOfferModal')?.classList.remove('open');
    }
    const jobOfferSearch = document.getElementById('jobOfferSearch');
    const filterOfferTables = () => {
        const term = String(jobOfferSearch?.value || '').trim().toLowerCase();
        ['offers-list', 'candidatures-list'].forEach((sectionId) => {
            document.querySelectorAll(`#${sectionId} tbody tr`).forEach((row) => {
                row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    };
    jobOfferSearch?.addEventListener('input', filterOfferTables);
    </script>

    <script src="../../assets/js/globe-explorer.js"></script>
    <script src="../../assets/js/mvc-inline-validation.js"></script>
    <script src="../../assets/js/user.js"></script>
    <script src="../../assets/js/skilluser.js"></script>
    <script src="../../assets/js/backoffice-dashboard.js"></script>
</body>
</html>
