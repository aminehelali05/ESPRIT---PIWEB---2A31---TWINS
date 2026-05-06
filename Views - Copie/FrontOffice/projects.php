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
$currentPage  = basename(parse_url((string)($_SERVER['REQUEST_URI'] ?? 'projects.php'), PHP_URL_PATH) ?: 'projects.php');
$currentQuery = (string)($_SERVER['QUERY_STRING'] ?? '');
$selfRedirect = $currentPage . ($currentQuery !== '' ? '?' . $currentQuery : '');
$redirectAfterPost = '';

// ── AI EXECUTION — AJAX endpoint ──────────────────────────────────────────────
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($clean($_POST['action'] ?? '') === 'execute_task_ai')
) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        echo json_encode(['success' => false, 'error' => 'Invalid token.']); exit;
    }
    $taskId = (int)($_POST['task_id'] ?? 0);
    try {
        $result = $controller->executeTaskWithAI($taskId, $userId);
        echo json_encode(['success' => true, 'output' => $result['output_text'], 'type' => $result['output_type']]); exit;
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
    }
}

// ── POST HANDLING ─────────────────────────────────────────────────────────────
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
                $pid = (int)($_POST['project_id'] ?? 0);
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
                    'project_id'  => (int)($_POST['project_id'] ?? 0),
                    'title'       => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status'      => strtolower($clean($_POST['status'] ?? 'todo')),
                    'deadline'    => $clean($_POST['deadline'] ?? ''),
                    'owner_id'    => $userId,
                ]);
                $notice = ['type' => 'success', 'message' => 'Task created successfully.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'update_task') {
                $controller->updateTaskForOwner((int)($_POST['task_id'] ?? 0), $userId, [
                    'title'       => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status'      => strtolower($clean($_POST['status'] ?? 'todo')),
                    'deadline'    => $clean($_POST['deadline'] ?? ''),
                ]);
                $notice = ['type' => 'success', 'message' => 'Task updated.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'delete_task') {
                $controller->deleteTaskForOwner((int)($_POST['task_id'] ?? 0), $userId);
                $notice = ['type' => 'success', 'message' => 'Task deleted.'];
                $redirectAfterPost = $selfRedirect;
            }

        } catch (Throwable $e) { $notice = ['type' => 'error', 'message' => $e->getMessage()]; }
    }
    $_SESSION['projects_notice_front'] = $notice;
    header('Location: ' . ($redirectAfterPost !== '' ? $redirectAfterPost : $selfRedirect));
    exit;
}

// ── DATA ──────────────────────────────────────────────────────────────────────
$projectIdView = (int)($_GET['view'] ?? $_GET['id'] ?? 0);
if ($projectIdView > 0) {
    $p = $controller->findById($projectIdView);
    // Security check: only show if owner or team/public
    if ($p && ((int)$p['owner_id'] === $userId || in_array((string)$p['visibility'], ['team', 'public'], true))) {
        $rows = [$p];
        $filters = ['q' => '', 'status' => 'all', 'visibility' => 'all', 'sort' => 'newest'];
    } else {
        $rows = [];
        $notice = ['type' => 'error', 'message' => 'Project not found or access denied.'];
    }
} else {
    $filters = [
        'q'          => $clean($_GET['q'] ?? ''),
        'status'     => $clean($_GET['status'] ?? 'all'),
        'visibility' => $clean($_GET['visibility'] ?? 'all'),
        'sort'       => $clean($_GET['sort'] ?? 'newest'),
    ];
    $rows = $controller->listFrontofficeRows($userId, $filters);
}
$projectIds     = array_map(static fn(array $r): int => (int)($r['id'] ?? 0), $rows);
$tasksByProject = $projectIds !== [] ? $controller->tasksMapForProjects($projectIds) : [];

$statusMeta = [
    'planning'  => ['bg'=>'rgba(99,102,241,.12)',  'text'=>'#4f46e5','dot'=>'#6366f1','label'=>'Planning'],
    'active'    => ['bg'=>'rgba(5,150,105,.12)',   'text'=>'#047857','dot'=>'#10b981','label'=>'Active'],
    'completed' => ['bg'=>'rgba(14,165,233,.12)',  'text'=>'#0284c7','dot'=>'#0ea5e9','label'=>'Completed'],
    'on_hold'   => ['bg'=>'rgba(245,158,11,.12)',  'text'=>'#b45309','dot'=>'#f59e0b','label'=>'On Hold'],
    'archived'  => ['bg'=>'rgba(107,114,128,.12)', 'text'=>'#4b5563','dot'=>'#9ca3af','label'=>'Archived'],
];

