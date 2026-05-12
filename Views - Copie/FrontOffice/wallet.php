<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/WalletController.php');

if (!UserController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in.';
  header('Location: auth.php');
  exit;
}

$sidebarUser      = UserController::currentUser() ?? [];
$currentUserId    = (int)($sidebarUser['id'] ?? 0);
$firstName        = trim((string) ($sidebarUser['first_name'] ?? 'Guest'));
$lastName         = trim((string) ($sidebarUser['last_name']  ?? 'User'));
$displayName      = trim($firstName . ' ' . $lastName) ?: 'Guest User';
$initials         = strtoupper(substr($firstName ?: 'G', 0, 1) . substr($lastName ?: 'U', 0, 1));
$displayEmail     = (string)($sidebarUser['email'] ?? '');
$isAdminSidebar   = strtolower($displayEmail) === 'admin@diversity.is';

$walletController = new WalletController();
$stats            = $walletController->getQuickStats($currentUserId);
$balance          = $stats['balance'];
$pendingBalance   = $stats['pending'];
$totalEarnings    = $stats['total_income'];
$totalSpent       = $stats['total_expense'];

// ── Handle AJAX wallet actions ──────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'wallet_action') {
    header('Content-Type: application/json');
    $jsonInput = json_decode((string) file_get_contents('php://input'), true);
    $act = $jsonInput['type'] ?? '';
    $amt = (float)($jsonInput['amount'] ?? 0);

    if ($act === 'deposit') {
        $ok = $walletController->addTransaction($currentUserId, 'deposit', $amt, "Instant Deposit", 'completed', 'deposit');
        echo json_encode(['success' => $ok, 'newBalance' => $ok ? $balance + $amt : $balance,
                          'message' => $ok ? "✓ $".number_format($amt,2)." added to your wallet." : "Transaction failed."]);
        exit;
    }
    if ($act === 'withdraw') {
        if ($amt < 10) { echo json_encode(['success' => false, 'message' => "Min withdrawal is \$10."]); exit; }
        if ($amt > $balance) { echo json_encode(['success' => false, 'message' => "Insufficient balance."]); exit; }
        $ok = $walletController->addTransaction($currentUserId, 'withdrawal', -$amt, "Withdrawal Request", 'pending', 'withdrawal');
        echo json_encode(['success' => $ok, 'newBalance' => $ok ? $balance - $amt : $balance,
                          'message' => $ok ? "✓ Withdrawal of $".number_format($amt,2)." queued." : "Withdrawal failed."]);
        exit;
    }
    if ($act === 'send') {
        $recipient = trim($jsonInput['recipient'] ?? '');
        $note      = trim($jsonInput['note'] ?? 'Money Transfer');
        if (!$recipient) { echo json_encode(['success'=>false,'message'=>"Recipient required."]); exit; }
        if ($amt < 1)    { echo json_encode(['success'=>false,'message'=>"Min transfer is \$1."]); exit; }
        if ($amt > $balance) { echo json_encode(['success'=>false,'message'=>"Insufficient balance."]); exit; }
        $desc = "Sent to $recipient" . ($note ? " – $note" : '');
        $ok = $walletController->addTransaction($currentUserId, 'transfer', -$amt, $desc, 'completed', 'transfer');
        echo json_encode(['success' => $ok, 'message' => $ok ? "✓ $".number_format($amt,2)." sent to $recipient." : "Transfer failed."]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// ── Load transactions ────────────────────────────────────────────────────────
$transactionsRaw  = $walletController->getTransactions($currentUserId, 50);
$transactions     = [];
foreach ($transactionsRaw as $t) {
    $type = $t['type'];
    $icon = 'arrow-down-right';
    $colorClass = 'income';

    if ($t['amount'] < 0) { $icon = 'arrow-up-right'; $colorClass = 'expense'; }
    if (str_contains($type, 'donation')) { $icon = 'gift'; $colorClass = 'donation'; }
    if (str_contains($type, 'transfer')) { $icon = 'send'; $colorClass = 'expense'; }

    $transactions[] = [
        'id'       => 'TXN-' . str_pad($t['id'], 5, '0', STR_PAD_LEFT),
        'rawId'    => $t['id'],
        'icon'     => $icon,
        'type'     => $colorClass,
        'title'    => $t['description'] ?: ucfirst(str_replace('_', ' ', $type)),
        'amount'   => (float)$t['amount'],
        'date'     => $t['created_at'],
        'dateStr'  => date('M d, Y', strtotime($t['created_at'])),
        'timeStr'  => date('g:i A', strtotime($t['created_at'])),
        'status'   => $t['status'],
        'category' => $t['source_module'] ?? 'wallet'
    ];
}

// ── Derived stats ────────────────────────────────────────────────────────────
$trendPercent = 0.0;
$trendUp      = true;
$totalSales   = 0.0;
$totalPending = $pendingBalance;

// Marketplace sub-total
try {
    $wc = new WalletController();
    $w  = $wc->getWallet($currentUserId);
    if ($w) {
        $q = (config::getConnexion())->prepare(
            "SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions
              WHERE wallet_id = :wid AND type = 'marketplace_sale' AND status = 'completed'"
        );
        $q->execute(['wid' => $w['id']]);
        $totalSales = (float)($q->fetchColumn() ?? 0);
    }
} catch (Throwable $e) {}

// ── Monthly chart data (last 6 months from DB or fallback) ──────────────────
$monthlyIncome  = [0,0,0,0,0,0];
$monthlyExpense = [0,0,0,0,0,0];
$monthLabels    = [];
for ($i = 5; $i >= 0; $i--) {
    $monthLabels[] = date('M', strtotime("-$i months"));
}
foreach ($transactions as $t) {
    $mIdx = null;
    for ($i = 0; $i < 6; $i++) {
        if (date('M', strtotime($t['date'])) === $monthLabels[$i] &&
            date('Y', strtotime($t['date'])) === date('Y', strtotime("-".(5-$i)." months"))) {
            $mIdx = $i; break;
        }
    }
    if ($mIdx !== null) {
        if ($t['amount'] > 0) $monthlyIncome[$mIdx]  += $t['amount'];
        else                  $monthlyExpense[$mIdx]  += abs($t['amount']);
    }
}

// ── Balance trend over last 7 days ──────────────────────────────────────────
$balanceTrend = [];
$runningBal   = $balance;
$dayLabels    = [];
for ($d = 6; $d >= 0; $d--) {
    $dayLabels[]   = date('D', strtotime("-$d days"));
    $balanceTrend[] = max(0, $runningBal - ($d * ($totalSpent / 30)));
}
$balanceTrend[6] = $balance; // today is exact

// ── Source breakdown ─────────────────────────────────────────────────────────
$srcMarketplace = 0; $srcDonations = 0; $srcOther = 0;
foreach ($transactions as $t) {
    if ($t['amount'] <= 0) continue;
    if ($t['category'] === 'marketplace' || $t['type'] === 'income') $srcMarketplace += $t['amount'];
    elseif ($t['type'] === 'donation') $srcDonations += $t['amount'];
    else $srcOther += $t['amount'];
}
$srcTotal = $srcMarketplace + $srcDonations + $srcOther ?: 1;

// ── Average transaction value ────────────────────────────────────────────────
$completedTxns = array_filter($transactions, fn($t) => $t['status'] === 'completed');
$avgTxnValue   = count($completedTxns) > 0
    ? array_sum(array_map(fn($t) => abs($t['amount']), $completedTxns)) / count($completedTxns)
    : 0;

// ── Pending vs completed ratio ───────────────────────────────────────────────
$pendingCount   = count(array_filter($transactions, fn($t) => $t['status'] === 'pending'));
$completedCount = count($completedTxns);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>Wallet | Diversity Collaboration</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>(function(){
    try { var t=localStorage.getItem('app_theme'); document.documentElement.setAttribute('data-theme',t==='dark'?'dark':'light'); }
    catch(e){ document.documentElement.setAttribute('data-theme','light'); }
  })();</script>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <script src="https://unpkg.com/lucide@latest"></script>

  <!-- Chart.js + jsPDF + autoTable -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

  <style>
    /* ══════════════════════════════════════════════
       CSS CUSTOM PROPS
    ═══════════════════════════════════════════════ */
    :root {
      --w-bg:        var(--color-bg);
      --w-surf:      var(--color-surface);
      --w-surf-alt:  var(--color-surface-2);
      --w-border:    var(--color-border);
      --w-text:      var(--color-text-primary);
      --w-muted:     var(--color-text-secondary);
      --w-heading:   var(--color-text-primary);
      --w-accent:    var(--color-accent);
      --w-accent2:   var(--color-accent-purple);
      --w-green:     var(--color-accent-emerald);
      --w-red:       var(--color-accent-rose);
      --w-amber:     var(--color-accent-amber);
      --w-radius:    var(--radius-lg);
      --w-radius-sm: var(--radius-md);
      --w-shadow:    0 4px 24px rgba(31,38,135,0.07);
      --w-shadow-lg: 0 12px 48px rgba(31,38,135,0.13);
      --w-glow:      rgba(99,102,241,0.18);
      font-family: 'Poppins', sans-serif;
    }

    /* ── Page layout ── */
    .profile-page-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: var(--sp-6);
      align-items: start;
      padding-bottom: var(--sp-12);
    }
    @media (max-width: 1100px) { .profile-page-layout { grid-template-columns: 1fr; } }

    /* ── Glass card ── */
    .glass-card {
      background: var(--glass-bg);
      backdrop-filter: var(--glass-blur);
      border: var(--glass-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--w-shadow);
      overflow: hidden;
      transition: transform 0.22s ease, box-shadow 0.22s ease;
    }
    .glass-card:hover { box-shadow: var(--w-shadow-lg); }
    .w-card-pad { padding: var(--sp-6); }

    [data-theme="dark"] .glass-card {
      background: rgba(15,23,42,0.6);
      border-color: rgba(255,255,255,0.08);
    }

    /* ═══════════════ BALANCE HERO ═══════════════ */
    .balance-hero {
      background: linear-gradient(135deg,#1e1b4b 0%,#312e81 38%,#4338ca 68%,#6d28d9 100%);
      background-size: 200% 200%;
      animation: heroGradient 12s ease infinite;
      border-radius: var(--radius-lg);
      padding: 36px 36px 28px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 20px 52px rgba(99,102,241,0.38);
    }
    @keyframes heroGradient {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .balance-hero-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(circle,rgba(255,255,255,0.055) 1px,transparent 1px);
      background-size: 24px 24px; pointer-events: none;
    }
    .balance-hero-glow {
      position: absolute; width: 480px; height: 480px;
      background: radial-gradient(circle,rgba(168,85,247,0.38),transparent 60%);
      top: -160px; right: -100px; pointer-events: none;
    }
    .balance-hero-top {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 6px; position: relative;
    }
    .balance-hero-label {
      font-size: 0.7rem; font-weight: 600; letter-spacing: 0.1em;
      text-transform: uppercase; color: rgba(255,255,255,0.6);
      display: flex; align-items: center; gap: 6px;
    }
    .balance-hero-label svg { width: 13px; height: 13px; }
    .balance-trend {
      display: flex; align-items: center; gap: 5px;
      padding: 4px 11px; border-radius: 100px; font-size: 0.72rem; font-weight: 700;
      background: rgba(16,185,129,0.2); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.32);
    }
    .balance-trend.down { background: rgba(239,68,68,0.2); color: #fca5a5; border-color: rgba(239,68,68,0.3); }
    .balance-trend svg { width: 13px; height: 13px; }

    .balance-amount {
      font-size: clamp(2.6rem,5vw,4rem); font-weight: 800; color: #fff;
      letter-spacing: -0.04em; line-height: 1; position: relative; cursor: default;
    }
    .balance-currency { font-size: 1.5rem; font-weight: 400; opacity: 0.65; vertical-align: super; margin-right: 3px; }
    .balance-pending { margin-top: 8px; font-size: 0.78rem; color: rgba(255,255,255,0.52); position: relative; }
    .balance-pending strong { color: rgba(255,255,255,0.85); }

    .balance-actions { display: flex; gap: 10px; margin-top: 30px; flex-wrap: wrap; position: relative; }
    .ba-btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 10px 22px; border-radius: 100px; font-family: 'Poppins',sans-serif;
      font-size: 0.82rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.22s;
    }
    .ba-btn svg { width: 15px; height: 15px; }
    .ba-primary {
      background: #fff; color: #312e81;
      box-shadow: 0 4px 18px rgba(0,0,0,0.22);
    }
    .ba-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.28); }
    .ba-outline {
      background: rgba(255,255,255,0.12); color: #fff;
      border: 1.5px solid rgba(255,255,255,0.28); backdrop-filter: blur(8px);
    }
    .ba-outline:hover { background: rgba(255,255,255,0.22); }

    /* ═══════════════ STATS DASHBOARD ═══════════════ */
    .quick-stats {
      display: grid; grid-template-columns: repeat(4,1fr); gap: 14px;
    }
    @media (max-width: 900px) { .quick-stats { grid-template-columns: repeat(2,1fr); } }
    @media (max-width: 500px) { .quick-stats { grid-template-columns: 1fr; } }

    .qs-card {
      background: var(--w-surf); border: 1px solid var(--w-border);
      border-radius: var(--w-radius-sm); padding: 20px;
      position: relative; overflow: hidden;
      transition: transform 0.22s, box-shadow 0.22s;
    }
    .qs-card:hover { transform: translateY(-3px); box-shadow: var(--w-shadow-lg); }
    .qs-icon {
      width: 38px; height: 38px; border-radius: 10px;
      display: flex; align-items: center; justify-content: center; margin-bottom: 14px;
    }
    .qs-icon svg { width: 17px; height: 17px; }
    .qs-green  { background: rgba(16,185,129,0.12); color: var(--w-green); }
    .qs-indigo { background: rgba(99,102,241,0.12); color: var(--w-accent); }
    .qs-amber  { background: rgba(245,158,11,0.12); color: var(--w-amber); }
    .qs-red    { background: rgba(239,68,68,0.12);  color: var(--w-red); }
    .qs-label  { font-size: 0.67rem; color: var(--w-muted); font-weight: 500; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
    .qs-value  { font-size: 1.25rem; font-weight: 800; color: var(--w-heading); letter-spacing: -0.025em; }
    .qs-sub    { font-size: 0.64rem; color: var(--w-muted); margin-top: 4px; }
    .qs-badge  {
      display: inline-flex; align-items: center; gap: 3px;
      font-size: 0.62rem; font-weight: 700; padding: 2px 7px; border-radius: 100px;
    }
    .qs-badge-up   { background: rgba(16,185,129,0.1); color: #059669; }
    .qs-badge-down { background: rgba(239,68,68,0.1); color: #dc2626; }
    .qs-card::after {
      content: ''; position: absolute; right: -20px; bottom: -20px;
      width: 90px; height: 90px; border-radius: 50%;
      background: currentColor; opacity: 0.04;
    }

    /* ═══════════════ ANALYTICS SECTION ═══════════════ */
    .analytics-grid {
      display: grid; grid-template-columns: 1.4fr 1fr; gap: 16px;
    }
    @media (max-width: 800px) { .analytics-grid { grid-template-columns: 1fr; } }

    .analytics-card { padding: 24px; }
    .analytics-header {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;
    }
    .analytics-title {
      font-size: 0.82rem; font-weight: 700; color: var(--w-heading);
      display: flex; align-items: center; gap: 8px;
    }
    .analytics-title svg { width: 15px; height: 15px; color: var(--w-accent); }
    .analytics-period-select {
      font-size: 0.68rem; color: var(--w-muted); padding: 4px 10px;
      border-radius: 8px; background: var(--w-surf-alt); border: 1px solid var(--w-border);
      cursor: pointer; outline: none; font-family: 'Poppins', sans-serif;
    }
    .chart-wrap { position: relative; width: 100%; }
    .chart-wrap canvas { max-width: 100%; }

    /* ═══════════════ TRANSACTION LIST ═══════════════ */
    .txn-header {
      display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
      padding: 20px 24px 0;
    }
    .txn-title {
      font-size: 0.88rem; font-weight: 700; color: var(--w-heading);
      display: flex; align-items: center; gap: 8px;
    }
    .txn-title svg { width: 15px; height: 15px; color: var(--w-accent); }

    .txn-search-wrap { position: relative; flex: 1; min-width: 140px; max-width: 260px; }
    .txn-search-wrap svg {
      position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
      width: 14px; height: 14px; color: var(--w-muted); pointer-events: none;
    }
    .wallet-search {
      width: 100%; height: 34px; padding: 0 12px 0 34px;
      border: 1px solid var(--w-border); border-radius: 100px;
      background: var(--w-surf-alt); font-size: 0.75rem; outline: none;
      font-family: 'Poppins', sans-serif; color: var(--w-text);
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .wallet-search:focus { border-color: rgba(99,102,241,0.4); box-shadow: 0 0 0 3px var(--w-glow); }

    .txn-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .txn-export-btn {
      display: flex; align-items: center; gap: 6px; padding: 6px 13px;
      border-radius: 8px; font-size: 0.72rem; font-weight: 600;
      color: var(--w-muted); background: var(--w-surf-alt);
      border: 1px solid var(--w-border); cursor: pointer;
      transition: all 0.18s; font-family: 'Poppins', sans-serif;
    }
    .txn-export-btn svg { width: 13px; height: 13px; }
    .txn-export-btn:hover { color: var(--w-accent); border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.05); }

    /* filter tabs */
    .filter-tabs {
      display: flex; gap: 6px; padding: 16px 24px;
      border-bottom: 1px solid var(--w-border); overflow-x: auto;
    }
    .filter-tabs::-webkit-scrollbar { display: none; }
    .ftab {
      padding: 6px 14px; border-radius: 100px; font-size: 0.72rem; font-weight: 600;
      color: var(--w-muted); background: transparent; border: 1px solid transparent;
      cursor: pointer; white-space: nowrap; transition: all 0.18s; font-family: 'Poppins', sans-serif;
    }
    .ftab:hover { background: var(--w-surf-alt); color: var(--w-text); }
    .ftab.active {
      background: linear-gradient(135deg,rgba(99,102,241,0.1),rgba(168,85,247,0.07));
      color: var(--w-accent); border-color: rgba(99,102,241,0.22);
    }
    [data-theme="dark"] .ftab.active { background: rgba(99,102,241,0.18); color: #a5b4fc; }

    /* txn rows */
    .txn-list { display: flex; flex-direction: column; }
    .txn-row {
      display: grid; grid-template-columns: 44px 1fr auto;
      gap: 14px; align-items: center; padding: 16px 24px;
      border-bottom: 1px solid var(--w-border); cursor: pointer; transition: background 0.15s;
    }
    .txn-row:last-child { border-bottom: none; }
    .txn-row:hover { background: var(--w-surf-alt); }

    .txn-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .txn-icon svg { width: 18px; height: 18px; }
    .txn-icon-income   { background: rgba(16,185,129,0.1); color: var(--w-green); }
    .txn-icon-expense  { background: rgba(239,68,68,0.1);  color: var(--w-red); }
    .txn-icon-donation { background: rgba(168,85,247,0.1); color: var(--w-accent2); }

    .txn-body { min-width: 0; }
    .txn-body-title { font-size: 0.84rem; font-weight: 600; color: var(--w-heading); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .txn-body-meta  { font-size: 0.68rem; color: var(--w-muted); margin-top: 3px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

    .txn-status {
      font-size: 0.6rem; font-weight: 700; padding: 2px 8px; border-radius: 100px;
      letter-spacing: 0.04em; text-transform: uppercase;
    }
    .txn-status-completed { background: rgba(16,185,129,0.1); color: #059669; }
    .txn-status-pending   { background: rgba(245,158,11,0.1);  color: #d97706; }
    .txn-status-failed    { background: rgba(239,68,68,0.1);   color: #dc2626; }

    .txn-right { text-align: right; flex-shrink: 0; }
    .txn-amount { font-size: 0.9rem; font-weight: 700; white-space: nowrap; }
    .txn-amount-positive { color: var(--w-green); }
    .txn-amount-negative { color: var(--w-red); }
    .txn-time { font-size: 0.65rem; color: var(--w-muted); margin-top: 3px; }

    /* empty state */
    .txn-empty { padding: 52px 24px; text-align: center; display: none; }
    .txn-empty-icon {
      width: 56px; height: 56px; border-radius: 16px;
      background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.14);
      display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: var(--w-accent);
    }
    .txn-empty-icon svg { width: 24px; height: 24px; }
    .txn-empty h4 { font-size: 0.88rem; font-weight: 600; color: var(--w-heading); margin-bottom: 6px; }
    .txn-empty p { font-size: 0.76rem; color: var(--w-muted); }

    /* ═══════════════ RIGHT SIDEBAR ═══════════════ */
    .wallet-right { display: flex; flex-direction: column; gap: 16px; position: sticky; top: 88px; }
    .insight-card { padding: 22px; }
    .insight-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
    .insight-title { font-size: 0.8rem; font-weight: 700; color: var(--w-heading); }

    .wi-row {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 0; border-bottom: 1px solid var(--w-border);
    }
    .wi-row:last-child { border-bottom: none; }
    .wi-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .wi-bar-wrap { flex: 1; height: 3px; border-radius: 2px; background: var(--w-border); }
    .wi-bar-fill { height: 100%; border-radius: 2px; transition: width 0.8s ease; }

    /* payment methods */
    .payment-method-card {
      padding: 14px 16px; border-radius: 12px;
      border: 1px solid var(--w-border); background: var(--w-surf-alt);
      display: flex; align-items: center; gap: 12px; margin: 0 12px 8px;
      cursor: pointer; transition: all 0.18s;
    }
    .payment-method-card:hover { border-color: rgba(99,102,241,0.3); transform: translateX(2px); }
    .pm-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .pm-stripe { background: rgba(99,91,255,0.1); }
    .pm-paypal { background: rgba(0,48,135,0.08); }
    .pm-info { flex: 1; min-width: 0; }
    .pm-name   { font-size: 0.8rem; font-weight: 600; color: var(--w-heading); }
    .pm-detail { font-size: 0.68rem; color: var(--w-muted); }
    .pm-status { width: 8px; height: 8px; border-radius: 50%; background: var(--w-green); box-shadow: 0 0 0 2px rgba(16,185,129,0.22); }

    /* quick send */
    .qsend-avatar {
      width: 46px; height: 46px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.82rem; font-weight: 700; cursor: pointer;
      transition: transform 0.18s, box-shadow 0.18s;
    }
    .qsend-avatar:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.18); }

    /* ═══════════════ MODALS ═══════════════ */
    .w-modal-overlay {
      position: fixed; inset: 0; z-index: var(--z-modal);
      background: rgba(7,13,26,0.32); /* Reduced opacity as global overlay adds more */
      display: flex; align-items: center; justify-content: center; padding: 20px;
      opacity: 0; pointer-events: none; transition: opacity 0.25s;
    }

    .w-modal-overlay.open { opacity: 1; pointer-events: auto; }
    .w-modal {
      background: var(--w-surf); border: 1px solid var(--w-border); border-radius: 24px;
      box-shadow: 0 28px 80px rgba(7,13,26,0.38); width: 100%; max-width: 440px;
      transform: translateY(18px) scale(0.97); transition: transform 0.28s cubic-bezier(0.16,1,0.3,1);
    }
    .w-modal-overlay.open .w-modal { transform: translateY(0) scale(1); }
    .w-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 22px 24px 0; }
    .w-modal-head h3 { font-size: 1rem; font-weight: 700; color: var(--w-heading); }
    .w-modal-close {
      width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--w-border);
      background: var(--w-surf-alt); color: var(--w-muted);
      display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.18s;
    }
    .w-modal-close:hover { color: var(--w-text); transform: rotate(90deg); }
    .w-modal-close svg { width: 14px; height: 14px; }
    .w-modal-body { padding: 20px 24px 26px; display: flex; flex-direction: column; gap: 16px; }

    .w-field { display: flex; flex-direction: column; gap: 6px; }
    .w-label { font-size: 0.7rem; font-weight: 700; color: var(--w-muted); text-transform: uppercase; letter-spacing: 0.08em; }
    .w-input {
      width: 100%; height: 48px; padding: 0 16px;
      border: 1.5px solid var(--w-border); border-radius: 12px;
      background: var(--w-surf-alt); font-family: 'Poppins',sans-serif;
      font-size: 1rem; font-weight: 600; color: var(--w-heading); outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .w-input:focus { border-color: rgba(99,102,241,0.5); box-shadow: 0 0 0 3px var(--w-glow); }
    .amount-big { font-size: 2rem; height: 64px; text-align: center; }

    .preset-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 8px; }
    .preset-btn {
      padding: 8px; border-radius: 10px; border: 1.5px solid var(--w-border);
      background: var(--w-surf-alt); font-family: 'Poppins',sans-serif;
      font-size: 0.8rem; font-weight: 600; color: var(--w-text); cursor: pointer; transition: all 0.18s;
    }
    .preset-btn:hover, .preset-btn.selected {
      background: rgba(99,102,241,0.08); border-color: var(--w-accent); color: var(--w-accent);
    }

    .pm-select-grid { display: flex; flex-direction: column; gap: 8px; }
    .pm-select-row {
      display: flex; align-items: center; gap: 10px; padding: 12px 14px;
      border-radius: 12px; border: 1.5px solid var(--w-border); cursor: pointer; transition: all 0.18s;
    }
    .pm-select-row:hover, .pm-select-row.active {
      border-color: var(--w-accent); background: rgba(99,102,241,0.05);
    }
    .pm-select-row input[type="radio"] { accent-color: var(--w-accent); }
    .pm-select-label { font-size: 0.82rem; font-weight: 500; color: var(--w-text); }

    .fee-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 10px 14px; border-radius: 10px;
      background: var(--w-surf-alt); border: 1px solid var(--w-border); font-size: 0.78rem;
    }
    .fee-row span { color: var(--w-muted); }
    .fee-row strong { color: var(--w-heading); font-weight: 700; }

    .w-confirm-btn {
      width: 100%; height: 50px; border-radius: 12px; border: none;
      background: linear-gradient(135deg,var(--w-accent),var(--w-accent2));
      color: #fff; font-family: 'Poppins',sans-serif; font-size: 0.9rem; font-weight: 700;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: all 0.22s; box-shadow: 0 4px 18px var(--w-glow);
    }
    .w-confirm-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 30px var(--w-glow); }
    .w-confirm-btn:disabled { opacity: 0.6; transform: none; cursor: not-allowed; }
    .w-confirm-btn svg { width: 16px; height: 16px; }

    .txn-detail-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 10px 0; border-bottom: 1px solid var(--w-border); font-size: 0.82rem;
    }
    .txn-detail-row:last-child { border-bottom: none; }
    .txn-detail-row span { color: var(--w-muted); }
    .txn-detail-row strong { color: var(--w-heading); font-weight: 600; }

    /* ═══════════════ TOAST ═══════════════ */
    .w-toast-wrap { position: fixed; top: 80px; right: 20px; z-index: 2000; display: flex; flex-direction: column; gap: 8px; }
    .w-toast {
      padding: 12px 18px; border-radius: 12px;
      background: var(--w-surf); border: 1px solid var(--w-border);
      box-shadow: 0 8px 32px rgba(7,13,26,0.2); font-size: 0.82rem; font-weight: 500;
      color: var(--w-text); display: flex; align-items: center; gap: 10px;
      max-width: 330px; animation: toastIn 0.3s cubic-bezier(0.16,1,0.3,1);
    }
    .w-toast.success { border-color: rgba(16,185,129,0.3); }

    /* ── Shimmer Effect ── */
    .shimmer {
      position: relative;
      overflow: hidden;
    }
    .shimmer::after {
      content: '';
      position: absolute;
      top: 0; right: 0; bottom: 0; left: 0;
      transform: translateX(-100%);
      background-image: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0,
        rgba(255, 255, 255, 0.05) 20%,
        rgba(255, 255, 255, 0.1) 60%,
        rgba(255, 255, 255, 0)
      );
      animation: shimmer 3s infinite;
    }
    @keyframes shimmer {
      100% { transform: translateX(100%); }
    }

    /* ── Floating Actions ── */
    .w-fab {
      position: fixed; bottom: 24px; right: 24px;
      width: 56px; height: 56px; border-radius: 28px;
      background: var(--w-accent); color: #fff;
      display: none; align-items: center; justify-content: center;
      box-shadow: 0 8px 24px rgba(99,102,241,0.4);
      z-index: 900; cursor: pointer; border: none;
      transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .w-fab:hover { transform: scale(1.1) rotate(5deg); }
    @media (max-width: 600px) { .w-fab { display: flex; } }

    .w-toast.success svg { color: var(--w-green); }
    .w-toast.error { border-color: rgba(239,68,68,0.3); }
    .w-toast.error svg { color: var(--w-red); }
    .w-toast svg { width: 16px; height: 16px; flex-shrink: 0; }
    @keyframes toastIn { from { opacity:0; transform:translateX(24px); } to { opacity:1; transform:translateX(0); } }

    /* ═══════════════ MISC ═══════════════ */
    @keyframes spin { to { transform: rotate(360deg); } }
    .loading-spinner { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.8s linear infinite; }

    .fade-in { opacity:0; transform:translateY(12px); animation: fadeUp 0.45s ease forwards; }
    @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
    .fade-in:nth-child(1){ animation-delay:0.05s }
    .fade-in:nth-child(2){ animation-delay:0.1s }
    .fade-in:nth-child(3){ animation-delay:0.15s }
    .fade-in:nth-child(4){ animation-delay:0.2s }
    .fade-in:nth-child(5){ animation-delay:0.25s }
    .fade-in:nth-child(6){ animation-delay:0.3s }

    .navbar-search { position:relative; flex:1; max-width:300px; margin:0 var(--sp-4); }
    .navbar-search input { width:100%; height:38px; padding:0 14px 0 40px; border-radius:var(--radius-full); border:1px solid var(--color-border); background:var(--color-bg-alt); font-size:0.83rem; outline:none; font-family:'Poppins',sans-serif; transition:all 0.2s; }
    .navbar-search input:focus { background:var(--color-surface); border-color:var(--color-accent); box-shadow:0 0 0 4px var(--color-accent-light); }
    .navbar-search svg { position:absolute; left:13px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--color-text-secondary); }

    .theme-toggle-btn { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:var(--w-surf-alt); border:1px solid var(--w-border); color:var(--w-muted); cursor:pointer; transition:all 0.18s; }
    .theme-toggle-btn:hover { color:var(--w-accent); }
    .theme-toggle-btn svg { width:16px; height:16px; }
    [data-theme="light"] .icon-moon { display:none; }
    [data-theme="dark"]  .icon-sun  { display:none; }

    /* number counter animation */
    .count-up { transition: all 0.6s ease; }

    /* mobile tabs */
    .mobile-tabs { display:none; position:fixed; bottom:0; left:0; right:0; z-index:200; background:var(--w-surf); border-top:1px solid var(--w-border); padding:8px 0 env(safe-area-inset-bottom); }
    .mobile-tabs-inner { display:flex; justify-content:space-around; }
    .mtab { flex:1; display:flex; flex-direction:column; align-items:center; gap:3px; padding:6px; font-size:0.58rem; font-weight:600; color:var(--w-muted); cursor:pointer; border:none; background:none; transition:color 0.18s; font-family:'Poppins',sans-serif; }
    .mtab.active { color:var(--w-accent); }
    .mtab svg { width:20px; height:20px; }

    @media (max-width:900px) { .wallet-right { position:static; } .mobile-tabs { display:block; } body { padding-bottom: 70px; } }
    @media (max-width:600px) { .balance-hero { padding:24px 20px; } .balance-amount { font-size:2.6rem; } .txn-row { padding:14px 16px; } .analytics-grid { grid-template-columns:1fr; } }

    /* Security badge */
    .security-badge { display:flex; align-items:center; gap:10px; padding:16px 20px; }
    .security-badge svg { width:20px; height:20px; color:var(--w-green); flex-shrink:0; }
    .security-badge-text strong { display:block; font-size:0.8rem; color:var(--w-heading); }
    .security-badge-text span { font-size:0.66rem; color:var(--w-muted); }
  </style>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">
  <canvas id="gradient-canvas"></canvas>

  <!-- ── Navbar ── -->
  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="container">
      <a href="home.php" class="navbar-brand" aria-label="Diversity home">
        <span class="brand-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        </span>
        Diversity.is
      </a>
      <div class="navbar-nav" id="navbar-nav" aria-hidden="true"></div>

      <div class="navbar-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" placeholder="Search transactions, contacts…" aria-label="Search">
      </div>

      <div class="navbar-actions">
        <button class="nav-icon-btn" aria-label="Notifications">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </button>
        <button class="theme-toggle" aria-label="Toggle theme">
          <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div class="nav-profile">
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($initials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

  <main class="profile-main" id="profile-main-content" tabindex="-1">
    <div class="container profile-page-layout">

  <!-- Toast container -->
  <div class="w-toast-wrap" id="wToastWrap"></div>

  <!-- ══ MAIN COLUMN ══ -->
  <div class="wallet-main" style="display:flex;flex-direction:column;gap:20px;min-width:0;">

    <!-- Balance hero -->
    <div class="balance-hero fade-in shimmer">
      <div class="balance-hero-dots"></div>
      <div class="balance-hero-glow"></div>

      <div class="balance-hero-top">
        <div class="balance-hero-label">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4h-4z"/></svg>
          Available Balance
        </div>
        <div class="balance-trend <?= $trendUp ? '' : 'down' ?>" id="balanceTrend">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 15l-6-6-6 6"/></svg>
          <?= $trendPercent ?>% this month
        </div>
      </div>

      <div class="balance-amount" id="heroBalance">
        <span class="balance-currency">$</span><span id="heroBalanceNum" data-target="<?= $balance ?>"><?= number_format($balance, 2) ?></span>
      </div>
      <div class="balance-pending">
        + <strong>$<?= number_format($pendingBalance, 2) ?></strong> pending release
      </div>

      <div class="balance-actions">
        <button class="ba-btn ba-primary" onclick="openModal('depositModal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
          Deposit Funds
        </button>
        <button class="ba-btn ba-outline" onclick="openModal('withdrawModal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
          Withdraw
        </button>
        <button class="ba-btn ba-outline" onclick="openModal('sendModal')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
          Send Money
        </button>
      </div>
    </div>

    <!-- Quick stats -->
    <div class="quick-stats fade-in">
      <div class="qs-card shimmer">
        <div class="qs-icon qs-green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div class="qs-label">Total Earnings</div>
        <div class="qs-value">$<?= number_format($totalEarnings, 0) ?></div>
        <div class="qs-sub">
          <span class="qs-badge qs-badge-up">↑ 18%</span> vs last month
        </div>
      </div>

      <div class="qs-card shimmer">
        <div class="qs-icon qs-indigo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <div class="qs-label">Marketplace Sales</div>
        <div class="qs-value">$<?= number_format($totalSales, 0) ?></div>
        <div class="qs-sub"><span class="qs-badge qs-badge-up">↑ 12%</span> growth</div>
      </div>

      <div class="qs-card shimmer">
        <div class="qs-icon qs-amber">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="qs-label">Pending Release</div>
        <div class="qs-value">$<?= number_format($totalPending, 0) ?></div>
        <div class="qs-sub">Releases in 3–5 days</div>
      </div>

      <div class="qs-card shimmer">
        <div class="qs-icon qs-red">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4h-4z"/></svg>
        </div>
        <div class="qs-label">Total Spent</div>
        <div class="qs-value">$<?= number_format($totalSpent, 0) ?></div>
        <div class="qs-sub">Fees + withdrawals</div>
      </div>
    </div>

    <!-- ── Extended statistics row ── -->
    <div class="quick-stats fade-in" style="grid-template-columns: repeat(3,1fr);">
      <div class="qs-card">
        <div class="qs-icon" style="background:rgba(6,182,212,0.12);color:#06b6d4;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="qs-label">Avg Transaction</div>
        <div class="qs-value">$<?= number_format($avgTxnValue, 2) ?></div>
        <div class="qs-sub">Per completed txn</div>
      </div>
      <div class="qs-card">
        <div class="qs-icon" style="background:rgba(168,85,247,0.12);color:#a855f7;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="qs-label">Completed</div>
        <div class="qs-value"><?= $completedCount ?> <span style="font-size:0.75rem;font-weight:400;color:var(--w-muted);">txns</span></div>
        <div class="qs-sub"><?= $pendingCount ?> pending</div>
      </div>
      <div class="qs-card">
        <div class="qs-icon" style="background:rgba(251,191,36,0.12);color:#f59e0b;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="qs-label">Success Rate</div>
        <div class="qs-value"><?= count($transactions) > 0 ? round(($completedCount / count($transactions)) * 100) : 100 ?>%</div>
        <div class="qs-sub"><span class="qs-badge qs-badge-up">Excellent</span></div>
      </div>
    </div>

    <!-- ── Analytics Charts ── -->
    <div class="analytics-grid fade-in">
      <!-- Line chart: Balance over time -->
      <div class="glass-card analytics-card">
        <div class="analytics-header">
          <div class="analytics-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
            Balance Over Time
          </div>
          <select class="analytics-period-select" id="balanceChartPeriod" onchange="updateBalanceChart(this.value)">
            <option value="7d">7 Days</option>
            <option value="30d">30 Days</option>
            <option value="6m" selected>6 Months</option>
          </select>
        </div>
        <div class="chart-wrap" style="height:200px;">
          <canvas id="balanceChart"></canvas>
        </div>
      </div>

      <!-- Pie chart: Earnings sources -->
      <div class="glass-card analytics-card">
        <div class="analytics-header">
          <div class="analytics-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            Earnings Sources
          </div>
        </div>
        <div class="chart-wrap" style="height:200px;display:flex;align-items:center;justify-content:center;">
          <canvas id="sourceChart" style="max-height:180px;max-width:180px;"></canvas>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;margin-top:14px;padding-top:14px;border-top:1px solid var(--w-border);">
          <div style="display:flex;justify-content:space-between;font-size:0.72rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--w-muted);"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#6366f1;"></span>Marketplace</span>
            <strong style="color:var(--w-heading);"><?= round(($srcMarketplace/$srcTotal)*100) ?>%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.72rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--w-muted);"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#a855f7;"></span>Donations</span>
            <strong style="color:var(--w-heading);"><?= round(($srcDonations/$srcTotal)*100) ?>%</strong>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.72rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--w-muted);"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;"></span>Other</span>
            <strong style="color:var(--w-heading);"><?= round(($srcOther/$srcTotal)*100) ?>%</strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Bar chart: Monthly income vs spending -->
    <div class="glass-card analytics-card fade-in">
      <div class="analytics-header">
        <div class="analytics-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><path d="M3 9h18M9 21V9"/></svg>
          Monthly Income vs Spending
        </div>
        <div style="display:flex;align-items:center;gap:14px;font-size:0.7rem;">
          <span style="display:flex;align-items:center;gap:5px;color:var(--w-muted);"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:rgba(99,102,241,0.7);"></span>Income</span>
          <span style="display:flex;align-items:center;gap:5px;color:var(--w-muted);"><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:rgba(239,68,68,0.6);"></span>Spent</span>
        </div>
      </div>
      <div class="chart-wrap" style="height:200px;">
        <canvas id="monthlyChart"></canvas>
      </div>
    </div>

    <!-- Transactions card -->
    <div class="glass-card fade-in">
      <div class="txn-header">
        <div class="txn-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
          Recent Transactions
        </div>
        <div class="txn-search-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" class="wallet-search" id="txnSearch" placeholder="Search history…">
        </div>
        <div class="txn-actions">
          <button class="txn-export-btn" onclick="exportCSV()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export CSV
          </button>
          <button class="txn-export-btn" onclick="exportPDF()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            PDF Report
          </button>
        </div>
      </div>

      <!-- Filter tabs -->
      <div class="filter-tabs">
        <button class="ftab active" data-filter="all">All</button>
        <button class="ftab" data-filter="income">Incoming</button>
        <button class="ftab" data-filter="expense">Outgoing</button>
        <button class="ftab" data-filter="donation">Donations</button>
        <button class="ftab" data-filter="marketplace">Marketplace</button>
        <button class="ftab" data-filter="withdrawal">Withdrawals</button>
      </div>

      <!-- Transaction rows -->
      <div class="txn-list" id="txnList">
        <?php foreach($transactions as $t):
          $isPos = $t['amount'] > 0;
          $iconClass = match($t['type']) {
            'income'   => 'txn-icon-income',
            'donation' => 'txn-icon-donation',
            default    => 'txn-icon-expense',
          };
          $amtClass = $isPos ? 'txn-amount-positive' : 'txn-amount-negative';
          $amtStr   = ($isPos ? '+$' : '-$') . number_format(abs($t['amount']), 2);
          $statusClass = 'txn-status-' . $t['status'];
        ?>
        <div class="txn-row"
             data-type="<?= $t['type'] ?>"
             data-category="<?= $t['category'] ?>"
             data-title="<?= htmlspecialchars(strtolower($t['title'])) ?>"
             onclick='openTxnDetail(<?= json_encode([
               "id"=>$t["id"],"rawId"=>$t["rawId"],"title"=>$t["title"],"amount"=>$t["amount"],
               "date"=>$t["dateStr"],"time"=>$t["timeStr"],"status"=>$t["status"],
               "category"=>$t["category"],"type"=>$t["type"]
             ]) ?>)'>

          <div class="txn-icon <?= $iconClass ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <?php if($t['icon']==='arrow-down-right'): ?>
                <line x1="7" y1="7" x2="17" y2="17"/><polyline points="7 17 17 17 17 7"/>
              <?php elseif($t['icon']==='arrow-up-right'): ?>
                <line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>
              <?php elseif($t['icon']==='gift'): ?>
                <polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
              <?php else: ?>
                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
              <?php endif; ?>
            </svg>
          </div>

          <div class="txn-body">
            <div class="txn-body-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="txn-body-meta">
              <span class="txn-status <?= $statusClass ?>"><?= ucfirst($t['status']) ?></span>
              <span><?= $t['dateStr'] ?></span>
              <span><?= htmlspecialchars($t['id']) ?></span>
            </div>
          </div>

          <div class="txn-right">
            <div class="txn-amount <?= $amtClass ?>"><?= $amtStr ?></div>
            <div class="txn-time"><?= $t['timeStr'] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="txn-empty" id="txnEmpty">
        <div class="txn-empty-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18"/></svg>
        </div>
        <h4>No transactions found</h4>
        <p>Try a different filter or make your first transaction.</p>
      </div>
    </div>

  </div><!-- /wallet-main -->

  <!-- ══ RIGHT SIDEBAR ══ -->
  <aside class="wallet-right">

    <!-- Income vs Spending chart (bar) -->
    <div class="glass-card insight-card fade-in">
      <div class="insight-header">
        <div class="insight-title" style="font-weight:700;color:var(--w-heading);">Income vs Spending</div>
        <div style="font-size:0.65rem;color:var(--w-muted);padding:3px 9px;border-radius:6px;background:var(--w-surf-alt);border:1px solid var(--w-border);">Last 7 days</div>
      </div>
      <div style="height:140px;margin-bottom:4px;">
        <canvas id="sidebarChart"></canvas>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding-top:14px;border-top:1px solid var(--w-border);">
        <div>
          <span style="font-size:0.63rem;color:var(--w-muted);text-transform:uppercase;letter-spacing:0.05em;">Income</span>
          <div style="font-size:1.05rem;font-weight:800;color:var(--w-heading);">$<?= number_format($totalEarnings, 0) ?></div>
          <div style="font-size:0.63rem;color:var(--w-green);font-weight:600;">↑ 22%</div>
        </div>
        <div>
          <span style="font-size:0.63rem;color:var(--w-muted);text-transform:uppercase;letter-spacing:0.05em;">Spent</span>
          <div style="font-size:1.05rem;font-weight:800;color:var(--w-heading);">$<?= number_format($totalSpent, 0) ?></div>
          <div style="font-size:0.63rem;color:var(--w-red);font-weight:600;">↑ 5%</div>
        </div>
      </div>
    </div>

    <!-- Payment methods -->
    <div class="glass-card fade-in">
      <div style="padding:16px 20px 8px;font-size:0.72rem;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:var(--w-muted);">Payment Methods</div>
      <div class="payment-method-card">
        <div class="pm-icon pm-stripe">💳</div>
        <div class="pm-info"><div class="pm-name">Stripe</div><div class="pm-detail">•••• 4242 · Connected</div></div>
        <div class="pm-status"></div>
      </div>
      <div class="payment-method-card">
        <div class="pm-icon pm-paypal">🅿️</div>
        <div class="pm-info"><div class="pm-name">PayPal</div><div class="pm-detail"><?= htmlspecialchars($displayEmail) ?></div></div>
        <div class="pm-status"></div>
      </div>
      <div style="padding:8px 16px 16px;">
        <button class="ba-btn ba-outline" style="width:100%;justify-content:center;background:var(--w-surf-alt);color:var(--w-text);border-color:var(--w-border);" onclick="showToast('Payment method management coming soon!','success')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M12 5v14M5 12h14"/></svg>
          Add Method
        </button>
      </div>
    </div>

    <!-- Spending breakdown -->
    <div class="glass-card w-card-pad fade-in">
      <div style="font-size:0.78rem;font-weight:700;color:var(--w-heading);margin-bottom:14px;">Spending Breakdown</div>
      <?php
      $breakdown = [
        ['Marketplace', '#6366f1', min(100, round(($srcMarketplace/$srcTotal)*100))],
        ['Withdrawals',  '#10b981', min(100, round(($totalSpent/max(1,$totalEarnings))*60))],
        ['Donations',    '#a855f7', min(100, round(($srcDonations/$srcTotal)*100))],
      ];
      foreach($breakdown as $b): ?>
      <div class="wi-row">
        <div class="wi-dot" style="background:<?= $b[1] ?>"></div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span style="font-size:0.72rem;color:var(--w-text);"><?= $b[0] ?></span>
            <span style="font-size:0.72rem;font-weight:600;color:var(--w-heading);"><?= $b[2] ?>%</span>
          </div>
          <div class="wi-bar-wrap">
            <div class="wi-bar-fill" style="width:<?= $b[2] ?>%;background:<?= $b[1] ?>;"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick send -->
    <div class="glass-card w-card-pad fade-in">
      <div style="font-size:0.78rem;font-weight:700;color:var(--w-heading);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;color:var(--w-accent)"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        Quick Send
      </div>
      <div style="display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap;" id="quickSendUsers">
        <?php
        $quickSendUsers = $walletController->getQuickSendUsers($currentUserId);
        if (empty($quickSendUsers)): ?>
          <div style="font-size:0.72rem;color:var(--w-muted);">Add friends to enable Quick Send</div>
        <?php else:
          $colors = ['#6366f1','#10b981','#f59e0b','#ef4444','#a855f7','#06b6d4','#f97316','#8b5cf6','#14b8a6','#ec4899'];
          foreach($quickSendUsers as $idx => $qsu):
            $qsuInit = strtoupper(substr($qsu['first_name'] ?? 'U', 0, 1) . substr($qsu['last_name'] ?? '', 0, 1));
            $qsuName = trim(($qsu['first_name'] ?? '') . ' ' . ($qsu['last_name'] ?? ''));
            $qsuColor = $colors[$idx % count($colors)];
            $qsuAvatar = $qsu['avatar_url'] ?? '';
        ?>
        <div style="text-align:center;cursor:pointer;" onclick="prefillSendUser(<?= (int)$qsu['id'] ?>, '<?= htmlspecialchars($qsuName, ENT_QUOTES) ?>')" title="<?= htmlspecialchars($qsuName) ?>">
          <?php if ($qsuAvatar): ?>
            <img src="<?= htmlspecialchars($qsuAvatar) ?>" alt="<?= htmlspecialchars($qsuName) ?>" class="qsend-avatar" style="border:2px solid <?= $qsuColor ?>44;object-fit:cover;">
          <?php else: ?>
            <div class="qsend-avatar" style="background:<?= $qsuColor ?>22;color:<?= $qsuColor ?>;border:2px solid <?= $qsuColor ?>44;">
              <?= $qsuInit ?>
            </div>
          <?php endif; ?>
          <div style="font-size:0.6rem;color:var(--w-muted);margin-top:4px;max-width:50px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($qsu['first_name'] ?? '') ?></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <button class="ba-btn" style="width:100%;justify-content:center;background:linear-gradient(135deg,var(--w-accent),var(--w-accent2));color:white;border:none;" onclick="openModal('sendModal')">Send Money</button>
    </div>

    <!-- Security badge -->
    <div class="glass-card fade-in">
      <div class="security-badge">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <div class="security-badge-text">
          <strong>Secured &amp; Encrypted</strong>
          <span>2FA · TLS 1.3 · PCI DSS Level 1</span>
        </div>
      </div>
    </div>

  </aside>

    </div>
  </main>

  <!-- ── Mobile bottom tabs ── -->
  <nav class="mobile-tabs">
    <div class="mobile-tabs-inner">
      <button class="mtab active" onclick="scrollTo(0,0)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/></svg>
        Overview
      </button>
      <button class="mtab" onclick="openModal('depositModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
        Deposit
      </button>
      <button class="mtab" onclick="openModal('withdrawModal')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
        Withdraw
      </button>
      <button class="mtab" onclick="document.getElementById('txnList').scrollIntoView({behavior:'smooth'})">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18"/></svg>
        History
      </button>
    </div>
  </nav>

