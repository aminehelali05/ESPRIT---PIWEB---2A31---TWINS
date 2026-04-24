<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ProjectController.php');
include_once(__DIR__ . '/../../config.php');
if (!UserController::isAuthenticated()) { header('Location: auth.php'); exit; }

$pdo         = config::getConnexion();
$controller  = new ProjectController($pdo);
$currentUser = UserController::currentUser() ?? [];
$userId      = (int)($currentUser['id'] ?? 0);
$role        = strtolower((string)($currentUser['role'] ?? 'user'));
$firstName   = trim((string)($currentUser['first_name'] ?? 'Member'));
$lastName    = trim((string)($currentUser['last_name'] ?? ''));
$displayName = trim($firstName . ' ' . $lastName) ?: 'Member';
$displayEmail = (string)($currentUser['email'] ?? '');
$isAdminSidebar = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$navInitials = strtoupper(substr($firstName, 0, 1) . substr($lastName ?: 'M', 0, 1));
$reputationScore = 84; $level = 'Level 12'; $streak = 7;

if (empty($_SESSION['csrf_token_front'])) { $_SESSION['csrf_token_front'] = bin2hex(random_bytes(24)); }
$csrfToken = (string)$_SESSION['csrf_token_front'];
$notice    = $_SESSION['projects_notice_front'] ?? ['type' => '', 'message' => ''];
unset($_SESSION['projects_notice_front']);
$clean     = static fn($v): string => trim((string)($v ?? ''));
$currentPage = basename(parse_url((string)($_SERVER['REQUEST_URI'] ?? 'projects.php'), PHP_URL_PATH) ?: 'projects.php');
$currentQuery = (string)($_SERVER['QUERY_STRING'] ?? '');
$selfRedirect = $currentPage . ($currentQuery !== '' ? '?' . $currentQuery : '');
$redirectAfterPost = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $notice = ['type' => 'error', 'message' => 'Invalid security token.'];
        $redirectAfterPost = $selfRedirect;
    } else {
        $action = strtolower($clean($_POST['action'] ?? ''));
        try {
            if ($action === 'create_project') {
                $payload = [
                    'title'             => $clean($_POST['title'] ?? ''),
                    'description'       => $clean($_POST['description'] ?? ''),
                    'short_description' => $clean($_POST['short_description'] ?? ''),
                    'technologies'      => $clean($_POST['technologies'] ?? ''),
                    'status'            => strtolower($clean($_POST['status'] ?? 'planning')),
                    'progress_percent'  => $_POST['progress_percent'] ?? 0,
                    'budget'            => $_POST['budget'] ?? '',
                    'due_date'          => $clean($_POST['due_date'] ?? ''),
                    'owner_id'          => $userId,
                    'visibility'        => strtolower($clean($_POST['visibility'] ?? 'team')),
                ];
                $controller->create($payload);
                $notice = ['type' => 'success', 'message' => 'Project created successfully!'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'update_project') {
                $pid     = (int)($_POST['project_id'] ?? 0);
                $payload = [
                    'title'             => $clean($_POST['title'] ?? ''),
                    'description'       => $clean($_POST['description'] ?? ''),
                    'short_description' => $clean($_POST['short_description'] ?? ''),
                    'technologies'      => $clean($_POST['technologies'] ?? ''),
                    'status'            => strtolower($clean($_POST['status'] ?? 'planning')),
                    'progress_percent'  => $_POST['progress_percent'] ?? 0,
                    'budget'            => $_POST['budget'] ?? '',
                    'due_date'          => $clean($_POST['due_date'] ?? ''),
                    'visibility'        => strtolower($clean($_POST['visibility'] ?? 'team')),
                ];
                $controller->updateOwnedByUser($pid, $userId, $payload);
                $notice = ['type' => 'success', 'message' => 'Project updated.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'delete_project') {
                $controller->deleteOwnedByUser((int)($_POST['project_id'] ?? 0), $userId);
                $notice = ['type' => 'success', 'message' => 'Project deleted.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'create_task') {
                $controller->createTask([
                    'projet_id' => (int)($_POST['projet_id'] ?? 0),
                    'title' => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status' => strtolower($clean($_POST['status'] ?? 'todo')),
                    'deadline' => $clean($_POST['deadline'] ?? ''),
                    'owner_id' => $userId,
                ]);
                $notice = ['type' => 'success', 'message' => 'Task created successfully.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'update_task') {
                $taskId = (int)($_POST['task_id'] ?? 0);
                $controller->updateTaskForOwner($taskId, $userId, [
                    'title' => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status' => strtolower($clean($_POST['status'] ?? 'todo')),
                    'deadline' => $clean($_POST['deadline'] ?? ''),
                ]);
                $notice = ['type' => 'success', 'message' => 'Task updated.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'delete_task') {
                $taskId = (int)($_POST['task_id'] ?? 0);
                $controller->deleteTaskForOwner($taskId, $userId);
                $notice = ['type' => 'success', 'message' => 'Task deleted.'];
                $redirectAfterPost = $selfRedirect;
            }
        } catch (Throwable $e) { $notice = ['type' => 'error', 'message' => $e->getMessage()]; }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['projects_notice_front'] = $notice;
    header('Location: ' . ($redirectAfterPost !== '' ? $redirectAfterPost : $selfRedirect));
    exit;
}
$filters = [
    'q' => $clean($_GET['q'] ?? ''), 'status' => $clean($_GET['status'] ?? 'all'),
    'visibility' => $clean($_GET['visibility'] ?? 'all'), 'sort' => $clean($_GET['sort'] ?? 'newest'),
];
$rows = $controller->listFrontofficeRows($userId, $filters);
$projectIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows);
$tasksByProject = $projectIds !== [] ? $controller->tasksMapForProjects($projectIds) : [];
$statusMeta = [
    'planning'  => ['bg'=>'rgba(99,102,241,.1)',   'text'=>'#6366f1','dot'=>'#6366f1','label'=>'Planning'],
    'active'    => ['bg'=>'rgba(5,150,105,.1)',    'text'=>'#059669','dot'=>'#10b981','label'=>'Active'],
    'completed' => ['bg'=>'rgba(14,165,233,.1)',   'text'=>'#0284c7','dot'=>'#0ea5e9','label'=>'Completed'],
    'on_hold'   => ['bg'=>'rgba(245,158,11,.1)',   'text'=>'#d97706','dot'=>'#f59e0b','label'=>'On Hold'],
    'archived'  => ['bg'=>'rgba(107,114,128,.1)',  'text'=>'#6b7280','dot'=>'#9ca3af','label'=>'Archived'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Explore and manage collaborative projects on Diversity.is.">
  <title>Projects — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    .module-page-header { margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .module-page-header-text h2 { font-size:1.35rem; font-weight:700; letter-spacing:-.02em; color:var(--color-text-primary); margin:0; }
    .module-page-header-text p  { font-size:.84rem; color:var(--color-text-secondary); margin:4px 0 0; }
    .module-notice { display:flex; align-items:center; gap:9px; padding:11px 15px; border-radius:12px; margin-bottom:18px; font-size:.84rem; font-weight:500; animation:mnotice .3s ease; }
    .module-notice.success { background:rgba(5,150,105,.09); border:1px solid rgba(5,150,105,.22); color:#065f46; }
    .module-notice.error   { background:rgba(225,29,72,.08); border:1px solid rgba(225,29,72,.18); color:#9f1239; }
    @keyframes mnotice { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

    /* Filters */
    .pj-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:12px 14px; margin-bottom:18px; }
    .pj-search-wrap { position:relative; flex:1; min-width:160px; }
    .pj-search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--color-text-muted); }
    .pj-search { width:100%; padding:8px 10px 8px 33px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; font-size:.82rem; color:var(--color-text-primary); }
    .pj-search:focus { border-color:var(--color-accent); outline:none; }
    .pj-sel { padding:8px 10px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; font-size:.81rem; color:var(--color-text-primary); cursor:pointer; }
    .pj-filter-btn { display:inline-flex; align-items:center; gap:5px; background:var(--color-accent); color:#fff; border:none; border-radius:8px; padding:8px 14px; font-size:.81rem; font-weight:600; cursor:pointer; }

    /* Grid */
    .pj-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:14px; }

    /* Card */
    .pj-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; transition:all .25s ease; }
    .pj-card:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(0,0,0,.08); border-color:var(--color-border-strong); }
    .pj-card-banner { height:4px; background:linear-gradient(90deg,var(--color-accent),var(--color-accent-secondary)); }
    .pj-card-body { padding:16px; flex:1; display:flex; flex-direction:column; gap:10px; }
    .pj-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
    .pj-card-title { font-size:.92rem; font-weight:700; color:var(--color-text-primary); line-height:1.3; }
    .pj-status-badge { display:inline-flex; align-items:center; gap:4px; padding:2px 9px; border-radius:999px; font-size:.68rem; font-weight:600; white-space:nowrap; flex-shrink:0; }
    .pj-status-dot { width:5px; height:5px; border-radius:50%; }
    .pj-short-desc { font-size:.79rem; color:var(--color-text-secondary); line-height:1.55; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

    /* Progress */
    .pj-progress-wrap { display:flex; flex-direction:column; gap:4px; }
    .pj-progress-label { display:flex; justify-content:space-between; font-size:.73rem; font-weight:500; color:var(--color-text-secondary); }
    .pj-progress-bar-bg { height:5px; background:var(--color-surface-2); border-radius:999px; overflow:hidden; border:1px solid var(--color-border); }
    .pj-progress-bar { height:100%; border-radius:999px; background:linear-gradient(90deg,var(--color-accent),var(--color-accent-secondary)); }

    /* Tech & meta */
    .pj-tech-pills { display:flex; flex-wrap:wrap; gap:4px; }
    .pj-tech-tag { background:var(--color-accent-light); color:var(--color-accent); border-radius:5px; padding:2px 7px; font-size:.69rem; font-weight:500; }
    .pj-card-meta { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:7px; }
    .pj-meta-info { display:flex; align-items:center; gap:4px; font-size:.73rem; color:var(--color-text-muted); }
    .pj-vis-pill { font-size:.7rem; font-weight:600; padding:2px 8px; border-radius:999px; background:rgba(99,102,241,.07); }

    /* Footer */
    .pj-card-footer { padding:10px 16px; background:var(--color-surface-2); border-top:1px solid var(--color-border); display:flex; gap:7px; flex-wrap:wrap; }

    /* Buttons */
    .pj-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 11px; border-radius:7px; border:none; cursor:pointer; font-size:.77rem; font-weight:600; transition:all .17s; }
    .pj-btn-primary { background:var(--color-accent); color:#fff; }
    .pj-btn-primary:hover { background:var(--color-accent-hover); }
    .pj-btn-danger  { background:rgba(225,29,72,.08); color:#be123c; border:1px solid rgba(225,29,72,.18); }
    .pj-btn-danger:hover { background:rgba(225,29,72,.13); }
    .pj-btn-ghost   { background:var(--color-surface); color:var(--color-text-secondary); border:1px solid var(--color-border); }
    .pj-btn-ghost:hover { color:var(--color-text-primary); }

    /* Empty */
    .pj-empty { text-align:center; padding:46px 20px; }
    .pj-empty-icon { margin:0 auto 13px; width:54px; height:54px; border-radius:14px; background:var(--color-surface-2); display:flex; align-items:center; justify-content:center; }
    .pj-empty h3 { font-size:.93rem; font-weight:600; color:var(--color-text-primary); }
    .pj-empty p  { font-size:.81rem; color:var(--color-text-secondary); margin-top:5px; }

    /* Modal */
    .pj-modal-backdrop { position:fixed; inset:0; background:rgba(15,21,42,.42); backdrop-filter:blur(5px); z-index:200; display:none; align-items:center; justify-content:center; padding:20px; }
    .pj-modal-backdrop.open { display:flex; animation:pmfade .2s ease; }
    @keyframes pmfade { from{opacity:0} to{opacity:1} }
    .pj-modal { background:var(--color-surface); border:1px solid var(--color-border-strong); border-radius:20px; padding:26px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-xl); animation:pmmodal .26s cubic-bezier(.16,1,.3,1); }
    @keyframes pmmodal { from{opacity:0;transform:scale(.96) translateY(10px)} to{opacity:1;transform:none} }
    .pj-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .pj-modal-head h3 { font-size:1.05rem; font-weight:700; color:var(--color-text-primary); margin:0; }
    .pj-modal-close { background:none; border:none; cursor:pointer; padding:4px; color:var(--color-text-muted); border-radius:6px; }
    .pj-fgrid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .pj-fgrid .full { grid-column:1/-1; }
    .pj-fg { display:flex; flex-direction:column; gap:4px; }
    .pj-fl { font-size:.77rem; font-weight:600; color:var(--color-text-secondary); }
    .pj-fi, .pj-fsel, .pj-fta { padding:8px 11px; background:var(--color-surface-2); border:1px solid var(--color-border-strong); border-radius:8px; font-size:.84rem; color:var(--color-text-primary); }
    .pj-fi:focus, .pj-fsel:focus, .pj-fta:focus { border-color:var(--color-accent); outline:none; }
    .pj-fta { resize:vertical; min-height:80px; }
  </style>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="container">
      <a href="home.php" class="navbar-brand" aria-label="Diversity home">
        <span class="brand-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </span>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav" aria-hidden="true"></div>
      <div class="navbar-actions">
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu"><div class="nav-avatar"><?= htmlspecialchars($navInitials) ?></div></button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header"><strong><?= htmlspecialchars($displayName) ?></strong><span><?= htmlspecialchars($displayEmail) ?></span></div>
            <a href="profile.php" class="nav-dropdown-item"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> My Profile</a>
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/><path d="M14 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6z"/><path d="M4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2z"/><path d="M14 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2z"/></svg> Dashboard</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

  <main class="home-hub" id="main-content" tabindex="-1">
    <div class="home-grid container">

      <aside class="home-left glass-card" aria-label="Quick profile and navigation">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($navInitials) ?></div>
          <div><h4><?= htmlspecialchars($displayName) ?></h4><p><?= htmlspecialchars($level) ?></p></div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Home Feed</a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> Social</a>
          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Profile</a>
          <a href="jobOffer.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg> Job Offers</a>
          <a class="active" href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg> Projects &amp; Collaborators</a>
          <a href="contracts.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg> Contracts</a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Challenges &amp; Daily Quiz</a>
          <?php if ($isAdminSidebar): ?>
            <div style="margin: 12px 0 6px 12px; font-size: 0.7rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.08em;">BackOffice</div>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Dashboard</a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= $reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak 🔥</span><strong><?= $streak ?> days</strong></div>
        </div>
      </aside>

      <section class="home-main" aria-label="Projects">
        <div class="module-page-header">
          <div class="module-page-header-text">
            <h2>Projects &amp; Collaborators</h2>
            <p>Create, track, and explore collaborative projects across the platform.</p>
          </div>
          <button class="pj-btn pj-btn-primary" onclick="document.getElementById('createModal').classList.add('open')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Project
          </button>
        </div>

        <?php if (!empty($notice['message'])): ?>
        <div class="module-notice <?= htmlspecialchars((string)($notice['type'] ?? 'success')) ?>">
          <span><?= htmlspecialchars((string)$notice['message']) ?></span>
        </div>
        <?php endif; ?>

        <form method="get">
          <div class="pj-filters">
            <div class="pj-search-wrap">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input class="pj-search" type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Search projects…">
            </div>
            <select class="pj-sel" name="status">
              <option value="all" <?= $filters['status']==='all'?'selected':'' ?>>All Status</option>
              <?php foreach (['planning','active','completed','on_hold','archived'] as $s): ?><option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
            </select>
            <select class="pj-sel" name="visibility">
              <option value="all" <?= $filters['visibility']==='all'?'selected':'' ?>>Visibility</option>
              <option value="team" <?= $filters['visibility']==='team'?'selected':'' ?>>Team</option>
              <option value="public" <?= $filters['visibility']==='public'?'selected':'' ?>>Public</option>
              <option value="private" <?= $filters['visibility']==='private'?'selected':'' ?>>Private</option>
            </select>
            <select class="pj-sel" name="sort">
              <option value="newest" <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
              <option value="progress" <?= $filters['sort']==='progress'?'selected':'' ?>>Progress</option>
              <option value="budget_desc" <?= $filters['sort']==='budget_desc'?'selected':'' ?>>Budget ↓</option>
            </select>
            <button type="submit" class="pj-filter-btn"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Filter</button>
          </div>
        </form>

        <?php if (empty($rows)): ?>
        <div class="pj-empty">
          <div class="pj-empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg></div>
          <h3>No projects found</h3>
          <p>Start your first project or adjust the filters.</p>
        </div>
        <?php else: ?>
        <div class="pj-grid">
          <?php foreach ($rows as $row):
            $pId      = (int)($row['id'] ?? 0);
            $pStatus  = (string)($row['status'] ?? 'planning');
            $sm       = $statusMeta[$pStatus] ?? $statusMeta['planning'];
            $isOwner  = (int)($row['owner_id'] ?? 0) === $userId;
            $progress = (int)($row['progress_percent'] ?? 0);
            $techs    = array_slice(array_filter(array_map('trim', explode(',', (string)($row['technologies'] ?? '')))), 0, 5);
            $dueDate  = (string)($row['due_date'] ?? '');
            $budget   = (float)($row['budget'] ?? 0);
            $shortDesc= trim((string)($row['short_description'] ?? '')) ?: trim((string)($row['description'] ?? ''));
            $pVis     = (string)($row['visibility'] ?? 'team');
            $projectTasks = $tasksByProject[$pId] ?? [];
          ?>
          <div class="pj-card">
            <div class="pj-card-banner"></div>
            <div class="pj-card-body">
              <div class="pj-card-head">
                <div class="pj-card-title"><?= htmlspecialchars((string)($row['title'] ?? '')) ?></div>
                <span class="pj-status-badge" style="background:<?= $sm['bg'] ?>;color:<?= $sm['text'] ?>">
                  <span class="pj-status-dot" style="background:<?= $sm['dot'] ?>"></span><?= $sm['label'] ?>
                </span>
              </div>
              <?php if ($shortDesc !== ''): ?><p class="pj-short-desc"><?= htmlspecialchars($shortDesc) ?></p><?php endif; ?>
              <div class="pj-progress-wrap">
                <div class="pj-progress-label"><span>Progress</span><span style="font-weight:700;color:var(--color-accent)"><?= $progress ?>%</span></div>
                <div class="pj-progress-bar-bg"><div class="pj-progress-bar" style="width:<?= $progress ?>%"></div></div>
              </div>
              <?php if (!empty($techs)): ?><div class="pj-tech-pills"><?php foreach ($techs as $t): ?><span class="pj-tech-tag"><?= htmlspecialchars(trim($t)) ?></span><?php endforeach; ?></div><?php endif; ?>
              <div class="pj-card-meta">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <?php if ($budget > 0): ?><span class="pj-meta-info"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg><?= number_format($budget, 0) ?> TND</span><?php endif; ?>
                  <?php if ($dueDate): ?><span class="pj-meta-info"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= htmlspecialchars(date('M j, Y', strtotime($dueDate))) ?></span><?php endif; ?>
                </div>
                <span class="pj-vis-pill"><?= ucfirst($pVis) ?></span>
              </div>
              <?php if (!empty($projectTasks)): ?>
              <div style="display:flex;flex-direction:column;gap:8px;border-top:1px solid var(--color-border);padding-top:10px;">
                <div style="font-size:.74rem;font-weight:600;color:var(--color-text-muted);display:flex;justify-content:space-between;align-items:center;">
                  <span>Tasks</span>
                  <span><?= count($projectTasks) ?></span>
                </div>
                <?php foreach ($projectTasks as $task): ?>
                <div style="background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:10px;padding:10px 11px;display:flex;flex-direction:column;gap:6px;">
                  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                    <strong style="font-size:.8rem;color:var(--color-text-primary);"><?= htmlspecialchars((string)($task['title'] ?? '')) ?></strong>
                    <span style="font-size:.68rem;font-weight:700;color:var(--color-accent);text-transform:uppercase;letter-spacing:.04em;"><?= htmlspecialchars((string)($task['status'] ?? 'todo')) ?></span>
                  </div>
                  <?php if (trim((string)($task['description'] ?? '')) !== ''): ?><div style="font-size:.72rem;color:var(--color-text-secondary);line-height:1.5;"><?= htmlspecialchars(mb_strimwidth(trim((string)($task['description'] ?? '')), 0, 140, '…')) ?></div><?php endif; ?>
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-size:.68rem;color:var(--color-text-muted);"><?= htmlspecialchars((string)($task['deadline'] ?? '')) ?></span>
                    <?php if ($isOwner): ?>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                      <button type="button" class="pj-btn pj-btn-ghost" style="padding:4px 8px;" onclick='openEditTaskModal(<?= (int)($task["id"] ?? 0) ?>, <?= $pId ?>, <?= json_encode((string)($task["title"] ?? ""), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode(trim((string)($task["description"] ?? "")), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($task["status"] ?? "todo"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($task["deadline"] ?? ""), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Edit</button>
                      <form method="post" data-confirm="Delete this task?" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_task">
                        <input type="hidden" name="task_id" value="<?= (int)($task['id'] ?? 0) ?>">
                        <button type="submit" class="pj-btn pj-btn-danger" style="padding:4px 8px;">Delete</button>
                      </form>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
              <?php if ($isOwner): ?>
            <div class="pj-card-footer">
                <button class="pj-btn pj-btn-ghost" onclick='openEditModal(<?= $pId ?>,<?= json_encode((string)($row['title'] ?? '')) ?>,<?= json_encode(trim((string)($row['description'] ?? ''))) ?>,<?= json_encode(trim((string)($row['short_description'] ?? ''))) ?>,<?= json_encode((string)($row['technologies'] ?? '')) ?>,<?= json_encode($pStatus) ?>,<?= $progress ?>,<?= $budget ?>,<?= json_encode($dueDate) ?>,<?= json_encode($pVis) ?>)'>
                  <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit
                </button>
                <button type="button" class="pj-btn pj-btn-primary" onclick='openTaskModal(<?= $pId ?>, <?= json_encode((string)($row["title"] ?? ""), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>Add Task</button>
                <form method="post" data-confirm="Delete this project?" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="action" value="delete_project">
                <input type="hidden" name="project_id" value="<?= $pId ?>">
                <button type="submit" class="pj-btn pj-btn-danger"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Delete</button>
              </form>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- Create Modal -->
  <div class="pj-modal-backdrop" id="createModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3>Create New Project</h3>
        <button class="pj-modal-close" onclick="document.getElementById('createModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="createProjectForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_project">
        <div class="pj-fgrid">
          <div class="pj-fg full"><label class="pj-fl">Project Title *</label><input type="text" name="title" class="pj-fi" placeholder="e.g. GreenOps Hub" required></div>
          <div class="pj-fg full"><label class="pj-fl">Short Description</label><input type="text" name="short_description" class="pj-fi" placeholder="One-line summary…"></div>
          <div class="pj-fg full"><label class="pj-fl">Full Description *</label><textarea name="description" class="pj-fta" placeholder="Describe goals, scope, deliverables…" required></textarea></div>
          <div class="pj-fg full"><label class="pj-fl">Technologies <span style="font-weight:400;color:var(--color-text-muted)">(comma-separated)</span></label><input type="text" name="technologies" class="pj-fi" placeholder="React, Laravel, PostgreSQL"></div>
          <div class="pj-fg"><label class="pj-fl">Status</label><select name="status" class="pj-fsel"><option value="planning">Planning</option><option value="active">Active</option><option value="completed">Completed</option><option value="on_hold">On Hold</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Visibility</label><select name="visibility" class="pj-fsel"><option value="team">Team</option><option value="public">Public</option><option value="private">Private</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Budget (TND)</label><input type="number" name="budget" class="pj-fi" min="0" step="0.01" placeholder="0.00"></div>
          <div class="pj-fg"><label class="pj-fl">Due Date</label><input type="date" name="due_date" class="pj-fi"></div>
          <div class="pj-fg full"><label class="pj-fl">Initial Progress (%)</label><input type="number" name="progress_percent" class="pj-fi" min="0" max="100" value="0"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Project</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="pj-modal-backdrop" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3>Edit Project</h3>
        <button class="pj-modal-close" onclick="document.getElementById('editModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="editProjectForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_project">
        <input type="hidden" name="project_id" id="editProjectId">
        <div class="pj-fgrid">
          <div class="pj-fg full"><label class="pj-fl">Project Title *</label><input type="text" name="title" id="editTitle" class="pj-fi" required></div>
          <div class="pj-fg full"><label class="pj-fl">Short Description</label><input type="text" name="short_description" id="editShortDesc" class="pj-fi"></div>
          <div class="pj-fg full"><label class="pj-fl">Full Description *</label><textarea name="description" id="editDesc" class="pj-fta" required></textarea></div>
          <div class="pj-fg full"><label class="pj-fl">Technologies</label><input type="text" name="technologies" id="editTech" class="pj-fi"></div>
          <div class="pj-fg"><label class="pj-fl">Status</label><select name="status" id="editStatus" class="pj-fsel"><option value="planning">Planning</option><option value="active">Active</option><option value="completed">Completed</option><option value="on_hold">On Hold</option><option value="archived">Archived</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Visibility</label><select name="visibility" id="editVisibility" class="pj-fsel"><option value="team">Team</option><option value="public">Public</option><option value="private">Private</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Budget (TND)</label><input type="number" name="budget" id="editBudget" class="pj-fi" min="0" step="0.01"></div>
          <div class="pj-fg"><label class="pj-fl">Due Date</label><input type="date" name="due_date" id="editDueDate" class="pj-fi"></div>
          <div class="pj-fg full"><label class="pj-fl">Progress (%)</label><input type="number" name="progress_percent" id="editProgress" class="pj-fi" min="0" max="100"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <div class="pj-modal-backdrop" id="taskModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3 id="taskModalTitle">Add Task</h3>
        <button class="pj-modal-close" onclick="document.getElementById('taskModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="createTaskForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_task">
        <input type="hidden" name="projet_id" id="taskProjectId">
        <div class="pj-fgrid">
          <div class="pj-fg full"><label class="pj-fl">Task Title *</label><input type="text" name="title" class="pj-fi" required></div>
          <div class="pj-fg full"><label class="pj-fl">Description *</label><textarea name="description" class="pj-fta" required></textarea></div>
          <div class="pj-fg"><label class="pj-fl">Status</label><select name="status" class="pj-fsel"><option value="todo">Todo</option><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="done">Done</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Deadline</label><input type="date" name="deadline" class="pj-fi"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('taskModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary">Save Task</button>
        </div>
      </form>
    </div>
  </div>

  <div class="pj-modal-backdrop" id="editTaskModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3>Edit Task</h3>
        <button class="pj-modal-close" onclick="document.getElementById('editTaskModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="editTaskForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_task">
        <input type="hidden" name="task_id" id="editTaskId">
        <div class="pj-fgrid">
          <div class="pj-fg full"><label class="pj-fl">Task Title *</label><input type="text" name="title" id="editTaskTitle" class="pj-fi" required></div>
          <div class="pj-fg full"><label class="pj-fl">Description *</label><textarea name="description" id="editTaskDescription" class="pj-fta" required></textarea></div>
          <div class="pj-fg"><label class="pj-fl">Status</label><select name="status" id="editTaskStatus" class="pj-fsel"><option value="todo">Todo</option><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="done">Done</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Deadline</label><input type="date" name="deadline" id="editTaskDeadline" class="pj-fi"></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('editTaskModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openEditModal(id,title,desc,shortDesc,tech,status,progress,budget,dueDate,visibility){
    document.getElementById('editProjectId').value   = id;
    document.getElementById('editTitle').value       = title;
    document.getElementById('editDesc').value        = desc;
    document.getElementById('editShortDesc').value   = shortDesc;
    document.getElementById('editTech').value        = tech;
    document.getElementById('editStatus').value      = status;
    document.getElementById('editProgress').value    = progress;
    document.getElementById('editBudget').value      = budget;
    document.getElementById('editDueDate').value     = dueDate;
    document.getElementById('editVisibility').value  = visibility;
    document.getElementById('editModal').classList.add('open');
  }
  function openTaskModal(projectId, projectTitle){
    document.getElementById('taskProjectId').value = projectId;
    document.getElementById('taskModalTitle').textContent = 'Add Task: ' + (projectTitle || '');
    document.getElementById('taskModal').classList.add('open');
  }
  function openEditTaskModal(taskId, projectId, title, description, status, deadline){
    document.getElementById('editTaskId').value = taskId;
    document.getElementById('editTaskTitle').value = title || '';
    document.getElementById('editTaskDescription').value = description || '';
    document.getElementById('editTaskStatus').value = status || 'todo';
    document.getElementById('editTaskDeadline').value = deadline || '';
    document.getElementById('editTaskModal').classList.add('open');
  }
  </script>

  <div class="home-toast-stack" id="homeToastStack" aria-live="polite"></div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/home.js"></script>
  <script src="../../assets/js/mvc-inline-validation.js"></script>

  <script>
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Please confirm this action.')) {
        event.preventDefault();
      }
    });
  });

  </script>
</body>
</html>
