<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/JobOfferController.php');
include_once(__DIR__ . '/../../config.php');

if (!UserController::isAuthenticated()) {
    $_SESSION['flash_error'] = 'Please sign in.';
    header('Location: auth.php'); exit;
}

$pdo        = config::getConnexion();
$controller = new JobOfferController($pdo);
$currentUser = UserController::currentUser() ?? [];
$userId      = (int)($currentUser['id'] ?? 0);
$role        = strtolower(trim((string)($currentUser['role'] ?? 'user')));
$resolvedRole = $role;
$sessionTitle = strtolower(trim((string)($currentUser['title'] ?? '')));
if (str_contains($sessionTitle, 'client')) {
  $resolvedRole = 'client';
} elseif (str_contains($sessionTitle, 'freelancer')) {
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
$isAdmin     = UserController::isAdmin();
$hasClientSignal = in_array($resolvedRole, ['client'], true) || str_contains($sessionTitle, 'client');
$hasFreelancerSignal = in_array($resolvedRole, ['freelancer', 'user'], true) || str_contains($sessionTitle, 'freelancer');
$isClient    = $isAdmin || $hasClientSignal || $ownsClientOffers;
$isFreelancer = !$isAdmin;
$canApplyToJobs = true;
$firstName   = trim((string)($currentUser['first_name'] ?? 'Member'));
$lastName    = trim((string)($currentUser['last_name'] ?? ''));
$displayName = trim($firstName . ' ' . $lastName) ?: 'Member';
$displayEmail = (string)($currentUser['email'] ?? '');
$isAdminSidebar = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$navInitials = strtoupper(substr($firstName, 0, 1) . substr($lastName ?: 'M', 0, 1));
$reputationScore = 84; $level = 'Level 12'; $streak = 7;

if (empty($_SESSION['csrf_token_front'])) {
    $_SESSION['csrf_token_front'] = bin2hex(random_bytes(24));
}
$csrfToken = (string)$_SESSION['csrf_token_front'];
$notice    = $_SESSION['job_offer_notice_front'] ?? ['type' => '', 'message' => ''];
unset($_SESSION['job_offer_notice_front']);
$redirectAfterPost = '';
$clean     = static fn($v): string => trim((string)($v ?? ''));
$currentPage = basename(parse_url((string)($_SERVER['REQUEST_URI'] ?? 'jobOffer.php'), PHP_URL_PATH) ?: 'jobOffer.php');
$currentQuery = (string)($_SERVER['QUERY_STRING'] ?? '');
$selfRedirect = $currentPage . ($currentQuery !== '' ? '?' . $currentQuery : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $notice = ['type' => 'error', 'message' => 'Invalid security token.'];
        $redirectAfterPost = $selfRedirect;
    } else {
        $action = strtolower($clean($_POST['action'] ?? ''));
        try {
            if ($action === 'create_offer' && $isClient) {
                $payload = [
                    'title'            => $clean($_POST['title'] ?? ''),
                    'description'      => $clean($_POST['description'] ?? ''),
                    'budget'           => (string)($_POST['budget'] ?? ''),
                    'skills_required'  => $clean($_POST['skills_required'] ?? ''),
                    'location'         => $clean($_POST['location'] ?? ''),
                    'experience_level' => $clean($_POST['experience_level'] ?? 'Mid'),
                    'project_type'     => $clean($_POST['project_type'] ?? 'Fixed Price'),
                    'status'           => $clean($_POST['status'] ?? 'open'),
                    'deadline_at'      => $clean($_POST['deadline_at'] ?? ''),
                    'client_id'        => $userId,
                ];
                $controller->createByClient($payload);
                $notice = ['type' => 'success', 'message' => 'Job offer published successfully!'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'update_offer' && $isClient) {
                $offerId = (int)($_POST['offer_id'] ?? 0);
                if ($offerId <= 0) {
                    throw new RuntimeException('Invalid offer.');
                }
                $payload = [
                    'title'            => $clean($_POST['title'] ?? ''),
                    'description'      => $clean($_POST['description'] ?? ''),
                    'budget'           => (string)($_POST['budget'] ?? ''),
                    'skills_required'  => $clean($_POST['skills_required'] ?? ''),
                    'location'         => $clean($_POST['location'] ?? ''),
                    'experience_level' => $clean($_POST['experience_level'] ?? 'Mid'),
                    'project_type'     => $clean($_POST['project_type'] ?? 'Fixed Price'),
                    'status'           => $clean($_POST['status'] ?? 'open'),
                    'deadline_at'      => $clean($_POST['deadline_at'] ?? ''),
                ];
                $targetClientId = $userId;
                if ($isAdmin) {
                    $targetOffer = $controller->findById($offerId);
                    $targetClientId = (int)($targetOffer['client_id'] ?? 0);
                }
                $updated = $controller->updateOwnedByClient($offerId, $targetClientId, $payload);
                if (!$updated) {
                    throw new RuntimeException('Unable to update this offer.');
                }
                $notice = ['type' => 'success', 'message' => 'Offer updated successfully.'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'delete_offer' && $isClient) {
                $offerId = (int)($_POST['offer_id'] ?? 0);
                if ($isAdmin || $controller->isOwnedByClient($offerId, $userId)) {
                    $controller->deleteCascade($offerId);
                    $notice = ['type' => 'success', 'message' => 'Offer deleted.'];
                }
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'apply_offer' && $canApplyToJobs) {
                $offerId = (int)($_POST['offer_id'] ?? 0);
                $candidatureId = (int)($_POST['candidature_id'] ?? 0);
                if ($controller->isOwnedByClient($offerId, $userId)) {
                    throw new RuntimeException('You cannot apply to your own offer.');
                }
                $controller->saveCandidatureForFreelancer(
                    $offerId,
                    $userId,
                    $candidatureId > 0 ? $candidatureId : null,
                    [
                        'cover_letter' => $clean($_POST['cover_letter'] ?? ''),
                        'proposed_budget' => $_POST['proposed_budget'] ?? '',
                        'estimated_delivery_days' => $_POST['estimated_delivery_days'] ?? '',
                        'skills_experience' => $clean($_POST['skills_experience'] ?? ''),
                    ],
                    $_FILES['attachments'] ?? []
                );
                $notice = ['type' => 'success', 'message' => $candidatureId > 0 ? 'Candidature updated.' : 'Candidature submitted!'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'delete_candidature' && $canApplyToJobs) {
                $offerId = (int)($_POST['offer_id'] ?? 0);
                if ($controller->deleteCandidatureForFreelancer($offerId, $userId)) {
                    $notice = ['type' => 'success', 'message' => 'Candidature deleted.'];
                } else {
                    $notice = ['type' => 'error', 'message' => 'Candidature not found.'];
                }
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'delete_candidature_client' && $isClient) {
                $offerId = (int)($_POST['offer_id'] ?? 0);
                $applicationId = (int)($_POST['application_id'] ?? 0);
                if (!$controller->isOwnedByClient($offerId, $userId)) {
                    throw new RuntimeException('You can only delete applications for your own offers.');
                }
                if ($controller->deleteCandidatureById($applicationId)) {
                    $notice = ['type' => 'success', 'message' => 'Candidature removed.'];
                } else {
                    $notice = ['type' => 'error', 'message' => 'Candidature not found.'];
                }
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'decide_application' && $isClient) {
                $applicationId = (int)($_POST['application_id'] ?? 0);
                $decision      = strtolower($clean($_POST['decision'] ?? 'rejected'));
                if (in_array($decision, ['accepted', 'rejected'], true)) {
                    $controller->decideApplication($userId, $applicationId, $decision);
                    if ($decision === 'accepted') {
                        $offerId = (int)($_POST['offer_id'] ?? 0);
                        $freelancerId = (int)($_POST['freelancer_id'] ?? 0);
                        $notice = ['type' => 'success', 'message' => 'Application accepted. Prepare the contract to continue.'];
                        if ($offerId > 0 && $freelancerId > 0) {
                            $redirectAfterPost = 'contracts.php?open_create=1&offer_id=' . $offerId . '&freelancer_id=' . $freelancerId;
                        }
                    } else {
                        $notice = ['type' => 'success', 'message' => 'Application rejected.'];
                        $redirectAfterPost = $selfRedirect;
                    }
                }
            }
        } catch (Throwable $e) { $notice = ['type' => 'error', 'message' => $e->getMessage()]; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($redirectAfterPost === '') {
    $redirectAfterPost = $selfRedirect;
  }
  if (str_starts_with($redirectAfterPost, 'contracts.php')) {
    $_SESSION['contract_notice_front'] = $notice;
  } else {
    $_SESSION['job_offer_notice_front'] = $notice;
  }
  header('Location: ' . $redirectAfterPost);
  exit;
}

$filters = [
    'q' => $clean($_GET['q'] ?? ''), 'status' => $clean($_GET['status'] ?? 'all'),
    'sort' => $clean($_GET['sort'] ?? 'newest'),
];
$rows               = $controller->listFrontofficeRows($filters);
$myApplications     = $controller->freelancerApplicationsMap($userId);
$clientApplications = $isClient    ? $controller->clientApplicationsByOffer($userId) : [];
$freelancerContracts = $controller->contractsMapForFreelancer($userId);
$clientContractsByOffer = $isClient ? $controller->contractsMapForClient($userId) : [];
$contractStateLabels = [
  'waiting_client' => 'Draft',
  'waiting_freelancer' => 'Waiting',
  'completed' => 'Finalized',
  'refused' => 'Refused',
];
$decodeAttachments = static function (?string $json): array {
  $decoded = json_decode((string)($json ?? ''), true);
  return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
};
$assetPath = static function (?string $path): string {
  $raw = trim((string)($path ?? ''));
  if ($raw === '') {
    return '';
  }
  return str_starts_with($raw, 'assets/') ? '../../' . $raw : $raw;
};
$localDateTime = static function (?string $value): string {
  if (!$value) {
    return '';
  }
  $ts = strtotime($value);
  return $ts ? date('Y-m-d\TH:i', $ts) : '';
};
$statusColors = [
    'open'        => ['bg'=>'rgba(5,150,105,.1)',  'text'=>'#059669','dot'=>'#10b981'],
    'in_progress' => ['bg'=>'rgba(99,102,241,.1)', 'text'=>'#6366f1','dot'=>'#6366f1'],
    'closed'      => ['bg'=>'rgba(107,114,128,.1)','text'=>'#6b7280','dot'=>'#9ca3af'],
    'archived'    => ['bg'=>'rgba(245,158,11,.1)', 'text'=>'#d97706','dot'=>'#f59e0b'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Offers - Diversity.is</title>
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    /* Module content inside home-main */
    .module-page-header { margin-bottom: 20px; display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:12px; }
    .module-page-header-text h2 { font-size: 1.35rem; font-weight: 700; letter-spacing: -.02em; color: var(--color-text-primary); margin:0; }
    .module-page-header-text p  { font-size: .84rem; color: var(--color-text-secondary); margin: 4px 0 0; }

    .module-notice { display:flex; align-items:center; gap:9px; padding:11px 15px; border-radius:12px; margin-bottom:18px; font-size:.84rem; font-weight:500; animation:mslideDown .3s ease; }
    .module-notice.success { background:rgba(5,150,105,.09); border:1px solid rgba(5,150,105,.22); color:#065f46; }
    .module-notice.error   { background:rgba(225,29,72,.08); border:1px solid rgba(225,29,72,.18); color:#9f1239; }
    @keyframes mslideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:none} }

    /* CTA Banner */
    .jo-cta-banner { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:linear-gradient(135deg,rgba(99,102,241,.06),rgba(14,165,233,.05)); border:1px solid rgba(99,102,241,.14); border-radius:14px; padding:16px 20px; margin-bottom:20px; }
    .jo-cta-banner h3 { font-size:.92rem; font-weight:600; color:var(--color-text-primary); margin:0; }
    .jo-cta-banner p  { font-size:.78rem; color:var(--color-text-secondary); margin:2px 0 0; }

    /* Filter bar */
    .jo-filters { display:flex; gap:8px; flex-wrap:wrap; align-items:center; background:var(--color-surface); border:1px solid var(--color-border); border-radius:12px; padding:12px 14px; margin-bottom:18px; }
    .jo-search-wrap { position:relative; flex:1; min-width:160px; }
    .jo-search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--color-text-muted); }
    .jo-search { width:100%; padding:8px 10px 8px 33px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; font-size:.82rem; color:var(--color-text-primary); }
    .jo-search:focus { border-color:var(--color-accent); outline:none; }
    .jo-sel { padding:8px 10px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:8px; font-size:.81rem; color:var(--color-text-primary); cursor:pointer; }
    .jo-filter-btn { display:inline-flex; align-items:center; gap:5px; background:var(--color-accent); color:#fff; border:none; border-radius:8px; padding:8px 14px; font-size:.81rem; font-weight:600; cursor:pointer; }

    /* Cards */
    .jo-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:14px; }
    .jo-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:16px; padding:18px; display:flex; flex-direction:column; gap:11px; transition:all .22s ease; position:relative; overflow:hidden; }
    .jo-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--color-accent),var(--color-accent-secondary)); }
    .jo-card:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(0,0,0,.07); border-color:var(--color-border-strong); }
    .jo-card-head { display:flex; align-items:flex-start; justify-content:space-between; gap:8px; }
    .jo-card-title { font-size:.93rem; font-weight:700; color:var(--color-text-primary); line-height:1.3; }
    .jo-status-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:999px; font-size:.69rem; font-weight:600; white-space:nowrap; flex-shrink:0; }
    .jo-status-dot  { width:5px; height:5px; border-radius:50%; }
    .jo-desc { font-size:.8rem; color:var(--color-text-secondary); line-height:1.6; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .jo-meta { display:flex; flex-wrap:wrap; gap:6px; }
    .jo-meta-pill { display:inline-flex; align-items:center; gap:4px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:7px; padding:3px 8px; font-size:.72rem; color:var(--color-text-secondary); }
    .jo-meta-pill svg { color:var(--color-accent); }
    .jo-skills { display:flex; flex-wrap:wrap; gap:5px; }
    .jo-skill-tag { background:var(--color-accent-light); color:var(--color-accent); border-radius:5px; padding:2px 8px; font-size:.69rem; font-weight:500; }
    .jo-budget { font-weight:700; color:var(--color-accent); }
    .jo-card-actions { margin-top:auto; display:flex; flex-wrap:wrap; gap:7px; align-items:center; }

    /* Buttons */
    .jo-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 12px; border-radius:8px; border:none; cursor:pointer; font-size:.79rem; font-weight:600; transition:all .17s; }
    .jo-btn-primary { background:var(--color-accent); color:#fff; }
    .jo-btn-primary:hover { background:var(--color-accent-hover); }
    .jo-btn-danger  { background:rgba(225,29,72,.08); color:#be123c; border:1px solid rgba(225,29,72,.18); }
    .jo-btn-danger:hover { background:rgba(225,29,72,.13); }
    .jo-btn-ghost   { background:var(--color-surface-2); color:var(--color-text-secondary); border:1px solid var(--color-border); }
    .jo-btn-ghost:hover { color:var(--color-text-primary); }
    .jo-btn-success { background:rgba(5,150,105,.1); color:#065f46; border:1px solid rgba(5,150,105,.22); }

    /* App pill */
    .jo-app-pill { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:7px; font-size:.77rem; font-weight:600; }
    .jo-app-pill.pending  { background:rgba(245,158,11,.1); color:#92400e; border:1px solid rgba(245,158,11,.22); }
    .jo-app-pill.accepted { background:rgba(5,150,105,.1); color:#065f46; border:1px solid rgba(5,150,105,.22); }
    .jo-app-pill.rejected { background:rgba(225,29,72,.08); color:#9f1239; border:1px solid rgba(225,29,72,.18); }

    /* Applications sub-panel */
    .jo-apps-list { display:flex; flex-direction:column; gap:7px; margin-top:8px; }
    .jo-app-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px; background:var(--color-surface-2); border:1px solid var(--color-border); border-radius:9px; padding:9px 12px; }
    .jo-app-applicant { font-size:.79rem; font-weight:600; color:var(--color-text-primary); }
    .jo-app-row-actions { display:flex; gap:5px; }
    .jo-app-meta { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .jo-app-meta-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:999px; background:rgba(99,102,241,.08); color:var(--color-accent); font-size:.68rem; font-weight:600; }
    .jo-attachment-list { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .jo-attachment-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px; background:rgba(15,23,42,.06); border:1px solid rgba(15,23,42,.08); color:var(--color-text-primary); text-decoration:none; font-size:.7rem; font-weight:600; }
    .jo-attachment-chip:hover { border-color:rgba(99,102,241,.24); color:var(--color-accent); }
    .jo-helper-text { font-size:.72rem; color:var(--color-text-muted); line-height:1.45; }
    .jo-upload-card { display:flex; flex-direction:column; gap:10px; }
    .jo-upload-zone { display:flex; align-items:center; gap:12px; width:100%; border:1px dashed rgba(99,102,241,.28); background:linear-gradient(180deg, rgba(99,102,241,.06), rgba(99,102,241,.02)); border-radius:14px; padding:13px 14px; cursor:pointer; transition:all .18s ease; }
    .jo-upload-zone:hover { border-color:var(--color-accent); background:rgba(99,102,241,.08); transform:translateY(-1px); }
    .jo-upload-zone.is-dragover { border-color:var(--color-accent); background:rgba(99,102,241,.1); box-shadow:inset 0 0 0 1px rgba(99,102,241,.14); }
    .jo-upload-icon { width:40px; height:40px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; background:var(--color-surface); color:var(--color-accent); flex-shrink:0; border:1px solid var(--color-border); }
    .jo-upload-copy { flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
    .jo-upload-copy strong { font-size:.84rem; color:var(--color-text-primary); line-height:1.25; }
    .jo-upload-copy span { font-size:.72rem; color:var(--color-text-muted); line-height:1.45; }
    .jo-upload-action { display:inline-flex; align-items:center; gap:5px; padding:7px 11px; border-radius:10px; background:var(--color-accent); color:#fff; font-size:.75rem; font-weight:700; flex-shrink:0; }
    .jo-upload-meta { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; font-size:.72rem; color:var(--color-text-muted); }
    .jo-file-input { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
    .jo-selected-files { display:flex; flex-wrap:wrap; gap:6px; }
    .jo-selected-chip { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px; background:rgba(15,23,42,.06); border:1px solid rgba(15,23,42,.08); color:var(--color-text-primary); font-size:.7rem; font-weight:600; }
    .jo-selected-chip small { color:var(--color-text-muted); font-weight:500; }

    /* Modal */
    .jo-modal-backdrop { position:fixed; inset:0; background:rgba(15,21,42,.42); backdrop-filter:blur(5px); z-index:200; display:none; align-items:center; justify-content:center; padding:20px; }
    .jo-modal-backdrop.open { display:flex; animation:mfadeIn .2s ease; }
    @keyframes mfadeIn { from{opacity:0} to{opacity:1} }
    .jo-modal { background:var(--color-surface); border:1px solid var(--color-border-strong); border-radius:20px; padding:26px; width:100%; max-width:580px; max-height:90vh; overflow-y:auto; box-shadow:var(--shadow-xl); animation:mmodalIn .26s cubic-bezier(.16,1,.3,1); }
    @keyframes mmodalIn { from{opacity:0;transform:scale(.96) translateY(10px)} to{opacity:1;transform:none} }
    .jo-modal-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
    .jo-modal-head h3 { font-size:1.05rem; font-weight:700; color:var(--color-text-primary); margin:0; }
    .jo-modal-close { background:none; border:none; cursor:pointer; padding:4px; color:var(--color-text-muted); border-radius:6px; }
    .jo-modal-close:hover { background:var(--color-surface-2); }
    .jo-fgrid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .jo-fgrid .full { grid-column:1/-1; }
    .jo-fg { display:flex; flex-direction:column; gap:4px; }
    .jo-fl { font-size:.77rem; font-weight:600; color:var(--color-text-secondary); }
    .jo-fi, .jo-fsel, .jo-fta { padding:8px 11px; background:var(--color-surface-2); border:1px solid var(--color-border-strong); border-radius:8px; font-size:.84rem; color:var(--color-text-primary); transition:border-color .17s; }
    .jo-fi:focus, .jo-fsel:focus, .jo-fta:focus { border-color:var(--color-accent); outline:none; }
    .jo-fta { resize:vertical; min-height:88px; }

    /* Empty */
    .jo-empty { text-align:center; padding:48px 20px; }
    .jo-empty-icon { margin:0 auto 14px; width:54px; height:54px; border-radius:14px; background:var(--color-surface-2); display:flex; align-items:center; justify-content:center; }
    .jo-empty h3 { font-size:.95rem; font-weight:600; color:var(--color-text-primary); }
    .jo-empty p  { font-size:.82rem; color:var(--color-text-secondary); margin-top:5px; }
  </style>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <!-- Navbar (identical to home.php) -->
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
          <button class="nav-profile-btn" aria-label="User menu">
            <div class="nav-avatar"><?= htmlspecialchars($navInitials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> My Profile</a>
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/><path d="M14 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6z"/><path d="M4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2z"/><path d="M14 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2z"/></svg> Dashboard</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <!-- Main Hub (exact home.php layout) -->
  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

  <main class="home-hub" id="main-content" tabindex="-1">
    <div class="home-grid container">

      <!-- Left Sidebar (identical to home.php) -->
      <aside class="home-left glass-card" aria-label="Quick profile and navigation">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($navInitials) ?></div>
          <div>
            <h4><?= htmlspecialchars($displayName) ?></h4>
            <p><?= htmlspecialchars($level) ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Home Feed</a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> Social</a>
          <a href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Profile</a>
          <a class="active" href="jobOffer.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg> Job Offers</a>
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg> Projects &amp; Collaborators</a>
          <a href="contracts.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/></svg> Contracts</a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Challenges &amp; Daily Quiz</a>
          <?php if ($isAdminSidebar): ?>
            <div style="margin: 12px 0 6px 12px; font-size: 0.7rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.08em;">BackOffice</div>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Dashboard</a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= $reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak</span><strong><?= $streak ?> days</strong></div>
        </div>
      </aside>

      <!-- Main Content -->
      <section class="home-main" aria-label="Job Offers">
        <div class="module-page-header">
          <div class="module-page-header-text">
            <h2>Job Offers</h2>
            <p><?= $isClient ? 'Post and manage your job offers, review freelancer applications.' : 'Browse open opportunities and submit your candidatures.' ?></p>
          </div>
          <?php if ($isClient): ?>
          <button class="jo-btn jo-btn-primary" onclick="document.getElementById('createModal').classList.add('open')">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Post Offer
          </button>
          <?php endif; ?>
        </div>

        <?php if (!empty($notice['message'])): ?>
        <div class="module-notice <?= htmlspecialchars((string)($notice['type'] ?? 'success')) ?>">
          <span><?= htmlspecialchars((string)$notice['message']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <form method="get">
          <div class="jo-filters">
            <div class="jo-search-wrap">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input class="jo-search" type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Search job offers...">
            </div>
            <select class="jo-sel" name="status">
              <option value="all" <?= $filters['status']==='all'?'selected':'' ?>>All Status</option>
              <?php foreach (['open','in_progress','closed','archived'] as $s): ?>
              <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
            <select class="jo-sel" name="sort">
              <option value="newest" <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
              <option value="budget_desc" <?= $filters['sort']==='budget_desc'?'selected':'' ?>>Budget high to low</option>
              <option value="deadline" <?= $filters['sort']==='deadline'?'selected':'' ?>>Deadline</option>
            </select>
            <button type="submit" class="jo-filter-btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
              Filter
            </button>
          </div>
        </form>

        <?php if (empty($rows)): ?>
        <div class="jo-empty">
          <div class="jo-empty-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></div>
          <h3>No job offers found</h3>
          <p><?= $isClient ? 'Post your first offer to attract freelancers.' : 'Check back later for new opportunities.' ?></p>
        </div>
        <?php else: ?>
        <div class="jo-grid">
          <?php foreach ($rows as $row):
            $rowId       = (int)($row['id'] ?? 0);
            $rowStatus   = (string)($row['status'] ?? 'open');
            $sc          = $statusColors[$rowStatus] ?? $statusColors['open'];
            $isOwner     = $isClient && ((int)($row['client_id'] ?? 0) === $userId || $isAdmin);
            $skills      = array_filter(array_map('trim', explode(',', (string)($row['skills_required'] ?? ''))));
            $apps        = $clientApplications[$rowId] ?? [];
            $myApp       = $myApplications[$rowId] ?? null;
            $deadline    = (string)($row['deadline_at'] ?? '');
            $deadlineStr = $deadline ? date('M j, Y', strtotime($deadline)) : '';
          ?>
          <div class="jo-card">
            <div class="jo-card-head">
              <div class="jo-card-title"><?= htmlspecialchars((string)($row['title'] ?? '')) ?></div>
              <span class="jo-status-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>">
                <span class="jo-status-dot" style="background:<?= $sc['dot'] ?>"></span>
                <?= ucfirst(str_replace('_',' ',$rowStatus)) ?>
              </span>
            </div>
            <p class="jo-desc"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></p>
            <div class="jo-meta">
              <?php if ((float)($row['budget'] ?? 0) > 0): ?>
              <span class="jo-meta-pill"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg><span class="jo-budget"><?= number_format((float)($row['budget'] ?? 0), 0) ?> TND</span></span>
              <?php endif; ?>
              <?php if ($deadlineStr): ?><span class="jo-meta-pill"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg><?= htmlspecialchars($deadlineStr) ?></span><?php endif; ?>
              <?php if (trim((string)($row['location'] ?? '')) !== ''): ?><span class="jo-meta-pill"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><?= htmlspecialchars((string)($row['location'] ?? '')) ?></span><?php endif; ?>
              <?php if (trim((string)($row['experience_level'] ?? '')) !== ''): ?><span class="jo-meta-pill"><?= htmlspecialchars((string)($row['experience_level'] ?? '')) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($skills)): ?>
            <div class="jo-skills"><?php foreach (array_slice($skills, 0, 5) as $skill): ?><span class="jo-skill-tag"><?= htmlspecialchars(trim($skill)) ?></span><?php endforeach; ?><?php if (count($skills) > 5): ?><span class="jo-skill-tag">+<?= count($skills)-5 ?></span><?php endif; ?></div>
            <?php endif; ?>
            <div class="jo-card-actions">
              <?php if ($isOwner): ?>
              <button type="button" class="jo-btn jo-btn-ghost jo-edit-offer-btn"
                      data-offer-id="<?= $rowId ?>"
                      data-client-id="<?= (int)($row['client_id'] ?? 0) ?>"
                      data-title="<?= htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES) ?>"
                      data-description="<?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES) ?>"
                      data-budget="<?= (float)($row['budget'] ?? 0) ?>"
                      data-skills="<?= htmlspecialchars((string)($row['skills_required'] ?? ''), ENT_QUOTES) ?>"
                      data-location="<?= htmlspecialchars((string)($row['location'] ?? ''), ENT_QUOTES) ?>"
                      data-experience="<?= htmlspecialchars((string)($row['experience_level'] ?? 'Mid'), ENT_QUOTES) ?>"
                      data-project-type="<?= htmlspecialchars((string)($row['project_type'] ?? 'Fixed Price'), ENT_QUOTES) ?>"
                      data-status="<?= htmlspecialchars((string)($row['status'] ?? 'open'), ENT_QUOTES) ?>"
                      data-deadline="<?= htmlspecialchars($localDateTime((string)($row['deadline_at'] ?? '')), ENT_QUOTES) ?>">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <form method="post" data-confirm="Delete this offer?" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_offer">
                <input type="hidden" name="offer_id" value="<?= $rowId ?>">
                <button type="submit" class="jo-btn jo-btn-danger"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg> Delete</button>
              </form>
              <?php endif; ?>
              <?php if ($canApplyToJobs && !$isOwner): ?>
                <?php if ($myApp): ?>
                <span class="jo-app-pill <?= htmlspecialchars((string)($myApp['status'] ?? 'pending')) ?>">Candidature - <?= ucfirst((string)($myApp['status'] ?? 'pending')) ?></span>
                <button class="jo-btn jo-btn-ghost" type="button"
                        onclick='openApplyModal(<?= json_encode([
                          "offerId" => $rowId,
                          "title" => (string)($row["title"] ?? ""),
                          "message" => (string)($myApp["message"] ?? $myApp["cover_letter"] ?? ""),
                          "candidatureId" => (int)($myApp["candidature_id"] ?? 0),
                          "attachments" => $decodeAttachments((string)($myApp["attachments_json"] ?? "")),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>
                  Edit candidature
                </button>
                <form method="post" style="display:inline" data-confirm="Delete your candidature?">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="action" value="delete_candidature">
                  <input type="hidden" name="offer_id" value="<?= $rowId ?>">
                  <button type="submit" class="jo-btn jo-btn-danger">Delete</button>
                </form>
                <?php if ((string)($myApp['status'] ?? '') === 'accepted' && isset($freelancerContracts[$rowId])): ?>
                <a class="jo-btn jo-btn-success" href="contracts.php?contract=<?= (int)$freelancerContracts[$rowId]['id'] ?>#contract-<?= (int)$freelancerContracts[$rowId]['id'] ?>">
                  <?= htmlspecialchars($contractStateLabels[(string)($freelancerContracts[$rowId]['workflow_state'] ?? 'waiting_freelancer')] ?? 'Review Contract') ?>
                </a>
                <?php endif; ?>
                <?php elseif ($rowStatus === 'open'): ?>
                <button class="jo-btn jo-btn-primary" type="button" onclick='openApplyModal(<?= json_encode([
                  "offerId" => $rowId,
                  "title" => (string)($row["title"] ?? ""),
                  "message" => "",
                  "candidatureId" => 0,
                  "attachments" => [],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg> Add Candidature
                </button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
            <?php if ($isOwner && !empty($apps)): ?>
            <div style="border-top:1px solid var(--color-border);padding-top:10px;margin-top:4px;">
              <div style="font-size:.74rem;font-weight:600;color:var(--color-text-muted);margin-bottom:7px;"><?= count($apps) ?> Application<?= count($apps) > 1 ? 's' : '' ?></div>
              <?php if (isset($clientContractsByOffer[$rowId])): ?>
              <a class="jo-btn jo-btn-success" href="contracts.php?contract=<?= (int)$clientContractsByOffer[$rowId]['id'] ?>#contract-<?= (int)$clientContractsByOffer[$rowId]['id'] ?>" style="margin-bottom:9px;">
                <?= htmlspecialchars($contractStateLabels[(string)($clientContractsByOffer[$rowId]['workflow_state'] ?? 'waiting_freelancer')] ?? 'Manage Contract') ?>
              </a>
              <?php endif; ?>
              <div class="jo-apps-list">
                <?php foreach ($apps as $app): ?>
                <?php $appAttachments = $decodeAttachments((string)($app['attachments_json'] ?? '')); ?>
                <div class="jo-app-row">
                    <div style="min-width:0;">
                      <div class="jo-app-applicant"><?= htmlspecialchars(trim((string)($app['first_name'] ?? '') . ' ' . (string)($app['last_name'] ?? ''))) ?></div>
                      <div style="font-size:.72rem;color:var(--color-text-secondary);line-height:1.45;max-width:420px;white-space:pre-line;"><?= htmlspecialchars(mb_strimwidth(trim((string)($app['message'] ?? '')), 0, 140, '...')) ?></div>
                      <div class="jo-app-meta">
                        <?php if ((float)($app['proposed_budget'] ?? 0) > 0): ?><span class="jo-app-meta-pill">Budget: <?= number_format((float)($app['proposed_budget'] ?? 0), 2) ?> TND</span><?php endif; ?>
                        <?php if ((int)($app['estimated_delivery_days'] ?? 0) > 0): ?><span class="jo-app-meta-pill">Delivery: <?= (int)($app['estimated_delivery_days'] ?? 0) ?> day<?= (int)($app['estimated_delivery_days'] ?? 0) > 1 ? 's' : '' ?></span><?php endif; ?>
                      </div>
                      <?php if (trim((string)($app['skills_experience'] ?? '')) !== ''): ?>
                      <div class="jo-helper-text" style="max-width:460px;white-space:pre-line;margin-top:6px;"><?= htmlspecialchars(mb_strimwidth(trim((string)($app['skills_experience'] ?? '')), 0, 180, '...')) ?></div>
                      <?php endif; ?>
                      <?php if ($appAttachments): ?>
                      <div class="jo-attachment-list">
                        <?php foreach ($appAttachments as $attachment): ?>
                        <?php $attachmentUrl = $assetPath((string)($attachment['path'] ?? '')); ?>
                        <?php if ($attachmentUrl !== ''): ?>
                        <a class="jo-attachment-chip" href="<?= htmlspecialchars($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
                          <span>Attachment</span>
                          <span><?= htmlspecialchars((string)($attachment['original_name'] ?? 'Open file')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="jo-app-row-actions">
                      <form method="post" style="display:inline" data-confirm="Delete this application?">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="delete_candidature_client">
                        <input type="hidden" name="offer_id" value="<?= $rowId ?>">
                        <input type="hidden" name="application_id" value="<?= (int)($app['id'] ?? 0) ?>">
                        <button type="submit" class="jo-btn jo-btn-danger" style="padding:4px 9px;font-size:.73rem;">Delete</button>
                      </form>
                      <?php if ((string)($app['status'] ?? '') === 'pending'): ?>
                      <form method="post" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="action" value="decide_application">
                      <input type="hidden" name="application_id" value="<?= (int)($app['id'] ?? 0) ?>">
                      <input type="hidden" name="offer_id" value="<?= $rowId ?>">
                      <input type="hidden" name="freelancer_id" value="<?= (int)($app['freelancer_id'] ?? 0) ?>">
                      <button name="decision" value="accepted" class="jo-btn jo-btn-success" style="padding:4px 9px;font-size:.73rem;">Accept</button>
                      <button name="decision" value="rejected" class="jo-btn jo-btn-danger" style="padding:4px 9px;font-size:.73rem;">Reject</button>
                    </form>
                    <?php else: ?>
                      <span class="jo-app-pill <?= htmlspecialchars((string)($app['status'] ?? 'pending')) ?>" style="padding:3px 8px;font-size:.72rem;"><?= ucfirst((string)($app['status'] ?? 'pending')) ?></span>
                      <?php if ((string)($app['status'] ?? '') === 'accepted' && !isset($clientContractsByOffer[$rowId])): ?>
                        <a class="jo-btn jo-btn-primary" style="padding:4px 9px;font-size:.73rem;" href="contracts.php?open_create=1&offer_id=<?= $rowId ?>&freelancer_id=<?= (int)($app['freelancer_id'] ?? 0) ?>">
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                          Prepare Contract
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
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
  <?php if ($isClient): ?>
  <div class="jo-modal-backdrop" id="createModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="jo-modal">
      <div class="jo-modal-head">
        <h3>Post a Job Offer</h3>
        <button class="jo-modal-close" onclick="document.getElementById('createModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="createOfferForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_offer">
        <div class="jo-fgrid">
          <div class="jo-fg full"><label class="jo-fl">Job Title *</label><input type="text" name="title" class="jo-fi" placeholder="e.g. Senior React Developer" required></div>
          <div class="jo-fg"><label class="jo-fl">Budget (TND)</label><input type="number" name="budget" class="jo-fi" min="0" step="0.01" placeholder="0.00"></div>
          <div class="jo-fg"><label class="jo-fl">Deadline</label><input type="datetime-local" name="deadline_at" class="jo-fi"></div>
          <div class="jo-fg"><label class="jo-fl">Experience Level</label><select name="experience_level" class="jo-fsel"><option value="Junior">Junior</option><option value="Mid" selected>Mid</option><option value="Senior">Senior</option><option value="Expert">Expert</option></select></div>
          <div class="jo-fg"><label class="jo-fl">Project Type</label><select name="project_type" class="jo-fsel"><option value="Fixed Price">Fixed Price</option><option value="Hourly">Hourly</option><option value="Retainer">Retainer</option><option value="Long-term">Long-term</option></select></div>
          <div class="jo-fg full"><label class="jo-fl">Location</label><input type="text" name="location" class="jo-fi" placeholder="e.g. Remote, Tunis, Paris"></div>
          <div class="jo-fg full"><label class="jo-fl">Required Skills <span style="font-weight:400;color:var(--color-text-muted)">(comma-separated)</span></label><input type="text" name="skills_required" class="jo-fi" placeholder="React, Node.js, PostgreSQL"></div>
          <div class="jo-fg full"><label class="jo-fl">Description *</label><textarea name="description" class="jo-fta" placeholder="Describe the project, responsibilities, and expectations..." required></textarea></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="jo-btn jo-btn-ghost" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="jo-btn jo-btn-primary"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg> Publish Offer</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isClient): ?>
  <div class="jo-modal-backdrop" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="jo-modal">
      <div class="jo-modal-head">
        <h3>Edit Job Offer</h3>
        <button class="jo-modal-close" onclick="document.getElementById('editModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="editOfferForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_offer">
        <input type="hidden" name="offer_id" id="edit_offer_id">
        <input type="hidden" name="client_id" id="edit_client_id" value="<?= $userId ?>">
        <div class="jo-fgrid">
          <div class="jo-fg full"><label class="jo-fl">Job Title *</label><input type="text" name="title" id="edit_title" class="jo-fi" required></div>
          <div class="jo-fg"><label class="jo-fl">Budget (TND)</label><input type="number" name="budget" id="edit_budget" class="jo-fi" min="0" step="0.01"></div>
          <div class="jo-fg"><label class="jo-fl">Deadline</label><input type="datetime-local" name="deadline_at" id="edit_deadline_at" class="jo-fi"></div>
          <div class="jo-fg"><label class="jo-fl">Experience Level</label><select name="experience_level" id="edit_experience_level" class="jo-fsel"><option value="Junior">Junior</option><option value="Mid">Mid</option><option value="Senior">Senior</option><option value="Expert">Expert</option></select></div>
          <div class="jo-fg"><label class="jo-fl">Project Type</label><select name="project_type" id="edit_project_type" class="jo-fsel"><option value="Fixed Price">Fixed Price</option><option value="Hourly">Hourly</option><option value="Retainer">Retainer</option><option value="Long-term">Long-term</option></select></div>
          <div class="jo-fg"><label class="jo-fl">Status</label><select name="status" id="edit_status" class="jo-fsel"><option value="open">Open</option><option value="in_progress">In progress</option><option value="closed">Closed</option><option value="archived">Archived</option></select></div>
          <div class="jo-fg full"><label class="jo-fl">Location</label><input type="text" name="location" id="edit_location" class="jo-fi"></div>
          <div class="jo-fg full"><label class="jo-fl">Required Skills</label><input type="text" name="skills_required" id="edit_skills_required" class="jo-fi"></div>
          <div class="jo-fg full"><label class="jo-fl">Description *</label><textarea name="description" id="edit_description" class="jo-fta" required></textarea></div>
        </div>
        <div style="display:flex;gap:8px;margin-top:18px;justify-content:flex-end;">
          <button type="button" class="jo-btn jo-btn-ghost" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="jo-btn jo-btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Apply Modal -->
  <?php if ($canApplyToJobs): ?>
  <div class="jo-modal-backdrop" id="applyModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="jo-modal" style="max-width:560px;">
      <div class="jo-modal-head">
        <h3 id="applyModalTitle">Add Candidature</h3>
        <button class="jo-modal-close" onclick="document.getElementById('applyModal').classList.remove('open')" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <form method="post" id="applyOfferForm" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="apply_offer">
        <input type="hidden" name="offer_id" id="applyOfferId" value="">
        <input type="hidden" name="candidature_id" id="applyCandidatureId" value="">
        <div class="jo-fg full" style="margin-bottom:14px;">
          <label class="jo-fl">Candidature Message *</label>
          <textarea name="cover_letter" id="applyCoverLetter" class="jo-fta" placeholder="Tell the client who you are and why you're a good fit..." required></textarea>
        </div>
        <div class="jo-fg full">
          <label class="jo-fl" for="applyAttachments">CV / Resume</label>
          <div class="jo-upload-card">
            <input type="file" name="attachments[]" id="applyAttachments" class="jo-file-input" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.zip,.rar,.7z" multiple>
            <label class="jo-upload-zone" id="applyAttachmentsZone" for="applyAttachments">
              <span class="jo-upload-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
              </span>
              <span class="jo-upload-copy">
                <strong>Drop your CV here or browse files</strong>
                <span>PDF, DOC, DOCX, PNG, JPG, ZIP or RAR. Up to 3 files.</span>
              </span>
              <span class="jo-upload-action">Choose files</span>
            </label>
            <div class="jo-upload-meta">
              <span>Current attachments stay visible below when you edit.</span>
              <span id="applyAttachmentCount">No files selected</span>
            </div>
            <div class="jo-helper-text">Upload your CV or supporting files. If you choose new files while editing, they will replace the current attachments.</div>
            <div class="jo-attachment-list" id="applyAttachmentList"></div>
            <div class="jo-selected-files" id="applySelectedFiles"></div>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;">
          <button type="button" class="jo-btn jo-btn-ghost" onclick="document.getElementById('applyModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="jo-btn jo-btn-primary"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg> Send candidature</button>
        </div>
      </form>
    </div>
  </div>
  <script>
  function renderApplyAttachments(attachments) {
    const list = document.getElementById('applyAttachmentList');
    if (!list) return;
    list.innerHTML = '';
    (attachments || []).forEach((attachment) => {
      const chip = document.createElement('span');
      chip.className = 'jo-attachment-chip';
      chip.innerHTML = `<span>Current</span><span>${String(attachment.original_name || 'Attachment')}</span>`;
      list.appendChild(chip);
    });
  }

  function formatAttachmentSize(bytes) {
    const value = Number(bytes || 0);
    if (!Number.isFinite(value) || value <= 0) {
      return '';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unitIndex = 0;
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }
    const display = unitIndex === 0 ? String(Math.round(size)) : size.toFixed(size >= 10 ? 0 : 1);
    return `${display} ${units[unitIndex]}`;
  }

  function renderApplySelectedFiles(files) {
    const list = document.getElementById('applySelectedFiles');
    const count = document.getElementById('applyAttachmentCount');
    if (!list || !count) return;

    const selected = Array.from(files || []);
    list.innerHTML = '';

    if (selected.length === 0) {
      count.textContent = 'No files selected';
      return;
    }

    count.textContent = `${selected.length} file${selected.length > 1 ? 's' : ''} selected`;
    selected.slice(0, 3).forEach((file) => {
      const chip = document.createElement('span');
      chip.className = 'jo-selected-chip';
      const sizeLabel = formatAttachmentSize(file.size);
      chip.innerHTML = `<span>${String(file.name || 'File')}</span>${sizeLabel ? `<small>${sizeLabel}</small>` : ''}`;
      list.appendChild(chip);
    });

    if (selected.length > 3) {
      const more = document.createElement('span');
      more.className = 'jo-selected-chip';
      more.innerHTML = `<span>+${selected.length - 3} more</span>`;
      list.appendChild(more);
    }
  }

  function openApplyModal(payload) {
    const data = payload || {};
    document.getElementById('applyOfferId').value = data.offerId || 0;
    document.getElementById('applyModalTitle').textContent = 'Candidature: ' + (data.title || 'Offer');
    document.getElementById('applyCoverLetter').value = data.message || '';
    document.getElementById('applyCandidatureId').value = data.candidatureId || 0;
    document.getElementById('applyAttachments').value = '';
    renderApplyAttachments(Array.isArray(data.attachments) ? data.attachments : []);
    renderApplySelectedFiles([]);
    document.getElementById('applyModal').classList.add('open');
  }

  const applyAttachmentsInput = document.getElementById('applyAttachments');
  const applyAttachmentsZone = document.getElementById('applyAttachmentsZone');
  if (applyAttachmentsInput) {
    applyAttachmentsInput.addEventListener('change', (event) => {
      renderApplySelectedFiles(event.target.files);
    });
  }
  if (applyAttachmentsInput && applyAttachmentsZone) {
    const setDragState = (active) => applyAttachmentsZone.classList.toggle('is-dragover', active);
    ['dragenter', 'dragover'].forEach((eventName) => {
      applyAttachmentsZone.addEventListener(eventName, (event) => {
        event.preventDefault();
        setDragState(true);
      });
    });
    ['dragleave', 'dragend'].forEach((eventName) => {
      applyAttachmentsZone.addEventListener(eventName, () => setDragState(false));
    });
    applyAttachmentsZone.addEventListener('drop', (event) => {
      event.preventDefault();
      setDragState(false);
      const dropped = event.dataTransfer && event.dataTransfer.files ? Array.from(event.dataTransfer.files) : [];
      if (!dropped.length) {
        return;
      }
      try {
        const transfer = new DataTransfer();
        dropped.slice(0, 3).forEach((file) => transfer.items.add(file));
        applyAttachmentsInput.files = transfer.files;
      } catch (error) {
        // Fallback to the browser's native picker if DataTransfer assignment is unavailable.
      }
      applyAttachmentsInput.dispatchEvent(new Event('change'));
    });
  }
  </script>
  <?php endif; ?>

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

  const editButtons = document.querySelectorAll('.jo-edit-offer-btn');
  editButtons.forEach((button) => {
    button.addEventListener('click', () => {
      const d = button.dataset;
      const modal = document.getElementById('editModal');
      if (!modal) return;
      document.getElementById('edit_offer_id').value = d.offerId || '';
      document.getElementById('edit_client_id').value = d.clientId || '<?= $userId ?>';
      document.getElementById('edit_title').value = d.title || '';
      document.getElementById('edit_description').value = d.description || '';
      document.getElementById('edit_budget').value = d.budget || '';
      document.getElementById('edit_skills_required').value = d.skills || '';
      document.getElementById('edit_location').value = d.location || '';
      document.getElementById('edit_experience_level').value = d.experience || 'Mid';
      document.getElementById('edit_project_type').value = d.projectType || 'Fixed Price';
      document.getElementById('edit_status').value = d.status || 'open';
      document.getElementById('edit_deadline_at').value = d.deadline || '';
      modal.classList.add('open');
    });
  });

  </script>
</body>
</html>