<!-- ══ DEPOSIT MODAL ══ -->
<div class="w-modal-overlay" id="depositModal">
  <div class="w-modal">
    <div class="w-modal-head">
      <h3>💳 Deposit Funds</h3>
      <button class="w-modal-close" onclick="closeModal('depositModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="w-modal-body">
      <div class="w-field">
        <div class="w-label">Amount</div>
        <input type="number" class="w-input amount-big" id="depositAmount" placeholder="0.00" min="1">
      </div>
      <div class="preset-grid">
        <button class="preset-btn" onclick="setAmount('depositAmount',10)">$10</button>
        <button class="preset-btn" onclick="setAmount('depositAmount',25)">$25</button>
        <button class="preset-btn" onclick="setAmount('depositAmount',50)">$50</button>
        <button class="preset-btn" onclick="setAmount('depositAmount',100)">$100</button>
      </div>
      <div class="w-field">
        <div class="w-label">Payment Method</div>
        <div class="pm-select-grid">
          <label class="pm-select-row active">
            <input type="radio" name="depositPM" value="stripe" checked>
            <span class="pm-select-label">💳 Stripe — •••• 4242</span>
          </label>
          <label class="pm-select-row">
            <input type="radio" name="depositPM" value="paypal">
            <span class="pm-select-label">🅿️ PayPal — <?= htmlspecialchars($displayEmail) ?></span>
          </label>
        </div>
      </div>
      <div class="fee-row">
        <span>Processing Fee (1.4% + $0.25)</span>
        <strong id="depositFee">$0.00</strong>
      </div>
      <div class="fee-row">
        <span>You will receive</span>
        <strong id="depositReceive" style="color:var(--w-green)">$0.00</strong>
      </div>
      <button class="w-confirm-btn" id="depositConfirmBtn" onclick="confirmDeposit()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
        Confirm Deposit
      </button>
    </div>
  </div>
