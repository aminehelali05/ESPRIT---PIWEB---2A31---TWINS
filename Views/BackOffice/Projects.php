<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ProjectController.php');
include_once(__DIR__ . '/../../config.php');

if (!UserController::isAuthenticated() || !UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php?mode=login');
    exit;
}

$pdo = config::getConnexion();
$controller = new ProjectController($pdo);

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
            if ($action === 'create_project') {
                $payload = [
                    'title' => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status' => strtolower($clean($_POST['status'] ?? 'planning')),
                    'progress_percent' => max(0, min(100, (int) ($_POST['progress_percent'] ?? 0))),
                    'budget' => max(0, (float) ($_POST['budget'] ?? 0)),
                    'due_date' => $controller->parseDate($clean($_POST['due_date'] ?? '')),
                    'owner_id' => (int) ($_POST['owner_id'] ?? 0),
                    'visibility' => strtolower($clean($_POST['visibility'] ?? 'team')),
                ];
                if ($payload['title'] === '' || $payload['description'] === '' || $payload['owner_id'] <= 0) {
                    throw new RuntimeException('Title, description, and owner are required.');
                }
                if (!in_array($payload['status'], ['planning', 'active', 'completed', 'on_hold', 'archived'], true)) {
                    $payload['status'] = 'planning';
                }
                if (!in_array($payload['visibility'], ['team', 'public', 'private'], true)) {
                    $payload['visibility'] = 'team';
                }
                $controller->create($payload);
                $notice = ['type' => 'success', 'message' => 'Project created.'];
            }

            if ($action === 'update_project') {
                $projectId = (int) ($_POST['project_id'] ?? 0);
                $status = strtolower($clean($_POST['status'] ?? 'planning'));
                $progress = max(0, min(100, (int) ($_POST['progress_percent'] ?? 0)));
                if (!in_array($status, ['planning', 'active', 'completed', 'on_hold', 'archived'], true)) {
                    $status = 'planning';
                }
                $controller->updateProgressStatus($projectId, $status, $progress);
                $notice = ['type' => 'success', 'message' => 'Project updated.'];
            }

            if ($action === 'delete_project') {
                $projectId = (int) ($_POST['project_id'] ?? 0);
                $controller->deleteById($projectId);
                $notice = ['type' => 'success', 'message' => 'Project deleted.'];
            }

            if ($action === 'create_task') {
                $controller->createTask([
                    'project_id' => (int) ($_POST['project_id'] ?? 0),
                    'title' => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status' => strtolower($clean($_POST['status'] ?? 'todo')),
                    'deadline' => $controller->parseDate($clean($_POST['deadline'] ?? '')),
                ]);
                $notice = ['type' => 'success', 'message' => 'Task created.'];
            }

            if ($action === 'update_task') {
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $controller->updateTask($taskId, [
                    'title' => $clean($_POST['title'] ?? ''),
                    'description' => $clean($_POST['description'] ?? ''),
                    'status' => strtolower($clean($_POST['status'] ?? 'todo')),
                    'deadline' => $controller->parseDate($clean($_POST['deadline'] ?? '')),
                ]);
                $notice = ['type' => 'success', 'message' => 'Task updated.'];
            }

            if ($action === 'delete_task') {
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $controller->deleteTask($taskId);
                $notice = ['type' => 'success', 'message' => 'Task deleted.'];
            }
            if ($action === 'ai_generate_task') {
                header('Content-Type: application/json');
                $projectId = (int) ($_POST['project_id'] ?? 0);
                $prompt = $clean($_POST['prompt'] ?? '');
                $userId = (int) (UserController::currentUser()['id'] ?? 0);
                try {
                    $result = $controller->generateTaskWithAI($projectId, $userId, $prompt);
                    echo json_encode(array_merge(['success' => true], $result));
                } catch (Throwable $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            }
            if ($action === 'export_csv') {
                $csvRows = $controller->listBackofficeRows();
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="projects-export-' . date('Y-m-d') . '.csv"');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Title', 'Owner', 'Status', 'Progress %', 'Budget (TND)', 'Visibility', 'Due Date', 'Created At']);
                foreach ($csvRows as $cr) {
                    fputcsv($out, [
                        $cr['id'] ?? '',
                        $cr['title'] ?? '',
                        trim(($cr['first_name'] ?? '') . ' ' . ($cr['last_name'] ?? '')),
                        strtoupper(str_replace('_', ' ', (string)($cr['status'] ?? 'planning'))),
                        (int)($cr['progress_percent'] ?? 0),
                        number_format((float)($cr['budget'] ?? 0), 2),
                        ucfirst((string)($cr['visibility'] ?? 'team')),
                        $cr['due_date'] ?? '',
                        $cr['created_at'] ?? ''
                    ]);
                }
                fclose($out);
                exit;
            }
            if ($action === 'export_pdf') {
                $pdfRows = $controller->listBackofficeRows();
                $stats = $controller->buildBackofficeStats($pdfRows);
                
                $html = '<html><head><style>
                    @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap");
                    body { font-family: "Poppins", "Helvetica", sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; margin: 0; padding: 20px; }
                    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
                    .header h1 { font-size: 22px; color: #0f172a; margin: 0; font-weight: 700; }
                    .header p { color: #64748b; font-size: 12px; margin: 5px 0 0; }
                    .stats-grid { display: table; width: 100%; margin-bottom: 25px; border-spacing: 10px; }
                    .stat-card { display: table-cell; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; text-align: center; width: 33%; }
                    .stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 600; margin-bottom: 4px; }
                    .stat-value { font-size: 18px; color: #0f172a; font-weight: 700; }
                    table { width: 100%; border-collapse: collapse; margin-top: 10px; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; }
                    th { background: #f1f5f9; color: #475569; font-weight: 700; text-align: left; padding: 12px 10px; font-size: 10px; text-transform: uppercase; }
                    td { padding: 10px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
                    .status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: 700; text-transform: uppercase; }
                    .status-active { background: #ecfdf5; color: #059669; }
                    .status-planning { background: #eff6ff; color: #2563eb; }
                    .status-on_hold { background: #fffbeb; color: #92400e; }
                    .progress-bar { width: 100%; height: 6px; background: #f1f5f9; border-radius: 3px; margin-top: 4px; overflow: hidden; }
                    .progress-fill { height: 100%; background: #4f46e5; }
                    .footer { margin-top: 30px; text-align: center; color: #94a3b8; font-size: 9px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
                </style></head><body>
                    <div class="header">
                        <h1>Project Portfolio Report</h1>
                        <p>Diversity.is Official Backoffice Export • Generated on ' . date('F j, Y, H:i') . '</p>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Projects</div>
                            <div class="stat-value">' . count($pdfRows) . ' Operations</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Total Valuation</div>
                            <div class="stat-value">' . number_format((float)($stats['total_budget'] ?? 0), 2) . ' TND</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Active Milestone</div>
                            <div class="stat-value">' . ($stats['active_projects'] ?? 0) . ' Active</div>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Project / Mission</th>
                                <th>Project Owner</th>
                                <th>Status</th>
                                <th>Investment</th>
                                <th>Execution</th>
                            </tr>
                        </thead>
                        <tbody>';
                foreach ($pdfRows as $row) {
                    $cName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    $status = strtolower($row['status'] ?? 'planning');
                    $progress = (int)($row['progress_percent'] ?? 0);
                    $html .= '<tr>
                        <td><strong>#' . ($row['id'] ?? '') . '</strong></td>
                        <td>' . htmlspecialchars($row['title'] ?? 'Generic Project') . '</td>
                        <td>' . htmlspecialchars($cName) . '</td>
                        <td><span class="status status-' . $status . '">' . strtoupper(str_replace('_', ' ', $status)) . '</span></td>
                        <td style="font-weight:700">' . number_format((float)($row['budget'] ?? 0), 2) . ' TND</td>
                        <td>
                            ' . $progress . '%
                            <div class="progress-bar"><div class="progress-fill" style="width:' . $progress . '%"></div></div>
                        </td>
                    </tr>';
                }
                $html .= '</tbody></table>
                    <div class="footer">
                        This document contains confidential project intelligence. Generated by Diversity.is Admin Panel.
                    </div>
                </body></html>';

                if (class_exists('\\Dompdf\\Dompdf')) {
                    $dompdf = new \Dompdf\Dompdf();
                    $dompdf->setOptions((new \Dompdf\Options())->set('isRemoteEnabled', true));
                    $dompdf->loadHtml($html);
                    $dompdf->setPaper('A4', 'landscape');
                    $dompdf->render();
                    $pdfOutput = $dompdf->output();
                    
                    while (ob_get_level() > 0) { ob_end_clean(); }
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="projects-report-' . date('Y-m-d') . '.pdf"');
                    header('Content-Length: ' . strlen($pdfOutput));
                    header('Cache-Control: no-cache, must-revalidate');
                    header('Pragma: no-cache');
                    echo $pdfOutput;
                    exit;
                } else {
                    throw new RuntimeException('DomPDF class not found. Please run composer install.');
                }
            }
        } catch (Throwable $exception) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || (isset($_POST['action']) && str_contains($_POST['action'], 'export'));
            if ($isAjax) {
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $exception->getMessage()]);
                exit;
            }
            $notice = ['type' => 'error', 'message' => $exception->getMessage()];
        }
    }
}

$users = $controller->listUsers();
$rows = $controller->listBackofficeRows();
$projectIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows);
$tasksByProject = $projectIds !== [] ? $controller->tasksMapForProjects($projectIds) : [];
$stats = $controller->buildBackofficeStats($rows);
$fromDate = trim((string)($_GET['from'] ?? ''));
$toDate = trim((string)($_GET['to'] ?? ''));
$globalSearch = strtolower(trim((string)($_GET['q'] ?? '')));
$inDateRange = static function (?string $value) use ($fromDate, $toDate): bool {
    if ($value === null || trim($value) === '') { return true; }
    $ts = strtotime($value);
    if ($ts === false) { return true; }
    if ($fromDate !== '') { $fromTs = strtotime($fromDate . ' 00:00:00'); if ($fromTs !== false && $ts < $fromTs) { return false; } }
    if ($toDate !== '') { $toTs = strtotime($toDate . ' 23:59:59'); if ($toTs !== false && $ts > $toTs) { return false; } }
    return true;
};
$filteredRows = array_values(array_filter($rows, static function (array $row) use ($globalSearch, $inDateRange): bool {
    if (!$inDateRange((string)($row['created_at'] ?? ''))) { return false; }
    if ($globalSearch === '') { return true; }
    return str_contains(strtolower((string)json_encode($row)), $globalSearch);
}));
$filteredProjectIds = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $filteredRows);
$filteredTasksByProject = [];
foreach ($filteredProjectIds as $projectId) {
    $filteredTasksByProject[$projectId] = $tasksByProject[$projectId] ?? [];
}

$sessionUser = UserController::currentUser() ?? [];
$displayName = trim((string) (($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? '')));
$displayName = $displayName !== '' ? $displayName : 'System Admin';
$displayEmail = (string) ($sessionUser['email'] ?? 'admin@diversity.is');
$initials = strtoupper(substr((string) ($sessionUser['first_name'] ?? 'A'), 0, 1) . substr((string) ($sessionUser['last_name'] ?? 'D'), 0, 1));

$statusDistribution = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
$workloadByOwner = [];
$durationByStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
$durationCountByStatus = ['todo' => 0, 'in_progress' => 0, 'done' => 0];
$burndownBuckets = [];
$completionPercentages = [];
$onTimeTotal = 0;
$onTimeDone = 0;
foreach ($filteredRows as $projectRow) {
    $completionPercentages[] = (int)($projectRow['progress_percent'] ?? 0);
    $owner = trim((string)($projectRow['first_name'] ?? '') . ' ' . (string)($projectRow['last_name'] ?? ''));
    $ownerKey = $owner !== '' ? $owner : 'Unknown';
    $projectTasks = $filteredTasksByProject[(int)($projectRow['id'] ?? 0)] ?? [];
    $workloadByOwner[$ownerKey] = ($workloadByOwner[$ownerKey] ?? 0) + count($projectTasks);
    foreach ($projectTasks as $taskRow) {
        $status = strtolower((string)($taskRow['status'] ?? 'todo'));
        if (!isset($statusDistribution[$status])) { $statusDistribution[$status] = 0; $durationByStatus[$status] = 0; $durationCountByStatus[$status] = 0; }
        $statusDistribution[$status]++;
        $createdAt = (string)($taskRow['created_at'] ?? '');
        $deadline = (string)($taskRow['deadline'] ?? '');
        if ($createdAt !== '' && $deadline !== '' && strtotime($deadline) !== false && strtotime($createdAt) !== false) {
            $days = max(0, (int)floor((strtotime($deadline) - strtotime($createdAt)) / 86400));
            $durationByStatus[$status] += $days;
            $durationCountByStatus[$status]++;
        }
        if ($deadline !== '' && strtotime($deadline) !== false) {
            $dayKey = date('Y-m-d', strtotime($deadline));
            if (!isset($burndownBuckets[$dayKey])) { $burndownBuckets[$dayKey] = 0; }
            if ($status !== 'done') { $burndownBuckets[$dayKey]++; }
            if ($status === 'done') {
                $onTimeTotal++;
                if (strtotime((string)($taskRow['updated_at'] ?? $taskRow['created_at'] ?? 'now')) <= strtotime($deadline)) { $onTimeDone++; }
            }
        }
    }
}
arsort($workloadByOwner);
ksort($burndownBuckets);
$averageCompletion = $completionPercentages !== [] ? round(array_sum($completionPercentages) / count($completionPercentages), 1) : 0;
$onTimeRate = $onTimeTotal > 0 ? round(($onTimeDone / $onTimeTotal) * 100, 1) : 0;
$durationAverages = [];
foreach ($durationByStatus as $status => $sum) {
    $durationAverages[$status] = ($durationCountByStatus[$status] ?? 0) > 0 ? round($sum / $durationCountByStatus[$status], 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice · Projects</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-form.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── RESET & TOKENS ───────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        :root {
            color-scheme: light;
            --accent:      #4f46e5;
            --accent-2:    #06b6d4;
            --radius-card: 24px;
            --radius-btn:  14px;
            --shadow-card: 0 10px 40px rgba(66,84,160,.06);
            --shadow-hover:0 20px 50px rgba(66,84,160,.12);
            --border-card: rgba(223, 230, 255, 0.5);
            --bg-page:     #f8faff;
            --text-main:   #1f2a44;
            --text-muted:  #64748b;
            --font:        'Poppins', Inter, system-ui, sans-serif;
        }
        body {
            margin: 0;
            font-family: var(--font);
            color: var(--text-main);
            background: var(--bg-page);
            min-height: 100vh;
        }

        /* ── APP SHELL ────────────────────────────────────────────── */
        .app-container { min-height: 100vh; background: transparent; }
        .sidebar {
            background: linear-gradient(180deg,#f7f8ff,#f1f4ff);
            border-right: 1px solid #dbe3ff;
            box-shadow: 8px 0 24px rgba(91,104,178,.08);
        }
        .main-content { background: transparent; }

        /* ── TOP HEADER ───────────────────────────────────────────── */
        .top-header {
            background: rgba(255,255,255,.82);
            border: 1px solid #dfe6ff;
            border-radius: 24px;
            padding: 1.15rem 1.6rem;
            box-shadow: 0 8px 28px rgba(66,84,160,.09);
            backdrop-filter: blur(8px);
        }

        /* ── BENTO GRID & SPAN CLASSES ────────────────────────────── */
        .bento-grid {
            display: grid;
            gap: 1.1rem;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            align-items: start;
        }
        .bento-grid > * { min-width: 0; }
        .span-1  { grid-column: span 1; }
        .span-2  { grid-column: span 2; }
        .span-3  { grid-column: span 3; }
        .span-4  { grid-column: span 4; }
        .span-5  { grid-column: span 5; }
        .span-6  { grid-column: span 6; }
        .span-7  { grid-column: span 7; }
        .span-8  { grid-column: span 8; }
        .span-9  { grid-column: span 9; }
        .span-10 { grid-column: span 10; }
        .span-11 { grid-column: span 11; }
        .span-12 { grid-column: span 12; }

        @media (max-width: 1200px) {
            .span-3 { grid-column: span 6; }
        }
        @media (max-width: 900px) {
            .span-3, .span-4, .span-6 { grid-column: span 12; }
            .field-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            [class*="span-"] { grid-column: span 12; }
        }

        /* ── CARD BASE ────────────────────────────────────────────── */
        .card {
            background: #fff;
            border: 1px solid var(--border-card);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            color: var(--text-main);
            transition: box-shadow .22s ease, transform .22s ease;
        }
        .card:hover {
            box-shadow: var(--shadow-hover);
            transform: translateY(-2px);
        }
        section.card { padding: 20px 22px; }

        /* ── KPI CARDS ────────────────────────────────────────────── */
        .kpi-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 22px 24px;
            min-height: 120px;
        }
        .kpi-card .kpi-body { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .kpi-h {
            font-size: .76rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            white-space: nowrap;
        }
        .kpi-v {
            font-size: 2.1rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
            white-space: nowrap;
        }
        .icon-box {
            flex-shrink: 0;
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
        }
        .icon-box svg { width: 24px; height: 24px; }
        .icon-blue   { background: rgba(59,130,246,.12); color: #2563eb; }
        .icon-indigo { background: rgba(99,102,241,.12); color: #4f46e5; }
        .icon-purple { background: rgba(168,85,247,.12); color: #7c3aed; }
        .icon-cyan   { background: rgba(6,182,212,.12);  color: #0891b2; }

        /* ── SECTION HEAD ─────────────────────────────────────────── */
        .section-head {
            display: flex; align-items: center;
            justify-content: space-between;
            gap: .75rem; margin-bottom: 1rem;
        }
        .section-head h2 {
            margin: 0;
            font-size: .95rem;
            font-weight: 700;
            color: var(--text-main);
        }

        /* ── CHART WRAPPER ────────────────────────────────────────── */
        .chart-wrap {
            position: relative;
            width: 100%;
            height: 190px;
        }
        .chart-wrap canvas { position: absolute; inset: 0; }

        /* ── KPI STAT TEXT (under ring charts) ───────────────────── */
        .kpi-ring-label {
            text-align: center;
            font-size: .95rem;
            font-weight: 700;
            margin-top: 8px;
        }

        /* ── ELEGANT TABLE ────────────────────────────────────────── */
        .elegant-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .elegant-table thead th {
            background: #f5f7ff;
            color: var(--text-muted);
            font-weight: 600;
            font-size: .73rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border-card);
        }
        .elegant-table tbody tr { background: #fff; transition: background .15s; }
        .elegant-table tbody tr:hover { background: #f7f9ff; }
        .elegant-table tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid #f0f3ff;
            vertical-align: middle;
            color: var(--text-main);
        }
        .elegant-table tbody td span {
            font-size: .72rem;
            color: var(--text-muted);
        }

        /* ── BUTTONS ──────────────────────────────────────────────── */
        /* Buttons */
        .btn-primary { 
            display:inline-flex; align-items:center; justify-content:center; gap:10px; border:0; 
            border-radius:var(--radius-btn); padding:.85rem 1.5rem; 
            background:linear-gradient(135deg,var(--accent),var(--accent-2)); 
            color:#fff; font-weight:700; font-family:var(--font); cursor:pointer; font-size:.88rem; 
            box-shadow:0 10px 25px rgba(79,70,229,.25); transition:all .25s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .btn-primary:hover { transform:translateY(-3px); filter:brightness(1.05); box-shadow:0 15px 35px rgba(79,70,229,.35); }
        
        .btn-export {
            display:inline-flex; align-items:center; gap:10px; padding:.75rem 1.4rem;
            border-radius:var(--radius-btn); border:1px solid #e2e8f0; background:#fff;
            color:var(--text-main); font-size:.88rem; font-weight:700; cursor:pointer;
            transition:all .3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0,0,0,.03);
            font-family: var(--font);
        }
        .btn-export:hover { 
            background:#f8fafc; border-color:var(--accent); color:var(--accent); 
            transform:translateY(-3px); box-shadow: 0 12px 24px rgba(79,70,229,.12); 
        }
        .btn-export.primary {
            background: linear-gradient(135deg, var(--accent), #6366f1);
            color: #fff;
            border: none;
            box-shadow: 0 10px 25px rgba(79,70,229,.2);
        }
        .btn-export.primary:hover {
            filter: brightness(1.1);
            box-shadow: 0 15px 35px rgba(79,70,229,.3);
        }

        .btn-mini { 
            border:none; background:rgba(79,70,229,0.08); color:var(--accent); 
            border-radius:10px; padding:.5rem 1rem; cursor:pointer; font-weight:700; 
            font-size:.78rem; font-family:var(--font); transition:all .2s; white-space:nowrap; 
        }
        .btn-mini:hover { background:var(--accent); color:#fff; transform:translateY(-1px); }
        .btn-mini.delete { background:rgba(225,29,72,0.08); color:#e11d48; }
        .btn-mini.delete:hover { background:#e11d48; color:#fff; }
        .btn-mini.view { background:rgba(100,116,139,0.08); color:#64748b; }
        .btn-mini.view:hover { background:#64748b; color:#fff; }

        /* ── FORM ELEMENTS ────────────────────────────────────────── */
        .field-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .9rem; }
        .field-grid .full { grid-column: 1 / -1; }
        .form-panel {
            padding: 1.15rem; border: 1px solid #dfe6ff;
            border-radius: 18px; background: #fbfcff;
        }
        .form-panel label { display: block; margin: 0 0 .35rem; color: var(--text-muted); font-size: .79rem; font-weight: 600; }
        .form-panel input, .form-panel select, .form-panel textarea {
            width: 100%; border: 1px solid #d6def9; border-radius: 12px;
            background: #fff; color: #25324b;
            padding: .72rem .9rem; outline: none; font-family: var(--font); font-size: .84rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-panel textarea { min-height: 110px; resize: vertical; }
        .form-panel input:focus, .form-panel select:focus, .form-panel textarea:focus {
            border-color: rgba(96,165,250,.7);
            box-shadow: 0 0 0 3px rgba(59,130,246,.14);
        }

        /* ── ACTION STACK ─────────────────────────────────────────── */
        .action-stack { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .action-stack form { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
        .action-stack select, .action-stack input:not([type="hidden"]) {
            min-height: 38px; padding: .48rem .7rem;
            border-radius: 10px; border: 1px solid #b7c9fb;
            background: #f7f9ff; color: #2b3959;
            font-weight: 600; font-family: var(--font); font-size: .8rem;
            outline: none;
        }
        .action-stack select:focus, .action-stack input:not([type="hidden"]):focus {
            border-color: #7d95ff; box-shadow: 0 0 0 3px rgba(79,70,229,.14);
        }
        .action-select {
            min-width: 130px; appearance: none;
            background-image: linear-gradient(45deg,transparent 50%,#566a95 50%),linear-gradient(135deg,#566a95 50%,transparent 50%);
            background-position: calc(100% - 16px) calc(50% - 3px),calc(100% - 10px) calc(50% - 3px);
            background-size: 6px 6px; background-repeat: no-repeat;
            padding-right: 2rem !important;
        }
        .action-input { width: 80px !important; text-align: center; }

        /* ── MODULE ACTIONS BAR ───────────────────────────────────── */
        .module-actions {
            display: flex; justify-content: space-between; gap: 10px;
            flex-wrap: wrap; align-items: center; margin-bottom: 1rem;
        }

        /* ── NOTICE ───────────────────────────────────────────────── */
        .notice { margin-bottom: 1rem; padding: .9rem 1.1rem; border-radius: 14px; font-size: .84rem; font-weight: 500; }
        .notice.success { background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.28); color: #065f46; }
        .notice.error   { background: rgba(239,68,68,.10);  border: 1px solid rgba(239,68,68,.28);  color: #991b1b; }

        /* ── MODAL ────────────────────────────────────────────────── */
        .bo-modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(15,21,42,.46); backdrop-filter: blur(6px);
            z-index: 200; display: none;
            align-items: center; justify-content: center; padding: 20px;
        }
        .bo-modal-backdrop.open { display: flex; animation: boFadeIn .2s ease; }
        @keyframes boFadeIn { from { opacity: 0; } to { opacity: 1; } }
        .bo-modal {
            width: 100%; max-width: 700px; max-height: 90vh; overflow-y: auto;
            background: #fff; border: 1px solid #d2dbef;
            border-radius: 20px; box-shadow: 0 26px 60px rgba(30,41,59,.28);
            padding: 26px;
            animation: boModalIn .26s cubic-bezier(.16,1,.3,1);
        }
        @keyframes boModalIn { from { opacity:0; transform:scale(.96) translateY(10px); } to { opacity:1; transform:none; } }
        .bo-modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        .bo-modal-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
        .bo-modal-close { background: none; border: none; cursor: pointer; padding: 4px; color: #8a9ab8; border-radius: 6px; }
        .bo-modal-close:hover { background: #eef2ff; color: #5e6b8f; }
        .bo-modal .form-panel { padding: 0; border: 0; background: transparent; box-shadow: none; }
        .bo-modal .form-panel input,
        .bo-modal .form-panel select,
        .bo-modal .form-panel textarea {
            padding: 8px 11px; background: #eef2fa;
            border: 1px solid #d2dbef; border-radius: 10px; font-size: .84rem;
        }
        .bo-modal-actions { display: flex; gap: 8px; margin-top: 18px; justify-content: flex-end; }
        .bo-modal-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; border-radius: 10px; font-size: .82rem;
            font-weight: 700; border: 1px solid transparent; cursor: pointer; font-family: var(--font);
        }
        .bo-modal-btn.ghost { background: #eef2fa; border-color: #d4ddef; color: #607092; }
        .bo-modal-btn.primary {
            background: linear-gradient(135deg,var(--accent),var(--accent-2));
            color: #fff; box-shadow: 0 8px 22px rgba(79,70,229,.25);
        }
        .bo-modal-btn.primary:hover { filter: brightness(1.04); }
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
            <a href="JobOffer.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                Job Offers
            </a>
            <a href="Projects.php" class="nav-item active">
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
        <header class="top-header animate-enter" style="margin-bottom: 2rem;">
            <div class="page-title">
                <h1>Project Portfolio</h1>
                <p>Strategic mission control and operational lifecycle management.</p>
            </div>
            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <div class="search-bar" style="margin:0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                    <input type="text" id="projectSearch" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" placeholder="Search projects, owners, status…" aria-label="Search projects">
                </div>
            </div>
        </header>

        <?php if ($notice['message'] !== ''): ?><div class="notice <?= htmlspecialchars($notice['type']) ?>"><?= htmlspecialchars($notice['message']) ?></div><?php endif; ?>

        <div class="module-actions" style="margin-bottom: 2rem;">
            <div style="display:flex; gap:12px; align-items:center;">
                <button class="btn-export" type="button" onclick="exportProjectsCsv()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Export Analytics (CSV)
                </button>
                <button class="btn-export primary" type="button" onclick="exportProjectsPdf()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Executive Summary (PDF)
                </button>
            </div>
            <button class="btn-primary" type="button" onclick="openCreateProjectModal()" style="margin-left:auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                Launch New Mission
            </button>
        </div>

        <div class="bento-grid">
            <!-- KPI ROW -->
            <div class="card span-4 kpi-card animate-enter" style="animation-delay:.04s;">
                <div class="kpi-body">
                    <div class="kpi-h">Total projects</div>
                    <div class="kpi-v"><?= count($filteredRows) ?></div>
                </div>
                <div class="icon-box icon-blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                </div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay:.07s;">
                <div class="kpi-body">
                    <div class="kpi-h">Active projects</div>
                    <div class="kpi-v"><?= count(array_filter($filteredRows, static fn(array $r): bool => (string)($r['status'] ?? '') === 'active')) ?></div>
                </div>
                <div class="icon-box icon-indigo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay:.10s;">
                <div class="kpi-body">
                    <div class="kpi-h">Completed</div>
                    <div class="kpi-v"><?= count(array_filter($filteredRows, static fn(array $r): bool => (string)($r['status'] ?? '') === 'completed')) ?></div>
                </div>
                <div class="icon-box icon-purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
            </div>

            <!-- CHART ROW -->
            <section class="card span-3 animate-enter" style="animation-delay:.13s;">
                <div class="section-head"><h2>Completion %</h2></div>
                <div class="chart-wrap"><canvas id="completionRingChart"></canvas></div>
                <div class="kpi-ring-label" style="color:#4f46e5;"><?= number_format($averageCompletion,1) ?>%</div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.15s;">
                <div class="section-head"><h2>Task status</h2></div>
                <div class="chart-wrap"><canvas id="taskStatusChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.17s;">
                <div class="section-head"><h2>On-time rate</h2></div>
                <div class="chart-wrap"><canvas id="onTimeChart"></canvas></div>
                <div class="kpi-ring-label" style="color:#22c55e;"><?= number_format($onTimeRate,1) ?>%</div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.19s;">
                <div class="section-head"><h2>Duration analysis</h2></div>
                <div class="chart-wrap"><canvas id="durationChart"></canvas></div>
            </section>

            <section class="card span-6 animate-enter" style="animation-delay:.21s;">
                <div class="section-head"><h2>Team workload</h2></div>
                <div class="chart-wrap" style="height:220px;"><canvas id="workloadChart"></canvas></div>
            </section>
            <section class="card span-6 animate-enter" style="animation-delay:.23s;">
                <div class="section-head"><h2>Burndown</h2></div>
                <div class="chart-wrap" style="height:220px;"><canvas id="burndownChart"></canvas></div>
            </section>

            <!-- PROJECTS TABLE -->
            <section class="card span-12 animate-enter" style="animation-delay:.25s;" id="projects-list">
                <div class="section-head">
                    <h2>Projects list</h2>
                    <span style="font-size:.78rem;color:var(--text-muted);"><?= count($filteredRows) ?> rows</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="elegant-table">
                        <thead><tr><th>ID</th><th>Title</th><th>Owner</th><th>Status</th><th>Progress</th><th>Visibility</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($filteredRows as $row): ?>
                            <tr>
                                <td style="font-weight:600;color:#4f46e5;">#<?= (int)($row['id']??0) ?></td>
                                <td>
                                    <strong style="display:block;"><?= htmlspecialchars((string)($row['title']??'')) ?></strong>
                                    <span>Budget: <?= number_format((float)($row['budget']??0),2) ?> TND</span>
                                </td>
                                <td><?= htmlspecialchars(trim((string)($row['first_name']??'').' '.(string)($row['last_name']??''))?:'Unknown') ?></td>
                                <td>
                                    <?php $s=(string)($row['status']??'planning'); ?>
                                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;font-size:.7rem;font-weight:700;background:<?= $s==='active'?'rgba(16,185,129,.12)':($s==='completed'?'rgba(99,102,241,.12)':'rgba(245,158,11,.12)') ?>;color:<?= $s==='active'?'#065f46':($s==='completed'?'#4f46e5':'#92400e') ?>;">
                                        <?= ucfirst(str_replace('_',' ',$s)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php $p=(int)($row['progress_percent']??0); ?>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;height:6px;background:#eef2ff;border-radius:3px;overflow:hidden;">
                                            <div style="height:100%;width:<?= $p ?>%;background:linear-gradient(90deg,#4f46e5,#06b6d4);border-radius:3px;"></div>
                                        </div>
                                        <span style="font-size:.74rem;font-weight:700;color:#4f46e5;min-width:32px;"><?= $p ?>%</span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars((string)($row['visibility']??'team')) ?></td>
                                <td style="text-align:right;">
                                    <div class="action-stack" style="justify-content:flex-end;">
                                        <form method="post" class="action-stack">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="update_project">
                                            <input type="hidden" name="project_id" value="<?= (int)($row['id']??0) ?>">
                                            <select name="status" class="action-select"><?php foreach(['planning','active','completed','on_hold','archived'] as $status): ?><option value="<?= $status ?>" <?= (string)($row['status']??'')===$status?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$status)) ?></option><?php endforeach; ?></select>
                                            <input type="number" name="progress_percent" min="0" max="100" value="<?= (int)($row['progress_percent']??0) ?>" class="action-input">
                                            <button class="btn-mini" type="submit">Save</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this project?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_project">
                                            <input type="hidden" name="project_id" value="<?= (int)($row['id']??0) ?>">
                                            <button class="btn-mini delete" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(!$filteredRows):?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:24px;">No projects yet.</td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- TASKS PER PROJECT -->
            <section class="card span-12 animate-enter" style="animation-delay:.27s;" id="tasks-list">
                <div class="section-head">
                    <h2>Tasks per project</h2>
                    <span style="font-size:.78rem;color:var(--text-muted);"><?= count($filteredRows) ?> projects</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <?php foreach($filteredRows as $row):
                        $pId=(int)($row['id']??0);
                        $projectTasks=$filteredTasksByProject[$pId]??[];
                    ?>
                    <div style="border:1px solid #dfe6ff;border-radius:16px;padding:16px;background:#fbfcff;">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
                            <div>
                                <strong style="font-size:.9rem;color:#1f2a44;"><?= htmlspecialchars((string)($row['title']??'')) ?></strong>
                                <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px;"><?= count($projectTasks) ?> task<?= count($projectTasks)===1?'':'s' ?></div>
                            </div>
                            <button type="button" class="btn-mini" onclick='openTaskModal(<?= $pId ?>, <?= json_encode((string)($row["title"]??""),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>+ Add task</button>
                        </div>
                        <?php if(empty($projectTasks)): ?>
                            <div style="font-size:.8rem;color:var(--text-muted);padding:8px 0;">No tasks yet.</div>
                        <?php else: ?>
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                                <?php foreach($projectTasks as $task): ?>
                                <div style="background:#fff;border:1px solid #e8eeff;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:7px;transition:box-shadow .2s;box-shadow:0 2px 8px rgba(66,84,160,.06);">
                                    <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">
                                        <strong style="font-size:.82rem;color:#1f2a44;line-height:1.3;"><?= htmlspecialchars((string)($task['title']??'')) ?></strong>
                                        <?php $ts=strtolower((string)($task['status']??'todo')); ?>
                                        <span style="flex-shrink:0;font-size:.65rem;font-weight:700;text-transform:uppercase;padding:2px 8px;border-radius:999px;background:<?= $ts==='done'?'rgba(16,185,129,.12)':($ts==='in_progress'?'rgba(245,158,11,.12)':'rgba(99,102,241,.12)') ?>;color:<?= $ts==='done'?'#065f46':($ts==='in_progress'?'#92400e':'#4f46e5') ?>;"><?= htmlspecialchars(str_replace('_',' ',$ts)) ?></span>
                                    </div>
                                    <?php if(trim((string)($task['description']??''))!==''): ?>
                                        <div style="font-size:.72rem;color:var(--text-muted);line-height:1.45;"><?= htmlspecialchars(mb_strimwidth(trim((string)($task['description']??'')),0,120,'…')) ?></div>
                                    <?php endif; ?>
                                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px;">
                                        <span style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars((string)($task['deadline']??'')) ?></span>
                                        <div style="display:flex;gap:5px;">
                                            <button type="button" class="btn-mini" style="padding:.38rem .65rem;" onclick='openEditTaskModal(<?= (int)($task["id"]??0) ?>, <?= $pId ?>, <?= json_encode((string)($task["title"]??""),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>, <?= json_encode(trim((string)($task["description"]??"")),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($task["status"]??"todo"),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string)($task["deadline"]??""),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>)'>Edit</button>
                                            <form method="post" onsubmit="return confirm('Delete this task?');" style="display:inline">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="task_id" value="<?= (int)($task['id']??0) ?>">
                                                <button type="submit" class="btn-mini delete" style="padding:.38rem .65rem;">Del</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <!-- CREATE PROJECT MODAL -->
        <div class="bo-modal-backdrop" id="createProjectModal" onclick="if(event.target===this) closeCreateProjectModal()">
            <div class="bo-modal">
                <div class="bo-modal-head">
                    <h3>Create New Project</h3>
                    <button type="button" class="bo-modal-close" onclick="closeCreateProjectModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="createProjectForm" method="post" class="form-panel" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_project">
                    <div class="field-grid">
                        <div><label>Title</label><input type="text" name="title" required></div>
                        <div><label>Budget (TND)</label><input type="number" name="budget" min="0" step="0.01" value="0"></div>
                        <div class="full"><label>Description</label><textarea name="description" required></textarea></div>
                        <div><label>Status</label><select name="status"><option value="planning">Planning</option><option value="active">Active</option><option value="completed">Completed</option><option value="on_hold">On hold</option><option value="archived">Archived</option></select></div>
                        <div><label>Progress %</label><input type="number" name="progress_percent" min="0" max="100" value="0"></div>
                        <div><label>Visibility</label><select name="visibility"><option value="team">Team</option><option value="public">Public</option><option value="private">Private</option></select></div>
                        <div><label>Due date</label><input type="date" name="due_date"></div>
                        <div class="full"><label>Owner</label><select name="owner_id" required><option value="">Select an owner</option><?php foreach($users as $user): ?><option value="<?= (int)($user['id']??0) ?>"><?= htmlspecialchars($controller->userLabel($user)) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="bo-modal-actions">
                        <button type="button" class="bo-modal-btn ghost" onclick="closeCreateProjectModal()">Cancel</button>
                        <button class="bo-modal-btn primary" type="submit">Create Project</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ADD TASK MODAL -->
        <div class="bo-modal-backdrop" id="taskModal" onclick="if(event.target===this) closeTaskModal()">
            <div class="bo-modal">
                <div class="bo-modal-head">
                    <h3 id="taskModalTitle">Add Task</h3>
                    <button type="button" class="bo-modal-close" onclick="closeTaskModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="createTaskForm" method="post" class="form-panel" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_task">
                    <input type="hidden" name="project_id" id="taskProjectId">
                    <div class="field-grid">
                        <div class="full"><label>Title</label><input type="text" name="title" required></div>
                        <div class="full"><label>Description</label><textarea name="description" required></textarea></div>
                        <div><label>Status</label><select name="status"><option value="todo">Todo</option><option value="in_progress">In progress</option><option value="done">Done</option></select></div>
                        <div><label>Deadline</label><input type="date" name="deadline"></div>
                    </div>
                    <div class="bo-modal-actions">
                        <button type="button" class="btn-mini" onclick="aiTaskAssistant()" style="margin-right:auto;background:linear-gradient(135deg,#4f46e5,#06b6d4);border-color:transparent;">✨ AI Assistant</button>
                        <button type="button" class="bo-modal-btn ghost" onclick="closeTaskModal()">Cancel</button>
                        <button class="bo-modal-btn primary" type="submit">Save Task</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- EDIT TASK MODAL -->
        <div class="bo-modal-backdrop" id="editTaskModal" onclick="if(event.target===this) closeEditTaskModal()">
            <div class="bo-modal">
                <div class="bo-modal-head">
                    <h3>Edit Task</h3>
                    <button type="button" class="bo-modal-close" onclick="closeEditTaskModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="editTaskForm" method="post" class="form-panel" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="task_id" id="editTaskId">
                    <div class="field-grid">
                        <div class="full"><label>Title</label><input type="text" name="title" id="editTaskTitle" required></div>
                        <div class="full"><label>Description</label><textarea name="description" id="editTaskDescription" required></textarea></div>
                        <div><label>Status</label><select name="status" id="editTaskStatus"><option value="todo">Todo</option><option value="in_progress">In progress</option><option value="done">Done</option></select></div>
                        <div><label>Deadline</label><input type="date" name="deadline" id="editTaskDeadline"></div>
                    </div>
                    <div class="bo-modal-actions">
                        <button type="button" class="bo-modal-btn ghost" onclick="closeEditTaskModal()">Cancel</button>
                        <button class="bo-modal-btn primary" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
/* ── MODALS ──────────────────────────────────────────────── */
const openModal  = id => document.getElementById(id)?.classList.add('open');
const closeModal = id => document.getElementById(id)?.classList.remove('open');
function openCreateProjectModal(){ openModal('createProjectModal'); }
function closeCreateProjectModal(){ closeModal('createProjectModal'); }
function openTaskModal(projectId, projectTitle){
    document.getElementById('taskProjectId').value = projectId;
    document.getElementById('taskModalTitle').textContent = 'Add Task: ' + (projectTitle||'');
    openModal('taskModal');
}
function closeTaskModal(){ closeModal('taskModal'); }
function openEditTaskModal(taskId,projectId,title,description,status,deadline){
    document.getElementById('editTaskId').value = taskId;
    document.getElementById('editTaskTitle').value = title||'';
    document.getElementById('editTaskDescription').value = description||'';
    document.getElementById('editTaskStatus').value = status||'todo';
    document.getElementById('editTaskDeadline').value = deadline||'';
    openModal('editTaskModal');
}
function closeEditTaskModal(){ closeModal('editTaskModal'); }

/* ── SEARCH ──────────────────────────────────────────────── */
document.getElementById('projectSearch')?.addEventListener('input', function(){
    const term = this.value.trim().toLowerCase();
    ['projects-list','tasks-list'].forEach(id => {
        document.querySelectorAll(`#${id} tbody tr`).forEach(row => {
            row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
});

/* ── REAL-TIME TOGGLE ────────────────────────────────────── */
let realtimeTimer = null;
document.getElementById('realtimeToggle')?.addEventListener('change', e => {
    if(e.target.checked){ realtimeTimer = setInterval(() => location.reload(), 45000); }
    else { clearInterval(realtimeTimer); realtimeTimer = null; }
});

/* ── CSV/PDF EXPORT ──────────────────────────────────────── */
function serverExportDownload(action, filenameDefault) {
    Swal.fire({ title: 'Generating...', text: 'Preparing your file...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', '<?= $csrfToken ?>');
    fetch('Projects.php', { method: 'POST', body: fd })
        .then(r => {
            const ct = r.headers.get('content-type') || '';
            if (ct.includes('application/json')) return r.json().then(j => { throw new Error(j.error || 'Export failed.'); });
            const disp = r.headers.get('content-disposition') || '';
            const match = disp.match(/filename="(.+?)"/);
            const filename = match ? match[1] : filenameDefault;
            return r.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            const url = URL.createObjectURL(blob);
            const a = Object.assign(document.createElement('a'), { href: url, download: filename });
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(url);
            Swal.fire({ title: 'Downloaded!', icon: 'success', timer: 1500, showConfirmButton: false });
        })
        .catch(err => Swal.fire('Error', err.message, 'error'));
}
function exportProjectsCsv(){ serverExportDownload('export_csv', 'projects-export.csv'); }
function exportProjectsPdf(){ serverExportDownload('export_pdf', 'projects-report.pdf'); }

/* ── CHARTS ──────────────────────────────────────────────── */
const chartOpts = { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:11,family:'Poppins'}}}} };

new Chart(document.getElementById('completionRingChart'),{
    type:'doughnut',
    data:{labels:['Completed','Remaining'],datasets:[{data:[<?= json_encode($averageCompletion) ?>,<?= json_encode(max(0,100-$averageCompletion)) ?>],backgroundColor:['#4f46e5','#e5e7eb'],borderWidth:0,borderRadius:4}]},
    options:{...chartOpts,cutout:'74%'}
});
new Chart(document.getElementById('taskStatusChart'),{
    type:'doughnut',
    data:{labels:<?= json_encode(array_map(static fn(string $l):string=>ucfirst(str_replace('_',' ',$l)),array_keys($statusDistribution)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{data:<?= json_encode(array_values($statusDistribution),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,backgroundColor:['#94a3b8','#f59e0b','#10b981','#6366f1'],borderWidth:0}]},
    options:chartOpts
});
new Chart(document.getElementById('onTimeChart'),{
    type:'doughnut',
    data:{labels:['On time','Late'],datasets:[{data:[<?= json_encode($onTimeRate) ?>,<?= json_encode(max(0,100-$onTimeRate)) ?>],backgroundColor:['#22c55e','#e5e7eb'],borderWidth:0,borderRadius:4}]},
    options:{...chartOpts,cutout:'74%'}
});
new Chart(document.getElementById('durationChart'),{
    type:'bar',
    data:{labels:<?= json_encode(array_map(static fn(string $l):string=>ucfirst(str_replace('_',' ',$l)),array_keys($durationAverages)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Avg days',data:<?= json_encode(array_values($durationAverages),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,backgroundColor:'#0ea5e9',borderRadius:6}]},
    options:{...chartOpts,plugins:{...chartOpts.plugins,legend:{display:false}}}
});
new Chart(document.getElementById('workloadChart'),{
    type:'bar',
    data:{labels:<?= json_encode(array_slice(array_keys($workloadByOwner),0,8),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Tasks',data:<?= json_encode(array_slice(array_values($workloadByOwner),0,8),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,backgroundColor:'#6366f1',borderRadius:6}]},
    options:{...chartOpts,indexAxis:'y'}
});
new Chart(document.getElementById('burndownChart'),{
    type:'line',
    data:{labels:<?= json_encode(array_keys($burndownBuckets),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Remaining tasks',data:<?= json_encode(array_values($burndownBuckets),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,.15)',tension:.4,fill:true,pointRadius:3}]},
    options:chartOpts
});

/* ── AI TASK ASSISTANT ───────────────────────────────────── */
function aiTaskAssistant(){
    const pId = document.getElementById('taskProjectId').value;
    Swal.fire({
        title:'AI Task Generator',input:'textarea',inputPlaceholder:'Tell AI what you want to generate…',
        showCancelButton:true,confirmButtonText:'Generate',showLoaderOnConfirm:true,
        preConfirm: prompt => {
            const fd = new FormData();
            fd.append('action','ai_generate_task'); fd.append('project_id',pId);
            fd.append('prompt',prompt); fd.append('csrf_token','<?= $csrfToken ?>');
            return fetch('Projects.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
                if(!data.success) throw new Error(data.error||'Failed');
                return data;
            }).catch(e=>Swal.showValidationMessage(`Error: ${e.message}`));
        }
    }).then(result=>{
        if(result.isConfirmed){
            const task=result.value.task;
            document.querySelector('#createTaskForm input[name="title"]').value=task.title||'';
            document.querySelector('#createTaskForm textarea[name="description"]').value=task.description||'';
            Swal.fire('Generated!','Task details filled.','success');
        }
    });
}


</script>

<script src="../../assets/js/globe-explorer.js"></script>
<script src="../../assets/js/mvc-inline-validation.js"></script>
<script src="../../assets/js/user.js"></script>
<script src="../../assets/js/skilluser.js"></script>
<script src="../../assets/js/backoffice-dashboard.js"></script>
</body>
</html>