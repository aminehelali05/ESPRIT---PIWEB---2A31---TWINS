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
$normalizeStatus = static function (string $raw): string {
    $map = ['pending'=>'pending','approved'=>'approved','rejected'=>'rejected','open'=>'approved','in_progress'=>'approved','active'=>'approved','closed'=>'rejected','archived'=>'rejected'];
    return $map[strtolower(trim($raw))] ?? 'pending';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $token)) {
        $notice = ['type' => 'error', 'message' => 'Invalid token.'];
    } else {
        $action = strtolower($clean($_POST['action'] ?? ''));
        try {
            if ($action === 'create_offer') {
                $payload = ['title'=>$clean($_POST['title']??''),'description'=>$clean($_POST['description']??''),'budget'=>max(0,(float)($_POST['budget']??0)),'skills_required'=>$clean($_POST['skills_required']??''),'location'=>$clean($_POST['location']??''),'experience_level'=>$clean($_POST['experience_level']??'Mid'),'project_type'=>$clean($_POST['project_type']??'Fixed Price'),'status'=>$normalizeStatus($clean($_POST['status']??'pending')),'client_id'=>(int)($_POST['client_id']??0),'salary'=>$clean($_POST['salary']??'')];
                if ($payload['title']===''||$payload['description']===''||$payload['client_id']<=0) throw new RuntimeException('Title, description and client are required.');
                $controller->createFromBackoffice($payload);
                $notice = ['type'=>'success','message'=>'Offer created.'];
            }
            if ($action === 'update_offer') {
                $controller->updateStatus((int)($_POST['offer_id']??0),$normalizeStatus($clean($_POST['status']??'pending')));
                $notice = ['type'=>'success','message'=>'Offer updated.'];
            }
            if ($action === 'delete_offer') {
                $controller->deleteCascade((int)($_POST['offer_id']??0));
                $notice = ['type'=>'success','message'=>'Offer deleted.'];
            }
            if ($action === 'delete_candidature') {
                $applicationId = (int)($_POST['application_id']??0);
                if ($controller->deleteCandidatureById($applicationId)) $notice=['type'=>'success','message'=>'Application deleted.'];
                else $notice=['type'=>'error','message'=>'Application not found.'];
            }
            if ($action === 'ai_analyze_salary') {
                header('Content-Type: application/json');
                try {
                    $result = $controller->analyzeSalarySuggestion(['title'=>$clean($_POST['title']??''),'description'=>$clean($_POST['description']??''),'skills_required'=>$clean($_POST['skills_required']??''),'location'=>$clean($_POST['location']??''),'experience_level'=>$clean($_POST['experience_level']??'Mid'),'project_type'=>$clean($_POST['project_type']??'Fixed Price'),'budget'=>max(0,(float)($_POST['budget']??0))]);
                    echo json_encode(array_merge(['success'=>true],$result));
                } catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
                exit;
            }
            if ($action === 'smart_match') {
                header('Content-Type: application/json');
                try {
                    $result = $controller->smartMatchCandidates((int)($_POST['offer_id']??0),(int)(UserController::currentUser()['id']??0));
                    echo json_encode(array_merge(['success'=>true],$result));
                } catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
                exit;
            }
            if ($action === 'export_csv') {
                $csvRows = $controller->listBackofficeRows();
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="job-offers-export-' . date('Y-m-d') . '.csv"');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Title', 'Client', 'Status', 'Budget (TND)', 'Location', 'Skills', 'Experience', 'Applications', 'Created At']);
                foreach ($csvRows as $cr) {
                    fputcsv($out, [
                        $cr['id'] ?? '',
                        $cr['title'] ?? '',
                        trim(($cr['first_name'] ?? '') . ' ' . ($cr['last_name'] ?? '')),
                        strtoupper($cr['status'] ?? 'pending'),
                        number_format((float)($cr['budget'] ?? 0), 2),
                        $cr['location'] ?? '',
                        $cr['skills_required'] ?? '',
                        $cr['experience_level'] ?? '',
                        $cr['applications_count'] ?? 0,
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
                    .status-approved { background: #ecfdf5; color: #059669; }
                    .status-pending { background: #fffbeb; color: #92400e; }
                    .status-rejected { background: #fef2f2; color: #dc2626; }
                    .footer { margin-top: 30px; text-align: center; color: #94a3b8; font-size: 9px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
                </style></head><body>
                    <div class="header">
                        <h1>Talent Acquisition Report</h1>
                        <p>Diversity.is Official Backoffice Export • Generated on ' . date('F j, Y, H:i') . '</p>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Postings</div>
                            <div class="stat-value">' . count($pdfRows) . ' Offers</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Total Budget</div>
                            <div class="stat-value">' . number_format((float)($stats['total_budget'] ?? 0), 2) . ' TND</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Applications</div>
                            <div class="stat-value">' . ($stats['total_applications'] ?? 0) . ' Total</div>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Opportunity / Title</th>
                                <th>Hiring Manager</th>
                                <th>Status</th>
                                <th>Budget Allocation</th>
                                <th>App Count</th>
                            </tr>
                        </thead>
                        <tbody>';
                foreach ($pdfRows as $row) {
                    $cName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    $status = strtolower($row['status'] ?? 'pending');
                    $html .= '<tr>
                        <td><strong>#' . ($row['id'] ?? '') . '</strong></td>
                        <td>' . htmlspecialchars($row['title'] ?? 'Generic Offer') . '</td>
                        <td>' . htmlspecialchars($cName) . '</td>
                        <td><span class="status status-' . $status . '">' . strtoupper($status) . '</span></td>
                        <td style="font-weight:700">' . number_format((float)($row['budget'] ?? 0), 2) . ' TND</td>
                        <td>' . ($row['applications_count'] ?? 0) . ' Applications</td>
                    </tr>';
                }
                $html .= '</tbody></table>
                    <div class="footer">
                        This document contains confidential talent intelligence. Generated by Diversity.is Admin Panel.
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
                    header('Content-Disposition: attachment; filename="job-offers-report-' . date('Y-m-d') . '.pdf"');
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
            $notice = ['type'=>'error','message'=>$exception->getMessage()];
        }
    }
}

$users = $controller->listUsers();
$rows = $controller->listBackofficeRows();
$candidatures = $controller->listAllCandidatures();
$stats = $controller->buildBackofficeStats($rows);
$fromDate = trim((string)($_GET['from']??''));
$toDate   = trim((string)($_GET['to']??''));
$globalSearch = strtolower(trim((string)($_GET['q']??'')));
$inDateRange = static function(?string $value) use ($fromDate,$toDate): bool {
    if($value===null||trim($value)==='') return true;
    $ts=strtotime($value); if($ts===false) return true;
    if($fromDate!==''){$fromTs=strtotime($fromDate.' 00:00:00');if($fromTs!==false&&$ts<$fromTs)return false;}
    if($toDate!==''){$toTs=strtotime($toDate.' 23:59:59');if($toTs!==false&&$ts>$toTs)return false;}
    return true;
};
$filteredRows = array_values(array_filter($rows,static fn(array $r)=>$inDateRange((string)($r['created_at']??''))&&($globalSearch===''||str_contains(strtolower((string)json_encode($r)),$globalSearch))));
$filteredCandidatures = array_values(array_filter($candidatures,static function(array $r) use ($globalSearch,$inDateRange){
    $ca=(string)($r['candidature_created_at']??$r['applied_at']??$r['created_at']??'');
    return $inDateRange($ca)&&($globalSearch===''||str_contains(strtolower((string)json_encode($r)),$globalSearch));
}));

$sessionUser = UserController::currentUser()??[];
$displayName = trim((string)(($sessionUser['first_name']??'').' '.($sessionUser['last_name']??'')))?:'System Admin';
$displayEmail = (string)($sessionUser['email']??'admin@diversity.is');
$initials = strtoupper(substr((string)($sessionUser['first_name']??'A'),0,1).substr((string)($sessionUser['last_name']??'D'),0,1));

$applicationTrend=[]; $statusDistribution=['pending'=>0,'accepted'=>0,'rejected'=>0];
$skillsCount=[]; $locationCount=[]; $acceptedByOffer=[]; $timeToHireByMonth=[];
foreach($filteredRows as $r){
    $loc=trim((string)($r['location']??'Unknown')); $locKey=$loc!==''?$loc:'Unknown';
    $locationCount[$locKey]=($locationCount[$locKey]??0)+1;
    foreach(preg_split('/[,\|;]/',(string)($r['skills_required']??''))?:[] as $sk){
        $sk=strtolower(trim($sk)); if($sk==='') continue; $skillsCount[$sk]=($skillsCount[$sk]??0)+1;
    }
}
foreach($filteredCandidatures as $r){
    $ca=(string)($r['candidature_created_at']??$r['applied_at']??$r['created_at']??'');
    $mk=$ca!==''?date('Y-m',strtotime($ca)):'unknown';
    $applicationTrend[$mk]=($applicationTrend[$mk]??0)+1;
    $st=strtolower((string)($r['status']??'pending'));
    if(!isset($statusDistribution[$st])) $statusDistribution[$st]=0;
    $statusDistribution[$st]++;
    if($st==='accepted'){$oid=(int)($r['job_offer_id']??0);if($oid>0)$acceptedByOffer[$oid]=$ca;}
}
foreach($filteredRows as $r){
    $oid=(int)($r['id']??0); $ca=(string)($r['created_at']??'');
    if($oid<=0||$ca===''||!isset($acceptedByOffer[$oid])) continue;
    $days=(int)floor((strtotime($acceptedByOffer[$oid])-strtotime($ca))/86400);
    $mk=date('Y-m',strtotime($acceptedByOffer[$oid]));
    if(!isset($timeToHireByMonth[$mk])) $timeToHireByMonth[$mk]=['sum'=>0,'count'=>0];
    $timeToHireByMonth[$mk]['sum']+=max(0,$days); $timeToHireByMonth[$mk]['count']++;
}
ksort($applicationTrend); arsort($skillsCount); arsort($locationCount); ksort($timeToHireByMonth);
$applicationsTotal=count($filteredCandidatures);
$acceptedTotal=(int)($statusDistribution['accepted']??0);
$conversionRate=$applicationsTotal>0?round(($acceptedTotal/$applicationsTotal)*100,1):0.0;
$topSkills=array_slice($skillsCount,0,6,true);
$topLocations=array_slice($locationCount,0,6,true);
$avgHireTrend=[];
foreach($timeToHireByMonth as $m=>$b) $avgHireTrend[$m]=$b['count']>0?round($b['sum']/$b['count'],1):0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice · Job Offers</title>
    <link rel="stylesheet" href="../../assets/css/backoffice-dashboard.css">
    <link rel="stylesheet" href="../../assets/css/user-form.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
        body { margin:0; font-family:var(--font); color:var(--text-main); background:var(--bg-page); min-height:100vh; }
        .app-container { min-height:100vh; background:transparent; }
        .sidebar { background:linear-gradient(180deg,#f7f8ff,#f1f4ff); border-right:1px solid #dbe3ff; box-shadow:8px 0 24px rgba(91,104,178,.08); }
        .main-content { background:transparent; }
        .top-header { background:rgba(255,255,255,.82); border:1px solid #dfe6ff; border-radius:24px; padding:1.15rem 1.6rem; box-shadow:0 8px 28px rgba(66,84,160,.09); backdrop-filter:blur(8px); }

        /* Grid */
        .bento-grid { display:grid; gap:1.1rem; grid-template-columns:repeat(12,minmax(0,1fr)); align-items:start; }
        .bento-grid > * { min-width:0; }
        .span-1{grid-column:span 1;} .span-2{grid-column:span 2;} .span-3{grid-column:span 3;}
        .span-4{grid-column:span 4;} .span-5{grid-column:span 5;} .span-6{grid-column:span 6;}
        .span-7{grid-column:span 7;} .span-8{grid-column:span 8;} .span-9{grid-column:span 9;}
        .span-10{grid-column:span 10;} .span-11{grid-column:span 11;} .span-12{grid-column:span 12;}
        @media(max-width:1200px){ .span-3{grid-column:span 6;} }
        @media(max-width:900px){ .span-3,.span-4,.span-6{grid-column:span 12;} .field-grid{grid-template-columns:1fr;} }
        @media(max-width:640px){ [class*="span-"]{grid-column:span 12;} }

        /* Card */
        .card { background:#fff; border:1px solid var(--border-card); border-radius:var(--radius-card); box-shadow:var(--shadow-card); color:var(--text-main); transition:box-shadow .22s,transform .22s; }
        .card:hover { box-shadow:var(--shadow-hover); transform:translateY(-2px); }
        section.card { padding:20px 22px; }

        /* KPI */
        .kpi-card { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:22px 24px; min-height:120px; }
        .kpi-body { display:flex; flex-direction:column; gap:4px; min-width:0; }
        .kpi-h { font-size:.76rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; white-space:nowrap; }
        .kpi-v { font-size:2.1rem; font-weight:800; color:var(--text-main); line-height:1.1; white-space:nowrap; }
        .icon-box { flex-shrink:0; width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; }
        .icon-box svg { width:24px; height:24px; }
        .icon-blue   { background:rgba(59,130,246,.12); color:#2563eb; }
        .icon-indigo { background:rgba(99,102,241,.12); color:#4f46e5; }
        .icon-purple { background:rgba(168,85,247,.12); color:#7c3aed; }
        .icon-amber  { background:rgba(245,158,11,.12);  color:#d97706; }

        .section-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1rem; }
        .section-head h2 { margin:0; font-size:.95rem; font-weight:700; }

        /* Chart wrapper */
        .chart-wrap { position:relative; width:100%; height:190px; }
        .chart-wrap canvas { position:absolute; inset:0; }
        .kpi-ring-label { text-align:center; font-size:.95rem; font-weight:700; margin-top:8px; }

        /* Table */
        .elegant-table { width:100%; border-collapse:collapse; font-size:.82rem; }
        .elegant-table thead th { background:#f5f7ff; color:var(--text-muted); font-weight:600; font-size:.73rem; text-transform:uppercase; letter-spacing:.05em; padding:10px 14px; text-align:left; border-bottom:1px solid var(--border-card); }
        .elegant-table tbody tr { background:#fff; transition:background .15s; }
        .elegant-table tbody tr:hover { background:#f7f9ff; }
        .elegant-table tbody td { padding:11px 14px; border-bottom:1px solid #f0f3ff; vertical-align:middle; }

        /* Status badges */
        .status-badge { display:inline-flex; align-items:center; gap:5px; padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:700; white-space:nowrap; }
        .status-badge .status-dot { width:6px; height:6px; border-radius:50%; }
        .status-badge.pending    { background:rgba(245,158,11,.12); color:#92400e; }
        .status-badge.pending .status-dot { background:#f59e0b; }
        .status-badge.approved, .status-badge.open, .status-badge.in_progress { background:rgba(16,185,129,.12); color:#065f46; }
        .status-badge.approved .status-dot, .status-badge.open .status-dot, .status-badge.in_progress .status-dot { background:#10b981; }
        .status-badge.rejected, .status-badge.closed, .status-badge.archived { background:rgba(239,68,68,.09); color:#991b1b; }
        .status-badge.rejected .status-dot { background:#ef4444; }

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
        
        .btn-approve { display:inline-flex; align-items:center; gap:4px; padding:7px 14px; border-radius:10px; border:none; background:rgba(16,185,129,.1); color:#059669; font-size:.76rem; font-weight:700; cursor:pointer; transition:all .2s; }
        .btn-approve:hover { background:#10b981; color:#fff; transform:translateY(-1px); }
        .btn-reject { display:inline-flex; align-items:center; gap:4px; padding:7px 14px; border-radius:10px; border:none; background:rgba(239,68,68,.1); color:#dc2626; font-size:.76rem; font-weight:700; cursor:pointer; transition:all .2s; }
        .btn-reject:hover { background:#ef4444; color:#fff; transform:translateY(-1px); }

        /* Form */
        .field-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.9rem; }
        .field-grid .full { grid-column:1/-1; }
        .form-panel { padding:1.15rem; border:1px solid #dfe6ff; border-radius:18px; background:#fbfcff; }
        .form-panel label { display:block; margin:0 0 .35rem; color:var(--text-muted); font-size:.79rem; font-weight:600; }
        .form-panel input,.form-panel select,.form-panel textarea { width:100%; border:1px solid #d6def9; border-radius:12px; background:#fff; color:#25324b; padding:.72rem .9rem; outline:none; font-family:var(--font); font-size:.84rem; transition:border-color .2s,box-shadow .2s; }
        .form-panel textarea { min-height:110px; resize:vertical; }
        .form-panel input:focus,.form-panel select:focus,.form-panel textarea:focus { border-color:rgba(96,165,250,.7); box-shadow:0 0 0 3px rgba(59,130,246,.14); }

        /* Action stack */
        .action-stack { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; }
        .action-stack form { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
        .action-stack select,.action-stack input:not([type="hidden"]) { min-height:38px; padding:.48rem .7rem; border-radius:10px; border:1px solid #b7c9fb; background:#f7f9ff; color:#2b3959; font-weight:600; font-family:var(--font); font-size:.8rem; outline:none; }
        .action-stack select:focus,.action-stack input:not([type="hidden"]):focus { border-color:#7d95ff; box-shadow:0 0 0 3px rgba(79,70,229,.14); }
        .action-select { min-width:130px; appearance:none; background-image:linear-gradient(45deg,transparent 50%,#566a95 50%),linear-gradient(135deg,#566a95 50%,transparent 50%); background-position:calc(100% - 16px) calc(50% - 3px),calc(100% - 10px) calc(50% - 3px); background-size:6px 6px; background-repeat:no-repeat; padding-right:2rem!important; }

        /* Module actions */
        .module-actions { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:1rem; }

        /* Notice */
        .notice { margin-bottom:1rem; padding:.9rem 1.1rem; border-radius:14px; font-size:.84rem; font-weight:500; }
        .notice.success { background:rgba(16,185,129,.12); border:1px solid rgba(16,185,129,.28); color:#065f46; }
        .notice.error   { background:rgba(239,68,68,.10);  border:1px solid rgba(239,68,68,.28);  color:#991b1b; }

        /* Modal */
        .bo-modal-backdrop { position:fixed; inset:0; background:rgba(15,21,42,.46); backdrop-filter:blur(6px); z-index:200; display:none; align-items:center; justify-content:center; padding:20px; }
        .bo-modal-backdrop.open { display:flex; animation:boFadeIn .2s ease; }
        @keyframes boFadeIn { from{opacity:0} to{opacity:1} }
        .bo-modal { width:100%; max-width:700px; max-height:90vh; overflow-y:auto; background:#fff; border:1px solid #d2dbef; border-radius:20px; box-shadow:0 26px 60px rgba(30,41,59,.28); padding:26px; animation:boModalIn .26s cubic-bezier(.16,1,.3,1); }
        @keyframes boModalIn { from{opacity:0;transform:scale(.96) translateY(10px)} to{opacity:1;transform:none} }
        .bo-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
        .bo-modal-head h3 { margin:0; font-size:1.05rem; font-weight:700; }
        .bo-modal-close { background:none; border:none; cursor:pointer; padding:4px; color:#8a9ab8; border-radius:6px; }
        .bo-modal-close:hover { background:#eef2ff; }
        .bo-modal .form-panel { padding:0; border:0; background:transparent; box-shadow:none; }
        .bo-modal .form-panel input,.bo-modal .form-panel select,.bo-modal .form-panel textarea { padding:8px 11px; background:#eef2fa; border:1px solid #d2dbef; border-radius:10px; font-size:.84rem; }
        .bo-modal-actions { display:flex; gap:8px; margin-top:18px; justify-content:flex-end; }
        .bo-modal-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:10px; font-size:.82rem; font-weight:700; border:1px solid transparent; cursor:pointer; font-family:var(--font); }
        .bo-modal-btn.ghost { background:#eef2fa; border-color:#d4ddef; color:#607092; }
        .bo-modal-btn.primary { background:linear-gradient(135deg,var(--accent),var(--accent-2)); color:#fff; box-shadow:0 8px 22px rgba(79,70,229,.25); }
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
            <a href="dashboardUser.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>Overview</a>
            <a href="dashboardUser.php#users" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>User</a>
            <a href="JobOffer.php" class="nav-item active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>Job Offers</a>
            <a href="Projects.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>Projects</a>
            <a href="Contracts.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg>Contracts</a>
            <a href="dashboardUser.php#settings" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>Settings</a>
            <a href="../FrontOffice/home.php" class="nav-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="5" x2="19" y1="12" y2="12"></line><polyline points="12 5 5 12 12 19"></polyline></svg>Exit Backend</a>
        </nav>
        <div class="user-widget">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info"><strong><?= htmlspecialchars($displayName) ?></strong><span><?= htmlspecialchars($displayEmail) ?></span></div>
            <a href="../../index.php?action=logout" class="logout-icon" aria-label="Sign out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" x2="9" y1="12" y2="12"></line></svg></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="top-header animate-enter" style="margin-bottom: 2rem;">
            <div class="page-title">
                <h1>Job Offers Intelligence</h1>
                <p>Strategic recruitment management and talent pipeline tracking.</p>
            </div>
            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <div class="search-bar" style="margin:0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                    <input type="text" id="jobOfferSearch" value="<?= htmlspecialchars((string)($_GET['q']??'')) ?>" placeholder="Search offers, applications, locations…">
                </div>
            </div>
        </header>

        <?php if($notice['message']!==''):?><div class="notice <?= htmlspecialchars($notice['type']) ?>"><?= htmlspecialchars($notice['message']) ?></div><?php endif;?>

        <div class="module-actions" style="margin-bottom: 2rem;">
            <div style="display:flex; gap:12px; align-items:center;">
                <button class="btn-export" type="button" onclick="exportJobCsv()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Export Analytics (CSV)
                </button>
                <button class="btn-export primary" type="button" onclick="exportJobPdf()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Global Report (PDF)
                </button>
            </div>
            <button class="btn-primary" type="button" onclick="openCreateOfferModal()" style="margin-left:auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                Post New Opportunity
            </button>
        </div>

        <div class="bento-grid">
            <!-- KPI ROW -->
            <div class="card span-4 kpi-card animate-enter" style="animation-delay:.04s;">
                <div class="kpi-body"><div class="kpi-h">Total offers</div><div class="kpi-v"><?= count($filteredRows) ?></div></div>
                <div class="icon-box icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay:.07s;">
                <div class="kpi-body"><div class="kpi-h">Approved offers</div><div class="kpi-v"><?= count(array_filter($filteredRows,static fn(array $r):bool=>in_array((string)($r['status']??''),['approved','open','in_progress'],true))) ?></div></div>
                <div class="icon-box icon-indigo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay:.10s;">
                <div class="kpi-body"><div class="kpi-h">Applications</div><div class="kpi-v"><?= count($filteredCandidatures) ?></div></div>
                <div class="icon-box icon-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg></div>
            </div>

            <!-- PENDING BANNER -->
            <?php $pendingCount=count(array_filter($filteredRows,static fn(array $r):bool=>(string)($r['status']??'')==='pending'));?>
            <?php if($pendingCount>0):?>
            <div class="card span-12 animate-enter" style="animation-delay:.115s;padding:14px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:linear-gradient(135deg,rgba(245,158,11,.06),rgba(251,191,36,.03));border-color:rgba(245,158,11,.22);">
                <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" width="22" height="22" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <div>
                    <strong style="font-size:.88rem;color:#92400e;"><?= $pendingCount ?> offer<?= $pendingCount===1?'':'s' ?> pending approval</strong>
                    <p style="font-size:.78rem;color:#a16207;margin:2px 0 0;">Review and approve/reject pending job offers below.</p>
                </div>
            </div>
            <?php endif;?>

            <!-- CHARTS ROW -->
            <section class="card span-6 animate-enter" style="animation-delay:.13s;">
                <div class="section-head"><h2>Applications trend</h2></div>
                <div class="chart-wrap" style="height:200px;position:relative;"><canvas id="applicationsTrendChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.15s;">
                <div class="section-head"><h2>Status distribution</h2></div>
                <div class="chart-wrap" style="height:200px;position:relative;"><canvas id="statusDonutChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.17s;">
                <div class="section-head"><h2>Conversion rate</h2></div>
                <div class="chart-wrap" style="height:180px;position:relative;"><canvas id="conversionGaugeChart"></canvas></div>
                <div class="kpi-ring-label" style="color:#4f46e5;margin-top:-35px;text-align:center;font-weight:800;position:relative;z-index:10;"><?= number_format($conversionRate,1) ?>%</div>
            </section>
            <section class="card span-6 animate-enter" style="animation-delay:.19s;">
                <div class="section-head"><h2>Top skills demanded</h2></div>
                <div class="chart-wrap" style="height:200px;position:relative;"><canvas id="skillsBarChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.21s;">
                <div class="section-head"><h2>Jobs by location</h2></div>
                <div class="chart-wrap" style="height:200px;position:relative;"><canvas id="locationBarChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.23s;">
                <div class="section-head"><h2>Time to hire</h2></div>
                <div class="chart-wrap" style="height:200px;position:relative;"><canvas id="hireTrendChart"></canvas></div>
            </section>

            <!-- OFFERS TABLE -->
            <section class="card span-12 animate-enter" style="animation-delay:.25s;" id="offers-list">
                <div class="section-head">
                    <h2>Offers list</h2>
                    <span style="font-size:.78rem;color:var(--text-muted);"><?= count($filteredRows) ?> rows</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="elegant-table">
                        <thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Status</th><th>Applications</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($filteredRows as $row):
                            $rowStatus=strtolower(trim((string)($row['status']??'pending')));
                            $normalizedStatus=$normalizeStatus($rowStatus);
                        ?>
                            <tr<?= $normalizedStatus==='pending'?' style="background:rgba(245,158,11,.025);"':'' ?>>
                                <td style="font-weight:600;color:#4f46e5;">#<?= (int)($row['id']??0) ?></td>
                                <td>
                                    <strong style="display:block;"><?= htmlspecialchars((string)($row['title']??'')) ?></strong>
                                    <span style="font-size:.74rem;color:#64748b;">Budget: <?= number_format((float)($row['budget']??0),2) ?> TND</span>
                                    <?php if(trim((string)($row['location']??''))!==''):?>
                                        <br><span style="font-size:.7rem;color:#94a3b8;">📍 <?= htmlspecialchars((string)($row['location']??'')) ?></span>
                                    <?php endif;?>
                                </td>
                                <td><?= htmlspecialchars(trim((string)($row['first_name']??'').' '.(string)($row['last_name']??''))?:'Unknown') ?></td>
                                <td><span class="status-badge <?= htmlspecialchars($rowStatus) ?>"><span class="status-dot"></span><?= ucfirst(str_replace('_',' ',$rowStatus)) ?></span></td>
                                <td><strong><?= (int)($row['applications_count']??0) ?></strong></td>
                                <td style="text-align:right;">
                                    <div class="action-stack" style="justify-content:flex-end;">
                                        <?php if($normalizedStatus==='pending'):?>
                                        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_offer"><input type="hidden" name="offer_id" value="<?= (int)($row['id']??0) ?>"><input type="hidden" name="status" value="approved"><button class="btn-approve" type="submit"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Approve</button></form>
                                        <form method="post" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_offer"><input type="hidden" name="offer_id" value="<?= (int)($row['id']??0) ?>"><input type="hidden" name="status" value="rejected"><button class="btn-reject" type="submit"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject</button></form>
                                        <?php else:?>
                                        <form method="post" class="action-stack">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_offer"><input type="hidden" name="offer_id" value="<?= (int)($row['id']??0) ?>">
                                            <select name="status" class="action-select"><?php foreach(['pending','approved','rejected'] as $status):?><option value="<?= $status ?>" <?= $normalizedStatus===$status?'selected':'' ?>><?= ucfirst($status) ?></option><?php endforeach;?></select>
                                            <button class="btn-mini" type="submit">Save</button>
                                        </form>
                                        <?php endif;?>
                                        <button class="btn-mini" type="button" onclick="smartMatch(<?= (int)($row['id']??0) ?>)" style="background:linear-gradient(135deg,#8b5cf6,#ec4899);border-color:transparent;">Smart Match</button>
                                        <form method="post" onsubmit="return confirm('Delete this offer?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="delete_offer"><input type="hidden" name="offer_id" value="<?= (int)($row['id']??0) ?>"><button class="btn-mini delete" type="submit">Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach;?>
                        <?php if(!$filteredRows):?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px;">No offers yet.</td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- CANDIDATURES TABLE -->
            <section class="card span-12 animate-enter" style="animation-delay:.27s;" id="candidatures-list">
                <div class="section-head">
                    <h2>Candidatures</h2>
                    <span style="font-size:.78rem;color:var(--text-muted);"><?= count($filteredCandidatures) ?> rows</span>
                </div>
                <div style="overflow-x:auto;">
                    <table class="elegant-table">
                        <thead><tr><th>ID</th><th>Offer</th><th>Freelancer</th><th>Message</th><th>Status</th><th>Created</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach($filteredCandidatures as $c):?>
                            <tr>
                                <td style="font-weight:600;color:#4f46e5;">#<?= (int)($c['application_id']??$c['candidature_id']??0) ?></td>
                                <td><strong><?= htmlspecialchars((string)($c['offer_title']??'')) ?></strong></td>
                                <td><?= htmlspecialchars(trim((string)($c['first_name']??'').' '.(string)($c['last_name']??''))) ?></td>
                                <td style="max-width:320px;font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars(mb_strimwidth(trim((string)($c['message']??'')),0,120,'…')) ?></td>
                                <td><?php $cs=strtolower((string)($c['status']??'pending'));?><span class="status-badge <?= $cs ?>" style="<?= $cs==='accepted'?'background:rgba(16,185,129,.12);color:#065f46;':'' ?>"><span class="status-dot" style="<?= $cs==='accepted'?'background:#10b981;':'' ?>"></span><?= ucfirst($cs) ?></span></td>
                                <td style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars((string)($c['candidature_created_at']??$c['applied_at']??'')) ?></td>
                                <td style="text-align:right;">
                                    <form method="post" onsubmit="return confirm('Delete this application?');" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete_candidature">
                                        <input type="hidden" name="application_id" value="<?= (int)($c['application_id']??0) ?>">
                                        <button class="btn-mini delete" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach;?>
                        <?php if(!$filteredCandidatures):?><tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:24px;">No candidatures yet.</td></tr><?php endif;?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- CREATE OFFER MODAL -->
        <div class="bo-modal-backdrop" id="createOfferModal" onclick="if(event.target===this) closeCreateOfferModal()">
            <div class="bo-modal">
                <div class="bo-modal-head">
                    <h3>Post a Job Offer</h3>
                    <button type="button" class="bo-modal-close" onclick="closeCreateOfferModal()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
                </div>
                <form id="createOfferForm" method="post" class="form-panel" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_offer">
                    <div class="field-grid">
                        <div><label>Title</label><input type="text" name="title" id="createOfferTitle" required></div>
                        <div><label>Budget (TND)</label><input type="number" name="budget" min="0" step="0.01" value="0"></div>
                        <div class="full"><label>Description</label><textarea name="description" required></textarea></div>
                        <div><label>Project Type</label><select name="project_type"><option>Fixed Price</option><option>Hourly</option><option>Retainer</option><option>Long-term</option></select></div>
                        <div><label>Experience Level</label><select name="experience_level"><option>Junior</option><option selected>Mid</option><option>Senior</option><option>Expert</option></select></div>
                        <div><label>Location</label><input type="text" name="location"></div>
                        <div>
                            <label>Salary</label>
                            <div style="display:flex;gap:6px;">
                                <input type="text" name="salary" id="create_salary_bo" style="flex:1;" placeholder="e.g. 3500 TND">
                                <button type="button" class="btn-mini" onclick="analyzeSalaryBo()" title="AI Suggestion" style="padding:.48rem .7rem;">✨ AI</button>
                            </div>
                        </div>
                        <div><label>Status</label><select name="status"><option value="active">Active</option><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="closed">Closed</option><option value="archived">Archived</option></select></div>
                        <div class="full"><label>Skills required</label><input type="text" name="skills_required" placeholder="e.g. PHP, React, MySQL"></div>
                        <div class="full"><label>Client</label><select name="client_id" required><option value="">Select a client</option><?php foreach($users as $user):?><option value="<?= (int)($user['id']??0) ?>"><?= htmlspecialchars($controller->userLabel($user)) ?></option><?php endforeach;?></select></div>
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
const openModal  = id => document.getElementById(id)?.classList.add('open');
const closeModal = id => document.getElementById(id)?.classList.remove('open');
function openCreateOfferModal(){ openModal('createOfferModal'); }
function closeCreateOfferModal(){ closeModal('createOfferModal'); }

document.getElementById('jobOfferSearch')?.addEventListener('input', function(){
    const term = this.value.trim().toLowerCase();
    ['offers-list','candidatures-list'].forEach(id => {
        document.querySelectorAll(`#${id} tbody tr`).forEach(r => {
            r.style.display = !term || r.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
});

let realtimeTimer = null;
document.getElementById('realtimeToggle')?.addEventListener('change', e => {
    if(e.target.checked){ realtimeTimer = setInterval(()=>location.reload(),45000); }
    else { clearInterval(realtimeTimer); realtimeTimer = null; }
});

function serverExportDownload(action, filenameDefault) {
    Swal.fire({ title: 'Generating...', text: 'Preparing your file...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    const fd = new FormData();
    fd.append('action', action);
    fd.append('csrf_token', '<?= $csrfToken ?>');
    fetch('JobOffer.php', { method: 'POST', body: fd })
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
function exportJobCsv(){ serverExportDownload('export_csv', 'job-offers-export.csv'); }
function exportJobPdf(){ serverExportDownload('export_pdf', 'job-offers-report.pdf'); }

const chartOpts = { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom',labels:{boxWidth:10,font:{size:11,family:'Poppins'}}}} };

new Chart(document.getElementById('applicationsTrendChart'),{
    type:'line',
    data:{labels:<?= json_encode(array_keys($applicationTrend),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Applications',data:<?= json_encode(array_values($applicationTrend),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,borderColor:'#4f46e5',backgroundColor:'rgba(79,70,229,.15)',tension:.4,fill:true,pointRadius:3}]},
    options:chartOpts
});
new Chart(document.getElementById('statusDonutChart'),{
    type:'doughnut',
    data:{labels:<?= json_encode(array_map('ucfirst',array_keys($statusDistribution)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{data:<?= json_encode(array_values($statusDistribution),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,backgroundColor:['#f59e0b','#10b981','#ef4444','#6366f1'],borderWidth:0}]},
    options:chartOpts
});
new Chart(document.getElementById('conversionGaugeChart'),{
    type:'doughnut',
    data:{labels:['Converted','Remaining'],datasets:[{data:[<?= json_encode($conversionRate) ?>,<?= json_encode(max(0,100-$conversionRate)) ?>],backgroundColor:['#06b6d4','#e5e7eb'],borderWidth:0,borderRadius:4}]},
    options:{...chartOpts,cutout:'74%'}
});
new Chart(document.getElementById('skillsBarChart'),{
    type:'bar',
    data:{labels:<?= json_encode(array_map(static fn(string $s):string=>ucfirst($s),array_keys($topSkills)),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Offers',data:<?= json_encode(array_values($topSkills),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,backgroundColor:'#6366f1',borderRadius:6}]},
    options:{...chartOpts,indexAxis:'y'}
});
new Chart(document.getElementById('locationBarChart'),{
    type:'bar',
    data:{labels:<?= json_encode(array_keys($topLocations),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Jobs',data:<?= json_encode(array_values($topLocations),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,backgroundColor:'#22c55e',borderRadius:6}]},
    options:{...chartOpts,indexAxis:'y'}
});
new Chart(document.getElementById('hireTrendChart'),{
    type:'line',
    data:{labels:<?= json_encode(array_keys($avgHireTrend),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,datasets:[{label:'Days',data:<?= json_encode(array_values($avgHireTrend),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,borderColor:'#f97316',backgroundColor:'rgba(249,115,22,.15)',tension:.4,fill:true,pointRadius:3}]},
    options:chartOpts
});

async function analyzeSalaryBo(){
    const title=document.getElementById('createOfferTitle').value;
    const desc=document.querySelector('#createOfferForm textarea[name="description"]').value;
    if(!title||!desc){ Swal.fire('Error','Please fill title and description first','error'); return; }
    Swal.fire({title:'AI Analyzing…',didOpen:()=>Swal.showLoading(),allowOutsideClick:false});
    const fd=new FormData(); fd.append('action','ai_analyze_salary'); fd.append('title',title); fd.append('description',desc); fd.append('csrf_token','<?= $csrfToken ?>');
    try {
        const data=await fetch('JobOffer.php',{method:'POST',body:fd}).then(r=>r.json());
        Swal.close();
        if(data.success){
            const res=await Swal.fire({title:'Salary Suggestion',html:`<b style="font-size:1.4rem;">${data.salary}</b><br><small style="color:#6b7693;">${data.explanation||''}</small>`,icon:'info',showCancelButton:true,confirmButtonText:'Apply'});
            if(res.isConfirmed) document.getElementById('create_salary_bo').value=data.salary;
        } else { Swal.fire('Error',data.error||'No suggestion returned.','warning'); }
    } catch(e){ Swal.fire('Error','AI analysis failed: '+e.message,'error'); }
}

function smartMatch(offerId){
    Swal.fire({title:'Finding Matches…',text:'AI is ranking candidates…',didOpen:()=>Swal.showLoading(),allowOutsideClick:false});
    const fd=new FormData(); fd.append('action','smart_match'); fd.append('offer_id',offerId); fd.append('csrf_token','<?= $csrfToken ?>');
    fetch('JobOffer.php',{method:'POST',body:fd}).then(r=>r.json()).then(data=>{
        if(data.success){
            let html='<div style="text-align:left;max-height:400px;overflow-y:auto;">';
            data.matches.forEach(m=>{html+=`<div style="padding:10px;border-bottom:1px solid #eee;"><strong>Match: ${m.match_percentage}%</strong> (User ID: ${m.user_id})<br><small>${m.reason}</small></div>`;});
            html+='</div>';
            Swal.fire({title:'Top AI Matches',html,icon:'info'});
        } else { Swal.fire('Error',data.error||'Failed to match','error'); }
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