</div>

<!-- ══ WITHDRAW MODAL ══ -->
<div class="w-modal-overlay" id="withdrawModal">
  <div class="w-modal">
    <div class="w-modal-head">
      <h3>🏦 Withdraw Funds</h3>
      <button class="w-modal-close" onclick="closeModal('withdrawModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="w-modal-body">
      <div class="fee-row">
        <span>Available to withdraw</span>
        <strong id="availableBalance" style="color:var(--w-green)">$<?= number_format($balance, 2) ?></strong>
      </div>
      <div class="w-field">
        <div class="w-label">Amount</div>
        <input type="number" class="w-input amount-big" id="withdrawAmount" placeholder="0.00" min="10" max="<?= $balance ?>">
      </div>
      <div class="preset-grid">
        <button class="preset-btn" onclick="setAmount('withdrawAmount',50)">$50</button>
        <button class="preset-btn" onclick="setAmount('withdrawAmount',100)">$100</button>
        <button class="preset-btn" onclick="setAmount('withdrawAmount',500)">$500</button>
        <button class="preset-btn" onclick="setAmount('withdrawAmount',<?= max(0,floor($balance)) ?>)">All</button>
      </div>
      <div class="w-field">
        <div class="w-label">Payout Method</div>
        <div class="pm-select-grid">
          <label class="pm-select-row active">
            <input type="radio" name="withdrawPM" value="paypal" checked>
            <span class="pm-select-label">🅿️ PayPal — Instant (1–2h)</span>
          </label>
          <label class="pm-select-row">
            <input type="radio" name="withdrawPM" value="bank">
            <span class="pm-select-label">🏦 Bank Transfer — 1–3 days</span>
          </label>
        </div>
      </div>
      <div class="fee-row">
        <span>Withdrawal Fee</span>
        <strong style="color:var(--w-green)">Free</strong>
      </div>
      <div class="fee-row">
        <span>You will receive</span>
        <strong id="withdrawReceive">$0.00</strong>
      </div>
      <button class="w-confirm-btn" id="withdrawConfirmBtn" onclick="confirmWithdraw()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
        Confirm Withdrawal
      </button>
    </div>
  </div>
