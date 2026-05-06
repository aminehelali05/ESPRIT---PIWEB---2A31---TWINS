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

/* ── AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['ajax'] ?? '') !== '') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token.');
        }
        $ajaxAction = strtolower($clean($_POST['ajax'] ?? ''));
        if ($ajaxAction === 'salary_analyze') {
            $result = $controller->analyzeSalarySuggestion($_POST, (int) ($_POST['force_refresh'] ?? 0) === 1);
            echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($ajaxAction === 'smart_match') {
            $offerId = (int) ($_POST['offer_id'] ?? 0);
            $result = $controller->smartMatchCandidates($offerId, $userId, (int) ($_POST['force_refresh'] ?? 0) === 1);
            echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($ajaxAction === 'ai_match_offer') {
            $offerId = (int) ($_POST['offer_id'] ?? 0);
            if ($offerId <= 0) throw new RuntimeException('Invalid offer ID.');
            $result = $controller->aiMatchOfferForUser($offerId, $userId, (int)($_POST['force_refresh'] ?? 0) === 1);
            echo json_encode(['success' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($ajaxAction === 'find_best_jobs') {
            $approvedOffers = $controller->listFrontofficeRows(['status' => 'all'], $userId);
            $matchResults = [];
            $topLimit = min(count($approvedOffers), 8);
            foreach (array_slice($approvedOffers, 0, $topLimit) as $offer) {
                $offerId = (int)($offer['id'] ?? 0);
                if ($offerId <= 0 || $controller->isOwnedByClient($offerId, $userId)) continue;
                try {
                    $match = $controller->aiMatchOfferForUser($offerId, $userId, false);
                    $matchResults[] = [
                        'offer_id'         => $offerId,
                        'title'            => (string)($offer['title'] ?? ''),
                        'budget'           => (float)($offer['budget'] ?? 0),
                        'location'         => (string)($offer['location'] ?? ''),
                        'match_percentage' => (int)($match['match_percentage'] ?? 0),
                        'explanation'      => (string)($match['explanation'] ?? ''),
                        'highlights'       => (array)($match['highlights'] ?? []),
                        'roi_score'        => (int)($match['roi_score'] ?? 0),
                    ];
                } catch (Throwable $ignore) {}
            }
            usort($matchResults, static fn($a, $b) => ($b['roi_score'] ?? 0) <=> ($a['roi_score'] ?? 0));
            echo json_encode(['success' => true, 'result' => ['matches' => array_slice($matchResults, 0, 5)]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        throw new RuntimeException('Unsupported AJAX action.');
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $notice = ['type' => 'error', 'message' => 'Invalid security token.'];
        $redirectAfterPost = $selfRedirect;
    } else {
        $action = strtolower($clean($_POST['form_action'] ?? $_POST['action'] ?? ''));
        try {
            if ($action === 'create_offer' && $isClient) {
                $payload = [
                    'title'            => $clean($_POST['title'] ?? ''),
                    'description'      => $clean($_POST['description'] ?? ''),
                    'budget'           => (string)($_POST['budget'] ?? ''),
                    'salary'           => $clean($_POST['salary'] ?? ''),
                    'skills_required'  => $clean($_POST['skills_required'] ?? ''),
                    'location'         => $clean($_POST['location'] ?? ''),
                    'experience_level' => $clean($_POST['experience_level'] ?? 'Mid'),
                    'project_type'     => $clean($_POST['project_type'] ?? 'Fixed Price'),
                    'status'           => 'pending',
                    'client_id'        => $userId,
                ];
                $controller->createByClient($payload);
                $notice = ['type' => 'success', 'message' => 'Job offer published successfully!'];
                $redirectAfterPost = $selfRedirect;
            }
            if ($action === 'update_offer' && $isClient) {
                $offerId = (int)($_POST['offer_id'] ?? 0);
                if ($offerId <= 0) throw new RuntimeException('Invalid offer.');
                $payload = [
                    'title'            => $clean($_POST['title'] ?? ''),
                    'description'      => $clean($_POST['description'] ?? ''),
                    'budget'           => (string)($_POST['budget'] ?? ''),
                    'salary'           => $clean($_POST['salary'] ?? ''),
                    'skills_required'  => $clean($_POST['skills_required'] ?? ''),
                    'location'         => $clean($_POST['location'] ?? ''),
                    'experience_level' => $clean($_POST['experience_level'] ?? 'Mid'),
                    'project_type'     => $clean($_POST['project_type'] ?? 'Fixed Price'),
                    'status'           => $clean($_POST['status'] ?? 'pending'),
                ];
                $targetClientId = $userId;
                if ($isAdmin) {
                    $targetOffer = $controller->findById($offerId);
                    $targetClientId = (int)($targetOffer['client_id'] ?? 0);
                }
                $updated = $controller->updateOwnedByClient($offerId, $targetClientId, $payload);
                if (!$updated) throw new RuntimeException('Unable to update this offer.');
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
                if ($controller->isOwnedByClient($offerId, $userId)) throw new RuntimeException('You cannot apply to your own offer.');
                $controller->saveCandidatureForFreelancer(
                    $offerId, $userId,
                    $candidatureId > 0 ? $candidatureId : null,
                    [
                        'cover_letter'            => $clean($_POST['cover_letter'] ?? ''),
                        'proposed_budget'          => $_POST['proposed_budget'] ?? '',
                        'estimated_delivery_days'  => $_POST['estimated_delivery_days'] ?? '',
                        'skills_experience'        => $clean($_POST['skills_experience'] ?? ''),
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
                if (!$controller->isOwnedByClient($offerId, $userId)) throw new RuntimeException('You can only delete applications for your own offers.');
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
    if ($redirectAfterPost === '') $redirectAfterPost = $selfRedirect;
    if (str_starts_with($redirectAfterPost, 'contracts.php')) {
        $_SESSION['contract_notice_front'] = $notice;
    } else {
        $_SESSION['job_offer_notice_front'] = $notice;
    }
    header('Location: ' . $redirectAfterPost);
    exit;
}

/* ── GET ── */
$filters = [
    'q'      => $clean($_GET['q'] ?? ''),
    'status' => $clean($_GET['status'] ?? 'all'),
    'sort'   => $clean($_GET['sort'] ?? 'newest'),
];
$rows                  = $controller->listFrontofficeRows($filters, $userId);
$myApplications        = $controller->freelancerApplicationsMap($userId);
$clientApplications    = $isClient ? $controller->clientApplicationsByOffer($userId) : [];
$freelancerContracts   = $controller->contractsMapForFreelancer($userId);
$clientContractsByOffer = $isClient ? $controller->contractsMapForClient($userId) : [];
$offerInsights = [];
foreach ($rows as $offerRow) {
    $offerKey = (int)($offerRow['id'] ?? 0);
    if ($offerKey <= 0) continue;
    try {
        $offerInsights[$offerKey] = $controller->buildOfferInsights($offerKey);
    } catch (Throwable $exception) {
        $offerInsights[$offerKey] = ['salary' => '', 'company_rating' => ['average_rating' => null, 'reviews_count' => 0]];
    }
}
$contractStateLabels = [
    'waiting_client'     => 'Draft',
    'waiting_freelancer' => 'Waiting',
    'completed'          => 'Finalized',
    'refused'            => 'Refused',
];
$decodeAttachments = static function (?string $json): array {
    $decoded = json_decode((string)($json ?? ''), true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
};
$assetPath = static function (?string $path): string {
    $raw = trim((string)($path ?? ''));
    if ($raw === '') return '';
    return str_starts_with($raw, 'assets/') ? '../../' . $raw : $raw;
};
$statusColors = [
    'pending'     => ['bg' => 'rgba(245,158,11,.1)', 'text' => '#92400e', 'dot' => '#f59e0b'],
    'approved'    => ['bg' => 'rgba(5,150,105,.1)',  'text' => '#059669', 'dot' => '#10b981'],
    'rejected'    => ['bg' => 'rgba(225,29,72,.08)', 'text' => '#9f1239', 'dot' => '#fb7185'],
    'open'        => ['bg' => 'rgba(5,150,105,.1)',  'text' => '#059669', 'dot' => '#10b981'],
    'in_progress' => ['bg' => 'rgba(99,102,241,.1)', 'text' => '#4338ca', 'dot' => '#6366f1'],
    'closed'      => ['bg' => 'rgba(107,114,128,.1)','text' => '#374151', 'dot' => '#6b7280'],
    'archived'    => ['bg' => 'rgba(107,114,128,.08)','text' => '#4b5563','dot' => '#9ca3af'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Offers — Diversity.is</title>
  <meta name="description" content="Browse job opportunities, submit candidatures, and get AI-powered job matching on Diversity.is">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/sidebar.css">
  <style>
    /* ════ BASE ════ */
    *, body { font-family: 'Poppins', system-ui, -apple-system, sans-serif; }

    /* ════ GLASS HEADER ════ */
    .jo-glass-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 16px;
      background: rgba(255,255,255,0.75);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(99,102,241,0.12);
      border-radius: 20px;
      padding: 22px 26px;
      margin-bottom: 22px;
      box-shadow: 0 8px 40px rgba(99,102,241,0.07), 0 1px 0 rgba(255,255,255,0.9) inset;
      animation: joSlideDown 0.4s cubic-bezier(.16,1,.3,1);
    }
    .jo-header-text h2 {
      font-size: 1.4rem;
      font-weight: 800;
      letter-spacing: -0.025em;
      color: var(--color-text-primary);
      margin: 0 0 3px;
    }
    .jo-header-text p {
      font-size: 0.82rem;
      color: var(--color-text-secondary);
      margin: 0;
    }
    .jo-header-actions {
      display: flex;
      gap: 9px;
      flex-wrap: wrap;
      align-items: center;
    }

    /* ════ BUTTONS ════ */
    .jo-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      font-size: 0.79rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      transition: all 0.22s ease;
      white-space: nowrap;
    }
    .jo-btn-primary {
      background: var(--color-accent, #6366f1);
      color: #fff;
      box-shadow: 0 4px 14px rgba(99,102,241,0.22);
    }
    .jo-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 7px 20px rgba(99,102,241,0.3); }
    .jo-btn-danger  { background: rgba(225,29,72,.08); color: #be123c; border: 1px solid rgba(225,29,72,.2); }
    .jo-btn-danger:hover { background: rgba(225,29,72,.14); }
    .jo-btn-ghost   { background: var(--color-surface-2); color: var(--color-text-secondary); border: 1px solid var(--color-border); }
    .jo-btn-ghost:hover { color: var(--color-text-primary); border-color: rgba(99,102,241,0.3); }
    .jo-btn-success { background: rgba(5,150,105,.09); color: #065f46; border: 1px solid rgba(5,150,105,.22); }

    /* Premium AI Buttons */
    .jo-btn-ai {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 16px;
      border-radius: 12px;
      border: none;
      cursor: pointer;
      font-size: 0.79rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      transition: all 0.22s ease;
      white-space: nowrap;
    }
    .jo-btn-ai-salary {
      background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
      color: #fff;
      box-shadow: 0 4px 14px rgba(14,165,233,0.22);
    }
    .jo-btn-ai-salary:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(99,102,241,0.3); }
    .jo-btn-ai-match {
      background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 100%);
      color: #fff;
      box-shadow: 0 4px 14px rgba(139,92,246,0.22);
    }
    .jo-btn-ai-match:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(139,92,246,0.32); }
    .jo-btn-ai:disabled, .jo-btn-ai-salary:disabled, .jo-btn-ai-match:disabled {
      opacity: 0.6;
      cursor: wait;
      transform: none;
    }

    /* ════ NOTICES ════ */
    .jo-notice {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 18px;
      font-size: 0.84rem;
      font-weight: 500;
      animation: joSlideDown 0.3s ease;
    }
    .jo-notice.success { background: rgba(5,150,105,.09); border: 1px solid rgba(5,150,105,.22); color: #065f46; }
    .jo-notice.error   { background: rgba(225,29,72,.08); border: 1px solid rgba(225,29,72,.18); color: #9f1239; }

    /* ════ FILTERS ════ */
    .jo-filters {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      padding: 12px 16px;
      margin-bottom: 20px;
    }
    .jo-search-wrap { position: relative; flex: 1; min-width: 160px; }
    .jo-search-wrap svg { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); pointer-events: none; }
    .jo-search { width: 100%; padding: 8px 10px 8px 34px; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius: 9px; font-size: 0.82rem; color: var(--color-text-primary); font-family: 'Poppins', sans-serif; }
    .jo-search:focus { border-color: var(--color-accent); outline: none; }
    .jo-sel { padding: 8px 12px; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius: 9px; font-size: 0.81rem; color: var(--color-text-primary); cursor: pointer; font-family: 'Poppins', sans-serif; }
    .jo-filter-btn { display: inline-flex; align-items: center; gap: 5px; background: var(--color-accent); color: #fff; border: none; border-radius: 9px; padding: 8px 16px; font-size: 0.81rem; font-weight: 700; cursor: pointer; font-family: 'Poppins', sans-serif; }

    /* ════ AI RESULTS PANELS ════ */
    .jo-ai-panel {
      background: linear-gradient(135deg, rgba(99,102,241,.04) 0%, rgba(168,85,247,.03) 100%);
      border: 1px solid rgba(99,102,241,.15);
      border-radius: 18px;
      padding: 20px;
      margin-bottom: 20px;
      animation: joSlideDown 0.4s cubic-bezier(.16,1,.3,1);
    }
    .jo-ai-panel-title {
      font-size: 0.92rem;
      font-weight: 800;
      color: var(--color-text-primary);
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 14px;
    }
    .jo-ai-panel-icon {
      width: 32px; height: 32px;
      border-radius: 10px;
      background: linear-gradient(135deg, #6366f1, #a855f7);
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .jo-match-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
    .jo-match-card {
      background: #fff;
      border: 1px solid rgba(99,102,241,.12);
      border-radius: 14px;
      padding: 14px;
      transition: all 0.22s ease;
      position: relative;
      overflow: hidden;
    }
    .jo-match-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 2px;
      background: linear-gradient(90deg, #6366f1, #a855f7);
    }
    .jo-match-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(99,102,241,.1); border-color: rgba(99,102,241,.25); }
    .jo-match-card.is-best {
      border-color: rgba(99,102,241,.3);
      box-shadow: 0 0 0 2px rgba(99,102,241,.15), 0 8px 24px rgba(99,102,241,.1);
    }
    .jo-match-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px; }
    .jo-match-title { font-size: 0.86rem; font-weight: 700; color: var(--color-text-primary); line-height: 1.3; }
    .jo-match-meta { font-size: 0.72rem; color: var(--color-text-muted); margin-top: 2px; }
    .jo-pct-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 44px; padding: 3px 8px;
      border-radius: 8px;
      font-size: 0.8rem; font-weight: 800;
      flex-shrink: 0;
    }
    .jo-pct-badge.high { background: rgba(5,150,105,.1); color: #059669; }
    .jo-pct-badge.mid  { background: rgba(245,158,11,.1); color: #d97706; }
    .jo-pct-badge.low  { background: rgba(239,68,68,.08); color: #dc2626; }
    .jo-roi-chip {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 8px; border-radius: 999px;
      font-size: 0.68rem; font-weight: 700;
    }
    .jo-roi-chip.high { background: rgba(5,150,105,.1); color: #047857; }
    .jo-roi-chip.mid  { background: rgba(245,158,11,.1); color: #b45309; }
    .jo-roi-chip.low  { background: rgba(239,68,68,.08); color: #dc2626; }
    .jo-match-exp { font-size: 0.76rem; color: var(--color-text-secondary); line-height: 1.55; margin: 8px 0; }
    .jo-match-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
    .jo-match-tag { background: var(--color-accent-light, rgba(99,102,241,.08)); color: var(--color-accent, #6366f1); border-radius: 5px; padding: 2px 7px; font-size: 0.68rem; font-weight: 600; }
    .jo-best-badge {
      display: inline-flex; align-items: center; gap: 4px;
      background: linear-gradient(135deg, #f59e0b, #ef4444);
      color: #fff;
      padding: 2px 8px; border-radius: 999px;
      font-size: 0.67rem; font-weight: 800;
      margin-bottom: 6px;
    }

    /* ════ JOB CARDS ════ */
    .jo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
    .jo-card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 18px;
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      transition: all 0.28s ease;
      position: relative;
      overflow: hidden;
      animation: joFadeIn 0.4s ease-out forwards;
    }
    .jo-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--color-accent, #6366f1), #a855f7);
      opacity: 0.7;
      transition: opacity 0.28s;
    }
    .jo-card:hover { transform: translateY(-3px); box-shadow: 0 14px 36px rgba(99,102,241,.11); border-color: rgba(99,102,241,.25); }
    .jo-card:hover::before { opacity: 1; }
    .jo-card.is-highlighted {
      border-color: rgba(99,102,241,.35);
      box-shadow: 0 0 0 2px rgba(99,102,241,.18), 0 14px 36px rgba(99,102,241,.12);
    }

    .jo-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .jo-card-title { font-size: 0.93rem; font-weight: 700; color: var(--color-text-primary); line-height: 1.3; }
    .jo-card-badges { display: flex; gap: 5px; align-items: center; flex-shrink: 0; }

    .jo-status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 999px; font-size: 0.69rem; font-weight: 700; white-space: nowrap; }
    .jo-status-dot  { width: 5px; height: 5px; border-radius: 50%; }

    /* Score badge on card (updated by AI) */
    .jo-card-score {
      display: none;
      align-items: center;
      padding: 2px 8px;
      border-radius: 8px;
      font-size: 0.68rem;
      font-weight: 800;
    }
    .jo-card-score.score-high { display: inline-flex; background: rgba(5,150,105,.1); color: #047857; }
    .jo-card-score.score-mid  { display: inline-flex; background: rgba(245,158,11,.1); color: #b45309; }
    .jo-card-score.score-low  { display: inline-flex; background: rgba(239,68,68,.08); color: #dc2626; }

    .jo-salary-row { font-weight: 700; font-size: 0.92rem; color: var(--color-accent, #6366f1); }
    .jo-poster-row { display: flex; align-items: center; gap: 8px; }
    .jo-poster-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1.5px solid var(--color-border); }
    .jo-poster-name { font-size: 0.79rem; color: var(--color-text-secondary); font-weight: 500; }
    .jo-desc { font-size: 0.8rem; color: var(--color-text-secondary); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .jo-meta { display: flex; flex-wrap: wrap; gap: 6px; }
    .jo-meta-pill { display: inline-flex; align-items: center; gap: 4px; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius: 7px; padding: 3px 8px; font-size: 0.72rem; color: var(--color-text-secondary); }
    .jo-skills { display: flex; flex-wrap: wrap; gap: 5px; }
    .jo-skill-tag { background: rgba(99,102,241,.08); color: var(--color-accent, #6366f1); border-radius: 5px; padding: 2px 8px; font-size: 0.69rem; font-weight: 600; }
    .jo-card-actions { margin-top: auto; display: flex; flex-wrap: wrap; gap: 7px; align-items: center; }

    /* App pill */
    .jo-app-pill { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 7px; font-size: 0.77rem; font-weight: 600; }
    .jo-app-pill.pending  { background: rgba(245,158,11,.1); color: #92400e; border: 1px solid rgba(245,158,11,.22); }
    .jo-app-pill.accepted { background: rgba(5,150,105,.1); color: #065f46; border: 1px solid rgba(5,150,105,.22); }
    .jo-app-pill.rejected { background: rgba(225,29,72,.08); color: #9f1239; border: 1px solid rgba(225,29,72,.18); }

    /* Applications sub-panel */
    .jo-apps-list { display: flex; flex-direction: column; gap: 7px; margin-top: 8px; }
    .jo-app-row { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 6px; background: var(--color-surface-2); border: 1px solid var(--color-border); border-radius: 10px; padding: 10px 13px; }
    .jo-app-applicant { font-size: 0.8rem; font-weight: 700; color: var(--color-text-primary); }
    .jo-app-row-actions { display: flex; gap: 5px; flex-wrap: wrap; }
    .jo-app-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
    .jo-app-meta-pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 999px; background: rgba(99,102,241,.08); color: var(--color-accent, #6366f1); font-size: 0.68rem; font-weight: 700; }
    .jo-attachment-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .jo-attachment-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 9px; border-radius: 999px; background: rgba(15,23,42,.05); border: 1px solid rgba(15,23,42,.08); color: var(--color-text-primary); text-decoration: none; font-size: 0.7rem; font-weight: 600; }
    .jo-attachment-chip:hover { border-color: rgba(99,102,241,.24); color: var(--color-accent); }
    .jo-helper-text { font-size: 0.72rem; color: var(--color-text-muted); line-height: 1.45; }

    /* Upload zone */
    .jo-upload-card { display: flex; flex-direction: column; gap: 12px; padding: 14px; border-radius: 16px; background: linear-gradient(180deg, rgba(99,102,241,.04), rgba(99,102,241,.015)); border: 1px solid rgba(99,102,241,.15); }
    .jo-upload-zone { display: flex; align-items: flex-start; gap: 14px; width: 100%; border: 1px dashed rgba(99,102,241,.3); background: linear-gradient(180deg, rgba(99,102,241,.07), rgba(99,102,241,.02)); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s ease; min-height: 100px; }
    .jo-upload-zone:hover { border-color: var(--color-accent, #6366f1); background: linear-gradient(180deg, rgba(99,102,241,.1), rgba(99,102,241,.04)); transform: translateY(-1px); }
    .jo-upload-zone.is-dragover { border-color: var(--color-accent, #6366f1); background: linear-gradient(180deg, rgba(99,102,241,.12), rgba(99,102,241,.05)); }
    .jo-upload-icon { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: var(--color-surface); color: var(--color-accent, #6366f1); flex-shrink: 0; border: 1px solid var(--color-border); }
    .jo-upload-copy { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
    .jo-upload-copy strong { font-size: 0.88rem; color: var(--color-text-primary); }
    .jo-upload-copy span { font-size: 0.73rem; color: var(--color-text-muted); }
    .jo-upload-badge { display: inline-flex; align-items: center; padding: 3px 7px; border-radius: 999px; background: rgba(99,102,241,.09); color: var(--color-accent); font-size: 0.66rem; font-weight: 700; margin-right: 4px; }
    .jo-upload-action { display: inline-flex; align-items: center; justify-content: center; gap: 5px; min-width: 96px; padding: 8px 12px; border-radius: 10px; background: var(--color-accent); color: #fff; font-size: 0.74rem; font-weight: 700; flex-shrink: 0; }
    .jo-upload-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: 0.71rem; color: var(--color-text-muted); }
    .jo-upload-counter { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 999px; background: rgba(15,23,42,.06); border: 1px solid rgba(15,23,42,.08); color: var(--color-text-secondary); font-size: 0.69rem; font-weight: 700; }
    .jo-upload-counter.is-active { background: rgba(5,150,105,.08); border-color: rgba(5,150,105,.16); color: #047857; }
    .jo-file-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .jo-selected-files { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
    .jo-selected-chip { display: flex; align-items: flex-start; gap: 10px; padding: 10px 11px; border-radius: 12px; background: var(--color-surface); border: 1px solid var(--color-border); font-size: 0.7rem; font-weight: 600; min-width: 0; }
    .jo-selected-chip-icon { width: 28px; height: 28px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; background: rgba(99,102,241,.12); color: var(--color-accent); font-size: 0.6rem; font-weight: 800; }
    .jo-selected-chip-body { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .jo-selected-chip-body strong { font-size: 0.72rem; color: var(--color-text-primary); line-height: 1.25; word-break: break-word; }
    .jo-selected-chip-body small { color: var(--color-text-muted); font-weight: 500; font-size: 0.65rem; }
    .jo-selected-chip.is-current .jo-selected-chip-icon { background: rgba(15,23,42,.08); color: var(--color-text-secondary); }
    .jo-selected-placeholder { display: flex; align-items: center; justify-content: center; padding: 12px 10px; border-radius: 10px; border: 1px dashed var(--color-border); color: var(--color-text-muted); font-size: 0.72rem; }

    /* Modal */
    .jo-modal-backdrop { position: fixed; inset: 0; background: rgba(15,21,42,.44); backdrop-filter: blur(6px); z-index: 200; display: none; align-items: center; justify-content: center; padding: 20px; }
    .jo-modal-backdrop.open { display: flex; animation: joFadeIn 0.2s ease; }
    .jo-modal { background: var(--color-surface); border: 1px solid var(--color-border-strong); border-radius: 22px; padding: 28px; width: 100%; max-width: 600px; max-height: 92vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(15,23,42,.18); animation: joModalIn 0.28s cubic-bezier(.16,1,.3,1); }
    .jo-modal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .jo-modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--color-text-primary); margin: 0; }
    .jo-modal-close { background: none; border: none; cursor: pointer; padding: 5px; color: var(--color-text-muted); border-radius: 8px; transition: all 0.15s; }
    .jo-modal-close:hover { background: var(--color-surface-2); color: var(--color-text-primary); }
    .jo-fgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .jo-fgrid .full { grid-column: 1 / -1; }
    .jo-fg { display: flex; flex-direction: column; gap: 5px; }
    .jo-fl { font-size: 0.77rem; font-weight: 700; color: var(--color-text-secondary); }
    .jo-fi, .jo-fsel, .jo-fta { padding: 9px 12px; background: var(--color-surface-2); border: 1px solid var(--color-border-strong); border-radius: 9px; font-size: 0.84rem; color: var(--color-text-primary); font-family: 'Poppins', sans-serif; transition: border-color 0.17s; }
    .jo-fi:focus, .jo-fsel:focus, .jo-fta:focus { border-color: var(--color-accent); outline: none; }
    .jo-fta { resize: vertical; min-height: 90px; }

    /* Empty */
    .jo-empty { text-align: center; padding: 56px 20px; }
    .jo-empty-icon { margin: 0 auto 16px; width: 56px; height: 56px; border-radius: 16px; background: var(--color-surface-2); display: flex; align-items: center; justify-content: center; }
    .jo-empty h3 { font-size: 0.96rem; font-weight: 700; color: var(--color-text-primary); }
    .jo-empty p  { font-size: 0.82rem; color: var(--color-text-secondary); margin-top: 5px; }

    /* Spinner */
    @keyframes joPulse { 0%,100%{opacity:1} 50%{opacity:.5} }
    .jo-loading-dots { display: inline-flex; gap: 3px; align-items: center; }
    .jo-loading-dots span { width: 4px; height: 4px; border-radius: 50%; background: currentColor; animation: joPulse 1.2s ease-in-out infinite; }
    .jo-loading-dots span:nth-child(2) { animation-delay: .2s; }
    .jo-loading-dots span:nth-child(3) { animation-delay: .4s; }

    /* Animations */
    @keyframes joSlideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:none; } }
    @keyframes joFadeIn    { from { opacity:0; transform:translateY(8px); }  to { opacity:1; transform:none; } }
    @keyframes joModalIn   { from { opacity:0; transform:scale(.95) translateY(12px); } to { opacity:1; transform:none; } }

    @media (max-width: 768px) {
      .jo-fgrid { grid-template-columns: 1fr; }
      .jo-match-grid { grid-template-columns: 1fr; }
      .jo-upload-zone { flex-direction: column; align-items: flex-start; min-height: auto; }
      .jo-upload-action { width: 100%; }
    }
  </style>
</head>
<body class="grid-dot-bg home-page-body with-global-left-sidebar">
  <a class="skip-link" href="#main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <!-- Navbar -->
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
            <a href="profile.php" class="nav-dropdown-item">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> My Profile
            </a>
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6z"/><path d="M14 6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2V6z"/><path d="M4 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2z"/><path d="M14 16a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2a2 2 0 0 1-2-2v-2z"/></svg> Dashboard
            </a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg> Sign Out
            </a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <?php include __DIR__ . '/partials/global-sidebar.php'; ?>

  <main class="home-hub" id="main-content" tabindex="-1">
    <div class="home-grid container">

      <!-- Left Sidebar -->
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
            <div style="margin:12px 0 6px 12px;font-size:.7rem;font-weight:700;color:var(--color-text-muted);text-transform:uppercase;letter-spacing:.08em;">BackOffice</div>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Dashboard</a>
          <?php endif; ?>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= $reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak</span><strong><?= $streak ?> days</strong></div>
        </div>
      </aside>

      <!-- Main Content -->
      <section class="home-main" aria-label="Job Offers">

        <!-- ═══ GLASS HEADER with ALL action buttons ═══ -->
        <div class="jo-glass-header">
          <div class="jo-header-text">
            <h2>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-3px;margin-right:6px;color:var(--color-accent)"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
              Job Offers
            </h2>
            <p><?= $isClient ? 'Post and manage your offers, review freelancer applications, run AI analysis.' : 'Browse open opportunities, get AI job matching, submit candidatures.' ?></p>
          </div>

          <div class="jo-header-actions">
            <?php if ($isClient): ?>
              <!-- Add Offer -->
              <button class="jo-btn jo-btn-primary" onclick="document.getElementById('createModal').classList.add('open')">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Job Offer
              </button>
              <!-- AI Salary Global (client sees per-card analysis) -->
              <button id="btn-salary-global" class="jo-btn-ai jo-btn-ai-salary" onclick="runGlobalSalaryAnalysis()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg>
                AI Analyse Salary
              </button>
            <?php endif; ?>

            <?php if ($canApplyToJobs && !$isClient): ?>
              <!-- AI Salary Global (freelancer sees market comparison) -->
              <button id="btn-salary-global" class="jo-btn-ai jo-btn-ai-salary" onclick="runGlobalSalaryAnalysis()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg>
                AI Analyse Salary
              </button>
              <!-- Automatic Matching -->
              <button id="btn-auto-match" class="jo-btn-ai jo-btn-ai-match" onclick="runAutomaticMatching()">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                Automatic Matching
              </button>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($notice['message'])): ?>
        <div class="jo-notice <?= htmlspecialchars((string)($notice['type'] ?? 'success')) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= ($notice['type'] ?? '') === 'error' ? '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' : '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' ?></svg>
          <span><?= htmlspecialchars((string)$notice['message']) ?></span>
        </div>
        <?php endif; ?>

        <!-- ═══ AI Results Panels (populated by JS) ═══ -->
        <div id="panel-matching" style="display:none;" class="jo-ai-panel">
          <div class="jo-ai-panel-title">
            <span class="jo-ai-panel-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg></span>
            🎯 Best Job Matches For You
          </div>
          <div id="matching-results" class="jo-match-grid"></div>
        </div>

        <div id="panel-salary" style="display:none;" class="jo-ai-panel">
          <div class="jo-ai-panel-title">
            <span class="jo-ai-panel-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
            💡 Global Salary Analysis — All Offers
          </div>
          <div id="salary-results" class="jo-match-grid"></div>
        </div>

        <!-- Filter Bar -->
        <form method="get">
          <div class="jo-filters">
            <div class="jo-search-wrap">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input class="jo-search" type="text" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Search job offers…">
            </div>
            <select class="jo-sel" name="status">
              <option value="all" <?= $filters['status']==='all'?'selected':'' ?>>All Status</option>
              <?php foreach (['pending','approved','rejected'] as $s): ?>
              <option value="<?= $s ?>" <?= $filters['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
            <select class="jo-sel" name="sort">
              <option value="newest" <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
              <option value="budget_desc" <?= $filters['sort']==='budget_desc'?'selected':'' ?>>Budget High→Low</option>
            </select>
            <button type="submit" class="jo-filter-btn">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
              Filter
            </button>
          </div>
        </form>

        <!-- ═══ JOB CARDS ═══ -->
        <?php if (empty($rows)): ?>
        <div class="jo-empty">
          <div class="jo-empty-icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
          </div>
          <h3>No job offers found</h3>
          <p><?= $isClient ? 'Post your first offer to attract freelancers.' : 'Check back later for new opportunities.' ?></p>
        </div>
        <?php else: ?>
        <div class="jo-grid" id="jo-grid">
          <?php foreach ($rows as $row):
            $rowId      = (int)($row['id'] ?? 0);
            $rowStatus  = (string)($row['status'] ?? 'pending');
            $sc         = $statusColors[$rowStatus] ?? $statusColors['pending'];
            $isOwner    = $isClient && ((int)($row['client_id'] ?? 0) === $userId || $isAdmin);
            $skills     = array_filter(array_map('trim', explode(',', (string)($row['skills_required'] ?? ''))));
            $apps       = $clientApplications[$rowId] ?? [];
            $myApp      = $myApplications[$rowId] ?? null;
            $insight    = $offerInsights[$rowId] ?? ['salary' => '', 'company_rating' => ['average_rating' => null, 'reviews_count' => 0]];
            $ratingInfo = $insight['company_rating'] ?? ['average_rating' => null, 'reviews_count' => 0];
            $displaySalary = trim((string)($row['salary'] ?? '')) !== ''
              ? (string)($row['salary'] ?? '')
              : ((float)($row['budget'] ?? 0) > 0 ? number_format((float)($row['budget'] ?? 0), 0) . ' TND' : 'Budget not specified');
          ?>
          <div class="jo-card" id="card-<?= $rowId ?>" data-offer-id="<?= $rowId ?>"
               data-title="<?= htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES) ?>"
               data-description="<?= htmlspecialchars(mb_substr((string)($row['description'] ?? ''), 0, 500), ENT_QUOTES) ?>"
               data-skills="<?= htmlspecialchars((string)($row['skills_required'] ?? ''), ENT_QUOTES) ?>"
               data-location="<?= htmlspecialchars((string)($row['location'] ?? ''), ENT_QUOTES) ?>"
               data-experience="<?= htmlspecialchars((string)($row['experience_level'] ?? ''), ENT_QUOTES) ?>"
               data-project-type="<?= htmlspecialchars((string)($row['project_type'] ?? ''), ENT_QUOTES) ?>"
               data-budget="<?= (float)($row['budget'] ?? 0) ?>">

            <div class="jo-card-head">
              <div class="jo-card-title"><?= htmlspecialchars((string)($row['title'] ?? '')) ?></div>
              <div class="jo-card-badges">
                <!-- Score badge — updated by AI analysis JS -->
                <span class="jo-card-score" id="score-<?= $rowId ?>"></span>
                <span class="jo-status-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['text'] ?>">
                  <span class="jo-status-dot" style="background:<?= $sc['dot'] ?>"></span>
                  <?= ucfirst(str_replace('_',' ',$rowStatus)) ?>
                </span>
              </div>
            </div>

            <div class="jo-salary-row"><?= htmlspecialchars($displaySalary) ?></div>

            <div class="jo-poster-row">
              <img class="jo-poster-avatar"
                   src="<?= htmlspecialchars((string)($row['avatar_url'] ?? 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . urlencode((string)($row['first_name'] ?? 'U')))) ?>"
                   alt="">
              <span class="jo-poster-name">
                <?= htmlspecialchars(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')) ?: 'Unknown Client') ?>
              </span>
            </div>

            <p class="jo-desc"><?= htmlspecialchars((string)($row['description'] ?? '')) ?></p>

            <div class="jo-meta">
              <?php if (trim((string)($row['location'] ?? '')) !== ''): ?>
              <span class="jo-meta-pill">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= htmlspecialchars((string)($row['location'] ?? '')) ?>
              </span>
              <?php endif; ?>
              <?php if (trim((string)($row['experience_level'] ?? '')) !== ''): ?>
              <span class="jo-meta-pill"><?= htmlspecialchars((string)($row['experience_level'] ?? '')) ?></span>
              <?php endif; ?>
              <?php if (trim((string)($row['project_type'] ?? '')) !== ''): ?>
              <span class="jo-meta-pill"><?= htmlspecialchars((string)($row['project_type'] ?? '')) ?></span>
              <?php endif; ?>
              <?php if (($ratingInfo['reviews_count'] ?? 0) > 0): ?>
              <span class="jo-meta-pill" style="color:var(--color-accent);font-weight:700;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <?= number_format((float)($ratingInfo['average_rating'] ?? 0), 1) ?>/5
              </span>
              <?php endif; ?>
            </div>

            <?php if (!empty($skills)): ?>
            <div class="jo-skills">
              <?php foreach (array_slice($skills, 0, 5) as $skill): ?>
              <span class="jo-skill-tag"><?= htmlspecialchars(trim($skill)) ?></span>
              <?php endforeach; ?>
              <?php if (count($skills) > 5): ?><span class="jo-skill-tag">+<?= count($skills)-5 ?></span><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Actions (NO AI buttons here — all AI is in header) -->
            <div class="jo-card-actions">
              <?php if ($isOwner): ?>
                <button type="button" class="jo-btn jo-btn-ghost jo-edit-offer-btn"
                        data-offer-id="<?= $rowId ?>"
                        data-client-id="<?= (int)($row['client_id'] ?? 0) ?>"
                        data-title="<?= htmlspecialchars((string)($row['title'] ?? ''), ENT_QUOTES) ?>"
                        data-description="<?= htmlspecialchars((string)($row['description'] ?? ''), ENT_QUOTES) ?>"
                        data-budget="<?= (float)($row['budget'] ?? 0) ?>"
                        data-salary="<?= htmlspecialchars((string)($row['salary'] ?? ''), ENT_QUOTES) ?>"
                        data-skills="<?= htmlspecialchars((string)($row['skills_required'] ?? ''), ENT_QUOTES) ?>"
                        data-location="<?= htmlspecialchars((string)($row['location'] ?? ''), ENT_QUOTES) ?>"
                        data-experience="<?= htmlspecialchars((string)($row['experience_level'] ?? 'Mid'), ENT_QUOTES) ?>"
                        data-project-type="<?= htmlspecialchars((string)($row['project_type'] ?? 'Fixed Price'), ENT_QUOTES) ?>"
                        data-status="<?= htmlspecialchars((string)($row['status'] ?? 'pending'), ENT_QUOTES) ?>">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit
                </button>
                <form method="post" data-confirm="Delete this offer?" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                  <input type="hidden" name="action" value="delete_offer">
                  <input type="hidden" name="offer_id" value="<?= $rowId ?>">
                  <button type="submit" class="jo-btn jo-btn-danger">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                    Delete
                  </button>
                </form>
              <?php endif; ?>

              <?php if ($canApplyToJobs && !$isOwner): ?>
                <?php if ($myApp): ?>
                  <span class="jo-app-pill <?= htmlspecialchars((string)($myApp['status'] ?? 'pending')) ?>">
                    Candidature — <?= ucfirst((string)($myApp['status'] ?? 'pending')) ?>
                  </span>
                  <button class="jo-btn jo-btn-ghost" type="button"
                          onclick='openApplyModal(<?= json_encode([
                            'offerId'       => $rowId,
                            'title'         => (string)($row['title'] ?? ''),
                            'message'       => (string)($myApp['message'] ?? $myApp['cover_letter'] ?? ''),
                            'candidatureId' => (int)($myApp['candidature_id'] ?? 0),
                            'attachments'   => $decodeAttachments((string)($myApp['attachments_json'] ?? '')),
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
                <?php elseif ($rowStatus === 'approved'): ?>
                  <button class="jo-btn jo-btn-primary" type="button"
                          onclick='openApplyModal(<?= json_encode([
                            'offerId'       => $rowId,
                            'title'         => (string)($row['title'] ?? ''),
                            'message'       => '',
                            'candidatureId' => 0,
                            'attachments'   => [],
                          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
                    Apply
                  </button>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <!-- Applications sub-panel (client only) -->
            <?php if ($isOwner && !empty($apps)): ?>
            <div style="border-top:1px solid var(--color-border);padding-top:12px;margin-top:2px;">
              <div style="font-size:.74rem;font-weight:700;color:var(--color-text-muted);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?= count($apps) ?> Application<?= count($apps) > 1 ? 's' : '' ?>
              </div>
              <?php if (isset($clientContractsByOffer[$rowId])): ?>
              <a class="jo-btn jo-btn-success" href="contracts.php?contract=<?= (int)$clientContractsByOffer[$rowId]['id'] ?>#contract-<?= (int)$clientContractsByOffer[$rowId]['id'] ?>" style="margin-bottom:10px;display:inline-flex;">
                <?= htmlspecialchars($contractStateLabels[(string)($clientContractsByOffer[$rowId]['workflow_state'] ?? 'waiting_freelancer')] ?? 'Manage Contract') ?>
              </a>
              <?php endif; ?>
              <div class="jo-apps-list">
                <?php foreach ($apps as $app):
                  $appAttachments = $decodeAttachments((string)($app['attachments_json'] ?? ''));
                ?>
                <div class="jo-app-row">
                  <div style="min-width:0;">
                    <div class="jo-app-applicant"><?= htmlspecialchars(trim((string)($app['first_name'] ?? '') . ' ' . (string)($app['last_name'] ?? ''))) ?></div>
                    <div style="font-size:.72rem;color:var(--color-text-secondary);line-height:1.5;max-width:420px;white-space:pre-line;margin-top:3px;"><?= htmlspecialchars(mb_strimwidth(trim((string)($app['message'] ?? '')), 0, 140, '…')) ?></div>
                    <div class="jo-app-meta">
                      <?php if ((float)($app['proposed_budget'] ?? 0) > 0): ?>
                      <span class="jo-app-meta-pill">Budget: <?= number_format((float)($app['proposed_budget'] ?? 0), 2) ?> TND</span>
                      <?php endif; ?>
                      <?php if ((int)($app['estimated_delivery_days'] ?? 0) > 0): ?>
                      <span class="jo-app-meta-pill">Delivery: <?= (int)($app['estimated_delivery_days'] ?? 0) ?> day<?= (int)($app['estimated_delivery_days'] ?? 0) > 1 ? 's' : '' ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if ($appAttachments): ?>
                    <div class="jo-attachment-list">
                      <?php foreach ($appAttachments as $att):
                        $attUrl = $assetPath((string)($att['path'] ?? ''));
                        if ($attUrl === '') continue;
                      ?>
                      <a class="jo-attachment-chip" href="<?= htmlspecialchars($attUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        <?= htmlspecialchars((string)($att['original_name'] ?? 'Attachment')) ?>
                      </a>
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
                      <button type="submit" class="jo-btn jo-btn-danger" style="padding:4px 9px;font-size:.72rem;">Delete</button>
                    </form>
                    <?php if ((string)($app['status'] ?? '') === 'pending'): ?>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                      <input type="hidden" name="action" value="decide_application">
                      <input type="hidden" name="application_id" value="<?= (int)($app['id'] ?? 0) ?>">
                      <input type="hidden" name="offer_id" value="<?= $rowId ?>">
                      <input type="hidden" name="freelancer_id" value="<?= (int)($app['freelancer_id'] ?? 0) ?>">
                      <button name="decision" value="accepted" class="jo-btn jo-btn-success" style="padding:4px 9px;font-size:.72rem;">Accept</button>
                      <button name="decision" value="rejected" class="jo-btn jo-btn-danger" style="padding:4px 9px;font-size:.72rem;">Reject</button>
                    </form>
                    <?php else: ?>
                      <span class="jo-app-pill <?= htmlspecialchars((string)($app['status'] ?? 'pending')) ?>" style="font-size:.71rem;"><?= ucfirst((string)($app['status'] ?? 'pending')) ?></span>
                      <?php if ((string)($app['status'] ?? '') === 'accepted' && !isset($clientContractsByOffer[$rowId])): ?>
                      <a class="jo-btn jo-btn-primary" style="padding:4px 9px;font-size:.72rem;" href="contracts.php?open_create=1&offer_id=<?= $rowId ?>&freelancer_id=<?= (int)($app['freelancer_id'] ?? 0) ?>">
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

  <!-- ═══════════════ MODALS ═══════════════ -->

  <?php if ($isClient): ?>
  <!-- Create Modal -->
  <div class="jo-modal-backdrop" id="createModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="jo-modal">
      <div class="jo-modal-head">
        <h3>Post a Job Offer</h3>
        <button class="jo-modal-close" onclick="document.getElementById('createModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="post" id="createOfferForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="create_offer">
        <div class="jo-fgrid">
          <div class="jo-fg full">
            <label class="jo-fl">Job Title *</label>
            <input type="text" name="title" id="create_title" class="jo-fi" placeholder="e.g. Senior React Developer" required>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Budget (TND)</label>
            <input type="number" name="budget" id="create_budget" class="jo-fi" min="0" step="0.01" placeholder="0.00">
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Salary</label>
            <div style="display:flex;gap:6px;">
              <input type="text" name="salary" id="create_salary" class="jo-fi" style="flex:1;" placeholder="e.g. 3500 TND">
              <button type="button" class="jo-btn-ai jo-btn-ai-salary" style="padding:7px 11px;font-size:.74rem;" id="btn-suggest-create"
                      onclick="suggestSalaryForModal('create')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                AI
              </button>
            </div>
            <div id="create_salary_hint" class="jo-helper-text" style="margin-top:4px;"></div>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Experience Level</label>
            <select name="experience_level" id="create_experience_level" class="jo-fsel">
              <option value="Junior">Junior</option>
              <option value="Mid" selected>Mid</option>
              <option value="Senior">Senior</option>
              <option value="Expert">Expert</option>
            </select>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Project Type</label>
            <select name="project_type" id="create_project_type" class="jo-fsel">
              <option value="Fixed Price">Fixed Price</option>
              <option value="Hourly">Hourly</option>
              <option value="Retainer">Retainer</option>
              <option value="Long-term">Long-term</option>
            </select>
          </div>
          <div class="jo-fg full">
            <label class="jo-fl">Location</label>
            <input type="text" name="location" id="create_location" class="jo-fi" placeholder="e.g. Remote, Tunis, Paris">
          </div>
          <div class="jo-fg full">
            <label class="jo-fl">Required Skills <span style="font-weight:400;color:var(--color-text-muted)">(comma-separated)</span></label>
            <input type="text" name="skills_required" id="create_skills_required" class="jo-fi" placeholder="React, Node.js, PostgreSQL">
          </div>
          <div class="jo-fg full">
            <label class="jo-fl">Description *</label>
            <textarea name="description" id="create_description" class="jo-fta" placeholder="Describe the project, responsibilities, and expectations…" required></textarea>
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:20px;justify-content:flex-end;">
          <button type="button" class="jo-btn jo-btn-ghost" onclick="document.getElementById('createModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="jo-btn jo-btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
            Publish Offer
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="jo-modal-backdrop" id="editModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="jo-modal">
      <div class="jo-modal-head">
        <h3>Edit Job Offer</h3>
        <button class="jo-modal-close" onclick="document.getElementById('editModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="post" id="editOfferForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="update_offer">
        <input type="hidden" name="offer_id" id="edit_offer_id">
        <input type="hidden" name="client_id" id="edit_client_id" value="<?= $userId ?>">
        <div class="jo-fgrid">
          <div class="jo-fg full">
            <label class="jo-fl">Job Title *</label>
            <input type="text" name="title" id="edit_title" class="jo-fi" required>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Budget (TND)</label>
            <input type="number" name="budget" id="edit_budget" class="jo-fi" min="0" step="0.01">
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Salary</label>
            <div style="display:flex;gap:6px;">
              <input type="text" name="salary" id="edit_salary" class="jo-fi" style="flex:1;" placeholder="e.g. 3500 TND">
              <button type="button" class="jo-btn-ai jo-btn-ai-salary" style="padding:7px 11px;font-size:.74rem;" id="btn-suggest-edit"
                      onclick="suggestSalaryForModal('edit')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                AI
              </button>
            </div>
            <div id="edit_salary_hint" class="jo-helper-text" style="margin-top:4px;"></div>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Experience Level</label>
            <select name="experience_level" id="edit_experience_level" class="jo-fsel">
              <option value="Junior">Junior</option>
              <option value="Mid">Mid</option>
              <option value="Senior">Senior</option>
              <option value="Expert">Expert</option>
            </select>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Project Type</label>
            <select name="project_type" id="edit_project_type" class="jo-fsel">
              <option value="Fixed Price">Fixed Price</option>
              <option value="Hourly">Hourly</option>
              <option value="Retainer">Retainer</option>
              <option value="Long-term">Long-term</option>
            </select>
          </div>
          <div class="jo-fg">
            <label class="jo-fl">Status</label>
            <select name="status" id="edit_status" class="jo-fsel">
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="jo-fg full">
            <label class="jo-fl">Location</label>
            <input type="text" name="location" id="edit_location" class="jo-fi">
          </div>
          <div class="jo-fg full">
            <label class="jo-fl">Required Skills</label>
            <input type="text" name="skills_required" id="edit_skills_required" class="jo-fi">
          </div>
          <div class="jo-fg full">
            <label class="jo-fl">Description *</label>
            <textarea name="description" id="edit_description" class="jo-fta" required></textarea>
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:20px;justify-content:flex-end;">
          <button type="button" class="jo-btn jo-btn-ghost" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="jo-btn jo-btn-primary">Save Changes</button>
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
        <button class="jo-modal-close" onclick="document.getElementById('applyModal').classList.remove('open')" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="post" id="applyOfferForm" novalidate enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="apply_offer">
        <input type="hidden" name="offer_id" id="applyOfferId" value="">
        <input type="hidden" name="candidature_id" id="applyCandidatureId" value="">
        <div class="jo-fg full" style="margin-bottom:14px;">
          <label class="jo-fl">Candidature Message *</label>
          <textarea name="cover_letter" id="applyCoverLetter" class="jo-fta" placeholder="Tell the client who you are and why you're a great fit…" required></textarea>
        </div>
        <div class="jo-fg full">
          <label class="jo-fl">CV / Resume</label>
          <div class="jo-upload-card">
            <input type="file" name="attachments[]" id="applyAttachments" class="jo-file-input" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.zip,.rar,.7z" multiple>
            <label class="jo-upload-zone" id="applyAttachmentsZone" for="applyAttachments">
              <span class="jo-upload-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="17 8 12 3 7 8"/>
                  <line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
              </span>
              <span class="jo-upload-copy">
                <strong>Drop your CV here or browse files</strong>
                <span>PDF, DOC, DOCX, PNG, ZIP — up to 3 files</span>
                <span>
                  <span class="jo-upload-badge">Fast upload</span>
                  <span class="jo-upload-badge">Max 3 files</span>
                </span>
              </span>
              <span class="jo-upload-action">Browse files</span>
            </label>
            <div class="jo-upload-meta">
              <span>Existing attachments stay when editing unless you pick new ones.</span>
              <span class="jo-upload-counter" id="applyAttachmentCount">No files selected</span>
            </div>
            <div class="jo-attachment-list" id="applyAttachmentList"></div>
            <div class="jo-selected-files" id="applySelectedFiles"></div>
          </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
          <button type="button" class="jo-btn jo-btn-ghost" onclick="document.getElementById('applyModal').classList.remove('open')">Cancel</button>
          <button type="submit" class="jo-btn jo-btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/></svg>
            Send Candidature
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="home-toast-stack" id="homeToastStack" aria-live="polite"></div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/home.js"></script>
  <script src="../../assets/js/mvc-inline-validation.js"></script>

  <script>
  /* ══════════════════════════════════════════════════════════════
     SHARED UTILITIES
  ══════════════════════════════════════════════════════════════ */

  /** Safely HTML-escape a value */
  function esc(v) {
    return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  /** Get file extension badge label from filename */
  function fileBadge(name) {
    const parts = String(name || '').trim().split('.');
    if (parts.length < 2) return 'FILE';
    const ext = parts.pop().toUpperCase();
    return ext && ext.length <= 5 ? ext : 'FILE';
  }

  /** Format byte size */
  function fmtSize(bytes) {
    const n = Number(bytes || 0);
    if (!Number.isFinite(n) || n <= 0) return '';
    const u = ['B','KB','MB','GB'];
    let s = n, i = 0;
    while (s >= 1024 && i < u.length - 1) { s /= 1024; i++; }
    return (i === 0 ? Math.round(s) : s.toFixed(s >= 10 ? 0 : 1)) + ' ' + u[i];
  }

  /** CSRF token */
  const CSRF = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';

  /**
   * Post an AJAX action to this page.
   * Returns the parsed `result` field on success, throws on error.
   */
  async function ajaxPost(payload) {
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.entries(payload || {}).forEach(([k, v]) => { if (k !== 'csrf_token') fd.append(k, v ?? ''); });
    const resp = await fetch(window.location.href, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd,
    });
    const data = await resp.json().catch(() => ({ success: false, message: 'Invalid JSON response' }));
    if (!resp.ok || !data.success) throw new Error(data.message || 'Request failed.');
    return data.result;
  }

  /** Show/hide a panel with animation */
  function showPanel(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'block'; el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
  }
  function hidePanel(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  }

  /** Set ROI score badge on a card */
  function setCardScore(offerId, roiScore) {
    const badge = document.getElementById('score-' + offerId);
    if (!badge) return;
    const n = Math.max(0, Math.min(10, parseInt(roiScore, 10) || 0));
    const cls = n >= 8 ? 'score-high' : (n >= 5 ? 'score-mid' : 'score-low');
    badge.className = 'jo-card-score ' + cls;
    badge.textContent = n + '/10';
  }

  /** Highlight card as best match */
  function highlightCard(offerId) {
    document.querySelectorAll('.jo-card').forEach(c => c.classList.remove('is-highlighted'));
    const card = document.getElementById('card-' + offerId);
    if (card) {
      card.classList.add('is-highlighted');
      card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  /* ══════════════════════════════════════════════════════════════
     ROI CLASS HELPERS
  ══════════════════════════════════════════════════════════════ */
  function roiClass(score) {
    const n = parseInt(score, 10) || 0;
    return n >= 8 ? 'high' : (n >= 5 ? 'mid' : 'low');
  }
  function pctClass(pct) {
    const n = parseInt(pct, 10) || 0;
    return n >= 70 ? 'high' : (n >= 40 ? 'mid' : 'low');
  }

  /* ══════════════════════════════════════════════════════════════
     AI — SUGGEST SALARY (in Create/Edit modals)
     FIX: single clean function with proper DOM targeting by prefix
  ══════════════════════════════════════════════════════════════ */
  async function suggestSalaryForModal(prefix) {
    // prefix = 'create' | 'edit'
    const salaryInput = document.getElementById(prefix + '_salary');
    const hintEl      = document.getElementById(prefix + '_salary_hint');
    const btnEl       = document.getElementById('btn-suggest-' + prefix);
    if (!salaryInput) return;

    const payload = {
      title:            (document.getElementById(prefix + '_title')            || {}).value || '',
      description:      (document.getElementById(prefix + '_description')      || {}).value || '',
      skills_required:  (document.getElementById(prefix + '_skills_required')  || {}).value || '',
      location:         (document.getElementById(prefix + '_location')         || {}).value || '',
      experience_level: (document.getElementById(prefix + '_experience_level') || {}).value || '',
      project_type:     (document.getElementById(prefix + '_project_type')     || {}).value || '',
      budget:           (document.getElementById(prefix + '_budget')           || {}).value || '',
    };

    const origValue = salaryInput.value;
    if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<span style="opacity:.7">…</span>'; }
    if (hintEl) hintEl.textContent = 'Analyzing market data…';
    salaryInput.value = '';
    salaryInput.placeholder = 'Analyzing…';

    try {
      const result = await ajaxPost({ ajax: 'salary_analyze', ...payload });

      // ── Fill salary input ──
      const suggested = String(result.salary || '').trim();
      salaryInput.value = suggested || origValue;
      salaryInput.placeholder = 'e.g. 3500 TND';

      // ── Show hint ──
      if (hintEl) {
        const roi = parseInt(result.roi_score, 10) || 0;
        const rc  = roiClass(roi);
        const roiLabel = roi >= 8 ? '⭐ High ROI' : (roi >= 5 ? '📈 Good ROI' : '⚠️ Low ROI');
        hintEl.innerHTML =
          '<span class="jo-roi-chip ' + rc + '" style="margin-right:6px;">' + roiLabel + ' ' + roi + '/10</span>' +
          esc(result.explanation || '');
      }
    } catch (err) {
      salaryInput.value = origValue;
      salaryInput.placeholder = 'e.g. 3500 TND';
      if (hintEl) hintEl.textContent = 'Error: ' + (err.message || 'Unable to suggest salary.');
    }

    if (btnEl) {
      btnEl.disabled = false;
      btnEl.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> AI';
    }
  }

  /* ══════════════════════════════════════════════════════════════
     AI — GLOBAL SALARY ANALYSIS (header button)
     Scans all cards, shows ROI scores + summary panel
  ══════════════════════════════════════════════════════════════ */
  async function runGlobalSalaryAnalysis() {
    const btn       = document.getElementById('btn-salary-global');
    const panel     = document.getElementById('panel-salary');
    const container = document.getElementById('salary-results');
    if (!btn || !panel || !container) return;

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="jo-loading-dots"><span></span><span></span><span></span></span> Scanning…';
    container.innerHTML = '';
    hidePanel('panel-salary');

    const cards = Array.from(document.querySelectorAll('.jo-card[data-offer-id]'));
    if (!cards.length) {
      btn.disabled = false;
      btn.innerHTML = origHtml;
      return;
    }

    let anyResult = false;

    for (const card of cards) {
      const offerId = card.getAttribute('data-offer-id') || '0';
      const title       = card.getAttribute('data-title')       || card.querySelector('.jo-card-title')?.textContent?.trim() || '';
      const description = card.getAttribute('data-description') || card.querySelector('.jo-desc')?.textContent?.trim()       || '';
      const skills      = card.getAttribute('data-skills')      || '';
      const location    = card.getAttribute('data-location')    || '';
      const experience  = card.getAttribute('data-experience')  || '';
      const projectType = card.getAttribute('data-project-type')|| '';
      const budget      = card.getAttribute('data-budget')      || '0';

      if (!title) continue;

      try {
        const result = await ajaxPost({
          ajax: 'salary_analyze',
          offer_id: offerId,
          title,
          description,
          skills_required: skills,
          location,
          experience_level: experience,
          project_type: projectType,
          budget,
        });

        const roi = parseInt(result.roi_score, 10) || 0;
        const rc  = roiClass(roi);

        // Update card score badge
        setCardScore(offerId, roi);

        // Build result card
        const salary   = String(result.salary || 'N/A').trim();
        const expl     = String(result.explanation || '').trim();
        const roiLabel = roi >= 8 ? '⭐ Highly competitive' : (roi >= 5 ? '📈 Fair market rate' : '⚠️ Below market');

        // Country comparison rows
        let countryHtml = '';
        const countries = Array.isArray(result.country_analysis) ? result.country_analysis : [];
        if (countries.length) {
          countryHtml = '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;">';
          countries.slice(0, 4).forEach(c => {
            countryHtml += '<span class="jo-match-tag" title="' + esc(c.comparison || '') + '">' + esc(c.country || '') + ': ' + esc(c.salary || '') + '</span>';
          });
          countryHtml += '</div>';
        }

        const div = document.createElement('div');
        div.className = 'jo-match-card';
        div.innerHTML =
          '<div class="jo-match-head">' +
            '<div>' +
              '<div class="jo-match-title">' + esc(title) + '</div>' +
              '<div class="jo-match-meta">' + esc(location || 'Remote') + (experience ? ' · ' + esc(experience) : '') + '</div>' +
            '</div>' +
            '<div style="text-align:right;">' +
              '<div style="font-weight:800;font-size:.95rem;color:var(--color-accent);">' + esc(salary) + '</div>' +
              '<span class="jo-roi-chip ' + rc + '" style="margin-top:3px;">' + roi + '/10</span>' +
            '</div>' +
          '</div>' +
          '<div style="font-size:.73rem;font-weight:600;color:' + (roi >= 8 ? '#047857' : (roi >= 5 ? '#b45309' : '#dc2626')) + ';margin:4px 0 6px;">' + esc(roiLabel) + '</div>' +
          (expl ? '<p class="jo-match-exp">' + esc(expl) + '</p>' : '') +
          countryHtml +
          '<div style="margin-top:10px;">' +
            '<a href="#card-' + esc(offerId) + '" class="jo-btn jo-btn-ghost" style="font-size:.73rem;padding:4px 10px;" onclick="event.preventDefault();document.getElementById(\'card-' + esc(offerId) + '\')?.scrollIntoView({behavior:\'smooth\',block:\'center\'})">' +
              'View Offer →' +
            '</a>' +
          '</div>';

        container.appendChild(div);
        anyResult = true;
      } catch (err) {
        console.warn('Salary analysis failed for offer', offerId, err.message);
      }
    }

    if (anyResult) showPanel('panel-salary');

    btn.disabled = false;
    btn.innerHTML = origHtml;
  }

  /* ══════════════════════════════════════════════════════════════
     AI — AUTOMATIC MATCHING (header button, freelancer only)
     Calls find_best_jobs endpoint, renders ranked match panel
  ══════════════════════════════════════════════════════════════ */
  async function runAutomaticMatching() {
    const btn       = document.getElementById('btn-auto-match');
    const panel     = document.getElementById('panel-matching');
    const container = document.getElementById('matching-results');
    if (!btn || !panel || !container) return;

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="jo-loading-dots"><span></span><span></span><span></span></span> Matching…';
    container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;font-size:.83rem;color:#6366f1;font-weight:600;">🔍 Analyzing your profile against all open positions…</div>';
    showPanel('panel-matching');

    try {
      const result = await ajaxPost({ ajax: 'find_best_jobs' });
      const matches = Array.isArray(result.matches) ? result.matches : [];

      container.innerHTML = '';

      if (!matches.length) {
        container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;font-size:.83rem;color:var(--color-text-secondary);">No strong matches found right now — try updating your profile skills.</div>';
        btn.disabled = false;
        btn.innerHTML = origHtml;
        return;
      }

      matches.forEach((m, idx) => {
        const pct     = parseInt(m.match_percentage, 10) || 0;
        const roi     = parseInt(m.roi_score, 10) || 0;
        const pc      = pctClass(pct);
        const rc      = roiClass(roi);
        const isBest  = idx === 0 && roi >= 7;
        const roiLbl  = roi >= 8 ? '⭐ High ROI' : (roi >= 5 ? '📈 Good ROI' : '⚠️ Low ROI');
        const offerId = String(m.offer_id || '');

        // Update card score badge
        setCardScore(offerId, roi);
        if (isBest) highlightCard(offerId);

        const highlights = Array.isArray(m.highlights) ? m.highlights : [];
        let tagsHtml = '';
        if (highlights.length) {
          tagsHtml = '<div class="jo-match-tags">' +
            highlights.slice(0, 5).map(h => '<span class="jo-match-tag">' + esc(h) + '</span>').join('') +
          '</div>';
        }

        const div = document.createElement('div');
        div.className = 'jo-match-card' + (isBest ? ' is-best' : '');
        div.innerHTML =
          (isBest ? '<span class="jo-best-badge">🏆 Best Match</span><br>' : '') +
          '<div class="jo-match-head">' +
            '<div>' +
              '<div class="jo-match-title">' + esc(m.title || 'Untitled') + '</div>' +
              '<div class="jo-match-meta">' +
                (m.budget > 0 ? Number(m.budget).toLocaleString() + ' TND' : '') +
                (m.location ? ' · ' + esc(m.location) : '') +
              '</div>' +
            '</div>' +
            '<div style="text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:3px;">' +
              '<span class="jo-pct-badge ' + pc + '">' + pct + '%</span>' +
              '<span class="jo-roi-chip ' + rc + '">' + roi + '/10 ' + roiLbl + '</span>' +
            '</div>' +
          '</div>' +
          (isBest && roi >= 8 ? '<div style="font-size:.78rem;color:#047857;font-weight:700;margin:4px 0 6px;">✨ Excellent fit — high ROI match recommended for you.</div>' : '') +
          '<p class="jo-match-exp">' + esc(m.explanation || '') + '</p>' +
          tagsHtml +
          '<a href="#card-' + esc(offerId) + '" class="jo-btn jo-btn-primary" style="font-size:.75rem;padding:5px 12px;margin-top:4px;" ' +
            'onclick="event.preventDefault();document.getElementById(\'card-' + esc(offerId) + '\')?.scrollIntoView({behavior:\'smooth\',block:\'center\'})">View &amp; Apply →</a>';

        container.appendChild(div);
      });

    } catch (err) {
      container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;color:#dc2626;font-size:.83rem;">' + esc(err.message || 'Matching failed.') + '</div>';
    }

    btn.disabled = false;
    btn.innerHTML = origHtml;
  }

  /* ══════════════════════════════════════════════════════════════
     EDIT MODAL — fill fields from data attributes
  ══════════════════════════════════════════════════════════════ */
  document.querySelectorAll('.jo-edit-offer-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const d = btn.dataset;
      const modal = document.getElementById('editModal');
      if (!modal) return;
      document.getElementById('edit_offer_id').value         = d.offerId     || '';
      document.getElementById('edit_client_id').value        = d.clientId    || '<?= $userId ?>';
      document.getElementById('edit_title').value            = d.title       || '';
      document.getElementById('edit_description').value      = d.description || '';
      document.getElementById('edit_budget').value           = d.budget      || '';
      document.getElementById('edit_skills_required').value  = d.skills      || '';
      document.getElementById('edit_location').value         = d.location    || '';
      document.getElementById('edit_experience_level').value = d.experience  || 'Mid';
      document.getElementById('edit_project_type').value     = d.projectType || 'Fixed Price';
      document.getElementById('edit_status').value           = d.status      || 'pending';
      document.getElementById('edit_salary').value           = d.salary      || '';
      const hint = document.getElementById('edit_salary_hint');
      if (hint) hint.textContent = '';
      modal.classList.add('open');
    });
  });

  /* ══════════════════════════════════════════════════════════════
     CONFIRM DELETE
  ══════════════════════════════════════════════════════════════ */
  document.querySelectorAll('form[data-confirm]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      if (!window.confirm(form.dataset.confirm || 'Please confirm this action.')) e.preventDefault();
    });
  });

  /* ══════════════════════════════════════════════════════════════
     APPLY MODAL — attachment handling
  ══════════════════════════════════════════════════════════════ */
  function openApplyModal(payload) {
    var data = payload || {};
    document.getElementById('applyOfferId').value       = data.offerId      || 0;
    document.getElementById('applyModalTitle').textContent = 'Candidature: ' + (data.title || 'Offer');
    document.getElementById('applyCoverLetter').value   = data.message      || '';
    document.getElementById('applyCandidatureId').value = data.candidatureId || 0;
    document.getElementById('applyAttachments').value   = '';
    renderSavedAttachments(Array.isArray(data.attachments) ? data.attachments : []);
    renderSelectedFiles([]);
    document.getElementById('applyModal').classList.add('open');
  }

  function renderSavedAttachments(attachments) {
    var list = document.getElementById('applyAttachmentList');
    if (!list) return;
    list.innerHTML = '';
    if (!attachments.length) {
      var empty = document.createElement('span');
      empty.className = 'jo-selected-placeholder';
      empty.textContent = 'No saved attachments yet';
      list.appendChild(empty);
      return;
    }
    attachments.forEach(function(att) {
      var chip = document.createElement('span');
      chip.className = 'jo-selected-chip is-current';
      chip.innerHTML =
        '<span class="jo-selected-chip-icon">' + esc(fileBadge(att.original_name || att.file_name || '')) + '</span>' +
        '<span class="jo-selected-chip-body"><strong>' + esc(att.original_name || att.file_name || 'Attachment') + '</strong><small>Saved</small></span>';
      list.appendChild(chip);
    });
  }

  function renderSelectedFiles(files) {
    var list  = document.getElementById('applySelectedFiles');
    var count = document.getElementById('applyAttachmentCount');
    if (!list || !count) return;
    var selected = Array.from(files || []);
    list.innerHTML = '';
    if (!selected.length) {
      count.textContent = 'No files selected';
      count.classList.remove('is-active');
      var empty = document.createElement('span');
      empty.className = 'jo-selected-placeholder';
      empty.textContent = 'Your selected files will appear here';
      list.appendChild(empty);
      return;
    }
    count.textContent = selected.length + (selected.length === 1 ? ' file' : ' files') + ' selected';
    count.classList.add('is-active');
    selected.slice(0, 3).forEach(function(file) {
      var chip = document.createElement('span');
      chip.className = 'jo-selected-chip';
      chip.innerHTML =
        '<span class="jo-selected-chip-icon">' + esc(fileBadge(file.name || '')) + '</span>' +
        '<span class="jo-selected-chip-body"><strong>' + esc(file.name || 'File') + '</strong>' +
        (fmtSize(file.size) ? '<small>' + esc(fmtSize(file.size)) + '</small>' : '') + '</span>';
      list.appendChild(chip);
    });
    if (selected.length > 3) {
      var more = document.createElement('span');
      more.className = 'jo-selected-chip';
      more.innerHTML = '<span class="jo-selected-chip-icon">+' + (selected.length - 3) + '</span><span class="jo-selected-chip-body"><strong>More files</strong></span>';
      list.appendChild(more);
    }
  }

  var applyFileInput = document.getElementById('applyAttachments');
  var applyFileZone  = document.getElementById('applyAttachmentsZone');
  if (applyFileInput) {
    applyFileInput.addEventListener('change', function(e) { renderSelectedFiles(e.target.files); });
  }
  if (applyFileInput && applyFileZone) {
    var setDrag = function(active) { applyFileZone.classList.toggle('is-dragover', active); };
    ['dragenter','dragover'].forEach(function(ev) { applyFileZone.addEventListener(ev, function(e) { e.preventDefault(); setDrag(true); }); });
    ['dragleave','dragend'].forEach(function(ev)  { applyFileZone.addEventListener(ev, function()  { setDrag(false); }); });
    applyFileZone.addEventListener('drop', function(e) {
      e.preventDefault(); setDrag(false);
      var dropped = e.dataTransfer && e.dataTransfer.files ? Array.from(e.dataTransfer.files) : [];
      if (!dropped.length) return;
      try {
        var dt = new DataTransfer();
        dropped.slice(0, 3).forEach(function(f) { dt.items.add(f); });
        applyFileInput.files = dt.files;
      } catch (ignore) {}
      applyFileInput.dispatchEvent(new Event('change'));
    });
  }
  </script>
</body>
</html>