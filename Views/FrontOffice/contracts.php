<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/ContractController.php');
include_once(__DIR__ . '/../../config.php');
if (!UserController::isAuthenticated()) { header('Location: auth.php'); exit; }

$pdo         = config::getConnexion();
$controller  = new ContractController($pdo);
$currentUser = UserController::currentUser() ?? [];
$userId      = (int)($currentUser['id'] ?? 0);
$role        = strtolower((string)($currentUser['role'] ?? 'user'));
$titleRole   = strtolower(trim((string)($currentUser['title'] ?? '')));
$resolvedRole = $role;
if (str_contains($titleRole, 'client')) {
  $resolvedRole = 'client';
} elseif (str_contains($titleRole, 'freelancer')) {
  $resolvedRole = 'freelancer';
}
if (!in_array($resolvedRole, ['client', 'freelancer', 'admin'], true) && $userId > 0) {
  $hasUserTitleColumn = false;
  try {
    $titleColumnStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'title'");
    $hasUserTitleColumn = (bool) ($titleColumnStmt && $titleColumnStmt->fetch(PDO::FETCH_ASSOC));
  } catch (Throwable $e) {
    $hasUserTitleColumn = false;
  }

  $roleQuery = $hasUserTitleColumn
    ? 'SELECT role, title FROM users WHERE id = :id LIMIT 1'
    : 'SELECT role FROM users WHERE id = :id LIMIT 1';
  $roleStmt = $pdo->prepare($roleQuery);
  $roleStmt->execute(['id' => $userId]);
  $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
  $dbRole = strtolower(trim((string)($roleRow['role'] ?? '')));
  $dbTitle = strtolower(trim((string)($roleRow['title'] ?? '')));
  if (in_array($dbRole, ['client', 'freelancer', 'admin'], true)) {
    $resolvedRole = $dbRole;
  } elseif (str_contains($dbTitle, 'client')) {
    $resolvedRole = 'client';
  } elseif (str_contains($dbTitle, 'freelancer')) {
    $resolvedRole = 'freelancer';
  }
}
$ownsClientOffers = false;
if ($userId > 0 && !in_array($resolvedRole, ['client', 'admin'], true)) {
  try {
    $clientOfferStmt = $pdo->prepare('SELECT id FROM job_offers WHERE client_id = :client_id LIMIT 1');
    $clientOfferStmt->execute(['client_id' => $userId]);
    $ownsClientOffers = (bool) $clientOfferStmt->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $ownsClientOffers = false;
  }
  if ($ownsClientOffers) {
    $resolvedRole = 'client';
  }
}
$isClient    = UserController::isAdmin() || $resolvedRole === 'client';
$isFreelancer = !$isClient;
$firstName   = trim((string)($currentUser['first_name'] ?? 'Member'));
$lastName    = trim((string)($currentUser['last_name'] ?? ''));
$displayName = trim($firstName . ' ' . $lastName) ?: 'Member';
$displayEmail = (string)($currentUser['email'] ?? '');
$isAdminSidebar = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$navInitials = strtoupper(substr($firstName, 0, 1) . substr($lastName ?: 'M', 0, 1));
$reputationScore = 84; $level = 'Level 12'; $streak = 7;