</div>

<!-- ══ SEND MONEY MODAL ══ -->
<div class="w-modal-overlay" id="sendModal">
  <div class="w-modal">
    <div class="w-modal-head">
      <h3>✉️ Send Money</h3>
      <button class="w-modal-close" onclick="closeModal('sendModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="w-modal-body">
      <input type="hidden" id="sendReceiverId" value="0">
      <div class="w-field">
        <div class="w-label">Recipient</div>
        <input type="text" class="w-input" id="sendRecipient" placeholder="Search friends…" autocomplete="off">
        <div id="sendRecipientDisplay" style="display:none;padding:10px 14px;border-radius:10px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.2);margin-top:6px;font-size:0.82rem;font-weight:600;color:var(--w-heading);">
          <span id="sendRecipientName"></span>
          <button onclick="clearRecipient()" style="float:right;background:none;border:none;cursor:pointer;color:var(--w-muted);font-size:0.8rem;">✕</button>
        </div>
      </div>
      <div class="w-field">
        <div class="w-label">Amount</div>
        <input type="number" class="w-input amount-big" id="sendAmount" placeholder="0.00" min="1">
      </div>
      <div class="preset-grid">
        <button class="preset-btn" onclick="setAmount('sendAmount',10)">$10</button>
        <button class="preset-btn" onclick="setAmount('sendAmount',25)">$25</button>
        <button class="preset-btn" onclick="setAmount('sendAmount',50)">$50</button>
        <button class="preset-btn" onclick="setAmount('sendAmount',100)">$100</button>
      </div>
      <div class="w-field">
        <div class="w-label">Note (optional)</div>
        <input type="text" class="w-input" id="sendNote" placeholder="Payment for…" style="height:44px;font-weight:400;">
      </div>
      <div class="fee-row">
        <span>Transfer fee</span>
        <strong style="color:var(--w-green)">Free</strong>
      </div>
      <button class="w-confirm-btn" id="sendConfirmBtn" onclick="confirmSend()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
        Send Money
      </button>
    </div>
  </div>
