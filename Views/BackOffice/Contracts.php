<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ContractController.php');
include_once(__DIR__ . '/../../config.php');

if (!UserController::isAuthenticated() || !UserController::isAdmin()) {
    header('Location: ../FrontOffice/auth.php?mode=login');
    exit;
}

$pdo = config::getConnexion();
$controller = new ContractController($pdo);

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
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || (isset($_POST['action']) && str_contains($_POST['action'], 'export'));
        try {
            if ($action === 'create_contract') {
                $payload = [
                    'job_offer_id' => (int) ($_POST['offer_id'] ?? 0),
                    'client_id' => (int) ($_POST['client_id'] ?? 0),
                    'freelancer_id' => (int) ($_POST['freelancer_id'] ?? 0),
                    'terms' => $clean($_POST['terms'] ?? ''),
                    'amount' => max(0, (float) ($_POST['amount'] ?? 0)),
                    'signed_at' => $controller->parseDateTimeLocal($clean($_POST['signed_at'] ?? '')),
                    'starts_at' => $controller->parseDateTimeLocal($clean($_POST['starts_at'] ?? '')),
                    'ends_at' => $controller->parseDateTimeLocal($clean($_POST['ends_at'] ?? '')),
                    'created_by_client_id' => (int) ($_POST['client_id'] ?? 0),
                    'status' => strtolower($clean($_POST['status'] ?? 'draft')),
                    'client_signature' => $clean($_POST['client_signature'] ?? ''),
                    'payment_details' => 'Payment amount: ' . max(0, (float) ($_POST['amount'] ?? 0)) . ' TND',
                ];
                if ($payload['client_id'] <= 0 || $payload['freelancer_id'] <= 0 || $payload['job_offer_id'] <= 0) {
                    throw new RuntimeException('Offer, client, and freelancer are required.');
                }
                if (!in_array($payload['status'], ['draft', 'active', 'signed', 'expired', 'cancelled'], true)) {
                    $payload['status'] = 'draft';
                }
                $contractId = $controller->create($payload);
                $_SESSION['contract_notice_front'] = [
                    'type' => 'success',
                    'message' => 'Contract created. Add / Manage Rules to continue.',
                ];
                header('Location: ../FrontOffice/contracts.php?contract=' . $contractId . '&open_rules=1#contract-' . $contractId);
                exit;
            }

            if ($action === 'update_contract') {
                $contractId = (int) ($_POST['contract_id'] ?? 0);
                $status = strtolower($clean($_POST['status'] ?? 'draft'));
                if (!in_array($status, ['draft', 'active', 'signed', 'expired', 'cancelled'], true)) {
                    $status = 'draft';
                }
                $controller->updateStatus($contractId, $status);
                $notice = ['type' => 'success', 'message' => 'Contract updated.'];
            }

            if ($action === 'delete_contract') {
                $contractId = (int) ($_POST['contract_id'] ?? 0);
                $controller->deleteById($contractId);
                $notice = ['type' => 'success', 'message' => 'Contract deleted.'];
            }
            if ($action === 'save_rules') {
                $contractId = (int) ($_POST['contract_id'] ?? 0);
                if ($contractId > 0) {
                    $controller->getRuleController()->replaceForContract($contractId, $_POST);
                    $notice = ['type' => 'success', 'message' => 'Rules saved successfully.'];
                }
            }
            if ($action === 'ai_analyze_contract') {
                header('Content-Type: application/json');
                $contractId = (int) ($_POST['contract_id'] ?? 0);
                $userId = (int) (UserController::currentUser()['id'] ?? 0);
                try {
                    $result = $controller->analyzeWithAI($contractId, $userId);
                    echo json_encode(array_merge(['success' => true], $result));
                } catch (Throwable $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            }

            if ($action === 'export_contract_pdf') {
                $contractId = (int) ($_POST['contract_id'] ?? 0);
                try {
                    $pdfPath = $controller->generateProfessionalPDF($contractId);
                    if (!is_file($pdfPath)) {
                        throw new RuntimeException('PDF file was not created.');
                    }
                    while (ob_get_level() > 0) { ob_end_clean(); }
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="contract-' . $contractId . '.pdf"');
                    header('Content-Length: ' . (string) filesize($pdfPath));
                    header('Cache-Control: no-cache, must-revalidate');
                    header('Pragma: no-cache');
                    readfile($pdfPath);
                    @unlink($pdfPath);
                } catch (Throwable $e) {
                    if (!headers_sent()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    }
                }
                exit;
            }

            if ($action === 'export_csv') {
                $allRows = $controller->listBackofficeRows();
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="contracts-export-' . date('Y-m-d') . '.csv"');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                echo "\xEF\xBB\xBF"; // UTF-8 BOM
                $output = fopen('php://output', 'w');
                fputcsv($output, ['ID', 'Offer', 'Client', 'Freelancer', 'Status', 'Amount (TND)', 'Starts At', 'Ends At', 'Created At']);
                foreach ($allRows as $csvRow) {
                    fputcsv($output, [
                        $csvRow['id'] ?? '',
                        $csvRow['offer_title'] ?? '',
                        trim(($csvRow['client_first'] ?? '') . ' ' . ($csvRow['client_last'] ?? '')),
                        trim(($csvRow['freelancer_first'] ?? '') . ' ' . ($csvRow['freelancer_last'] ?? '')),
                        $csvRow['status'] ?? '',
                        number_format((float)($csvRow['amount'] ?? 0), 2),
                        $csvRow['starts_at'] ?? '',
                        $csvRow['ends_at'] ?? '',
                        $csvRow['created_at'] ?? ''
                    ]);
                }
                fclose($output);
                exit;
            }

            if ($action === 'export_pdf') {
                $allRows = $controller->listBackofficeRows();
                $stats = $controller->buildBackofficeStats($allRows);
                
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
                    .status-signed { background: #ecfdf5; color: #059669; }
                    .status-active { background: #eff6ff; color: #2563eb; }
                    .status-draft { background: #f8fafc; color: #64748b; }
                    .footer { margin-top: 30px; text-align: center; color: #94a3b8; font-size: 9px; border-top: 1px solid #f1f5f9; padding-top: 15px; }
                </style></head><body>
                    <div class="header">
                        <h1>Contracts Intelligence Report</h1>
                        <p>Diversity.is Official Backoffice Export • Generated on ' . date('F j, Y, H:i') . '</p>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">Total Volume</div>
                            <div class="stat-value">' . count($allRows) . ' Contracts</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Total Valuation</div>
                            <div class="stat-value">' . number_format((float)($stats['total_amount'] ?? 0), 2) . ' TND</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">Active Deployments</div>
                            <div class="stat-value">' . ($stats['active'] ?? 0) . ' Active</div>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Contract / Offer</th>
                                <th>Stakeholders</th>
                                <th>Status</th>
                                <th>Investment</th>
                                <th>Lifecycle</th>
                            </tr>
                        </thead>
                        <tbody>';
                foreach ($allRows as $row) {
                    $cName = trim(($row['client_first'] ?? '') . ' ' . ($row['client_last'] ?? ''));
                    $fName = trim(($row['freelancer_first'] ?? '') . ' ' . ($row['freelancer_last'] ?? ''));
                    $status = strtolower($row['status'] ?? 'draft');
                    $html .= '<tr>
                        <td><strong>#' . ($row['id'] ?? '') . '</strong></td>
                        <td>' . htmlspecialchars($row['offer_title'] ?? 'Generic Contract') . '</td>
                        <td>' . htmlspecialchars($cName) . '<br><span style="color:#64748b">→ ' . htmlspecialchars($fName) . '</span></td>
                        <td><span class="status status-' . $status . '">' . strtoupper($status) . '</span></td>
                        <td style="font-weight:700">' . number_format((float)($row['amount'] ?? 0), 2) . ' TND</td>
                        <td>' . ($row['starts_at'] ? date('M j, Y', strtotime($row['starts_at'])) : 'N/A') . '</td>
                    </tr>';
                }
                $html .= '</tbody></table>
                    <div class="footer">
                        This document contains confidential business intelligence. Generated by Diversity.is Admin Panel.
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
                    header('Content-Disposition: attachment; filename="contracts-global-report-' . date('Y-m-d') . '.pdf"');
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
$offers = $controller->listOffers();
$rows = $controller->listBackofficeRows();
$stats = $controller->buildBackofficeStats($rows);
$fromDate = trim((string)($_GET['from'] ?? ''));
$toDate = trim((string)($_GET['to'] ?? ''));
$globalSearch = strtolower(trim((string)($_GET['q'] ?? '')));
$inDateRange = static function (?string $value) use ($fromDate, $toDate): bool {
    if ($value === null || trim($value) === '') {
        return true;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return true;
    }
    if ($fromDate !== '') {
        $fromTs = strtotime($fromDate . ' 00:00:00');
        if ($fromTs !== false && $ts < $fromTs) {
            return false;
        }
    }
    if ($toDate !== '') {
        $toTs = strtotime($toDate . ' 23:59:59');
        if ($toTs !== false && $ts > $toTs) {
            return false;
        }
    }
    return true;
};
$filteredRows = array_values(array_filter($rows, static function (array $row) use ($globalSearch, $inDateRange): bool {
    if (!$inDateRange((string)($row['created_at'] ?? ''))) {
        return false;
    }
    if ($globalSearch === '') {
        return true;
    }
    return str_contains(strtolower((string)json_encode($row)), $globalSearch);
}));

$sessionUser = UserController::currentUser() ?? [];
$displayName = trim((string) (($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? '')));
$displayName = $displayName !== '' ? $displayName : 'System Admin';
$displayEmail = (string) ($sessionUser['email'] ?? 'admin@diversity.is');
$initials = strtoupper(substr((string) ($sessionUser['first_name'] ?? 'A'), 0, 1) . substr((string) ($sessionUser['last_name'] ?? 'D'), 0, 1));

$navItems = [
    ['label' => 'Dashboard', 'href' => 'dashboardUser.php'],
    ['label' => 'Job Offers', 'href' => 'JobOffer.php'],
    ['label' => 'Projects', 'href' => 'Projects.php'],
    ['label' => 'Contracts', 'href' => 'Contracts.php', 'active' => true],
];
$totalContractValue = 0.0;
$statusDistribution = [];
$lifecycleByMonth = [];
$renewalCalendar = [];
$paymentProgress = ['paid' => 0, 'pending' => 0];
$riskDistribution = ['0-20' => 0, '21-40' => 0, '41-60' => 0, '61-80' => 0, '81-100' => 0];
foreach ($filteredRows as $contractRow) {
    $totalContractValue += (float)($contractRow['amount'] ?? 0);
    $status = strtolower((string)($contractRow['status'] ?? 'draft'));
    $statusDistribution[$status] = ($statusDistribution[$status] ?? 0) + 1;
    $startsAt = (string)($contractRow['starts_at'] ?? '');
    $endsAt = (string)($contractRow['ends_at'] ?? '');
    if ($startsAt !== '' && $endsAt !== '' && strtotime($startsAt) !== false && strtotime($endsAt) !== false) {
        $month = date('Y-m', strtotime($endsAt));
        $days = max(0, (int)floor((strtotime($endsAt) - strtotime($startsAt)) / 86400));
        if (!isset($lifecycleByMonth[$month])) {
            $lifecycleByMonth[$month] = ['sum' => 0, 'count' => 0];
        }
        $lifecycleByMonth[$month]['sum'] += $days;
        $lifecycleByMonth[$month]['count']++;
    }
    if ($endsAt !== '' && strtotime($endsAt) !== false) {
        $renewalCalendar[date('Y-m', strtotime($endsAt))] = ($renewalCalendar[date('Y-m', strtotime($endsAt))] ?? 0) + 1;
    }
    if (in_array($status, ['signed', 'finalized'], true)) {
        $paymentProgress['paid']++;
    } else {
        $paymentProgress['pending']++;
    }
    $analysis = $controller->latestAnalysisByContractId((int)($contractRow['id'] ?? 0));
    if (is_array($analysis) && isset($analysis['risk_score'])) {
        $risk = max(0, min(100, (int)$analysis['risk_score']));
        if ($risk <= 20) {
            $riskDistribution['0-20']++;
        } elseif ($risk <= 40) {
            $riskDistribution['21-40']++;
        } elseif ($risk <= 60) {
            $riskDistribution['41-60']++;
        } elseif ($risk <= 80) {
            $riskDistribution['61-80']++;
        } else {
            $riskDistribution['81-100']++;
        }
    }
}
ksort($renewalCalendar);
ksort($lifecycleByMonth);
$lifecycleAverage = [];
foreach ($lifecycleByMonth as $month => $bucket) {
    $lifecycleAverage[$month] = $bucket['count'] > 0 ? round($bucket['sum'] / $bucket['count'], 1) : 0;
}
?>
 <!DOCTYPE html>
 <html lang="en">
 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice · Contracts</title>
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
        }
        .app-container { min-height: 100vh; background: transparent; }
        .sidebar {
            background: linear-gradient(180deg, #f7f8ff 0%, #f1f4ff 100%);
            border-right: 1px solid #dbe3ff;
            box-shadow: 8px 0 24px rgba(91, 104, 178, 0.08);
        }
        .main-content { background: transparent; }
        .top-header {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 28px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 40px rgba(66, 84, 160, 0.05);
        }
        .page-title h1 { margin:0; font-size:1.6rem; font-weight:800; color:var(--text-main); letter-spacing:-0.5px; }
        .page-title p { margin:5px 0 0; color:var(--text-muted); font-size:.9rem; }
        .bento-grid { display: grid; gap: 1.5rem; grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .card {
            background: #ffffff;
            border: 1px solid var(--border-card);
            border-radius: var(--radius-card);
            padding: 1.5rem;
            box-shadow: var(--shadow-card);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        .card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); }
        .span-3 { grid-column: span 3; }
        .span-4 { grid-column: span 4; }
        .span-6 { grid-column: span 6; }
        .span-12 { grid-column: span 12; }
        @media (max-width: 1024px) {
            .span-3, .span-4, .span-6 { grid-column: span 6; }
        }
        @media (max-width: 768px) {
            .span-3, .span-4, .span-6, .span-12 { grid-column: span 12; }
        }
        .kpi-card { display:flex; align-items:center; justify-content:space-between; gap:1rem; min-height:126px; }
        .kpi-h { font-size:.88rem; color:var(--text-muted); font-weight:600; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px; }
        .kpi-v { font-size:1.85rem; font-weight:800; color:var(--text-main); line-height:1; }
        .icon-box { width:54px; height:54px; border-radius:16px; display:flex; align-items:center; justify-content:center; }
        .icon-blue { background:rgba(59,130,246,.12); color:#3b82f6; }
        .icon-indigo { background:rgba(79,70,229,.12); color:#4f46e5; }
        .icon-purple { background:rgba(139,92,246,.12); color:#8b5cf6; }
        .section-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:1.5rem; }
        .section-head h2 { font-size:1.05rem; font-weight:700; color:var(--text-main); margin:0; }
        .elegant-table { width:100%; border-collapse:separate; border-spacing:0 8px; margin-top:-8px; }
        .elegant-table thead th { 
            background:#f8faff; color:var(--text-muted); font-weight:700; font-size:.78rem; 
            text-transform:uppercase; letter-spacing:0.5px; padding:1.1rem 1rem; border:none; 
            text-align:left;
        }
        .elegant-table thead th:first-child { border-radius:12px 0 0 12px; }
        .elegant-table thead th:last-child { border-radius:0 12px 12px 0; }
        .elegant-table tbody tr { 
            background:#fff; transition:all 0.2s ease;
            box-shadow:0 2px 8px rgba(66,84,160,0.03);
        }
        .elegant-table tbody tr:hover { 
            background:#fbfcff; transform:scale(1.002) translateY(-2px);
            box-shadow:0 8px 20px rgba(66,84,160,0.08);
        }
        .elegant-table td { padding:1.1rem 1rem; border-top:1px solid #f1f4ff; border-bottom:1px solid #f1f4ff; color:var(--text-main); font-size:.86rem; }
        .elegant-table td:first-child { border-left:1px solid #f1f4ff; border-radius:12px 0 0 12px; }
        .elegant-table td:last-child { border-right:1px solid #f1f4ff; border-radius:0 12px 12px 0; }
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
            border-radius:10px; padding:.45rem .85rem; cursor:pointer; font-weight:700; 
            font-size:.74rem; font-family:var(--font); transition:all .2s; white-space:nowrap; 
        }
        .btn-mini:hover { background:var(--accent); color:#fff; transform:translateY(-1px); }
        .btn-mini.delete { background:rgba(225,29,72,0.08); color:#e11d48; }
        .btn-mini.delete:hover { background:#e11d48; color:#fff; }
        .btn-mini.view { background:rgba(100,116,139,0.08); color:#64748b; }
        .btn-mini.view:hover { background:#64748b; color:#fff; }
        .btn-ai { background:linear-gradient(135deg, #8b5cf6, #ec4899); color:#fff; }
        .btn-ai:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(139,92,246,0.3); }
        .btn-success { background:rgba(16,185,129,0.1); color:#059669; }
        .btn-success:hover { background:#10b981; color:#fff; }
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
        .btn-mini.view {
            border-color: #a5b4fc;
            color: #2b3959;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        }
        .btn-mini.view:hover { box-shadow: 0 12px 24px rgba(79, 70, 229, 0.16); }
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
        .bo-signature-wrap { border:1px dashed #d2dbef; border-radius:12px; padding:10px; background:#fff; }
        .bo-signature-canvas { width:100%; height:150px; background:#fff; border:1px solid #d6def9; border-radius:10px; display:block; touch-action:none; cursor:crosshair; }
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
            <a href="JobOffer.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                Job Offers
            </a>
            <a href="Projects.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                Projects
            </a>
            <a href="Contracts.php" class="nav-item active">
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
                <h1>Contracts Center</h1>
                <p>Strategic oversight and lifecycle management for all active agreements.</p>
            </div>
            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <div class="search-bar" style="margin:0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" x2="16.65" y1="21" y2="16.65"></line></svg>
                    <input type="text" id="contractSearch" value="<?= htmlspecialchars((string)($_GET['q'] ?? '')) ?>" placeholder="Search ID, offer, stakeholders..." aria-label="Search contracts">
                </div>
            </div>
        </header>

        <?php if ($notice['message'] !== ''): ?><div class="notice <?= htmlspecialchars($notice['type']) ?>"><?= htmlspecialchars($notice['message']) ?></div><?php endif; ?>

        <div class="module-actions" style="margin-bottom: 2rem;">
            <div style="display:flex; gap:12px; align-items:center;">
                <button class="btn-export" type="button" id="btnExportCsv" onclick="exportContractsCsv()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Export CSV
                </button>
                <button class="btn-export primary" type="button" id="btnExportPdf" onclick="exportContractsPdf()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Global Report (PDF)
                </button>
            </div>
            <button class="btn-primary" type="button" onclick="openCreateContractModal()" style="margin-left:auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="12" x2="12" y1="5" y2="19"></line><line x1="5" x2="19" y1="12" y2="12"></line></svg>
                Deploy New Contract
            </button>
        </div>

        <div class="bento-grid">
            <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.05s;">
                <div><div class="kpi-h">Total contracts</div><div class="kpi-v"><?= count($filteredRows) ?></div></div>
                <div class="icon-box icon-blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg></div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.08s;">
                <div><div class="kpi-h">Active contracts</div><div class="kpi-v"><?= count(array_filter($filteredRows, static fn(array $r): bool => in_array((string)($r['status'] ?? ''), ['active', 'signed', 'finalized'], true))) ?></div></div>
                <div class="icon-box icon-indigo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
            </div>
            <div class="card span-4 kpi-card animate-enter" style="animation-delay: 0.11s;">
                <div><div class="kpi-h">Draft</div><div class="kpi-v"><?= count(array_filter($filteredRows, static fn(array $r): bool => (string)($r['status'] ?? '') === 'draft')) ?></div></div>
                <div class="icon-box icon-purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="24" height="24" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg></div>
            </div>
            <section class="card span-3 animate-enter" style="animation-delay:.13s;">
                <div class="section-head"><h2>Total value</h2></div>
                <div style="font-size:1.4rem;font-weight:700;color:#4f46e5;"><?= number_format($totalContractValue, 2) ?> TND</div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.14s;">
                <div class="section-head"><h2>Status distribution</h2></div>
                <div style="height:200px;position:relative;"><canvas id="contractStatusChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.15s;">
                <div class="section-head"><h2>Payment progress</h2></div>
                <div style="height:200px;position:relative;"><canvas id="paymentProgressChart"></canvas></div>
            </section>
            <section class="card span-3 animate-enter" style="animation-delay:.16s;">
                <div class="section-head"><h2>Risk distribution</h2></div>
                <div style="height:200px;position:relative;"><canvas id="riskDistributionChart"></canvas></div>
            </section>
            <section class="card span-6 animate-enter" style="animation-delay:.17s;">
                <div class="section-head"><h2>Lifecycle duration trend</h2></div>
                <div style="height:220px;position:relative;"><canvas id="lifecycleTrendChart"></canvas></div>
            </section>
            <section class="card span-6 animate-enter" style="animation-delay:.175s;">
                <div class="section-head"><h2>Renewal calendar</h2></div>
                <div style="height:220px;position:relative;"><canvas id="renewalCalendarChart"></canvas></div>
            </section>

            <section class="card span-12 animate-enter" style="animation-delay: 0.18s;" id="contracts-list">
                        <div class="section-head"><h2>Contracts list</h2><span style="font-size:0.8rem;color:var(--b-text-muted);"><?= count($filteredRows) ?> rows</span></div>
                        <div style="overflow-x: auto;">
                    <table class="elegant-table">
                        <thead><tr><th>ID</th><th>Offer</th><th>Client / Freelancer</th><th>Status</th><th>Amount</th><th>Rules</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($filteredRows as $row): ?>
                            <tr>
                                <td><?= (int) ($row['id'] ?? 0) ?></td>
                                <td><strong><?= htmlspecialchars((string) ($row['offer_title'] ?? 'Offer')) ?></strong></td>
                                <td><?= htmlspecialchars(trim((string) ($row['client_first'] ?? '') . ' ' . (string) ($row['client_last'] ?? ''))) ?> / <?= htmlspecialchars(trim((string) ($row['freelancer_first'] ?? '') . ' ' . (string) ($row['freelancer_last'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars((string) ($row['status'] ?? 'draft')) ?></td>
                                <td><?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                                <td style="max-width:260px;">
                                    <div style="font-size:.76rem;color:#5e6b8f;line-height:1.45;"><?= htmlspecialchars(mb_strimwidth(trim((string) ($row['rules_terms'] ?? '')), 0, 80, '…')) ?></div>
                                    <div style="font-size:.68rem;color:#6b7693;"><?= htmlspecialchars((string) ($row['rules_deadline'] ?? '')) ?></div>
                                </td>
                                <td style="text-align:right;">
                                    <div class="action-stack" style="justify-content:flex-end;">
                                        <button type="button" class="btn-mini view"
                                                onclick='openContractViewModal(<?= (int) ($row["id"] ?? 0) ?>, <?= json_encode((string) ($row["offer_title"] ?? "Offer"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode(trim((string) ($row["client_first"] ?? '') . ' ' . (string) ($row["client_last"] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode(trim((string) ($row["freelancer_first"] ?? '') . ' ' . (string) ($row["freelancer_last"] ?? '')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string) ($row["status"] ?? "draft"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode(number_format((float) ($row["amount"] ?? 0), 2), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string) ($row["terms"] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string) ($row["rules_terms"] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string) ($row["rules_deadline"] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string) ($row["rules_payment_terms"] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, <?= json_encode((string) ($row["rules_penalties"] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>View</button>
                                        <button class="btn-mini btn-ai" type="button" onclick="aiAnalyzeContract(<?= (int) ($row['id'] ?? 0) ?>)">Analyze</button>
                                        <button class="btn-mini" type="button" style="background:#f3f4f6;color:#374151;" onclick='openRulesModal(<?= (int) ($row["id"] ?? 0) ?>, <?= json_encode($row["rules"] ?? [], JSON_UNESCAPED_UNICODE) ?>)'>Rules</button>
                                        <form method="post" class="action-stack">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="update_contract"><input type="hidden" name="contract_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                            <select name="status" class="action-select"><?php foreach (['draft', 'active', 'signed', 'expired', 'cancelled'] as $status): ?><option value="<?= $status ?>" <?= (string) ($row['status'] ?? '') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select>
                                            <button class="btn-mini" type="submit">Save</button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this contract?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="delete_contract"><input type="hidden" name="contract_id" value="<?= (int) ($row['id'] ?? 0) ?>"><button class="btn-mini delete" type="submit">Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$filteredRows): ?><tr><td colspan="7">No contracts yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="bo-modal-backdrop" id="createContractModal" onclick="if(event.target===this) closeCreateContractModal()">
            <div class="bo-modal">
                <div class="bo-modal-head">
                    <h3>Create Contract</h3>
                    <button type="button" class="bo-modal-close" onclick="closeCreateContractModal()" aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form id="createContractForm" method="post" class="form-panel" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="action" value="create_contract">
                    <div class="field-grid">
                        <div><label>Status</label><select name="status"><option value="draft">Draft</option><option value="active">Active</option><option value="signed">Signed</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option></select></div>
                        <div><label>Amount</label><input type="number" min="0" step="0.01" name="amount" value="0"></div>
                        <div><label>Offer</label><select name="offer_id" required><option value="">Select offer</option><?php foreach ($offers as $offer): ?><option value="<?= (int) ($offer['id'] ?? 0) ?>">#<?= (int) ($offer['id'] ?? 0) ?> · <?= htmlspecialchars((string) ($offer['title'] ?? 'Offer')) ?></option><?php endforeach; ?></select></div>
                        <div><label>Client</label><select name="client_id" required><option value="">Select client</option><?php foreach ($users as $user): ?><option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($controller->userLabel($user)) ?></option><?php endforeach; ?></select></div>
                        <div><label>Freelancer</label><select name="freelancer_id" required><option value="">Select freelancer</option><?php foreach ($users as $user): ?><option value="<?= (int) ($user['id'] ?? 0) ?>"><?= htmlspecialchars($controller->userLabel($user)) ?></option><?php endforeach; ?></select></div>
                        <div><label>Signed at</label><input type="datetime-local" name="signed_at"></div>
                        <div><label>Start at</label><input type="datetime-local" name="starts_at"></div>
                        <div><label>End at</label><input type="datetime-local" name="ends_at"></div>
                        <div class="full"><label>Terms</label><textarea name="terms"></textarea></div>
                    </div>
                    <div class="bo-modal-actions">
                        <button type="button" class="bo-modal-btn ghost" onclick="closeCreateContractModal()">Cancel</button>
                        <button class="bo-modal-btn primary" type="submit">Create Contract</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="bo-modal-backdrop" id="viewContractModal" onclick="if(event.target===this) closeContractViewModal()">
            <div class="bo-modal" style="max-width:760px;">
                <div class="bo-modal-head">
                    <h3>Contract Details</h3>
                    <button type="button" class="bo-modal-close" onclick="closeContractViewModal()" aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <div class="form-panel">
                    <div class="field-grid">
                        <div class="full"><label>Offer</label><input type="text" id="viewContractOffer" readonly></div>
                        <div><label>Client</label><input type="text" id="viewContractClient" readonly></div>
                        <div><label>Freelancer</label><input type="text" id="viewContractFreelancer" readonly></div>
                        <div><label>Status</label><input type="text" id="viewContractStatus" readonly></div>
                        <div><label>Amount</label><input type="text" id="viewContractAmount" readonly></div>
                        <div class="full"><label>Contract Terms</label><textarea id="viewContractTerms" readonly></textarea></div>
                        <div class="full"><label>Rules Terms</label><textarea id="viewRulesTerms" readonly></textarea></div>
                        <div><label>Rules Deadline</label><input type="text" id="viewRulesDeadline" readonly></div>
                        <div class="full"><label>Payment Terms</label><textarea id="viewRulesPayment" readonly></textarea></div>
                        <div class="full"><label>Penalties</label><textarea id="viewRulesPenalties" readonly></textarea></div>
                    </div>
                </div>
                <div class="bo-modal-actions">
                    <button type="button" class="bo-modal-btn ghost" onclick="closeContractViewModal()">Close</button>
                </div>
            </div>
        </div>
        <div class="bo-modal-backdrop" id="rulesModal" onclick="if(event.target===this)closeRulesModal()">
            <div class="bo-modal" style="max-width:640px;">
                <div class="bo-modal-head">
                    <h3>Contract Rules</h3>
                    <button type="button" class="bo-modal-close" onclick="closeRulesModal()" aria-label="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>
                <form method="post" id="contractRulesForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_rules">
                    <input type="hidden" name="contract_id" id="rulesContractId">
                    <div id="rulesList" style="max-height:60vh;overflow-y:auto;padding-right:10px;margin-bottom:15px;"></div>
                    <button type="button" class="bo-modal-btn ghost" style="width:100%;margin-bottom:15px;" onclick="addRuleRow()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Add another rule
                    </button>
                    <div class="bo-modal-actions">
                        <button type="button" class="bo-modal-btn ghost" onclick="closeRulesModal()">Cancel</button>
                        <button type="submit" class="bo-modal-btn primary">Save Rules</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

    <script>
    function openCreateContractModal(){
        document.getElementById('createContractModal')?.classList.add('open');
    }
    function closeCreateContractModal(){
        document.getElementById('createContractModal')?.classList.remove('open');
    }
    function openContractViewModal(id, offer, client, freelancer, status, amount, terms, rulesTerms, rulesDeadline, rulesPayment, rulesPenalties){
        document.getElementById('viewContractOffer').value = offer || '';
        document.getElementById('viewContractClient').value = client || '';
        document.getElementById('viewContractFreelancer').value = freelancer || '';
        document.getElementById('viewContractStatus').value = status || '';
        document.getElementById('viewContractAmount').value = amount || '';
        document.getElementById('viewContractTerms').value = terms || '';
        document.getElementById('viewRulesTerms').value = rulesTerms || '';
        document.getElementById('viewRulesDeadline').value = rulesDeadline || '';
        document.getElementById('viewRulesPayment').value = rulesPayment || '';
        document.getElementById('viewRulesPenalties').value = rulesPenalties || '';
        document.getElementById('viewContractModal')?.classList.add('open');
    }

    function closeRulesModal() {
        document.getElementById('rulesModal')?.classList.remove('open');
    }

    function addRuleRow(rule = {}) {
        const list = document.getElementById('rulesList');
        if (!list) return;
        const index = list.querySelectorAll('.bo-dynamic-rule').length;
        const row = document.createElement('div');
        row.className = 'bo-dynamic-rule';
        row.style = 'border:1px solid #e5e7eb;border-radius:10px;padding:15px;background:#f9fafb;margin-bottom:12px;position:relative;';
        row.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <strong style="font-size:0.85rem;color:#111827;">Rule #${index + 1}</strong>
                <button type="button" class="btn-mini delete ct-rule-remove" style="padding:4px 8px;">Remove</button>
            </div>
            <div class="field-grid">
                <div class="full"><label>Title *</label><input type="text" name="rule_titles[]" value="${rule.title || ''}" required></div>
                <div><label>Type</label><input type="text" name="rule_types[]" value="${rule.rule_type || rule.type || 'scope'}" placeholder="e.g. deadline, payment..."></div>
                <div><label>Due Date</label><input type="date" name="rule_due_dates[]" value="${rule.due_date || ''}"></div>
                <div class="full"><label>Description *</label><textarea name="rule_descriptions[]" required>${rule.description || ''}</textarea></div>
                <div class="full"><label>Penalty</label><textarea name="rule_penalties[]">${rule.penalty || ''}</textarea></div>
            </div>
        `;
        row.querySelector('.ct-rule-remove').addEventListener('click', () => {
            row.remove();
            list.querySelectorAll('.bo-dynamic-rule').forEach((r, i) => {
                r.querySelector('strong').textContent = 'Rule #' + (i + 1);
            });
        });
        list.appendChild(row);
    }

    function openRulesModal(contractId, rules = []) {
        const modal = document.getElementById('rulesModal');
        const contractInput = document.getElementById('rulesContractId');
        const list = document.getElementById('rulesList');
        if (!modal || !contractInput || !list) return;
        contractInput.value = contractId;
        list.innerHTML = '';
        const existingRules = Array.isArray(rules) && rules.length > 0 ? rules : [{}];
        existingRules.forEach(r => addRuleRow(r));
        modal.classList.add('open');
    }
    function closeContractViewModal(){
        document.getElementById('viewContractModal')?.classList.remove('open');
    }
    const contractSearch = document.getElementById('contractSearch');
    const filterContractsTable = () => {
        const term = String(contractSearch?.value || '').trim().toLowerCase();
        document.querySelectorAll('#contracts-list tbody tr').forEach((row) => {
            row.style.display = !term || row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    };
    contractSearch?.addEventListener('input', filterContractsTable);

    function serverExportDownload(action, filenameDefault, mimeType) {
        Swal.fire({ title: 'Generating...', text: 'Preparing your file...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        const fd = new FormData();
        fd.append('action', action);
        fd.append('csrf_token', '<?= $csrfToken ?>');
        fetch('Contracts.php', { method: 'POST', body: fd })
            .then(r => {
                const ct = r.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return r.json().then(j => { throw new Error(j.error || 'Export failed.'); });
                }
                const disp = r.headers.get('content-disposition') || '';
                const match = disp.match(/filename="(.+?)"/);
                const filename = match ? match[1] : filenameDefault;
                return r.blob().then(blob => ({ blob, filename }));
            })
            .then(({ blob, filename }) => {
                const url = URL.createObjectURL(blob);
                const a = Object.assign(document.createElement('a'), { href: url, download: filename });
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                Swal.fire({ title: 'Downloaded!', icon: 'success', timer: 1500, showConfirmButton: false });
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
    }
    function exportContractsCsv() {
        serverExportDownload('export_csv', 'contracts-export.csv', 'text/csv');
    }
    function exportContractsPdf() {
        serverExportDownload('export_pdf', 'contracts-report.pdf', 'application/pdf');
    }
    const chartDefaults = { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { 
            legend: { 
                position: 'bottom',
                labels: { boxWidth: 12, padding: 15, font: { size: 11, family: 'Poppins' } }
            } 
        } 
    };
    new Chart(document.getElementById('contractStatusChart'), {
        type: 'doughnut',
        data: { labels: <?= json_encode(array_map(static fn(string $label): string => ucfirst($label), array_keys($statusDistribution)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, datasets: [{ data: <?= json_encode(array_values($statusDistribution), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, backgroundColor: ['#94a3b8','#4f46e5','#10b981','#f59e0b','#ef4444'] }] },
        options: chartDefaults
    });
    new Chart(document.getElementById('paymentProgressChart'), {
        type: 'doughnut',
        data: { labels: ['Paid', 'Pending'], datasets: [{ data: <?= json_encode(array_values($paymentProgress), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, backgroundColor: ['#22c55e','#f59e0b'] }] },
        options: chartDefaults
    });
    new Chart(document.getElementById('riskDistributionChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_keys($riskDistribution), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, datasets: [{ label: 'Contracts', data: <?= json_encode(array_values($riskDistribution), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, backgroundColor: '#ef4444' }] },
        options: chartDefaults
    });
    new Chart(document.getElementById('lifecycleTrendChart'), {
        type: 'line',
        data: { labels: <?= json_encode(array_keys($lifecycleAverage), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, datasets: [{ label: 'Days', data: <?= json_encode(array_values($lifecycleAverage), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,.2)', tension: .3, fill: true }] },
        options: chartDefaults
    });
    new Chart(document.getElementById('renewalCalendarChart'), {
        type: 'bar',
        data: { labels: <?= json_encode(array_keys($renewalCalendar), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, datasets: [{ label: 'Renewals', data: <?= json_encode(array_values($renewalCalendar), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, backgroundColor: '#06b6d4' }] },
        options: chartDefaults
    });

    function setupBackSignaturePad(canvas, hiddenInput, clearButton) {
        if (!canvas || !hiddenInput) return;
        const ctx = canvas.getContext('2d');
        let drawing = false;
        let hasStroke = false;
        let lastX = 0;
        let lastY = 0;

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();
            const snapshot = hasStroke ? canvas.toDataURL('image/png') : '';
            canvas.width = Math.max(1, Math.floor(rect.width * ratio));
            canvas.height = Math.max(1, Math.floor(rect.height * ratio));
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, rect.width, rect.height);
            ctx.lineWidth = 2.2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';
            if (snapshot) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, rect.width, rect.height);
                img.src = snapshot;
            }
        }

        function pointFromEvent(event) {
            const rect = canvas.getBoundingClientRect();
            const source = event.touches ? event.touches[0] : event;
            return { x: source.clientX - rect.left, y: source.clientY - rect.top };
        }

        function start(event) {
            event.preventDefault();
            const point = pointFromEvent(event);
            drawing = true;
            lastX = point.x;
            lastY = point.y;
        }

        function move(event) {
            if (!drawing) return;
            event.preventDefault();
            const point = pointFromEvent(event);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(point.x, point.y);
            ctx.stroke();
            lastX = point.x;
            lastY = point.y;
            hasStroke = true;
            hiddenInput.value = canvas.toDataURL('image/png');
        }

        function stop() { drawing = false; }
        function clear() {
            hasStroke = false;
            hiddenInput.value = '';
            resizeCanvas();
        }

        canvas.addEventListener('mousedown', start);
        canvas.addEventListener('mousemove', move);
        document.addEventListener('mouseup', stop);
        canvas.addEventListener('touchstart', start, { passive: false });
        canvas.addEventListener('touchmove', move, { passive: false });
        document.addEventListener('touchend', stop);
        clearButton?.addEventListener('click', clear);
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }

    setupBackSignaturePad(
        document.getElementById('backClientSignatureCanvas'),
        document.getElementById('back_client_signature'),
        document.getElementById('backClearSignature')
    );

        function aiAnalyzeContract(contractId) {
            Swal.fire({ title: 'AI Analyzing Contract...', text: 'Evaluating risk and terms...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
            const fd = new FormData();
            fd.append('action', 'ai_analyze_contract');
            fd.append('contract_id', contractId);
            fd.append('csrf_token', '<?= $csrfToken ?>');

            fetch('Contracts.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const score = data.risk_score || 0;
                        const color = score > 70 ? '#ef4444' : (score > 40 ? '#f59e0b' : '#10b981');
                        let html = `<div style="text-align:left;">
                            <div style="font-size:1.5rem;font-weight:800;color:${color};text-align:center;margin-bottom:15px;">Risk Score: ${score}/100</div>
                            <strong>Pros:</strong><p>${data.pros || 'N/A'}</p>
                            <strong>Cons:</strong><p>${data.cons || 'N/A'}</p>
                            <strong>Recommendation:</strong><p>${data.recommendation || 'N/A'}</p>
                        </div>`;
                        Swal.fire({ title: 'AI Contract Analysis', html: html, icon: 'info', width: '600px' });
                    } else {
                        Swal.fire('Error', data.error || 'Analysis failed', 'error');
                    }
                });
        }

        function exportContractPdf(contractId) {
            Swal.fire({ title: 'Generating PDF...', text: 'Creating contract document...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
            const fd = new FormData();
            fd.append('action', 'export_contract_pdf');
            fd.append('contract_id', contractId);
            fd.append('csrf_token', '<?= $csrfToken ?>');

            fetch('Contracts.php', { method: 'POST', body: fd })
                .then(r => {
                    const ct = r.headers.get('content-type') || '';
                    if (ct.includes('application/json')) {
                        return r.json().then(j => { throw new Error(j.error || 'Generation failed.'); });
                    }
                    const disp = r.headers.get('content-disposition') || '';
                    const match = disp.match(/filename="(.+?)"/);
                    const filename = match ? match[1] : 'Contract-' + contractId + '.pdf';
                    return r.blob().then(blob => ({ blob, filename }));
                })
                .then(({ blob, filename }) => {
                    const url = URL.createObjectURL(blob);
                    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    Swal.fire({ title: 'PDF Ready!', text: 'Download started.', icon: 'success', timer: 2000, showConfirmButton: false });
                })
                .catch(err => Swal.fire('Error', err.message, 'error'));
        }
    </script>

    <script src="../../assets/js/globe-explorer.js"></script>
    <script src="../../assets/js/mvc-inline-validation.js"></script>
    <script src="../../assets/js/user.js"></script>
    <script src="../../assets/js/skilluser.js"></script>
    <script src="../../assets/js/backoffice-dashboard.js"></script>
 </body>
 </html>