if (empty($_SESSION['csrf_token_front'])) { $_SESSION['csrf_token_front'] = bin2hex(random_bytes(24)); }
$csrfToken = (string)$_SESSION['csrf_token_front'];
$notice    = $_SESSION['contract_notice_front'] ?? ['type' => '', 'message' => ''];
unset($_SESSION['contract_notice_front']);
$clean     = static fn($v): string => trim((string)($v ?? ''));
$currentPage = basename(parse_url((string)($_SERVER['REQUEST_URI'] ?? 'contracts.php'), PHP_URL_PATH) ?: 'contracts.php');
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
            if ($action === 'create_contract' && $isClient) {
              $pair = $clean($_POST['application_pair'] ?? '');
              [$offerRaw, $freelancerRaw] = array_pad(explode(':', $pair, 2), 2, '0');
              $offerId = (int) $offerRaw;
              $freelancerId = (int) $freelancerRaw;

              if ($offerId <= 0 || $freelancerId <= 0) {
                throw new RuntimeException('Please select an accepted application.');
              }
              if (!$controller->canClientCreateContractForPair($userId, $offerId, $freelancerId)) {
                throw new RuntimeException('This contract cannot be created (already exists or application is not accepted).');
              }

              $contractId = $controller->create([
                'job_offer_id' => $offerId,
                'client_id' => $userId,
                'freelancer_id' => $freelancerId,
                'terms' => $clean($_POST['terms'] ?? ''),
                'amount' => $_POST['amount'] ?? '',
                'starts_at' => $clean($_POST['starts_at'] ?? ''),
                'ends_at' => $clean($_POST['ends_at'] ?? ''),
                'payment_details' => $clean($_POST['payment_details'] ?? ''),
                'created_by_client_id' => $userId,
              ]);
              $notice = ['type' => 'success', 'message' => 'Contract created. Add the rules to continue.'];
              $redirectAfterPost = 'contracts.php?contract=' . $contractId . '&open_rules=1#contract-' . $contractId;
            }

            if ($action === 'update_contract' && $isClient) {
              $contractId = (int)($_POST['contract_id'] ?? 0);
              $updated = $controller->updateByClient($contractId, $userId, [
                    'terms' => $clean($_POST['terms'] ?? ''),
                    'amount' => $_POST['amount'] ?? '',
                    'starts_at' => $clean($_POST['starts_at'] ?? ''),
                    'ends_at' => $clean($_POST['ends_at'] ?? ''),
                    'payment_details' => $clean($_POST['payment_details'] ?? ''),
                ]);
              if (!$updated) {
                throw new RuntimeException('You can only update your own unsigned contracts.');
              }
              $notice = ['type' => 'success', 'message' => 'Contract updated.'];
              $redirectAfterPost = 'contracts.php?contract=' . $contractId . '#contract-' . $contractId;
            }
            if ($action === 'save_rules' && $isClient) {
              $contractId = (int)($_POST['contract_id'] ?? 0);
              $controller->saveRulesByClient($contractId, $userId, [
                'rule_titles' => array_map($clean, (array)($_POST['rule_titles'] ?? [])),
                'rule_types' => array_map($clean, (array)($_POST['rule_types'] ?? [])),
                'rule_descriptions' => array_map($clean, (array)($_POST['rule_descriptions'] ?? [])),
                'rule_due_dates' => array_map($clean, (array)($_POST['rule_due_dates'] ?? [])),
                'rule_penalties' => array_map($clean, (array)($_POST['rule_penalties'] ?? [])),
              ]);
              $notice = ['type' => 'success', 'message' => 'Rules saved. You can now sign the contract.'];
              $redirectAfterPost = 'contracts.php?contract=' . $contractId . '#contract-' . $contractId;
            }
            if ($action === 'delete_contract' && $isClient) {
              $contractId = (int)($_POST['contract_id'] ?? 0);
              $deleted = $controller->deleteByClient($contractId, $userId);
              if (!$deleted) {
                throw new RuntimeException('You can only delete your own unsigned contracts.');
              }
              $notice = ['type' => 'success', 'message' => 'Contract deleted.'];
              $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'sign_client_contract' && $isClient) {
              $contractId = (int)($_POST['contract_id'] ?? 0);
              $signed = $controller->signByClient($contractId, $userId, $clean($_POST['client_signature'] ?? ''));
              if (!$signed) {
                throw new RuntimeException('Unable to sign this contract.');
              }
              $notice = ['type' => 'success', 'message' => 'Client signature saved. Waiting for the freelancer signature.'];
              $redirectAfterPost = 'contracts.php?contract=' . $contractId . '#contract-' . $contractId;
            }
            if ($action === 'sign_contract' && $isFreelancer) {
              $contractId = (int)($_POST['contract_id'] ?? 0);
              $signed = $controller->signByFreelancer($contractId, $userId, $clean($_POST['freelancer_signature'] ?? ''));
              if (!$signed) {
                throw new RuntimeException('Unable to sign this contract.');
              }
              $notice = ['type' => 'success', 'message' => 'Contract signed successfully.'];
              $redirectAfterPost = 'contracts.php?contract=' . $contractId . '#contract-' . $contractId;
            }
        } catch (Throwable $e) { $notice = ['type' => 'error', 'message' => $e->getMessage()]; }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $_SESSION['contract_notice_front'] = $notice;
  header('Location: ' . ($redirectAfterPost !== '' ? $redirectAfterPost : $selfRedirect));
  exit;
}
$contracts = $controller->listUserContracts($userId);
$acceptedPairs = $isClient ? $controller->listClientAcceptedApplicationsWithoutContract($userId) : [];
$selectedContractId = (int)($_GET['contract'] ?? 0);
$prefillOfferId = (int)($_GET['offer_id'] ?? 0);
$prefillFreelancerId = (int)($_GET['freelancer_id'] ?? 0);
$openCreateModal = $isClient && ((int)($_GET['open_create'] ?? 0) === 1 || ($prefillOfferId > 0 && $prefillFreelancerId > 0));
$openRulesModal = $isClient && ((int)($_GET['open_rules'] ?? 0) === 1) && $selectedContractId > 0;
$selectedContractRules = [];
foreach ($contracts as $contractRowForRules) {
  if ((int)($contractRowForRules['id'] ?? 0) === $selectedContractId) {
    $selectedContractRules = is_array($contractRowForRules['rules'] ?? null) ? $contractRowForRules['rules'] : [];
    break;
  }
}
$localDateTime = static function (?string $value): string {
  if (!$value) {
    return '';
  }
  $ts = strtotime($value);
  return $ts ? date('Y-m-d\\TH:i', $ts) : '';
};
$statTotals = ['total'=>0,'waiting'=>0,'finalized'=>0,'pending'=>0];
$freelancerPendingToSign = 0;
foreach ($contracts as $c) {
    $statTotals['total']++;
    $s = (string)($c['status'] ?? 'draft');
    if ($s === 'waiting') $statTotals['waiting']++;
    if ($s === 'finalized') $statTotals['finalized']++;
    if ($s === 'draft')  $statTotals['pending']++;
    if ($isFreelancer && (int)($c['freelancer_id'] ?? 0) === $userId && $s === 'waiting') {
      $freelancerPendingToSign++;
    }
}
$statusMeta = [
    'draft'     => ['bg'=>'rgba(107,114,128,.1)', 'text'=>'#6b7280','dot'=>'#9ca3af','label'=>'Draft'],
    'waiting'   => ['bg'=>'rgba(99,102,241,.1)',  'text'=>'#6366f1','dot'=>'#6366f1','label'=>'Waiting'],
    'finalized' => ['bg'=>'rgba(5,150,105,.1)',   'text'=>'#059669','dot'=>'#10b981','label'=>'Finalized'],
    'expired'   => ['bg'=>'rgba(245,158,11,.1)',  'text'=>'#d97706','dot'=>'#f59e0b','label'=>'Expired'],
    'cancelled' => ['bg'=>'rgba(225,29,72,.08)',  'text'=>'#be123c','dot'=>'#f43f5e','label'=>'Cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage your contracts on Diversity.is.">
  <title>Contracts — Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    .module-page-header { margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .module-page-header h2 { font-size:1.35rem; font-weight:700; letter-spacing:-.02em; color:var(--color-text-primary); margin:0; }
    .module-page-header p  { font-size:.84rem; color:var(--color-text-secondary); margin:4px 0 0; }
    .module-notice { display:flex; align-items:center; gap:9px; padding:11px 15px; border-radius:12px; margin-bottom:18px; font-size:.84rem; font-weight:500; animation:mnotice .3s ease; }
    .module-notice.success { background:rgba(5,150,105,.09); border:1px solid rgba(5,150,105,.22); color:#065f46; }
    .module-notice.error   { background:rgba(225,29,72,.08); border:1px solid rgba(225,29,72,.18); color:#9f1239; }
    .module-notice.info    { background:rgba(59,130,246,.08); border:1px solid rgba(59,130,246,.18); color:#1d4ed8; }
    @keyframes mnotice { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

    /* Info banner */
    .ct-info { display:flex; align-items:flex-start; gap:11px; background:linear-gradient(135deg,rgba(99,102,241,.05),rgba(14,165,233,.04)); border:1px solid rgba(99,102,241,.13); border-radius:12px; padding:14px 17px; margin-bottom:18px; }
    .ct-info svg { color:var(--color-accent); flex-shrink:0; margin-top:1px; }
    .ct-info-text h4 { font-size:.84rem; font-weight:600; color:var(--color-text-primary); margin:0; }
    .ct-info-text p  { font-size:.78rem; color:var(--color-text-secondary); margin:3px 0 0; }

    /* Stats */
    .ct-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:18px; }
    .ct-stat { background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:13px 15px; display:flex; align-items:center; gap:11px; }
    .ct-stat-icon { width:38px; height:38px; border-radius:9px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .ct-stat-label { font-size:.72rem; color:var(--color-text-secondary); font-weight:500; }
    .ct-stat-value { font-size:1.4rem; font-weight:700; color:var(--color-text-primary); line-height:1; }

    /* Contract cards */
    .ct-list { display:flex; flex-direction:column; gap:14px; }
    .ct-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:16px; overflow:hidden; transition:box-shadow .22s,border-color .22s; }
    .ct-card:hover { box-shadow:0 6px 22px rgba(0,0,0,.06); border-color:var(--color-border-strong); }
    .ct-card-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; padding:15px 18px; border-bottom:1px solid var(--color-border); }
    .ct-card-header-left { display:flex; align-items:center; gap:10px; }
    .ct-contract-icon { width:36px; height:36px; border-radius:9px; background:var(--color-accent-light); display:flex; align-items:center; justify-content:center; color:var(--color-accent); flex-shrink:0; }
    .ct-contract-title { font-size:.92rem; font-weight:700; color:var(--color-text-primary); }
    .ct-contract-sub   { font-size:.74rem; color:var(--color-text-secondary); margin-top:1px; }
    .ct-status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:999px; font-size:.7rem; font-weight:600; }
    .ct-status-dot { width:5px; height:5px; border-radius:50%; }
    .ct-card-body { padding:15px 18px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:13px; }
    .ct-field-label { font-size:.71rem; color:var(--color-text-muted); font-weight:500; margin-bottom:2px; }
    .ct-field-value { font-size:.88rem; font-weight:600; color:var(--color-text-primary); }
    .ct-field-value.amount { font-size:1.05rem; color:var(--color-accent); }
    .ct-flow-steps { display:flex; flex-wrap:wrap; gap:8px; padding:14px 18px 0; }
    .ct-flow-step { display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; font-size:.7rem; font-weight:700; letter-spacing:.01em; background:var(--color-surface-2); color:var(--color-text-muted); border:1px solid var(--color-border); transition:all .18s ease; }
    .ct-flow-step.done { background:rgba(5,150,105,.1); color:#065f46; border-color:rgba(5,150,105,.22); }
    .ct-flow-step.current { background:rgba(99,102,241,.1); color:#4338ca; border-color:rgba(99,102,241,.18); }
    .ct-terms-block { padding:12px 18px; font-size:.81rem; color:var(--color-text-secondary); line-height:1.65; border-top:1px solid var(--color-border); white-space:pre-line; }
    .ct-rules-grid { padding:12px 18px; border-top:1px solid var(--color-border); display:grid; grid-template-columns:1fr 1fr; gap:12px; background:var(--color-surface); }
    .ct-rules-grid .full { grid-column:1/-1; }
    .ct-rules-copy { font-size:.79rem; color:var(--color-text-secondary); line-height:1.6; white-space:pre-line; }
    .ct-rules-empty { padding:12px 18px; border-top:1px solid var(--color-border); font-size:.79rem; color:var(--color-text-secondary); background:rgba(148,163,184,.06); }
    .ct-rules-section { padding:13px 18px; border-top:1px solid var(--color-border); background:var(--color-surface); }
    .ct-rules-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .ct-rules-head h4 { margin:0; font-size:.83rem; color:var(--color-text-primary); }
    .ct-rule-list { display:grid; gap:9px; }
    .ct-rule-item { border:1px solid var(--color-border); border-radius:8px; padding:10px 11px; background:var(--color-surface-2); }
    .ct-rule-top { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:5px; }
    .ct-rule-title { font-size:.8rem; font-weight:700; color:var(--color-text-primary); }
    .ct-rule-type { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--color-accent); background:var(--color-accent-light); border-radius:999px; padding:2px 7px; white-space:nowrap; }
    .ct-rule-desc { font-size:.76rem; color:var(--color-text-secondary); line-height:1.55; white-space:pre-line; }
    .ct-rule-meta { display:flex; flex-wrap:wrap; gap:7px; margin-top:7px; font-size:.68rem; color:var(--color-text-muted); }
    .ct-dynamic-rule { border:1px solid var(--color-border); border-radius:8px; padding:12px; background:var(--color-surface-2); margin-bottom:10px; }
    .ct-dynamic-rule-head { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
    .ct-dynamic-rule-head strong { font-size:.79rem; color:var(--color-text-primary); }
    .ct-card-footer { padding:12px 18px; background:var(--color-surface-2); border-top:1px solid var(--color-border); display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

    /* Edit panel */
    .ct-edit-panel { display:none; padding:15px 18px; border-top:1px solid var(--color-border); background:var(--color-surface-2); }
    .ct-edit-panel.open { display:block; }
    .ct-edit-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    .ct-edit-grid .full { grid-column:1/-1; }
    .ct-edit-label { font-size:.76rem; font-weight:600; color:var(--color-text-secondary); display:block; margin-bottom:4px; }
    .ct-edit-input, .ct-edit-select, .ct-edit-textarea { width:100%; padding:7px 10px; background:var(--color-surface); border:1px solid var(--color-border-strong); border-radius:7px; font-size:.82rem; color:var(--color-text-primary); }
    .ct-edit-input:focus, .ct-edit-select:focus, .ct-edit-textarea:focus { border-color:var(--color-accent); outline:none; }
    .ct-edit-textarea { resize:vertical; min-height:72px; }
    .ct-edit-actions { display:flex; gap:7px; margin-top:12px; justify-content:flex-end; }

    /* Buttons */
    .ct-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 12px; border-radius:8px; border:none; cursor:pointer; font-size:.79rem; font-weight:600; transition:all .17s; }
    .ct-btn-primary { background:var(--color-accent); color:#fff; }
    .ct-btn-primary:hover { background:var(--color-accent-hover); }
    .ct-btn-success { background:rgba(5,150,105,.1); color:#065f46; border:1px solid rgba(5,150,105,.22); }
    .ct-btn-success:hover { background:rgba(5,150,105,.16); }
    .ct-btn-danger  { background:rgba(225,29,72,.08); color:#be123c; border:1px solid rgba(225,29,72,.18); }
    .ct-btn-danger:hover { background:rgba(225,29,72,.13); }
    .ct-btn-ghost   { background:var(--color-surface); color:var(--color-text-secondary); border:1px solid var(--color-border); }
    .ct-btn-ghost:hover { color:var(--color-text-primary); }
    .ct-btn[disabled] { opacity:.55; cursor:not-allowed; transform:none; box-shadow:none; }

    /* Create modal */
    .ct-modal-backdrop { position:fixed; inset:0; background:rgba(15,21,42,.42); backdrop-filter:blur(5px); z-index:200; display:none; align-items:center; justify-content:center; padding:20px; }
    .ct-modal-backdrop.open { display:flex; animation:ctfade .2s ease; }
    @keyframes ctfade { from{opacity:0} to{opacity:1} }
    .ct-modal { background:var(--color-surface); border:1px solid var(--color-border-strong); border-radius:20px; padding:26px; width:100%; max-width:640px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-xl); animation:ctmodal .26s cubic-bezier(.16,1,.3,1); }
    @keyframes ctmodal { from{opacity:0;transform:scale(.96) translateY(10px)} to{opacity:1;transform:none} }
    .ct-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .ct-modal-head h3 { font-size:1.05rem; font-weight:700; color:var(--color-text-primary); margin:0; }
    .ct-modal-close { background:none; border:none; cursor:pointer; padding:4px; color:var(--color-text-muted); border-radius:6px; }
    .ct-modal-close:hover { background:var(--color-surface-2); }
    .ct-modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .ct-modal-grid .full { grid-column:1/-1; }
    .ct-modal-fg { display:flex; flex-direction:column; gap:4px; }
    .ct-modal-fl { font-size:.77rem; font-weight:600; color:var(--color-text-secondary); }
    .ct-modal-fi, .ct-modal-sel, .ct-modal-ta { padding:8px 11px; background:var(--color-surface-2); border:1px solid var(--color-border-strong); border-radius:8px; font-size:.84rem; color:var(--color-text-primary); }
    .ct-modal-fi:focus, .ct-modal-sel:focus, .ct-modal-ta:focus { border-color:var(--color-accent); outline:none; }
    .ct-modal-ta { resize:vertical; min-height:88px; }
    .ct-signature-wrap { border:1px dashed var(--color-border-strong); border-radius:12px; padding:10px; background:var(--color-surface); }
    .ct-signature-canvas { width:100%; height:150px; display:block; background:#fff; border:1px solid var(--color-border); border-radius:8px; touch-action:none; cursor:crosshair; }
    .ct-signature-row { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:8px; }
    .ct-signature-help { font-size:.72rem; color:var(--color-text-muted); }
    .ct-sign-modal-copy { font-size:.82rem; color:var(--color-text-secondary); line-height:1.55; margin-bottom:12px; }

    /* Empty */
    .ct-empty { text-align:center; padding:44px 20px; }
    .ct-empty-icon { margin:0 auto 13px; width:52px; height:52px; border-radius:14px; background:var(--color-surface-2); display:flex; align-items:center; justify-content:center; }
    .ct-empty h3 { font-size:.93rem; font-weight:600; color:var(--color-text-primary); }
    .ct-empty p  { font-size:.81rem; color:var(--color-text-secondary); margin-top:5px; }

    @media(max-width:700px){
      .ct-stats { grid-template-columns:1fr 1fr; }
      .ct-card-body { grid-template-columns:1fr 1fr; }
      .ct-edit-grid { grid-template-columns:1fr 1fr; }
      .ct-rules-grid { grid-template-columns:1fr; }
    }
    @media(max-width:460px){
      .ct-stats { grid-template-columns:1fr; }
      .ct-card-body { grid-template-columns:1fr; }
      .ct-edit-grid { grid-column:1; }
    }
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
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg> Projects &amp; Collaborators</a>
          <a class="active" href="contracts.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg> Contracts</a>
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

      <section class="home-main" aria-label="Contracts">
        <div class="module-page-header">
          <div>
            <h2>Contracts</h2>
            <p><?= $isClient ? 'Create the contract first, add the rules next, then sign when everything is ready.' : 'Review contract details and sign after the client completes the setup.' ?></p>
          </div>
          <?php if ($isClient): ?>
            <button type="button" class="ct-btn ct-btn-primary" onclick="openCreateContractModal()">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Create Contract
            </button>
          <?php endif; ?>
        </div>

        <?php if (!empty($notice['message'])): ?>
        <div class="module-notice <?= htmlspecialchars((string)($notice['type'] ?? 'success')) ?>">
          <span><?= htmlspecialchars((string)$notice['message']) ?></span>
        </div>
        <?php elseif ($freelancerPendingToSign > 0): ?>
        <div class="module-notice info">
          <span>Your application has been accepted. The contract is ready for your signature.</span>
        </div>
        <?php endif; ?>

        <div class="ct-info">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/></svg>
          <div class="ct-info-text">
            <h4>How contracts work</h4>
            <p><?= $isClient ? 'Step 1: create the contract. Step 2: add the rules. Step 3: sign as client. Step 4: the freelancer signs last.' : 'Wait for the client to create the contract, add the rules, and sign before you complete the final signature.' ?></p>
          </div>
        </div>

        <div class="ct-stats">
          <div class="ct-stat">
            <div class="ct-stat-icon" style="background:rgba(99,102,241,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--color-accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg></div>
            <div><div class="ct-stat-label">Total</div><div class="ct-stat-value"><?= $statTotals['total'] ?></div></div>
          </div>
          <div class="ct-stat">
            <div class="ct-stat-icon" style="background:rgba(5,150,105,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
            <div><div class="ct-stat-label">Finalized</div><div class="ct-stat-value"><?= $statTotals['finalized'] ?></div></div>
          </div>
          <div class="ct-stat">
            <div class="ct-stat-icon" style="background:rgba(245,158,11,.1)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div><div class="ct-stat-label">Waiting</div><div class="ct-stat-value"><?= $statTotals['waiting'] ?></div></div>
          </div>
        </div>

        <?php if (empty($contracts)): ?>
        <div class="ct-empty">
          <div class="ct-empty-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg></div>
          <h3>No contracts yet</h3>
          <p><?= $isClient ? 'Accept a freelancer application on Job Offers, then create a contract here.' : 'No contracts assigned to you yet.' ?></p>
        </div>
        <?php else: ?>
        <div class="ct-list">
          <?php foreach ($contracts as $row):
            $cId         = (int)($row['id'] ?? 0);
            $cStatus     = (string)($row['status'] ?? 'draft');
            $sm          = $statusMeta[$cStatus] ?? $statusMeta['draft'];
            $isOwnerC    = $isClient && (int)($row['client_id'] ?? 0) === $userId;
            $rules       = is_array($row['rules'] ?? null) ? $row['rules'] : [];
            $rulesJson   = htmlspecialchars(json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $hasRules    = count($rules) > 0;
            $clientSigned = (int)($row['client_signed'] ?? 0) === 1;
            $freelancerSigned = (int)($row['freelancer_signed'] ?? 0) === 1;
            $canManageRules = $isOwnerC && !$clientSigned && !$freelancerSigned;
            $canClientSign = $isOwnerC && $hasRules && !$clientSigned && !$freelancerSigned && trim((string)($row['freelancer_refused_at'] ?? '')) === '';
            $canFreelancerSign = $isFreelancer && (int)($row['freelancer_id'] ?? 0) === $userId && $hasRules && $clientSigned && !$freelancerSigned && trim((string)($row['freelancer_refused_at'] ?? '')) === '';
            $clientName  = htmlspecialchars(trim((string)($row['client_first'] ?? '') . ' ' . (string)($row['client_last'] ?? '')));
            $fName       = htmlspecialchars(trim((string)($row['freelancer_first'] ?? '') . ' ' . (string)($row['freelancer_last'] ?? '')));
            $startsAt    = (string)($row['starts_at'] ?? '');
            $endsAt      = (string)($row['ends_at'] ?? '');
          ?>
          <div class="ct-card" id="contract-<?= $cId ?>">
            <div class="ct-card-header">
              <div class="ct-card-header-left">
                <span class="ct-status-badge" style="background:<?= $sm['bg'] ?>;color:<?= $sm['text'] ?>">
                  <span class="ct-status-dot" style="background:<?= $sm['dot'] ?>"></span>
                  <?= $sm['label'] ?>
                </span>
                <div>
                  <div class="ct-contract-title"><?= htmlspecialchars((string)($row['offer_title'] ?? 'Contract')) ?></div>
                  <div class="ct-contract-sub"><?= $clientName ?> → <?= $fName ?></div>
                </div>
              </div>
              <div class="ct-contract-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg></div>
            </div>
            <div class="ct-card-body">
              <div><div class="ct-field-label">Amount</div><div class="ct-field-value amount"><?= number_format((float)($row['amount'] ?? 0), 2) ?> TND</div></div>
              <div><div class="ct-field-label">Start Date</div><div class="ct-field-value"><?= $startsAt ? htmlspecialchars(date('M j, Y', strtotime($startsAt))) : '—' ?></div></div>
              <div><div class="ct-field-label">End Date</div><div class="ct-field-value"><?= $endsAt ? htmlspecialchars(date('M j, Y', strtotime($endsAt))) : '—' ?></div></div>
            </div>
            <?php if (trim((string)($row['terms'] ?? '')) !== ''): ?>
            <div class="ct-terms-block"><div class="ct-field-label" style="margin-bottom:4px;">Terms</div><?= nl2br(htmlspecialchars(substr((string)($row['terms'] ?? ''), 0, 380))) ?><?php if (strlen((string)($row['terms'] ?? '')) > 380): ?>…<?php endif; ?></div>
            <?php endif; ?>

            <?php if ($isOwnerC): ?>
            <div class="ct-edit-panel" id="edit-panel-<?= $cId ?>">
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="update_contract">
                <input type="hidden" name="contract_id" value="<?= $cId ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($cStatus) ?>">
            <div class="ct-edit-grid">
                  <div><label class="ct-edit-label">Amount (TND)</label><input type="number" name="amount" min="0" step="0.01" value="<?= (float)($row['amount'] ?? 0) ?>" class="ct-edit-input" required></div>
                  <div><label class="ct-edit-label">Start Date</label><input type="datetime-local" name="starts_at" value="<?= htmlspecialchars($localDateTime((string)($row['starts_at'] ?? ''))) ?>" class="ct-edit-input"></div>
                  <div><label class="ct-edit-label">End Date</label><input type="datetime-local" name="ends_at" value="<?= htmlspecialchars($localDateTime((string)($row['ends_at'] ?? ''))) ?>" class="ct-edit-input"></div>
                  <div class="full"><label class="ct-edit-label">Terms</label><textarea name="terms" class="ct-edit-textarea" required><?= htmlspecialchars((string)($row['terms'] ?? '')) ?></textarea></div>
                </div>
                <div class="ct-edit-actions">
                  <button type="button" class="ct-btn ct-btn-ghost" onclick="toggleEdit(<?= $cId ?>)">Cancel</button>
                  <button type="submit" class="ct-btn ct-btn-primary"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save</button>
                </div>
              </form>
            </div>
            <?php endif; ?>

            <div class="ct-rules-section">
              <div class="ct-rules-head">
                <h4>View Rules</h4>
                <span style="font-size:.7rem;color:var(--color-text-muted);"><?= count($rules) ?> rule<?= count($rules) === 1 ? '' : 's' ?></span>
              </div>
              <?php if ($hasRules): ?>
                <div class="ct-rule-list">
                  <?php foreach ($rules as $rule): ?>
                    <div class="ct-rule-item">
                      <div class="ct-rule-top">
                        <div class="ct-rule-title"><?= htmlspecialchars((string)($rule['title'] ?? 'Contract rule')) ?></div>
                        <?php
                          $ruleTypeValue = strtolower((string) ($rule['rule_type'] ?? 'other'));
                          $ruleTypeLabel = [
                            'payment' => 'Payment',
                            'deadline' => 'Deadline',
                            'scope' => 'Scope',
                            'penalties' => 'Penalties',
                            'other' => 'Other',
                            'delivery' => 'Scope',
                            'communication' => 'Scope',
                            'quality' => 'Scope',
                            'revision' => 'Scope',
                            'legal' => 'Penalties',
                          ][$ruleTypeValue] ?? ucfirst($ruleTypeValue);
                        ?>
                        <span class="ct-rule-type"><?= htmlspecialchars($ruleTypeLabel) ?></span>
                      </div>
                      <div class="ct-rule-desc"><?= nl2br(htmlspecialchars((string)($rule['description'] ?? ''))) ?></div>
                      <div class="ct-rule-meta">
                        <?php if (trim((string)($rule['due_date'] ?? '')) !== ''): ?><span>Due <?= htmlspecialchars((string)$rule['due_date']) ?></span><?php endif; ?>
                        <?php if (trim((string)($rule['penalty'] ?? '')) !== ''): ?><span>Penalty: <?= htmlspecialchars(mb_strimwidth((string)$rule['penalty'], 0, 110, '...')) ?></span><?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="ct-rules-empty" style="border-top:0;padding:0;background:transparent;">No rules attached yet. Add rules before signing this contract.</div>
              <?php endif; ?>
            </div>

            <div class="ct-card-footer">
              <?php if ($isOwnerC): ?>
              <button class="ct-btn ct-btn-ghost" onclick="toggleEdit(<?= $cId ?>)" id="edit-toggle-<?= $cId ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <?php if ($canManageRules): ?>
              <button type="button" class="ct-btn ct-btn-primary" onclick='openRulesModal(<?= $cId ?>, <?= $rulesJson ?>)'>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= $hasRules ? 'Edit Rules' : 'Add Rules' ?>
              </button>
              <?php endif; ?>
              <form method="post" data-confirm="Delete this contract?" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_contract">
                <input type="hidden" name="contract_id" value="<?= $cId ?>">
                <button type="submit" class="ct-btn ct-btn-danger"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Delete</button>
              </form>
              <?php if (!$clientSigned): ?>
              <form method="post" style="display:inline" class="ct-sign-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="sign_client_contract">
                <input type="hidden" name="contract_id" value="<?= $cId ?>">
                <input type="hidden" name="client_signature" value="">
                <button type="button" class="ct-btn ct-btn-success ct-sign-trigger" <?= $canClientSign ? '' : 'disabled' ?> data-sign-role="client"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg> Sign Contract</button>
              </form>
              <?php endif; ?>
              <?php endif; ?>
              <?php if ($isFreelancer && (int)($row['freelancer_id'] ?? 0) === $userId && !$freelancerSigned): ?>
              <form method="post" style="display:inline" class="ct-sign-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="sign_contract">
                <input type="hidden" name="contract_id" value="<?= $cId ?>">
                <input type="hidden" name="freelancer_signature" value="">
                <button type="button" class="ct-btn ct-btn-success ct-sign-trigger" <?= $canFreelancerSign ? '' : 'disabled' ?> data-sign-role="freelancer"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg> Sign Contract</button>
              </form>
              <?php endif; ?>
              <span style="margin-left:auto;font-size:.72rem;color:var(--color-text-muted);">Contract #<?= $cId ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <?php if ($isClient): ?>
  <div class="ct-modal-backdrop" id="createContractModal" onclick="if(event.target===this)closeCreateContractModal()">
    <div class="ct-modal">
      <div class="ct-modal-head">
        <h3>Create Contract</h3>
        <button class="ct-modal-close" onclick="closeCreateContractModal()" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <?php if (empty($acceptedPairs)): ?>
        <div class="ct-empty" style="padding:12px 0 4px;">
          <h3 style="margin-top:0;">No accepted applications available</h3>
          <p>Accept a freelancer from Job Offers first, then create the contract here.</p>
        </div>
      <?php else: ?>
      <form method="post" id="createContractForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_contract">
        <input type="hidden" name="status" value="draft">
        <div class="ct-modal-grid">
          <div class="ct-modal-fg full">
            <label class="ct-modal-fl">Accepted Application *</label>
            <select name="application_pair" class="ct-modal-sel" required>
              <option value="">Select accepted application</option>
              <?php foreach ($acceptedPairs as $pair):
                $offerId = (int)($pair['job_offer_id'] ?? 0);
                $freelancerId = (int)($pair['freelancer_id'] ?? 0);
                $pairValue = $offerId . ':' . $freelancerId;
                $isSelected = $offerId === $prefillOfferId && $freelancerId === $prefillFreelancerId;
                $freelancerName = trim((string)($pair['first_name'] ?? '') . ' ' . (string)($pair['last_name'] ?? '')) ?: ((string)($pair['email'] ?? 'Freelancer'));
              ?>
                <option value="<?= htmlspecialchars($pairValue) ?>" <?= $isSelected ? 'selected' : '' ?>>
                  <?= htmlspecialchars((string)($pair['offer_title'] ?? 'Offer')) ?> → <?= htmlspecialchars($freelancerName) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="ct-modal-fg">
            <label class="ct-modal-fl">Amount (TND)</label>
            <input type="number" min="0" step="0.01" name="amount" class="ct-modal-fi" value="0">
          </div>
          <div class="ct-modal-fg">
            <label class="ct-modal-fl">Start Date</label>
            <input type="datetime-local" name="starts_at" class="ct-modal-fi">
          </div>
          <div class="ct-modal-fg">
            <label class="ct-modal-fl">End Date</label>
            <input type="datetime-local" name="ends_at" class="ct-modal-fi">
          </div>
          <div class="ct-modal-fg full">
            <label class="ct-modal-fl">Terms *</label>
            <textarea name="terms" class="ct-modal-ta" placeholder="Describe scope, deliverables, milestones, and payment terms..." required></textarea>
          </div>
          <div class="ct-modal-fg full">
            <label class="ct-modal-fl">Payment Method *</label>
            <input type="text" name="payment_details" class="ct-modal-fi" placeholder="Bank transfer, cash, card, milestone payment..." required>
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="ct-btn ct-btn-ghost" onclick="closeCreateContractModal()">Cancel</button>
          <button type="submit" class="ct-btn ct-btn-primary"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Create Contract</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="ct-modal-backdrop" id="rulesModal" onclick="if(event.target===this)closeRulesModal()">
    <div class="ct-modal">
      <div class="ct-modal-head">
        <h3>Contract Rules</h3>
        <button class="ct-modal-close" onclick="closeRulesModal()" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="post" id="contractRulesForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="save_rules">
        <input type="hidden" name="contract_id" id="rulesContractId">
        <div id="rulesList"></div>
        <button type="button" class="ct-btn ct-btn-ghost" onclick="addRuleRow()">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add another rule
        </button>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="ct-btn ct-btn-ghost" onclick="closeRulesModal()">Cancel</button>
          <button type="submit" class="ct-btn ct-btn-primary">Save Rules</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="ct-modal-backdrop" id="signContractModal" onclick="if(event.target===this)closeSignModal()">
    <div class="ct-modal" style="max-width:520px;">
      <div class="ct-modal-head">
        <h3>Sign Contract</h3>
        <button class="ct-modal-close" onclick="closeSignModal()" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p class="ct-sign-modal-copy">Draw your signature below to confirm this contract.</p>
      <div class="ct-signature-wrap" id="signSignatureWrap">
        <canvas id="contractSignatureCanvas" class="ct-signature-canvas"></canvas>
        <div class="ct-signature-row">
          <span class="ct-signature-help">Mouse and touch input are supported.</span>
          <button type="button" class="ct-btn ct-btn-ghost" id="clearContractSignature">Clear</button>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
        <button type="button" class="ct-btn ct-btn-ghost" onclick="closeSignModal()">Cancel</button>
        <button type="button" class="ct-btn ct-btn-success" id="submitSignatureButton">Sign now</button>
      </div>
    </div>
  </div>

  <script>
  let activeSignatureForm = null;
  let signaturePad = null;

  function openCreateContractModal() {
    const modal = document.getElementById('createContractModal');
    if (modal) {
      modal.classList.add('open');
    }
  }
  function closeCreateContractModal() {
    const modal = document.getElementById('createContractModal');
    if (modal) modal.classList.remove('open');
  }

  <?php if ($openCreateModal && $isClient): ?>
  window.addEventListener('DOMContentLoaded', () => {
    openCreateContractModal();
  });
  <?php endif; ?>

  const ruleTypeOptions = ['payment', 'deadline', 'scope', 'penalties', 'other'];
  const legacyRuleTypeMap = {
    delivery: 'scope',
    communication: 'scope',
    quality: 'scope',
    revision: 'scope',
    legal: 'penalties',
    penalty: 'penalties'
  };

  function normalizeRuleType(value) {
    const type = String(value ?? '').trim().toLowerCase();
    const mapped = legacyRuleTypeMap[type] || type;
    return ruleTypeOptions.includes(mapped) ? mapped : 'scope';
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[char]));
  }

  function closeRulesModal() {
    document.getElementById('rulesModal')?.classList.remove('open');
  }

  function refreshRuleNumbers() {
    document.querySelectorAll('#rulesList .ct-dynamic-rule').forEach((row, index) => {
      const label = row.querySelector('.ct-rule-number');
      if (label) label.textContent = 'Rule #' + (index + 1);
      const removeButton = row.querySelector('.ct-rule-remove');
      if (removeButton) removeButton.disabled = document.querySelectorAll('#rulesList .ct-dynamic-rule').length <= 1;
    });
  }

  function addRuleRow(rule = {}) {
    const list = document.getElementById('rulesList');
    if (!list) return;
    const index = list.querySelectorAll('.ct-dynamic-rule').length;
    const selectedType = normalizeRuleType(rule.rule_type || rule.type || 'scope');
    const row = document.createElement('div');
    row.className = 'ct-dynamic-rule';
    row.innerHTML = `
      <div class="ct-dynamic-rule-head">
        <strong class="ct-rule-number">Rule #${index + 1}</strong>
        <button type="button" class="ct-btn ct-btn-ghost ct-rule-remove">Remove</button>
      </div>
      <div class="ct-modal-grid">
        <div class="ct-modal-fg">
          <label class="ct-modal-fl">Rule Title *</label>
          <input type="text" name="rule_titles[]" class="ct-modal-fi" value="${escapeHtml(rule.title || '')}" required>
        </div>
        <div class="ct-modal-fg">
          <label class="ct-modal-fl">Type</label>
          <select name="rule_types[]" class="ct-modal-sel">
            ${ruleTypeOptions.map((type) => `<option value="${type}" ${type === selectedType ? 'selected' : ''}>${type.charAt(0).toUpperCase() + type.slice(1)}</option>`).join('')}
          </select>
        </div>
        <div class="ct-modal-fg">
          <label class="ct-modal-fl">Due Date</label>
          <input type="date" name="rule_due_dates[]" class="ct-modal-fi" value="${escapeHtml(rule.due_date || '')}">
        </div>
        <div class="ct-modal-fg full">
          <label class="ct-modal-fl">Description *</label>
          <textarea name="rule_descriptions[]" class="ct-modal-ta" required>${escapeHtml(rule.description || '')}</textarea>
        </div>
        <div class="ct-modal-fg full">
          <label class="ct-modal-fl">Penalty</label>
          <textarea name="rule_penalties[]" class="ct-modal-ta">${escapeHtml(rule.penalty || '')}</textarea>
        </div>
      </div>
    `;
    row.querySelector('.ct-rule-remove')?.addEventListener('click', () => {
      row.remove();
      refreshRuleNumbers();
      window.MVCInlineValidation?.initForm?.(document.getElementById('contractRulesForm'), true);
    });
    list.appendChild(row);
    refreshRuleNumbers();
    window.MVCInlineValidation?.initForm?.(document.getElementById('contractRulesForm'), true);
  }

  function openRulesModal(contractId, rules = []) {
    const modal = document.getElementById('rulesModal');
    const contractInput = document.getElementById('rulesContractId');
    const list = document.getElementById('rulesList');
    if (!modal || !contractInput || !list) return;
    contractInput.value = contractId;
    list.innerHTML = '';
    const existingRules = Array.isArray(rules) && rules.length > 0 ? rules : [{}];
    existingRules.forEach((rule) => addRuleRow(rule));
    modal.classList.add('open');
    window.MVCInlineValidation?.initForm?.(document.getElementById('contractRulesForm'), true);
  }

  <?php if ($openRulesModal && $isClient): ?>
  window.addEventListener('DOMContentLoaded', () => {
    openRulesModal(<?= (int)$selectedContractId ?>, <?= json_encode($selectedContractRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
  });
  <?php endif; ?>

  function toggleEdit(id) {
    const p = document.getElementById('edit-panel-' + id);
    const b = document.getElementById('edit-toggle-' + id);
    if (!p) return;
    const open = p.classList.toggle('open');
    if (b) b.innerHTML = open ? '✕ Close' : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit';
  }
  </script>

  <div class="home-toast-stack" id="homeToastStack" aria-live="polite"></div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/home.js"></script>
  <script src="../../assets/js/mvc-inline-validation.js"></script>

  <script>
  const notice = <?= json_encode($notice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const freelancerPendingToSign = <?= (int) $freelancerPendingToSign ?>;
  if (notice && notice.message && window.Swal) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: notice.type === 'success' ? 'success' : 'error',
      title: notice.message,
      showConfirmButton: false,
      timer: 3200,
      timerProgressBar: true,
    });
  } else if (freelancerPendingToSign > 0 && window.Swal) {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'info',
      title: 'Your application has been accepted. Please sign your contract.',
      showConfirmButton: false,
      timer: 3600,
      timerProgressBar: true,
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      Swal.fire({
        title: 'Are you sure?',
        text: form.dataset.confirm || 'Please confirm this action.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, continue',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#6366f1',
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  function setupSignaturePad(canvas, hiddenInput, clearButton) {
    if (!canvas || !hiddenInput) return null;
    const ctx = canvas.getContext('2d');
    let drawing = false;
    let hasStroke = false;
    let lastX = 0;
    let lastY = 0;

    function resizeCanvas() {
      const ratio = Math.max(window.devicePixelRatio || 1, 1);
      const rect = canvas.getBoundingClientRect();
      if (rect.width === 0 || rect.height === 0) return;
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
      return {
        x: source.clientX - rect.left,
        y: source.clientY - rect.top
      };
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

    function stop() {
      drawing = false;
    }

    function clear() {
      hasStroke = false;
      hiddenInput.value = '';
      resizeCanvas();
    }

    canvas.addEventListener('pointerdown', start);
    canvas.addEventListener('pointermove', move);
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointerleave', stop);
    canvas.addEventListener('pointercancel', stop);
    clearButton?.addEventListener('click', clear);
    window.addEventListener('resize', resizeCanvas);
    requestAnimationFrame(() => {
      requestAnimationFrame(resizeCanvas);
    });

    return { clear, resize: resizeCanvas };
  }

  function closeSignModal() {
    document.getElementById('signContractModal')?.classList.remove('open');
    if (signaturePad && typeof signaturePad.clear === 'function') {
      signaturePad.clear();
    }
    activeSignatureForm = null;
  }

  document.querySelectorAll('.ct-sign-trigger').forEach((button) => {
    button.addEventListener('click', () => {
      if (button.disabled) return;
      activeSignatureForm = button.closest('.ct-sign-form');
      const hiddenInput = activeSignatureForm?.querySelector('input[name$="_signature"]');
      const modal = document.getElementById('signContractModal');
      const wrap = document.getElementById('signSignatureWrap');
      if (!activeSignatureForm || !hiddenInput || !modal || !wrap) return;

      hiddenInput.value = '';
      modal.classList.add('open');
      requestAnimationFrame(() => {
        signaturePad = setupSignaturePad(
          document.getElementById('contractSignatureCanvas'),
          hiddenInput,
          document.getElementById('clearContractSignature')
        );
        window.MVCInlineValidation?.setSignatureError?.(wrap, []);
      });
    });
  });

  document.getElementById('submitSignatureButton')?.addEventListener('click', () => {
    const wrap = document.getElementById('signSignatureWrap');
    const hiddenInput = activeSignatureForm?.querySelector('input[name$="_signature"]');
    if (!activeSignatureForm || !hiddenInput || !hiddenInput.value) {
      window.MVCInlineValidation?.setSignatureError?.(wrap, ['Please draw your signature first.']);
      return;
    }
    activeSignatureForm.submit();
  });

  </script>
</body>
</html>