</div>

<!-- ══ TRANSACTION DETAIL MODAL ══ -->
<div class="w-modal-overlay" id="txnDetailModal">
  <div class="w-modal">
    <div class="w-modal-head">
      <h3>Transaction Details</h3>
      <button class="w-modal-close" onclick="closeModal('txnDetailModal')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
    </div>
    <div class="w-modal-body" id="txnDetailBody"></div>
  </div>
</div>

<!-- ══ MOBILE FAB ══ -->
<button class="w-fab" onclick="openModal('depositModal')" aria-label="Deposit">
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
</button>

<!-- ══ JAVASCRIPT ══ -->
<script>
/* ─── PHP data bridged to JS ─────────────────────────── */
const WALLET_DATA = {
  userId:       <?= $currentUserId ?>,
  balance:      <?= $balance ?>,
  displayName:  <?= json_encode($displayName) ?>,
  displayEmail: <?= json_encode($displayEmail) ?>,
  monthLabels:  <?= json_encode($monthLabels) ?>,
  monthIncome:  <?= json_encode($monthlyIncome) ?>,
  monthExpense: <?= json_encode($monthlyExpense) ?>,
  balanceTrend: <?= json_encode($balanceTrend) ?>,
  dayLabels:    <?= json_encode($dayLabels) ?>,
  sources: {
    marketplace: <?= round(($srcMarketplace/$srcTotal)*100) ?>,
    donations:   <?= round(($srcDonations/$srcTotal)*100) ?>,
    other:       <?= round(($srcOther/$srcTotal)*100) ?>
  }
};