// Build current page base URL for QR codes
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl   = rtrim($protocol . '://' . $host . $scriptDir, '/');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Projects — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    /* ── Page Header ────────────────────────────────────────── */
    .module-page-header { margin-bottom:24px; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .module-page-header-text h2 { font-size:1.45rem; font-weight:800; letter-spacing:-.03em; color:var(--color-text-primary); margin:0; }
    .module-page-header-text p  { font-size:.84rem; color:var(--color-text-secondary); margin:5px 0 0; }

    /* ── Notice ─────────────────────────────────────────────── */
    .module-notice { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:14px; margin-bottom:20px; font-size:.84rem; font-weight:600; animation:noticeIn .35s cubic-bezier(.16,1,.3,1); }
    .module-notice.success { background:rgba(5,150,105,.08); border:1px solid rgba(5,150,105,.2); color:#047857; }
    .module-notice.error   { background:rgba(225,29,72,.07); border:1px solid rgba(225,29,72,.18); color:#9f1239; }
    @keyframes noticeIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }

    /* ── Filters ─────────────────────────────────────────────── */
    .pj-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:var(--color-surface); border:1px solid var(--color-border); border-radius:14px; padding:13px 16px; margin-bottom:22px; box-shadow:0 1px 4px rgba(0,0,0,.04); }
    .pj-search-wrap { position:relative; flex:1; min-width:160px; }
    .pj-search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--color-text-muted); pointer-events:none; }
    .pj-search { width:100%; padding:8px 10px 8px 34px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:9px; font-size:.82rem; color:var(--color-text-primary); transition:border-color .2s; }
    .pj-search:focus { border-color:var(--color-accent); outline:none; }
    .pj-sel { padding:8px 10px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:9px; font-size:.81rem; color:var(--color-text-primary); cursor:pointer; }
    .pj-filter-btn { display:inline-flex; align-items:center; gap:5px; background:var(--color-accent); color:#fff; border:none; border-radius:9px; padding:8px 15px; font-size:.81rem; font-weight:700; cursor:pointer; transition:all .18s; }
    .pj-filter-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }

    /* ── Grid ────────────────────────────────────────────────── */
    .pj-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:18px; }

    /* ── Card ────────────────────────────────────────────────── */
    .pj-card {
      background:var(--color-surface);
      border:1px solid var(--color-border);
      border-radius:20px;
      overflow:hidden;
      display:flex;
      flex-direction:column;
      transition:transform .26s cubic-bezier(.16,1,.3,1), box-shadow .26s ease, border-color .2s;
      animation:cardIn .4s cubic-bezier(.16,1,.3,1) both;
    }
    .pj-card:hover { transform:translateY(-5px); box-shadow:0 16px 40px rgba(0,0,0,.1); border-color:rgba(99,102,241,.25); }
    @keyframes cardIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:none} }
    .pj-card:nth-child(2) { animation-delay:.06s }
    .pj-card:nth-child(3) { animation-delay:.12s }
    .pj-card:nth-child(4) { animation-delay:.18s }
    .pj-card:nth-child(n+5) { animation-delay:.24s }

    /* top color bar */
    .pj-card-banner { height:5px; background:linear-gradient(90deg,var(--color-accent) 0%,var(--color-accent-secondary,#a855f7) 100%); }

    .pj-card-body { padding:18px; flex:1; display:flex; flex-direction:column; gap:12px; }

    .pj-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
    .pj-card-title { font-size:.97rem; font-weight:800; color:var(--color-text-primary); line-height:1.25; letter-spacing:-.02em; }

    .pj-status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:.68rem; font-weight:700; white-space:nowrap; flex-shrink:0; letter-spacing:.02em; }
    .pj-status-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }

    .pj-short-desc { font-size:.8rem; color:var(--color-text-secondary); line-height:1.6; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

    /* Progress */
    .pj-progress-wrap { display:flex; flex-direction:column; gap:5px; }
    .pj-progress-label { display:flex; justify-content:space-between; font-size:.73rem; font-weight:600; color:var(--color-text-secondary); }
    .pj-progress-bar-bg { height:6px; background:var(--color-surface-2); border-radius:999px; overflow:hidden; border:1px solid var(--color-border); }
    .pj-progress-bar { height:100%; border-radius:999px; background:linear-gradient(90deg,var(--color-accent),var(--color-accent-secondary,#a855f7)); transition:width .8s cubic-bezier(.16,1,.3,1); }

    /* Tech */
    .pj-tech-pills { display:flex; flex-wrap:wrap; gap:5px; }
    .pj-tech-tag { background:rgba(99,102,241,.08); color:var(--color-accent); border-radius:6px; padding:2px 8px; font-size:.69rem; font-weight:600; border:1px solid rgba(99,102,241,.14); }

    /* Meta */
    .pj-card-meta { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; padding-top:4px; border-top:1px solid var(--color-border); margin-top:2px; }
    .pj-meta-info { display:flex; align-items:center; gap:4px; font-size:.73rem; color:var(--color-text-muted); }
    .pj-vis-pill { font-size:.68rem; font-weight:700; padding:2px 9px; border-radius:999px; background:rgba(99,102,241,.07); color:var(--color-accent); border:1px solid rgba(99,102,241,.14); letter-spacing:.02em; text-transform:uppercase; }

    /* ── QR Section ─────────────────────────────────────────── */
    .pj-qr-card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 18px;
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 16px;
      box-shadow: 0 4px 15px rgba(0,0,0,.03);
      transition: all .2s ease;
      margin: 10px 0;
    }
    .pj-qr-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.06); border-color: var(--color-accent); }
    .pj-qr-img-wrap {
      width: 90px; height: 90px; padding: 6px; background: #fff; border-radius: 12px; border: 1px solid var(--color-border); flex-shrink: 0;
    }
    .pj-qr-img-wrap img { width: 100%; height: 100%; object-fit: contain; }
    .pj-qr-content { flex: 1; min-width: 0; }
    .pj-qr-content strong { display: block; font-size: .88rem; font-weight: 800; color: var(--color-text-primary); margin-bottom: 2px; }
    .pj-qr-content span { display: block; font-size: .74rem; color: var(--color-text-secondary); margin-bottom: 12px; line-height: 1.4; }
    .pj-qr-btn-dl {
      display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: linear-gradient(135deg, var(--color-accent) 0%, #7c3aed 100%);
      color: #fff; border: none; border-radius: 8px; font-size: .75rem; font-weight: 700; cursor: pointer; transition: all .2s; box-shadow: 0 4px 12px rgba(99,102,241,0.25);
    }
    .pj-qr-btn-dl:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(99,102,241,0.35); filter: brightness(1.05); }

    /* ── Validation States ──────────────────────────────────── */
    .pj-fg.is-invalid .pj-fi, .pj-fg.is-invalid .pj-fsel, .pj-fg.is-invalid .pj-fta { border-color: #e11d48 !important; background-color: rgba(225,29,72,0.02) !important; }
    .pj-fg.is-valid .pj-fi, .pj-fg.is-valid .pj-fsel, .pj-fg.is-valid .pj-fta { border-color: #10b981 !important; background-color: rgba(16,185,129,0.02) !important; }
    .pj-fg.is-invalid .pj-fl { color: #e11d48 !important; }
    .pj-fg.is-valid .pj-fl { color: #10b981 !important; }
    .pj-fi:focus { border-color: var(--color-accent) !important; box-shadow: 0 0 0 4px rgba(99,102,241,0.1) !important; }
    .field-error { font-size: .72rem; font-weight: 600; color: #be123c; margin-top: 4px; animation: slideDown .2s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:none} }
    /* ── Task Section ────────────────────────────────────────── */
    .pj-tasks-section { display:flex; flex-direction:column; gap:9px; }
    .pj-tasks-header { display:flex; justify-content:space-between; align-items:center; }
    .pj-tasks-label { font-size:.74rem; font-weight:700; color:var(--color-text-primary); }
    .pj-tasks-count { font-size:.71rem; font-weight:600; color:var(--color-text-muted); background:var(--color-surface-2); padding:1px 8px; border-radius:999px; border:1px solid var(--color-border); }

    .pj-task-card {
      background:var(--color-surface-2);
      border:1px solid var(--color-border);
      border-radius:12px;
      padding:11px 13px;
      display:flex;
      flex-direction:column;
      gap:7px;
      cursor:pointer;
      transition:all .2s ease;
      position:relative;
      overflow:hidden;
    }
    .pj-task-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:linear-gradient(180deg,var(--color-accent),var(--color-accent-secondary,#a855f7)); border-radius:0 2px 2px 0; opacity:0; transition:opacity .2s; }
    .pj-task-card:hover { border-color:rgba(99,102,241,.3); background:rgba(99,102,241,.03); transform:translateX(2px); }
    .pj-task-card:hover::before { opacity:1; }

    .pj-task-head { display:flex; justify-content:space-between; gap:8px; align-items:flex-start; }
    .pj-task-title { font-size:.82rem; font-weight:700; color:var(--color-text-primary); line-height:1.3; }
    .pj-task-status { font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; padding:2px 7px; border-radius:999px; flex-shrink:0; }
    .pj-task-status.todo       { background:rgba(107,114,128,.1);  color:#4b5563; }
    .pj-task-status.in_progress{ background:rgba(245,158,11,.1);   color:#b45309; }
    .pj-task-status.done       { background:rgba(5,150,105,.1);    color:#047857; }
    .pj-task-desc { font-size:.75rem; color:var(--color-text-secondary); line-height:1.5; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .pj-task-footer { display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
    .pj-task-deadline { font-size:.69rem; color:var(--color-text-muted); display:flex; align-items:center; gap:3px; }
    .pj-task-actions { display:flex; gap:5px; }

    /* ── Buttons ──────────────────────────────────────────────── */
    .pj-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 12px; border-radius:9px; border:none; cursor:pointer; font-size:.77rem; font-weight:700; transition:all .18s; white-space:nowrap; }
    .pj-btn-primary { background:var(--color-accent); color:#fff; }
    .pj-btn-primary:hover { filter:brightness(1.1); transform:translateY(-1px); }
    .pj-btn-danger  { background:rgba(225,29,72,.08); color:#be123c; border:1px solid rgba(225,29,72,.18); }
    .pj-btn-danger:hover { background:rgba(225,29,72,.14); }
    .pj-btn-ghost   { background:var(--color-surface); color:var(--color-text-secondary); border:1px solid var(--color-border); }
    .pj-btn-ghost:hover { color:var(--color-text-primary); border-color:var(--color-border-strong); }
    .pj-btn-ai {
      display:inline-flex; align-items:center; gap:6px;
      background:linear-gradient(135deg,#6366f1 0%,#a855f7 100%);
      color:#fff; border:none; border-radius:9px;
      padding:7px 14px; font-size:.77rem; font-weight:700;
      cursor:pointer; transition:all .2s;
      box-shadow:0 3px 10px rgba(99,102,241,.25);
    }
    .pj-btn-ai:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(99,102,241,.35); }
    .pj-btn-ai:disabled { opacity:.6; cursor:wait; }
    .pj-btn-ai-sm {
      display:inline-flex; align-items:center; gap:4px;
      background:linear-gradient(135deg,#6366f1 0%,#a855f7 100%);
      color:#fff; border:none; border-radius:7px;
      padding:4px 10px; font-size:.7rem; font-weight:700;
      cursor:pointer; transition:all .18s;
      box-shadow:0 2px 8px rgba(99,102,241,.2);
    }
    .pj-btn-ai-sm:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(99,102,241,.3); }
    .pj-btn-sm { padding:4px 9px; font-size:.72rem; }

    /* ── Card Footer ─────────────────────────────────────────── */
    .pj-card-footer { padding:12px 18px; background:var(--color-surface-2); border-top:1px solid var(--color-border); display:flex; gap:7px; flex-wrap:wrap; align-items:center; }

    /* ── Empty ───────────────────────────────────────────────── */
    .pj-empty { text-align:center; padding:60px 20px; animation:cardIn .4s ease; }
    .pj-empty-icon { margin:0 auto 16px; width:64px; height:64px; border-radius:18px; background:var(--color-surface-2); display:flex; align-items:center; justify-content:center; border:1px solid var(--color-border); }
    .pj-empty h3 { font-size:1rem; font-weight:700; color:var(--color-text-primary); }
    .pj-empty p  { font-size:.82rem; color:var(--color-text-secondary); margin-top:6px; }

    /* ── Section Divider ─────────────────────────────────────── */
    .pj-section-divider { height:1px; background:var(--color-border); margin:4px 0; }

    /* ── Modal ───────────────────────────────────────────────── */
    .pj-modal-backdrop { position:fixed; inset:0; background:rgba(15,21,42,.45); backdrop-filter:blur(6px); z-index:200; display:none; align-items:center; justify-content:center; padding:20px; }
    .pj-modal-backdrop.open { display:flex; animation:fadeIn .2s ease; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    .pj-modal { background:var(--color-surface); border:1px solid var(--color-border-strong); border-radius:22px; padding:28px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; box-shadow:0 24px 80px rgba(0,0,0,.18); animation:modalIn .28s cubic-bezier(.16,1,.3,1); }
    @keyframes modalIn { from{opacity:0;transform:scale(.95) translateY(12px)} to{opacity:1;transform:none} }
    .pj-modal-lg { max-width:720px; }
    .pj-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
    .pj-modal-head h3 { font-size:1.08rem; font-weight:800; color:var(--color-text-primary); margin:0; letter-spacing:-.02em; }
    .pj-modal-close { background:var(--color-surface-2); border:1px solid var(--color-border); cursor:pointer; padding:6px; color:var(--color-text-muted); border-radius:8px; transition:all .17s; display:flex; }
    .pj-modal-close:hover { background:var(--color-surface); color:var(--color-text-primary); }

    /* form grid */
    .pj-fgrid { display:grid; grid-template-columns:1fr 1fr; gap:13px; }
    .pj-fgrid .full { grid-column:1/-1; }
    .pj-fg { display:flex; flex-direction:column; gap:5px; }
    .pj-fl { font-size:.76rem; font-weight:700; color:var(--color-text-secondary); }
    .pj-fi, .pj-fsel, .pj-fta { padding:9px 12px; background:var(--color-surface-2); border:1px solid var(--color-border-strong); border-radius:9px; font-size:.84rem; color:var(--color-text-primary); transition:border-color .2s; }
    .pj-fi:focus, .pj-fsel:focus, .pj-fta:focus { border-color:var(--color-accent); outline:none; box-shadow:0 0 0 3px rgba(99,102,241,.1); }
    .pj-fta { resize:vertical; min-height:85px; font-family:inherit; line-height:1.5; }
    .pj-modal-actions { display:flex; gap:8px; margin-top:20px; justify-content:flex-end; }

    /* ── AI Result Container ─────────────────────────────────── */
    .pj-ai-result {
      background:linear-gradient(135deg,rgba(99,102,241,.04) 0%,rgba(168,85,247,.04) 100%);
      border:1px solid rgba(99,102,241,.2);
      border-radius:14px;
      padding:18px;
      margin-top:16px;
      animation:resultIn .4s cubic-bezier(.16,1,.3,1);
    }
    @keyframes resultIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
    .pj-ai-result-header { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:14px; }
    .pj-ai-result-label { display:flex; align-items:center; gap:6px; font-size:.78rem; font-weight:800; color:#6366f1; letter-spacing:.02em; text-transform:uppercase; }
    .pj-ai-result-label svg { animation:pulse 2s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .pj-ai-result-body {
      font-size:.83rem; color:var(--color-text-primary); line-height:1.7;
      max-height:350px; overflow-y:auto; padding-right:10px;
      scrollbar-width:thin; scrollbar-color:rgba(99,102,241,.3) transparent;
    }
    .pj-ai-result-body::-webkit-scrollbar { width:6px; }
    .pj-ai-result-body::-webkit-scrollbar-thumb { background:rgba(99,102,241,.3); border-radius:10px; }
    .pj-ai-result-body h2, .pj-ai-result-body h3 { font-size:.88rem; font-weight:700; margin:14px 0 6px; color:var(--color-text-primary); }
    .pj-ai-result-body strong { font-weight:700; color:var(--color-text-primary); }
    .pj-ai-result-body ul, .pj-ai-result-body ol { padding-left:18px; margin:8px 0; }
    .pj-ai-result-body li { margin-bottom:5px; }
    .pj-ai-result-body code { background:var(--color-surface-2); padding:1px 5px; border-radius:4px; font-family:monospace; font-size:.8rem; }
    .pj-ai-result-body pre { background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; padding:12px; overflow-x:auto; margin:8px 0; }
    .pj-ai-result-body pre code { background:none; padding:0; }

    .pj-ai-loading { display:flex; align-items:center; gap:10px; padding:16px; font-size:.83rem; font-weight:600; color:var(--color-text-secondary); }
    .pj-ai-spinner { width:18px; height:18px; border:2px solid rgba(99,102,241,.2); border-top-color:#6366f1; border-radius:50%; animation:spin .7s linear infinite; flex-shrink:0; }
    @keyframes spin { to{transform:rotate(360deg)} }

    .pj-ai-download { display:inline-flex; align-items:center; gap:5px; font-size:.74rem; font-weight:700; color:var(--color-accent); padding:5px 11px; border-radius:8px; background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.18); cursor:pointer; transition:all .17s; text-decoration:none; }
    .pj-ai-download:hover { background:rgba(99,102,241,.14); transform:translateY(-1px); }
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

      <!-- Left sidebar -->
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
            <div style="margin:12px 0 6px 12px;font-size:.7rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.08em;">BackOffice</div>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Dashboard</a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= $reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak 🔥</span><strong><?= $streak ?> days</strong></div>
        </div>
      </aside>

      <!-- Main content -->
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
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><?= $notice['type']==='success' ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' : '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' ?></svg>
          <span><?= htmlspecialchars((string)$notice['message']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="get">
          <div class="pj-filters">
            <div class="pj-search-wrap">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input class="pj-search" type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Search projects…">
            </div>
            <select class="pj-sel" name="status">
              <option value="all" <?= $filters['status']==='all'?'selected':'' ?>>All Status</option>
              <?php foreach (['planning','active','completed','on_hold','archived'] as $s): ?>
              <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
            <select class="pj-sel" name="visibility">
              <option value="all" <?= $filters['visibility']==='all'?'selected':'' ?>>All Visibility</option>
              <option value="team"   <?= $filters['visibility']==='team'?'selected':'' ?>>Team</option>
              <option value="public" <?= $filters['visibility']==='public'?'selected':'' ?>>Public</option>
              <option value="private"<?= $filters['visibility']==='private'?'selected':'' ?>>Private</option>
            </select>
            <select class="pj-sel" name="sort">
              <option value="newest"     <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
              <option value="progress"   <?= $filters['sort']==='progress'?'selected':'' ?>>Progress</option>
              <option value="budget_desc"<?= $filters['sort']==='budget_desc'?'selected':'' ?>>Budget ↓</option>
            </select>
            <button type="submit" class="pj-filter-btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
              Filter
            </button>
          </div>
        </form>

        <!-- Cards -->
        <?php if (empty($rows)): ?>
        <div class="pj-empty">
          <div class="pj-empty-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg></div>
          <h3>No projects found</h3>
          <p>Start your first project or adjust the filters.</p>
        </div>
        <?php else: ?>
        <div class="pj-grid">
          <?php foreach ($rows as $idx => $row):
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

            // QR code URL — links to this projects page with an anchor per project
            $projectUrl = $baseUrl . '/projects.php?view=' . $pId;
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&margin=6&data=' . urlencode($projectUrl);
            $qrDownloadUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&format=png&data=' . urlencode($projectUrl);
          ?>
          <div class="pj-card" id="project-<?= $pId ?>" style="animation-delay:<?= min($idx * 0.07, 0.42) ?>s">
            <div class="pj-card-banner"></div>
            <div class="pj-card-body">

              <!-- Head: title + status -->
              <div class="pj-card-head">
                <div class="pj-card-title"><?= htmlspecialchars((string)($row['title'] ?? '')) ?></div>
                <span class="pj-status-badge" style="background:<?= $sm['bg'] ?>;color:<?= $sm['text'] ?>">
                  <span class="pj-status-dot" style="background:<?= $sm['dot'] ?>"></span><?= $sm['label'] ?>
                </span>
              </div>

              <!-- Short description -->
              <?php if ($shortDesc !== ''): ?>
              <p class="pj-short-desc"><?= htmlspecialchars($shortDesc) ?></p>
              <?php endif; ?>

              <!-- Progress bar -->
              <div class="pj-progress-wrap">
                <div class="pj-progress-label">
                  <span>Progress</span>
                  <span style="font-weight:800;color:var(--color-accent)"><?= $progress ?>%</span>
                </div>
                <div class="pj-progress-bar-bg">
                  <div class="pj-progress-bar" style="width:<?= $progress ?>%"></div>
                </div>
              </div>

              <!-- Tech tags -->
              <?php if (!empty($techs)): ?>
              <div class="pj-tech-pills">
                <?php foreach ($techs as $t): ?><span class="pj-tech-tag"><?= htmlspecialchars(trim($t)) ?></span><?php endforeach; ?>
              </div>
              <?php endif; ?>

              <!-- Meta -->
              <div class="pj-card-meta">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <?php if ($budget > 0): ?>
                  <span class="pj-meta-info">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <?= number_format($budget, 0) ?> TND
                  </span>
                  <?php endif; ?>
                  <?php if ($dueDate): ?>
                  <span class="pj-meta-info">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <?= htmlspecialchars(date('M j, Y', strtotime($dueDate))) ?>
                  </span>
                  <?php endif; ?>
                </div>
                <span class="pj-vis-pill"><?= ucfirst($pVis) ?></span>
              </div>

              <div class="pj-section-divider"></div>

              <!-- QR Code -->
              <?php
                $tasksList = "";
                if (!empty($projectTasks)) {
                    $tasksList = "\nTasks List:\n";
                    foreach (array_slice($projectTasks, 0, 5) as $t) {
                        $tasksList .= "- " . ($t['title'] ?? 'Untitled') . "\n";
                    }
                    if (count($projectTasks) > 5) $tasksList .= "- ... and " . (count($projectTasks) - 5) . " more\n";
                }
                $qrRawData = "Project: " . ($row['title'] ?? 'N/A') . "\n"
                           . "Description: " . ($shortDesc ?: 'No description') . "\n"
                           . $tasksList
                           . "Created: " . (isset($row['created_at']) ? date('M j, Y', strtotime($row['created_at'])) : 'N/A');

                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=8&data=' . urlencode($qrRawData);
                $qrDownloadUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&margin=15&format=png&data=' . urlencode($qrRawData);
              ?>
              <div class="pj-qr-card">
                <div class="pj-qr-img-wrap">
                  <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR for <?= htmlspecialchars((string)($row['title'] ?? '')) ?>" loading="lazy">
                </div>
                <div class="pj-qr-content">
                  <strong>Project Data Hub</strong>
                  <span>Scan to view project info and task list instantly on any mobile device.</span>
                  <a class="pj-qr-btn-dl" href="<?= htmlspecialchars($qrDownloadUrl) ?>" download="project-data-<?= $pId ?>.png" target="_blank">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download QR
                  </a>
                </div>
              </div>

              <div class="pj-section-divider"></div>

              <!-- Tasks -->
              <?php if (!empty($projectTasks)): ?>
              <div class="pj-tasks-section">
                <div class="pj-tasks-header">
                  <span class="pj-tasks-label">Tasks</span>
                  <span class="pj-tasks-count"><?= count($projectTasks) ?></span>
                </div>
                <?php foreach ($projectTasks as $task):
                  $tId     = (int)($task['id'] ?? 0);
                  $tStatus = str_replace(' ', '_', strtolower((string)($task['status'] ?? 'todo')));
                  $tTitle  = (string)($task['title'] ?? '');
                  $tDesc   = trim((string)($task['description'] ?? ''));
                  $tDeadline = (string)($task['deadline'] ?? '');
                ?>
                <div class="pj-task-card"
                     data-task-id="<?= $tId ?>"
                     data-project-id="<?= $pId ?>"
                     data-title="<?= htmlspecialchars($tTitle) ?>"
                     data-desc="<?= htmlspecialchars($tDesc) ?>"
                     data-status="<?= htmlspecialchars($tStatus) ?>"
                     data-deadline="<?= htmlspecialchars($tDeadline) ?>"
                     data-is-owner="<?= $isOwner ? '1' : '0' ?>"
                     data-project-title="<?= htmlspecialchars((string)($row['title']??'')) ?>"
                     data-project-desc="<?= htmlspecialchars(trim((string)($row['description']??''))) ?>"
                     role="button" tabindex="0">
                  <div class="pj-task-head">
                    <span class="pj-task-title"><?= htmlspecialchars($tTitle) ?></span>
                    <span class="pj-task-status <?= htmlspecialchars($tStatus) ?>"><?= htmlspecialchars(str_replace('_',' ',$tStatus)) ?></span>
                  </div>
                  <?php if ($tDesc !== ''): ?>
                  <div class="pj-task-desc"><?= htmlspecialchars(mb_strimwidth($tDesc, 0, 120, '…')) ?></div>
                  <?php endif; ?>
                  <div class="pj-task-footer">
                    <?php if ($tDeadline !== ''): ?>
                    <span class="pj-task-deadline">
                      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                      <?= htmlspecialchars(date('M j, Y', strtotime($tDeadline))) ?>
                    </span>
                    <?php else: ?><span></span><?php endif; ?>
                    <?php if ($isOwner): ?>
                    <div class="pj-task-actions" onclick="event.stopPropagation()">
                      <button type="button" class="pj-btn pj-btn-ghost pj-btn-sm"
                              onclick="openEditTaskModal(<?= $tId ?>, <?= $pId ?>, <?= json_encode($tTitle, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($tDesc, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($tStatus, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($tDeadline, JSON_UNESCAPED_UNICODE) ?>)">
                        Edit
                      </button>
                      <form method="post" data-confirm="Delete this task?" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_task">
                        <input type="hidden" name="task_id" value="<?= $tId ?>">
                        <button type="submit" class="pj-btn pj-btn-danger pj-btn-sm">Delete</button>
                      </form>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>

            </div><!-- /.pj-card-body -->

            <?php if ($isOwner): ?>
            <div class="pj-card-footer">
              <button class="pj-btn pj-btn-ghost" onclick='openEditModal(<?= $pId ?>,<?= json_encode((string)($row["title"]??""),JSON_UNESCAPED_UNICODE) ?>,<?= json_encode(trim((string)($row["description"]??"")),JSON_UNESCAPED_UNICODE) ?>,<?= json_encode(trim((string)($row["short_description"]??"")),JSON_UNESCAPED_UNICODE) ?>,<?= json_encode((string)($row["technologies"]??""),JSON_UNESCAPED_UNICODE) ?>,<?= json_encode($pStatus,JSON_UNESCAPED_UNICODE) ?>,<?= $progress ?>,<?= $budget ?>,<?= json_encode($dueDate,JSON_UNESCAPED_UNICODE) ?>,<?= json_encode($pVis,JSON_UNESCAPED_UNICODE) ?>)'>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button type="button" class="pj-btn pj-btn-primary" onclick='openTaskModal(<?= $pId ?>, <?= json_encode((string)($row["title"]??""),JSON_UNESCAPED_UNICODE) ?>)'>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Task
              </button>

              <form method="post" data-confirm="Delete this project and all its tasks?" style="display:inline;margin-left:auto">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_project">
                <input type="hidden" name="project_id" value="<?= $pId ?>">
                <button type="submit" class="pj-btn pj-btn-danger">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                  Delete
                </button>
              </form>
            </div>
            <?php endif; ?>
          </div><!-- /.pj-card -->
          <?php endforeach; ?>
        </div><!-- /.pj-grid -->
        <?php endif; ?>

      </section>
    </div>
  </main>

  <!-- ══════════════════ MODALS ══════════════════ -->

  <!-- Create Project -->
  <div class="pj-modal-backdrop" id="createModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3>Create New Project</h3>
        <button class="pj-modal-close" onclick="document.getElementById('createModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
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
        <div class="pj-modal-actions">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create Project
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Project -->
  <div class="pj-modal-backdrop" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3>Edit Project</h3>
        <button class="pj-modal-close" onclick="document.getElementById('editModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
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
        <div class="pj-modal-actions">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Task -->
  <div class="pj-modal-backdrop" id="taskModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3 id="taskModalTitle">Add Task</h3>
        <button class="pj-modal-close" onclick="document.getElementById('taskModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="post" id="createTaskForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_task">
        <input type="hidden" name="project_id" id="taskProjectId">
        <div class="pj-fgrid">
          <div class="pj-fg full"><label class="pj-fl">Task Title *</label><input type="text" name="title" class="pj-fi" required></div>
          <div class="pj-fg full"><label class="pj-fl">Description *</label><textarea name="description" class="pj-fta" required></textarea></div>
          <div class="pj-fg"><label class="pj-fl">Status</label><select name="status" class="pj-fsel"><option value="todo">Todo</option><option value="in_progress">In Progress</option><option value="done">Done</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Deadline</label><input type="date" name="deadline" class="pj-fi"></div>
        </div>
        <div class="pj-modal-actions">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('taskModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary">Save Task</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Task -->
  <div class="pj-modal-backdrop" id="editTaskModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal">
      <div class="pj-modal-head">
        <h3>Edit Task</h3>
        <button class="pj-modal-close" onclick="document.getElementById('editTaskModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="post" id="editTaskForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_task">
        <input type="hidden" name="task_id" id="editTaskId">
        <div class="pj-fgrid">
          <div class="pj-fg full"><label class="pj-fl">Task Title *</label><input type="text" name="title" id="editTaskTitle" class="pj-fi" required></div>
          <div class="pj-fg full"><label class="pj-fl">Description *</label><textarea name="description" id="editTaskDescription" class="pj-fta" required></textarea></div>
          <div class="pj-fg"><label class="pj-fl">Status</label><select name="status" id="editTaskStatus" class="pj-fsel"><option value="todo">Todo</option><option value="in_progress">In Progress</option><option value="done">Done</option></select></div>
          <div class="pj-fg"><label class="pj-fl">Deadline</label><input type="date" name="deadline" id="editTaskDeadline" class="pj-fi"></div>
        </div>
        <div class="pj-modal-actions">
          <button type="button" class="pj-btn pj-btn-ghost" onclick="document.getElementById('editTaskModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="pj-btn pj-btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>



  <!-- Task Detail + AI Execution Modal -->
  <div class="pj-modal-backdrop" id="taskDetailModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="pj-modal pj-modal-lg">
      <div class="pj-modal-head">
        <h3 id="taskDetailTitle">Task Detail</h3>
        <button class="pj-modal-close" onclick="document.getElementById('taskDetailModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Project Context -->
      <div id="projectContextZone" style="background:rgba(99,102,241,.04); border:1px solid rgba(99,102,241,.1); border-radius:12px; padding:12px; margin-bottom:16px;">
          <div style="font-size:.68rem; font-weight:800; color:#6366f1; text-transform:uppercase; letter-spacing:.05em; margin-bottom:4px;">Project Context</div>
          <div id="projectContextTitle" style="font-size:.85rem; font-weight:700; color:var(--color-text-primary);">—</div>
          <div id="projectContextDesc" style="font-size:.78rem; color:var(--color-text-secondary); margin-top:2px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">—</div>
      </div>

      <!-- Task meta strip -->
      <div id="taskDetailMeta" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;"></div>

      <!-- Task description -->
      <div style="background:var(--color-surface-2);border:1px solid var(--color-border);border-radius:12px;padding:14px;margin-bottom:16px;">
        <div style="font-size:.74rem;font-weight:700;color:var(--color-text-muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em;">Description</div>
        <div id="taskDetailDesc" style="font-size:.84rem;color:var(--color-text-primary);line-height:1.7;white-space:pre-wrap;">—</div>
      </div>

      <!-- Owner-only actions -->
      <div id="taskDetailOwnerActions" style="display:none;gap:8px;flex-wrap:wrap;margin-bottom:16px;"></div>

      <!-- AI Execution zone -->
      <div style="border-top:1px solid var(--color-border);padding-top:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:4px;">
          <div style="font-size:.78rem;font-weight:800;color:var(--color-text-primary);">AI Task Execution</div>
          <button id="btnRunAi" class="pj-btn-ai" onclick="runTaskWithAI()" style="padding:8px 20px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:2px;"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Let AI Do This Task
          </button>
        </div>
        <div style="font-size:.76rem;color:var(--color-text-muted);margin-bottom:12px;">AI will read this task and produce the actual deliverable — report, plan, code, or analysis.</div>
        <div id="taskDetailAiZone"></div>
      </div>
    </div>
  </div>

  <!-- Toast stack -->
  <div class="home-toast-stack" id="homeToastStack" aria-live="polite"></div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/home.js"></script>
  <script src="../../assets/js/mvc-inline-validation.js"></script>

  <script>
  // ── Event Delegation for Task Clicks ──────────────────────────────────────────
  document.addEventListener('click', e => {
    const card = e.target.closest('.pj-task-card');
    if (card && !e.target.closest('.pj-task-actions')) {
      const d = card.dataset;
      openTaskDetailModal(
        parseInt(d.taskId),
        d.title,
        d.desc,
        d.status,
        d.deadline,
        d.isOwner === '1',
        parseInt(d.projectId),
        d.projectTitle,
        d.projectDesc
      );
    }
  });

  // Handle Enter/Space for accessibility
  document.addEventListener('keydown', e => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('.pj-task-card')) {
      if (!e.target.closest('.pj-task-actions')) {
        e.preventDefault();
        e.target.click();
      }
    }
  });

  // ── confirm delete ────────────────────────────────────────────────────────────
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!confirm(form.dataset.confirm || 'Confirm this action?')) e.preventDefault();
    });
  });

  // ── Modal helpers ─────────────────────────────────────────────────────────────
  function openEditModal(id,title,desc,shortDesc,tech,status,progress,budget,dueDate,visibility){
    const form = document.getElementById('editProjectForm');
    if(form) form.reset();

    document.getElementById('editProjectId').value  = id;
    document.getElementById('editTitle').value      = title;
    document.getElementById('editDesc').value       = desc;
    document.getElementById('editShortDesc').value  = shortDesc;
    document.getElementById('editTech').value       = tech;
    document.getElementById('editStatus').value     = status;
    document.getElementById('editProgress').value   = progress;
    document.getElementById('editBudget').value     = budget;
    document.getElementById('editDueDate').value    = dueDate;
    document.getElementById('editVisibility').value = visibility;
    document.getElementById('editModal').classList.add('open');

    if(window.MVCInlineValidation) window.MVCInlineValidation.initForm(form, true);
  }
  function openTaskModal(projectId, projectTitle){
    const form = document.getElementById('createTaskForm');
    if(form) form.reset();

    document.getElementById('taskProjectId').value = projectId;
    document.getElementById('taskModalTitle').textContent = 'Add Task: ' + (projectTitle || '');
    document.getElementById('taskModal').classList.add('open');

    if(window.MVCInlineValidation) window.MVCInlineValidation.initForm(form, false);
  }
  function openEditTaskModal(taskId, projectId, title, description, status, deadline){
    const form = document.getElementById('editTaskForm');
    if(form) form.reset();

    document.getElementById('editTaskId').value          = taskId;
    document.getElementById('editTaskTitle').value       = title || '';
    document.getElementById('editTaskDescription').value = description || '';
    document.getElementById('editTaskStatus').value      = status || 'todo';
    document.getElementById('editTaskDeadline').value    = deadline || '';
    document.getElementById('editTaskModal').classList.add('open');

    if(window.MVCInlineValidation) window.MVCInlineValidation.initForm(form, true);
  }


  // ── Task Detail + AI Execution ─────────────────────────────────────────────────
  let _currentTaskId = null;
  let _currentProjectId = null;
  let _isOwner = false;
  let _currentTaskTitle = '';
  let _currentTaskDesc = '';

  function openTaskDetailModal(taskId, title, desc, status, deadline, isOwner, projectId, pTitle, pDesc){
    _currentTaskId    = taskId;
    _currentProjectId = projectId;
    _isOwner          = isOwner;
    _currentTaskTitle = title;
    _currentTaskDesc  = desc;

    // header
    document.getElementById('taskDetailTitle').textContent = title || 'Task Detail';

    // context
    document.getElementById('projectContextTitle').textContent = pTitle || '—';
    document.getElementById('projectContextDesc').textContent  = pDesc  || '—';

    // meta strip
    const meta = document.getElementById('taskDetailMeta');
    const statusColors = { todo:'#4b5563', in_progress:'#b45309', done:'#047857' };
    const statusBg     = { todo:'rgba(107,114,128,.1)', in_progress:'rgba(245,158,11,.1)', done:'rgba(5,150,105,.1)' };
    const sColor = statusColors[status] || '#4b5563';
    const sBg    = statusBg[status]    || 'rgba(107,114,128,.1)';
    meta.innerHTML = `
      <span style="padding:3px 11px;border-radius:999px;font-size:.71rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;background:${sBg};color:${sColor}">${(status||'todo').replace('_',' ')}</span>
      ${deadline ? `<span style="display:flex;align-items:center;gap:4px;font-size:.74rem;color:var(--color-text-muted)"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Due ${deadline}</span>` : ''}
    `;

    // description
    document.getElementById('taskDetailDesc').textContent = desc || 'No description provided.';

    // owner actions
    const ownerDiv = document.getElementById('taskDetailOwnerActions');
    if (isOwner) {
      ownerDiv.style.display = 'flex';
      ownerDiv.innerHTML = `
        <button class="pj-btn pj-btn-ghost" onclick="document.getElementById('taskDetailModal').classList.remove('open');openEditTaskModal(${taskId},${projectId},${JSON.stringify(title)},${JSON.stringify(desc)},${JSON.stringify(status)},${JSON.stringify(deadline)})">
          Edit Task
        </button>
      `;
    } else {
      ownerDiv.style.display = 'none';
    }

    // clear AI zone
    document.getElementById('taskDetailAiZone').innerHTML = '';
    document.getElementById('btnRunAi').disabled = false;
    document.getElementById('btnRunAi').innerHTML = `
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
      Run Task with AI
    `;

    document.getElementById('taskDetailModal').classList.add('open');
  }

  async function runTaskWithAI(){
    if (!_currentTaskId) return;
    const btn  = document.getElementById('btnRunAi');
    const zone = document.getElementById('taskDetailAiZone');

    btn.disabled = true;
    btn.innerHTML = `<span class="pj-ai-spinner" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;display:inline-block;animation:spin .7s linear infinite"></span> AI is working…`;

    zone.innerHTML = `<div class="pj-ai-loading"><div class="pj-ai-spinner"></div> Executing task — generating your deliverable…</div>`;

    try {
      const fd = new FormData();
      fd.append('action',      'execute_task_ai');
      fd.append('task_id',     _currentTaskId);
      fd.append('csrf_token',  '<?= $csrfToken ?>');

      const resp = await fetch('projects.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
      });
      const data = await resp.json();

      if (!data.success) throw new Error(data.error || 'AI returned no output.');

      // Render result
      const outputHtml = markdownToHtml(data.output || '');
      zone.innerHTML = `
        <div class="pj-ai-result">
          <div class="pj-ai-result-header">
            <span class="pj-ai-result-label">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
              AI Output — ${(data.type||'text').toUpperCase()}
            </span>
            <div style="display:flex;gap:7px;">
              <button class="pj-ai-download" onclick="copyAiResult()">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                Copy
              </button>
              <button class="pj-ai-download" onclick="downloadAiResult()">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download
              </button>
            </div>
          </div>
          <div class="pj-ai-result-body" id="aiOutputBody">${outputHtml}</div>
        </div>
      `;
      window._lastAiOutput = data.output;
      window._lastAiTitle  = _currentTaskTitle;

    } catch (err) {
      zone.innerHTML = `
        <div style="padding:14px;background:rgba(225,29,72,.06);border:1px solid rgba(225,29,72,.18);border-radius:12px;font-size:.82rem;color:#be123c;font-weight:600;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:4px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          Error: ${err.message}
        </div>`;
    } finally {
      btn.disabled = false;
      btn.innerHTML = `
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        Run Again
      `;
    }
  }

  function downloadAiResult(){
    const text  = window._lastAiOutput || '';
    const title = (window._lastAiTitle || 'task-output').replace(/[^a-z0-9]/gi,'_').toLowerCase();
    const blob  = new Blob([text], { type: 'text/plain;charset=utf-8' });
    const url   = URL.createObjectURL(blob);
    const a     = document.createElement('a');
    a.href      = url;
    a.download  = title + '_ai_output.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function copyAiResult(){
    const text = window._lastAiOutput || '';
    if(!text) return;
    navigator.clipboard.writeText(text).then(()=>{
      const btn = event.currentTarget;
      const oldHtml = btn.innerHTML;
      btn.innerHTML = '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
      setTimeout(()=> btn.innerHTML = oldHtml, 2000);
    });
  }

  // Minimal markdown renderer (bold, headings, code, lists)
  function markdownToHtml(md){
    if (!md) return '';
    let html = md
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/```([\s\S]*?)```/g, (_,c)=>`<pre><code>${c.trim()}</code></pre>`)
      .replace(/`([^`]+)`/g, '<code>$1</code>')
      .replace(/^### (.+)$/gm, '<h3>$1</h3>')
      .replace(/^## (.+)$/gm,  '<h2>$1</h2>')
      .replace(/^# (.+)$/gm,   '<h2>$1</h2>')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/\*(.+?)\*/g,    '<em>$1</em>')
      .replace(/^[-*] (.+)$/gm, '<li>$1</li>')
      .replace(/(<li>[\s\S]*?<\/li>\n?)+/g, m=>`<ul>${m}</ul>`)
      .replace(/\n\n/g,'<br><br>')
      .replace(/\n/g,'<br>');
    return html;
  }
  </script>
</body>
</html>