const TRANSACTIONS_DATA = <?= json_encode(array_map(fn($t) => [
  'id'       => $t['id'],
  'rawId'    => $t['rawId'],
  'title'    => $t['title'],
  'amount'   => $t['amount'],
  'date'     => $t['dateStr'],
  'time'     => $t['timeStr'],
  'status'   => $t['status'],
  'category' => $t['category'],
  'type'     => $t['type'],
], $transactions)) ?>;

let currentBalance = WALLET_DATA.balance;

/* ─── Theme ─────────────────────────────────────── */
document.querySelector('.theme-toggle')?.addEventListener('click', () => {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const next   = isDark ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  try { localStorage.setItem('app_theme', next); } catch(e) {}
  updateChartTheme();
});

/* ─── Modal helpers (Integrated with Global System) ───────── */
function openModal(id) {
  if (typeof window.openGlobalModal === 'function') {
    window.openGlobalModal(id);
  } else {
    // Fallback if main.js is not loaded
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  }
}
function closeModal(id) {
  if (typeof window.closeGlobalModal === 'function') {
    window.closeGlobalModal(id);
  } else {
    // Fallback if main.js is not loaded
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
  }
}
// Note: Global listeners in main.js handle Esc and Overlay-click


/* ─── Amount presets ─────────────────────────── */
function setAmount(inputId, val) {
  const el = document.getElementById(inputId);
  if (el) { el.value = val; el.dispatchEvent(new Event('input')); }
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('selected'));
  event?.target?.classList?.add('selected');
}

/* ─── Deposit fee calculation ────────────────── */
const depositAmtEl = document.getElementById('depositAmount');
depositAmtEl.addEventListener('input', () => {
  const amt = parseFloat(depositAmtEl.value) || 0;
  const fee = amt > 0 ? (amt * 0.014 + 0.25) : 0;
  document.getElementById('depositFee').textContent    = '$' + fee.toFixed(2);
  document.getElementById('depositReceive').textContent = '$' + Math.max(0, amt - fee).toFixed(2);
});

/* ─── Withdraw calc ───────────────────────────── */
document.getElementById('withdrawAmount').addEventListener('input', function() {
  const amt = parseFloat(this.value) || 0;
  document.getElementById('withdrawReceive').textContent = '$' + amt.toFixed(2);
});

/* ─── Radio row highlight ─────────────────────── */
document.querySelectorAll('input[type="radio"]').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll(`input[name="${r.name}"]`).forEach(rb => {
      rb.closest('.pm-select-row').classList.toggle('active', rb.checked);
    });
  });
});

/* ─── API call helper ─────────────────────────── */
async function walletAction(type, payload) {
  const resp = await fetch('?action=wallet_action', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type, ...payload })
  });
  return resp.json();
}

/* ─── Confirm deposit ─────────────────────────── */
async function confirmDeposit() {
  const amt = parseFloat(document.getElementById('depositAmount').value) || 0;
  if (amt < 1) { showToast('Please enter at least $1.', 'error'); return; }
  const btn = document.getElementById('depositConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="loading-spinner"></div> Processing…';
  try {
    const res = await walletAction('deposit', { amount: amt });
    closeModal('depositModal');
    if (res.success) {
      currentBalance += amt;
      animateBalance(currentBalance);
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 2200);
    } else {
      showToast(res.message || 'Deposit failed.', 'error');
    }
  } catch(e) {
    showToast('Network error. Please retry.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg> Confirm Deposit';
  }
}

/* ─── Confirm withdraw ────────────────────────── */
async function confirmWithdraw() {
  const amt = parseFloat(document.getElementById('withdrawAmount').value) || 0;
  if (amt < 10) { showToast('Minimum withdrawal is $10.', 'error'); return; }
  if (amt > currentBalance) { showToast('Insufficient balance.', 'error'); return; }
  const btn = document.getElementById('withdrawConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="loading-spinner"></div> Processing…';
  try {
    const res = await walletAction('withdraw', { amount: amt });
    closeModal('withdrawModal');
    if (res.success) {
      currentBalance -= amt;
      animateBalance(currentBalance);
      document.getElementById('availableBalance').textContent = '$' + currentBalance.toFixed(2);
      showToast(res.message, 'success');
      setTimeout(() => location.reload(), 2200);
    } else {
      showToast(res.message || 'Withdrawal failed.', 'error');
    }
  } catch(e) {
    showToast('Network error. Please retry.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Confirm Withdrawal';
  }
}

/* ─── Confirm send ────────────────────────────── */
async function confirmSend() {
  const receiverId = parseInt(document.getElementById('sendReceiverId').value) || 0;
  const amt        = parseFloat(document.getElementById('sendAmount').value) || 0;
  const note       = document.getElementById('sendNote').value.trim();
  if (receiverId <= 0) { showToast('Please select a recipient.', 'error'); return; }
  if (amt < 1)    { showToast('Please enter at least $1.', 'error'); return; }
  if (amt > currentBalance) { showToast('Insufficient balance.', 'error'); return; }
  const btn = document.getElementById('sendConfirmBtn');
  btn.disabled = true;
  btn.innerHTML = '<div class="loading-spinner"></div> Sending…';
  try {
    const res = await fetch('../../index.php?action=wallet_send_money', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ receiver_id: receiverId, amount: amt, note })
    });
    const data = await res.json();
    closeModal('sendModal');
    if (data.success) {
      currentBalance = data.new_balance ?? (currentBalance - amt);
      animateBalance(currentBalance);
      showToast(data.message, 'success');
      setTimeout(() => location.reload(), 2200);
    } else {
      showToast(data.message || 'Transfer failed.', 'error');
    }
  } catch(e) {
    showToast('Network error. Please retry.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg> Send Money';
  }
}

/* ─── Quick send prefill with user ID ────────── */
function prefillSendUser(userId, name) {
  document.getElementById('sendReceiverId').value = userId;
  document.getElementById('sendRecipient').value = name;
  document.getElementById('sendRecipient').style.display = 'none';
  document.getElementById('sendRecipientDisplay').style.display = 'block';
  document.getElementById('sendRecipientName').textContent = '💸 Sending to: ' + name;
  openModal('sendModal');
}

function clearRecipient() {
  document.getElementById('sendReceiverId').value = 0;
  document.getElementById('sendRecipient').value = '';
  document.getElementById('sendRecipient').style.display = '';
  document.getElementById('sendRecipientDisplay').style.display = 'none';
}

/* ─── Balance counter animation ─────────────── */
function animateBalance(target) {
  const el = document.getElementById('heroBalanceNum');
  const start = parseFloat(el.textContent.replace(/,/g,'')) || 0;
  const dur = 800;
  const t0  = performance.now();
  function step(now) {
    const p   = Math.min((now - t0) / dur, 1);
    const ease = p < 0.5 ? 2*p*p : -1+(4-2*p)*p;
    el.textContent = (start + (target - start) * ease).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    if (p < 1) requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}

/* ─── Transaction detail modal ───────────────── */
function openTxnDetail(txn) {
  const isPos     = txn.amount > 0;
  const amtStr    = (isPos ? '+$' : '-$') + Math.abs(txn.amount).toFixed(2);
  const amtColor  = isPos ? 'var(--w-green)' : 'var(--w-red)';
  const statusColors = { completed:'#059669', pending:'#d97706', failed:'#dc2626' };
  document.getElementById('txnDetailBody').innerHTML = `
    <div>
      <div class="txn-detail-row"><span>Transaction ID</span><strong>${txn.id}</strong></div>
      <div class="txn-detail-row"><span>Description</span><strong>${txn.title}</strong></div>
      <div class="txn-detail-row"><span>Amount</span><strong style="color:${amtColor};font-size:1.1rem">${amtStr}</strong></div>
      <div class="txn-detail-row"><span>Type</span><strong style="text-transform:capitalize">${txn.type}</strong></div>
      <div class="txn-detail-row"><span>Category</span><strong style="text-transform:capitalize">${txn.category}</strong></div>
      <div class="txn-detail-row"><span>Date</span><strong>${txn.date} at ${txn.time}</strong></div>
      <div class="txn-detail-row"><span>Status</span><strong style="color:${statusColors[txn.status]??'inherit'}">${txn.status.charAt(0).toUpperCase()+txn.status.slice(1)}</strong></div>
    </div>
  `;
  openModal('txnDetailModal');
}

/* ─── Filter tabs ────────────────────────────── */
const filterMap = {
  all:         () => true,
  income:      r => r.dataset.type === 'income',
  expense:     r => r.dataset.type === 'expense',
  donation:    r => r.dataset.type === 'donation',
  marketplace: r => r.dataset.category === 'marketplace',
  withdrawal:  r => r.dataset.category === 'withdrawal',
};

document.querySelectorAll('.ftab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    applyFilter();
  });
});

function applyFilter() {
  const activeFilter = document.querySelector('.ftab.active').dataset.filter;
  const search = document.getElementById('txnSearch').value.toLowerCase();
  const rows   = document.querySelectorAll('.txn-row');
  let visible  = 0;
  rows.forEach(r => {
    const passFilter = filterMap[activeFilter]?.(r) ?? true;
    const passSearch = !search || r.dataset.title.includes(search);
    r.style.display  = passFilter && passSearch ? '' : 'none';
    if (passFilter && passSearch) visible++;
  });
  document.getElementById('txnEmpty').style.display = visible === 0 ? 'block' : 'none';
}

document.getElementById('txnSearch').addEventListener('input', applyFilter);

/* ─── REAL CSV Export ────────────────────────── */
function exportCSV() {
  const visible = TRANSACTIONS_DATA.filter(t => {
    const activeFilter = document.querySelector('.ftab.active')?.dataset.filter || 'all';
    if (activeFilter === 'all') return true;
    if (activeFilter === 'income')      return t.type === 'income';
    if (activeFilter === 'expense')     return t.type === 'expense';
    if (activeFilter === 'donation')    return t.type === 'donation';
    if (activeFilter === 'marketplace') return t.category === 'marketplace';
    if (activeFilter === 'withdrawal')  return t.category === 'withdrawal';
    return true;
  });

  const headers = ['Transaction ID','Description','Amount','Type','Category','Date','Time','Status'];
  const rows    = visible.map(t => [
    t.id, `"${t.title.replace(/"/g,'""')}"`,
    t.amount.toFixed(2), t.type, t.category, t.date, t.time, t.status
  ]);

  const summary = [
    [], ['=== WALLET SUMMARY ==='],
    [`Account,${<?= json_encode($displayName) ?>}`],
    [`Email,${<?= json_encode($displayEmail) ?>}`],
    [`Current Balance,$${currentBalance.toFixed(2)}`],
    [`Export Date,${new Date().toLocaleDateString()}`],
    [], ['=== TRANSACTIONS ===']
  ];

  const csvContent = [
    ...summary.map(r => r.join(',')),
    headers.join(','),
    ...rows.map(r => r.join(','))
  ].join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = Object.assign(document.createElement('a'), {
    href: url, download: `wallet-export-${new Date().toISOString().slice(0,10)}.csv`
  });
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
  showToast('CSV downloaded successfully!', 'success');
}

/* ─── REAL PDF Export ─────────────────────────── */
function exportPDF() {
  if (typeof window.jspdf === 'undefined') {
    showToast('PDF library loading, please retry in a moment.', 'error');
    return;
  }

  showToast('Generating PDF report…', 'success');
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

  const accentR = 99, accentG = 102, accentB = 241;
  const pageW   = doc.internal.pageSize.getWidth();
  const pageH   = doc.internal.pageSize.getHeight();

  /* Header gradient (simulated) */
  doc.setFillColor(30, 27, 75);
  doc.rect(0, 0, pageW, 52, 'F');
  doc.setFillColor(99, 102, 241);
  doc.rect(0, 36, pageW, 16, 'F');

  /* Logo text */
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(22);
  doc.setFont('helvetica', 'bold');
  doc.text('Diversity.is', 14, 18);

  doc.setFontSize(10);
  doc.setFont('helvetica', 'normal');
  doc.setTextColor(200, 200, 255);
  doc.text('Wallet Statement Report', 14, 26);
  doc.text(`Generated: ${new Date().toLocaleString()}`, 14, 32);

  /* Balance strip */
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(9);
  doc.setFont('helvetica', 'bold');
  doc.text('CURRENT BALANCE', 14, 44);
  doc.setFontSize(16);
  doc.text(`$${currentBalance.toLocaleString('en-US',{minimumFractionDigits:2})}`, 14, 50);

  doc.setFontSize(9);
  doc.text(`Account: ${<?= json_encode($displayName) ?>}`, pageW - 80, 44);
  doc.text(`Email: ${<?= json_encode($displayEmail) ?>}`, pageW - 80, 50);

  /* Summary cards */
  const cards = [
    { label: 'Total Earnings', value: `$${<?= $totalEarnings ?>}` },
    { label: 'Total Spent',    value: `$${<?= $totalSpent ?>}` },
    { label: 'Pending',        value: `$${<?= $pendingBalance ?>}` },
    { label: 'Avg Txn Value',  value: `$${<?= round($avgTxnValue, 2) ?>}` },
  ];
  const cardW = (pageW - 28 - 9) / 4;
  cards.forEach((c, i) => {
    const x = 14 + i * (cardW + 3);
    doc.setFillColor(245, 247, 255);
    doc.roundedRect(x, 60, cardW, 20, 2, 2, 'F');
    doc.setTextColor(100, 100, 140);
    doc.setFontSize(7);
    doc.setFont('helvetica', 'normal');
    doc.text(c.label.toUpperCase(), x + 3, 67);
    doc.setTextColor(30, 30, 60);
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.text(c.value, x + 3, 75);
  });

  /* Transactions table */
  doc.setFontSize(11);
  doc.setFont('helvetica', 'bold');
  doc.setTextColor(30, 30, 60);
  doc.text('Transaction History', 14, 92);

  const tableRows = TRANSACTIONS_DATA.map(t => [
    t.id,
    t.title.length > 30 ? t.title.slice(0,28)+'…' : t.title,
    (t.amount >= 0 ? '+' : '') + '$' + Math.abs(t.amount).toFixed(2),
    t.type.charAt(0).toUpperCase()+t.type.slice(1),
    t.date,
    t.status.charAt(0).toUpperCase()+t.status.slice(1)
  ]);

  doc.autoTable({
    startY: 96,
    head: [['ID','Description','Amount','Type','Date','Status']],
    body: tableRows,
    headStyles: {
      fillColor: [accentR, accentG, accentB],
      textColor: 255,
      fontStyle: 'bold',
      fontSize: 8
    },
    bodyStyles: { fontSize: 7.5, textColor: [40,40,60] },
    alternateRowStyles: { fillColor: [248,248,255] },
    columnStyles: {
      0: { cellWidth: 24 },
      1: { cellWidth: 56 },
      2: { cellWidth: 22, halign: 'right' },
      3: { cellWidth: 20 },
      4: { cellWidth: 22 },
      5: { cellWidth: 22 }
    },
    margin: { left: 14, right: 14 },
    didParseCell: (data) => {
      if (data.column.index === 2 && data.section === 'body') {
        const val = data.cell.raw;
        data.cell.styles.textColor = val.startsWith('+') ? [5,150,105] : [220,38,38];
        data.cell.styles.fontStyle = 'bold';
      }
      if (data.column.index === 5 && data.section === 'body') {
        const val = data.cell.raw;
        data.cell.styles.textColor =
          val === 'Completed' ? [5,150,105] :
          val === 'Pending'   ? [217,119,6] : [220,38,38];
      }
    }
  });

  /* Footer */
  const finalY = doc.lastAutoTable?.finalY || 200;
  doc.setFontSize(8);
  doc.setTextColor(140,140,160);
  doc.setFont('helvetica','normal');
  doc.text('This document is auto-generated by Diversity.is · All figures in USD', 14, Math.min(finalY+10, pageH-14));
  doc.text(`Page 1 of 1  ·  Exported ${new Date().toISOString().slice(0,10)}`, pageW-70, Math.min(finalY+10, pageH-14));

  doc.save(`wallet-report-${new Date().toISOString().slice(0,10)}.pdf`);
  showToast('PDF report downloaded!', 'success');
}

/* ─── Toast helper ───────────────────────────── */
function showToast(msg, type = 'success') {
  const wrap = document.getElementById('wToastWrap');
  const t = document.createElement('div');
  t.className = 'w-toast ' + type;
  t.innerHTML = (type === 'success'
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
  ) + `<span>${msg}</span>`;
  wrap.appendChild(t);
  setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(24px)'; t.style.transition='all 0.3s'; setTimeout(() => t.remove(), 300); }, 4000);
}

/* ─── Charts ─────────────────────────────────── */
let balanceChartInst, monthlyChartInst, sourceChartInst, sidebarChartInst;

function getChartColors() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return {
    grid:   isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)',
    text:   isDark ? 'rgba(255,255,255,0.45)' : 'rgba(0,0,0,0.45)',
    accent: 'rgba(99,102,241,',
    green:  'rgba(16,185,129,',
    red:    'rgba(239,68,68,',
  };
}

function buildBalanceChart(period) {
  const c = getChartColors();
  const ctx = document.getElementById('balanceChart');
  if (!ctx) return;
  if (balanceChartInst) balanceChartInst.destroy();

  let labels, data;
  if (period === '7d') {
    labels = WALLET_DATA.dayLabels;
    data   = WALLET_DATA.balanceTrend;
  } else if (period === '30d') {
    labels = Array.from({length:30}, (_,i) => {
      const d = new Date(); d.setDate(d.getDate()-29+i);
      return d.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    }).filter((_,i)=>i%5===0);
    data   = labels.map((_,i) => Math.max(0, WALLET_DATA.balance * (0.7 + 0.3 * i / labels.length) + Math.random()*200));
  } else {
    labels = WALLET_DATA.monthLabels;
    data   = WALLET_DATA.monthIncome.map((v,i) => Math.max(0, WALLET_DATA.balance - WALLET_DATA.monthExpense.reduce((s,x)=>s+x,0) + WALLET_DATA.monthIncome.slice(0,i+1).reduce((s,x)=>s+x,0)));
  }

  const fillGradient = ctx.canvas.getContext('2d').createLinearGradient(0, 0, 0, 300);
  fillGradient.addColorStop(0, 'rgba(99,102,241,0.15)');
  fillGradient.addColorStop(1, 'rgba(99,102,241,0)');

  balanceChartInst = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Balance',
        data,
        borderColor: 'rgba(99,102,241,1)',
        backgroundColor: fillGradient,
        borderWidth: 3,
        fill: true,
        tension: 0.45,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointHitRadius: 20,
        pointBackgroundColor: 'rgba(99,102,241,1)',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: {
        callbacks: { label: ctx => ' $' + ctx.raw.toFixed(2) }
      }},
      scales: {
        x: { grid: { color: c.grid }, ticks: { color: c.text, font: { size: 10, family: 'Poppins' } } },
        y: { grid: { color: c.grid }, ticks: { color: c.text, font: { size: 10, family: 'Poppins' }, callback: v => '$'+v.toFixed(0) } }
      }
    }
  });
}

function updateBalanceChart(period) { buildBalanceChart(period); }

function buildMonthlyChart() {
  const c = getChartColors();
  const ctx = document.getElementById('monthlyChart');
  if (!ctx) return;
  if (monthlyChartInst) monthlyChartInst.destroy();
  const incGrad = ctx.canvas.getContext('2d').createLinearGradient(0, 0, 0, 200);
  incGrad.addColorStop(0, 'rgba(99,102,241,0.8)');
  incGrad.addColorStop(1, 'rgba(168,85,247,0.7)');

  const expGrad = ctx.canvas.getContext('2d').createLinearGradient(0, 0, 0, 200);
  expGrad.addColorStop(0, 'rgba(239,68,68,0.7)');
  expGrad.addColorStop(1, 'rgba(244,63,94,0.6)');

  monthlyChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: WALLET_DATA.monthLabels,
      datasets: [
        { label: 'Income', data: WALLET_DATA.monthIncome, backgroundColor: incGrad, borderRadius: 6, borderSkipped: false },
        { label: 'Spent',  data: WALLET_DATA.monthExpense, backgroundColor: expGrad, borderRadius: 6, borderSkipped: false }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` $${ctx.raw.toFixed(2)}` } }
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: c.text, font: { size: 10, family: 'Poppins' } } },
        y: { grid: { color: c.grid }, ticks: { color: c.text, font: { size: 10, family: 'Poppins' }, callback: v => '$'+v } }
      }
    }
  });
}

function buildSourceChart() {
  const ctx = document.getElementById('sourceChart');
  if (!ctx) return;
  if (sourceChartInst) sourceChartInst.destroy();
  const { marketplace, donations, other } = WALLET_DATA.sources;
  sourceChartInst = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Marketplace','Donations','Other'],
      datasets: [{
        data: [marketplace, donations, other],
        backgroundColor: ['rgba(99,102,241,0.85)','rgba(168,85,247,0.85)','rgba(16,185,129,0.85)'],
        borderWidth: 2,
        borderColor: 'transparent',
        hoverOffset: 8
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}%` } }
      }
    }
  });
}

function buildSidebarChart() {
  const c = getChartColors();
  const ctx = document.getElementById('sidebarChart');
  if (!ctx) return;
  if (sidebarChartInst) sidebarChartInst.destroy();
  const income  = [420, 850, 0, 640, 0, 1200, 0];
  const expense = [0, 300, 12, 0, 75, 0, 500];
  const incGrad = ctx.canvas.getContext('2d').createLinearGradient(0, 0, 0, 100);
  incGrad.addColorStop(0, 'rgba(16,185,129,0.8)');
  incGrad.addColorStop(1, 'rgba(16,185,129,0.4)');

  const expGrad = ctx.canvas.getContext('2d').createLinearGradient(0, 0, 0, 100);
  expGrad.addColorStop(0, 'rgba(239,68,68,0.7)');
  expGrad.addColorStop(1, 'rgba(239,68,68,0.3)');

  sidebarChartInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [
        { label: 'Income', data: income, backgroundColor: incGrad, borderRadius: 4, borderSkipped: false },
        { label: 'Spent',  data: expense, backgroundColor: expGrad, borderRadius: 4, borderSkipped: false }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: c.text, font: { size: 9 } } },
        y: { display: false }
      }
    }
  });
}

function updateChartTheme() {
  buildBalanceChart(document.getElementById('balanceChartPeriod')?.value || '6m');
  buildMonthlyChart();
  buildSidebarChart();
}

/* ─── Init all charts ─────────────────────────── */
window.addEventListener('load', () => {
  buildBalanceChart('6m');
  buildMonthlyChart();
  buildSourceChart();
  buildSidebarChart();
  lucide.createIcons();
  
  // Entrance animations
  setTimeout(() => {
    animateBalance(WALLET_DATA.balance);
  }, 300);
});
</script>
  <script src="../../assets/js/home.js"></script>
</body>
</html>