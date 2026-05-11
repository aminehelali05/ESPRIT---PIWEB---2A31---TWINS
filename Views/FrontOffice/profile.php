<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');
include_once(__DIR__ . '/../../Controllers/EventController.php');

if (!UserController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in to access your profile.';
  header('Location: auth.php');
  exit;
}

$userController = new UserController();
$eventController = new EventController();

$buildGeneratedAvatarUrl = static function (string $firstName, string $lastName, string $email = ''): string {
  $seed = trim($firstName . '-' . $lastName . '-' . $email);
  if ($seed === '') {
    $seed = 'diversity-user-' . date('YmdHis');
  }
  return 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . rawurlencode($seed);
};

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_upload_avatar') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
  };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }
  $sessionUserForUpload = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForUpload['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }
  $existingForUpload = $userController->getUserById($currentUserId);
  if (!$existingForUpload) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForUpload->getIsBlocked() ?? 0) === 1) { $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423); }
  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $clean = static function ($value): string { return trim((string) ($value ?? '')); };
  $sanitizeFileName = static function (string $fileName): string {
    $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    return trim((string) $normalized, '._-') ?: ('avatar_' . date('Ymd_His'));
  };
  $storeAvatarImage = static function ($rawImageData, string $preferredFileName = '') use ($sanitizeFileName): ?string {
    $raw = trim((string) ($rawImageData ?? ''));
    if ($raw === '') { return null; }
    $mime = 'image/png';
    $binary = '';
    if (preg_match('/^data:(image\/(png|jpeg|jpg|webp|svg\+xml));base64,(.+)$/i', $raw, $matches)) {
      $mime = strtolower((string) ($matches[1] ?? 'image/png'));
      $binary = base64_decode((string) ($matches[3] ?? ''), true) ?: '';
    } else {
      $binary = base64_decode($raw, true) ?: '';
    }
    if ($binary === '') { return null; }
    $ext = 'png';
    if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) { $ext = 'jpg'; }
    elseif (strpos($mime, 'webp') !== false) { $ext = 'webp'; }
    elseif (strpos($mime, 'svg+xml') !== false) { $ext = 'svg'; }
    $baseName = $sanitizeFileName($preferredFileName);
    if (stripos($baseName, '.'.$ext) === false) { $baseName .= '.' . $ext; }
    $facesDir = realpath(__DIR__ . '/../../assets') . DIRECTORY_SEPARATOR . 'faces';
    if (!is_dir($facesDir)) { @mkdir($facesDir, 0775, true); }
    if (!is_dir($facesDir) || !is_writable($facesDir)) { return null; }
    $finalName = uniqid('face_', true) . '_' . $baseName;
    $absolutePath = $facesDir . DIRECTORY_SEPARATOR . $finalName;
    $written = @file_put_contents($absolutePath, $binary);
    if ($written === false) { return null; }
    return '../../assets/faces/' . $finalName;
  };
  $imageData = (string) ($jsonInput['image_data'] ?? '');
  $fileName = $clean($jsonInput['file_name'] ?? 'avatar');
  $savedPath = $storeAvatarImage($imageData, $fileName);
  if (!$savedPath) { $respond(['success' => false, 'message' => 'Could not save avatar image.'], 500); }
  $respond(['success' => true, 'avatar_url' => $savedPath]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_save_avatar') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }
  $sessionUserForAvatarSave = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForAvatarSave['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }
  $existingForAvatarSave = $userController->getUserById($currentUserId);
  if (!$existingForAvatarSave) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForAvatarSave->getIsBlocked() ?? 0) === 1) { $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423); }
  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $avatarUrl = trim((string) ($jsonInput['avatar_url'] ?? ''));
  if ($avatarUrl === '') { $respond(['success' => false, 'message' => 'Avatar URL is required.'], 400); }
  $existing = $userController->getUserById($currentUserId);
  if (!$existing) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  $ok = $userController->updateProfile($currentUserId, [
    'first_name' => (string) $existing->getFirstName(), 'last_name' => (string) $existing->getLastName(),
    'email' => (string) $existing->getEmail(), 'phone' => (string) ($existing->getPhone() ?? ''),
    'role' => (string) ($existing->getRole() ?? 'client'), 'country' => (string) ($existing->getCountry() ?? 'Unknown'),
    'bio' => (string) ($existing->getBio() ?? ''), 'title' => (string) ($existing->getTitle() ?? ''),
    'skills' => (string) ($existing->getSkills() ?? ''), 'avatar_url' => $avatarUrl,
  ]);
  if (!$ok) { $respond(['success' => false, 'message' => 'Could not save avatar.'], 500); }
  $_SESSION['auth_user']['avatar_url'] = $avatarUrl;
  $respond(['success' => true, 'avatar_url' => $avatarUrl]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_update') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }
  $sessionUserForUpdate = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForUpdate['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }
  $existingForUpdateCheck = $userController->getUserById($currentUserId);
  if (!$existingForUpdateCheck) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForUpdateCheck->getIsBlocked() ?? 0) === 1) { $respond(['success' => false, 'message' => 'Account is blocked. Profile editing is disabled until admin review.'], 423); }
  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $clean = static function ($value): string { return trim((string) ($value ?? '')); };
  $normalizePhone = static function ($value): string { return preg_replace('/[^\d+]/', '', trim((string) ($value ?? ''))); };
  $firstName = $clean($jsonInput['first_name'] ?? '');
  $lastName  = $clean($jsonInput['last_name'] ?? '');
  $email     = $clean($jsonInput['email'] ?? '');
  $phone     = $normalizePhone($jsonInput['phone'] ?? '');
  if ($firstName === '' || $lastName === '' || $email === '') { $respond(['success' => false, 'message' => 'First name, last name, and email are required.'], 400); }
  if ($phone === '' || !preg_match('/^\+\d{8,15}$/', $phone)) { $respond(['success' => false, 'message' => 'Phone must start with +country code and contain 8 to 15 digits.'], 400); }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $respond(['success' => false, 'message' => 'Invalid email format.'], 400); }
  if ($userController->emailExists($email, $currentUserId)) { $respond(['success' => false, 'message' => 'Email already exists.'], 400); }
  $existingForUpdate = $userController->getUserById($currentUserId);
  $normalizeRole = static function ($value): string {
    $role = strtolower(trim((string) ($value ?? '')));
    return in_array($role, ['client', 'freelancer'], true) ? $role : 'client';
  };
  $currentAvatarUrl = $existingForUpdate ? (string) ($existingForUpdate->getAvatarUrl() ?? '') : '';
  $generatedAvatar = $buildGeneratedAvatarUrl(
    $firstName !== '' ? $firstName : (string) ($existingForUpdate ? $existingForUpdate->getFirstName() : 'User'),
    $lastName  !== '' ? $lastName  : (string) ($existingForUpdate ? $existingForUpdate->getLastName()  : 'Member'),
    $email     !== '' ? $email     : (string) ($existingForUpdate ? $existingForUpdate->getEmail()      : '')
  );
  $avatarUrl = array_key_exists('avatar_url', $jsonInput) ? $clean($jsonInput['avatar_url'] ?? '') : $currentAvatarUrl;
  if ($avatarUrl === '') { $avatarUrl = $generatedAvatar; }
  $countryValue = $clean($jsonInput['country'] ?? '');
  if ($countryValue === '') { $countryValue = 'Unknown'; }
  $ok = $userController->updateProfile($currentUserId, [
    'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email,
    'phone'   => $phone,
    'role'    => $normalizeRole($jsonInput['role'] ?? ($existingForUpdate ? $existingForUpdate->getRole() : 'client')),
    'country' => $countryValue,
    'bio'     => $clean($jsonInput['bio'] ?? ''),
    'skills'  => array_key_exists('skills', $jsonInput) ? $clean($jsonInput['skills'] ?? '') : (string) ($existingForUpdate ? ($existingForUpdate->getSkills() ?? '') : ''),
    'avatar_url' => $avatarUrl,
  ]);
  if (!$ok) { $respond(['success' => false, 'message' => 'Could not update profile.'], 500); }
  $_SESSION['auth_user']['first_name'] = $firstName;
  $_SESSION['auth_user']['last_name']  = $lastName;
  $_SESSION['auth_user']['email']      = $email;
  $_SESSION['auth_user']['avatar_url'] = $avatarUrl;
  $savedUser = $userController->getUserById($currentUserId);
  if (!$savedUser) { $respond(['success' => true]); }
  $respond(['success' => true, 'user' => [
    'id' => (int) $savedUser->getId(), 'first_name' => (string) $savedUser->getFirstName(),
    'last_name' => (string) $savedUser->getLastName(), 'email' => (string) $savedUser->getEmail(),
    'phone' => (string) ($savedUser->getPhone() ?? ''), 'role' => (string) ($savedUser->getRole() ?? 'client'),
    'country' => (string) ($savedUser->getCountry() ?? 'Unknown'), 'title' => (string) ($savedUser->getTitle() ?? ''),
    'skills' => (string) ($savedUser->getSkills() ?? ''), 'bio' => (string) ($savedUser->getBio() ?? ''),
    'avatar_url' => (string) ($savedUser->getAvatarUrl() ?? $generatedAvatar),
  ]]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'request_delete') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }
  $sessionUserForDelete = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForDelete['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }
  $existingForDelete = $userController->getUserById($currentUserId);
  if (!$existingForDelete) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForDelete->getIsBlocked() ?? 0) === 1) {
    $respond([
      'success' => true,
      'blocked' => true,
      'message' => 'Your account is already blocked and waiting for admin approval.'
    ]);
  }
  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $reason = trim((string) ($jsonInput['reason'] ?? ''));
  $requestResult = $userController->createDeleteRequest($currentUserId, $currentUserId, $reason);
  if ($requestResult === 'pending_exists') {
    $respond([
      'success' => true,
      'blocked' => true,
      'message' => 'Delete request already sent. Please wait for admin approval.'
    ]);
  }
  if (!$requestResult) {
    $maybeBlockedUser = $userController->getUserById($currentUserId);
    if ($maybeBlockedUser && (int) ($maybeBlockedUser->getIsBlocked() ?? 0) === 1) {
      $respond([
        'success' => true,
        'blocked' => true,
        'message' => 'Delete request sent successfully. Your account is now blocked until admin approval.'
      ]);
    }

    if ($userController->hasPendingDeleteRequest($currentUserId)) {
      $respond([
        'success' => true,
        'blocked' => true,
        'message' => 'Delete request already sent. Please wait for admin approval.'
      ]);
    }

    $respond(['success' => false, 'message' => 'Could not submit delete request. Please try again.'], 500);
  }
  $respond([
    'success' => true,
    'blocked' => true,
    'message' => 'Delete request sent successfully. Your account is now blocked until admin approval.'
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'cancel_delete_request') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }
  $sessionUserForCancel = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForCancel['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }
  $existingForCancel = $userController->getUserById($currentUserId);
  if (!$existingForCancel) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  $pendingDeleteRequest = $userController->getPendingDeleteRequest($currentUserId);
  if (!$pendingDeleteRequest) {
    $respond(['success' => false, 'message' => 'No pending deletion request found.'], 409);
  }
  if (!$userController->cancelDeleteRequest($currentUserId)) {
    $respond(['success' => false, 'message' => 'Could not cancel delete request. Please try again.'], 500);
  }
  $respond([
    'success' => true,
    'blocked' => false,
    'message' => 'Deletion request canceled successfully. Your account is active again.',
    'request_state' => 'canceled'
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'change_password') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }
  $sessionUserForPassword = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForPassword['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }
  $existingForPasswordCheck = $userController->getUserById($currentUserId);
  if (!$existingForPasswordCheck) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForPasswordCheck->getIsBlocked() ?? 0) === 1) { $respond(['success' => false, 'message' => 'Account is blocked. Password changes are disabled until admin review.'], 423); }
  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $currentPassword  = (string) ($jsonInput['current_password'] ?? '');
  $newPassword      = (string) ($jsonInput['new_password'] ?? '');
  $confirmPassword  = (string) ($jsonInput['confirm_password'] ?? '');
  if (trim($currentPassword) === '' || trim($newPassword) === '' || trim($confirmPassword) === '') { $respond(['success' => false, 'message' => 'All password fields are required.'], 400); }
  if ($newPassword !== $confirmPassword) { $respond(['success' => false, 'message' => 'New password and confirmation do not match.'], 400); }
  if (strlen($newPassword) < 10) { $respond(['success' => false, 'message' => 'New password must be at least 10 characters.'], 400); }
  $existingUser = $userController->getUserById($currentUserId);
  if (!$existingUser) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if (!password_verify($currentPassword, (string) $existingUser->getPassword())) { $respond(['success' => false, 'message' => 'Current password is incorrect.'], 400); }
  if (!$userController->changePassword($currentUserId, $newPassword)) { $respond(['success' => false, 'message' => 'Could not update password.'], 500); }
  $respond(['success' => true, 'message' => 'Password updated successfully.']);
}

/* ── Page data ──────────────────────────────────────── */
$sessionUser      = UserController::currentUser();
$userController   = new UserController();
$userModel        = $userController->getUserById((int) ($sessionUser['id'] ?? 0));

$displayFirstName       = (string) ($userModel ? $userModel->getFirstName() : ($sessionUser['first_name'] ?? ''));
$displayLastName        = (string) ($userModel ? $userModel->getLastName()  : ($sessionUser['last_name']  ?? ''));
$displayName            = trim($displayFirstName . ' ' . $displayLastName);
$displayName            = $displayName !== '' ? $displayName : 'Member User';
$displayEmail           = (string) ($userModel ? $userModel->getEmail() : ($sessionUser['email'] ?? ''));
$isAdminSidebar         = strtolower(trim($displayEmail)) === 'admin@diversity.is';
$displayCountryRaw      = (string) ($userModel ? ($userModel->getCountry() ?? '') : '');
$displayCountry         = trim($displayCountryRaw) !== '' ? trim($displayCountryRaw) : 'Unknown';
$displayTitle           = (string) ($userModel ? ($userModel->getTitle()  ?? '') : '');
$displayBio             = (string) ($userModel ? ($userModel->getBio()    ?? '') : '');
$displaySkills          = (string) ($userModel ? ($userModel->getSkills() ?? '') : '');
$displayPhone           = (string) ($userModel ? ($userModel->getPhone()  ?? '') : '');
$displayAvatarUrl       = (string) ($userModel ? ($userModel->getAvatarUrl() ?? '') : ($sessionUser['avatar_url'] ?? ''));
$displayRole            = (string) ($userModel ? ($userModel->getRole() ?? 'user') : ($sessionUser['role'] ?? 'user'));
$displayRoleNormalized  = in_array(strtolower($displayRole), ['client', 'freelancer'], true) ? strtolower($displayRole) : 'client';
$displayStatus          = (int) ($userModel ? ($userModel->getStatus()    ?? 1) : 1);
$displayIsBlocked       = (int) ($userModel ? ($userModel->getIsBlocked() ?? 0) : 0);
$pendingDeleteRequest   = $sessionUser ? $userController->getPendingDeleteRequest((int) ($sessionUser['id'] ?? 0)) : null;
$hasPendingDeleteRequest = $pendingDeleteRequest !== null;
$displayBadge           = (string) ($userModel ? ($userModel->getBadge()         ?? '') : '');
$displayXp              = (int)    ($userModel ? ($userModel->getXp()             ?? 0)  : 0);
$displayFaceEnrolled    = (int)    ($userModel ? ($userModel->getFaceEnrolled()   ?? 0)  : 0);
$displayFaceImagesPath  = (string) ($userModel ? ($userModel->getFaceImagesPath() ?? '') : '');
$displayFaceDescriptor  = (string) ($userModel ? ($userModel->getFaceDescriptor() ?? '') : '');
$displayFaceEnrolledAt  = (string) ($userModel ? ($userModel->getFaceEnrolledAt() ?? '') : '');
$displayLastSeen        = (string) ($userModel ? ($userModel->getLastSeen()       ?? '') : '');
$signInHistory          = $userController->getSignInHistory((int) ($sessionUser['id'] ?? 0), 24);
$createdAt              = (string) ($userModel ? ($userModel->getCreatedAt() ?? '') : '');
$joinedLabel            = $createdAt !== '' ? date('M Y', strtotime($createdAt)) : 'Recently';
$memberSinceFullLabel   = $createdAt !== '' ? date('F d, Y', strtotime($createdAt)) : date('F d, Y');
$initials               = strtoupper(substr($displayFirstName ?: 'M', 0, 1) . substr($displayLastName ?: 'U', 0, 1));
$isOwner                = true;
$reputationScore        = 87;
$reputationGrowth       = '+14% this month';
$profileLevel           = 'Level 12 Collaborator';
$streakDays             = 7;
$sidebarDisplayName     = $displayName;
$sidebarInitials        = $initials;
$skillItems             = array_values(array_filter(array_map('trim', explode(',', $displaySkills !== '' ? $displaySkills : 'React, Node.js, TypeScript, UX Strategy, Collaboration, AI Workflows'))));

$lastSeenLabel = 'Last seen: recently active';
if ($displayLastSeen !== '') {
  $lastSeenTs = strtotime($displayLastSeen);
  if ($lastSeenTs) {
    $diff = time() - $lastSeenTs;
    if      ($diff < 60)     { $lastSeenLabel = 'Last seen: just now'; }
    elseif  ($diff < 3600)   { $mins  = (int) floor($diff/60);   $lastSeenLabel = 'Last seen: '.$mins.' minute'.($mins>1?'s':'').' ago'; }
    elseif  ($diff < 86400)  { $hours = (int) floor($diff/3600);  $lastSeenLabel = 'Last seen: '.$hours.' hour'.($hours>1?'s':'').' ago'; }
    elseif  ($diff < 604800) { $days  = (int) floor($diff/86400); $lastSeenLabel = 'Last seen: '.$days.' day'.($days>1?'s':'').' ago'; }
    else                     { $lastSeenLabel = 'Last seen: '.date('M d, Y', $lastSeenTs); }
  }
}

$normalizedSignIns = [];
foreach ((array) $signInHistory as $entry) {
  $rawSignedInAt = (string) ($entry['signed_in_at'] ?? '');
  if ($rawSignedInAt === '') { continue; }
  $signedInTs = strtotime($rawSignedInAt);
  if ($signedInTs === false) { continue; }
  $normalizedSignIns[] = [
    'timestamp' => $signedInTs,
    'iso' => date('Y-m-d H:i:s', $signedInTs),
    'ip_address' => trim((string) ($entry['ip_address'] ?? '')),
    'device_type' => trim((string) ($entry['device_type'] ?? '')),
    'user_agent' => trim((string) ($entry['user_agent'] ?? '')),
  ];
}
usort($normalizedSignIns, static function (array $a, array $b): int { return (int) ($b['timestamp'] ?? 0) <=> (int) ($a['timestamp'] ?? 0); });

$signInCountByDay = [];
foreach ($normalizedSignIns as $sessionEntry) {
  $dayKey = date('Y-m-d', (int) ($sessionEntry['timestamp'] ?? 0));
  $signInCountByDay[$dayKey] = (int) ($signInCountByDay[$dayKey] ?? 0) + 1;
}

$calendarRangeDays = 21;
$calendarDays      = [];
$todayDate         = new DateTimeImmutable('today');
for ($offset = $calendarRangeDays - 1; $offset >= 0; $offset--) {
  $dayDate        = $todayDate->sub(new DateInterval('P' . $offset . 'D'));
  $dayKey         = $dayDate->format('Y-m-d');
  $calendarDays[] = [
    'key'      => $dayKey,
    'day'      => $dayDate->format('d'),
    'weekday'  => strtoupper($dayDate->format('D')),
    'is_today' => $offset === 0,
    'sessions' => (int) ($signInCountByDay[$dayKey] ?? 0),
  ];
}

$hasSignInData  = !empty($normalizedSignIns);
$recentSignIns  = array_slice($normalizedSignIns, 0, 5);
$activeDaysCount = count(array_filter($calendarDays, static function (array $day): bool { return (int) ($day['sessions'] ?? 0) > 0; }));

if (trim($displayAvatarUrl) === '') {
  $displayAvatarUrl = $buildGeneratedAvatarUrl($displayFirstName, $displayLastName, $displayEmail);
}
$displayAvatarResolved = $displayAvatarUrl;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Your professional profile on Diversity.is — manage your identity, skills, and activity.">
  <title>Profile — Diversity.is</title>

  <!-- Poppins font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../../assets/css/global.css">
  <link rel="stylesheet" href="../../assets/css/home.css">
  <link rel="stylesheet" href="../../assets/css/profile.css">
  <link rel="stylesheet" href="../../assets/css/profile-light.css">
  <link rel="stylesheet" href="../../assets/css/user-form.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <!-- ═══════════════════════════════════════════════
       PROFILE PAGE — DESIGN OVERRIDES
       Scoped to .about-section-v2 and related classes
  ════════════════════════════════════════════════════ -->
  <style>
    /* ── Base font override for profile page ── */
    .profile-main,
    .profile-main * {
      font-family: 'Poppins', sans-serif;
    }

    /* ══════════════════════════════════════════
       ABOUT SECTION v2 — Full Redesign
    ══════════════════════════════════════════ */
    .about-section-v2 {
      padding: 0;
      overflow: hidden;
    }

    .about-v2-inner {
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    /* ── Bio block ── */
    .about-v2-bio-block {
      padding: 28px 28px 0;
    }

    .about-v2-label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--color-accent, #6366f1);
      margin-bottom: 12px;
    }

    .about-v2-label svg {
      opacity: 0.7;
    }

    .about-v2-bio-text {
      font-size: 14px;
      font-weight: 400;
      line-height: 1.75;
      color: var(--color-text-secondary, #6b7280);
      margin: 0;
      max-width: 56ch;
    }

    /* ── Meta pills row ── */
    .about-v2-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      padding: 20px 28px;
      border-bottom: 1px solid var(--color-border, rgba(0,0,0,0.06));
    }

    .about-v2-pill {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 6px 14px;
      background: var(--color-surface-alt, rgba(99,102,241,0.06));
      border: 1px solid var(--color-border, rgba(99,102,241,0.12));
      border-radius: 100px;
      font-size: 12px;
      font-weight: 500;
      color: var(--color-text, #374151);
      transition: background 0.2s;
    }

    .about-v2-pill svg {
      width: 13px;
      height: 13px;
      color: var(--color-accent, #6366f1);
      flex-shrink: 0;
    }

    .about-v2-pill strong {
      font-weight: 600;
      color: var(--color-text-heading, #111827);
    }

    /* ── Calendar section ── */
    .about-v2-calendar {
      padding: 24px 28px 28px;
    }

    .about-v2-cal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 18px;
    }

    .about-v2-cal-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--color-text, #374151);
    }

    .about-v2-cal-title svg {
      width: 15px;
      height: 15px;
      color: var(--color-accent, #6366f1);
    }

    .about-v2-cal-badge {
      font-size: 11px;
      font-weight: 500;
      padding: 3px 10px;
      border-radius: 100px;
      background: var(--color-surface-alt, rgba(99,102,241,0.07));
      color: var(--color-text-secondary, #6b7280);
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
    }

    /* Grid of day cells */
    .about-v2-cal-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
      margin-bottom: 16px;
    }

    .about-v2-cal-cell {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 2px;
      padding: 7px 4px;
      border-radius: 8px;
      background: var(--color-surface-base, rgba(0,0,0,0.03));
      border: 1px solid transparent;
      transition: all 0.15s ease;
      cursor: default;
    }

    .about-v2-cal-cell .cal-day-num {
      font-size: 12px;
      font-weight: 600;
      color: var(--color-text-muted, #9ca3af);
      line-height: 1;
    }

    .about-v2-cal-cell .cal-day-wd {
      font-size: 9px;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: var(--color-text-muted, #d1d5db);
      line-height: 1;
    }

    /* Active day */
    .about-v2-cal-cell.is-active {
      background: linear-gradient(135deg, rgba(99,102,241,0.14), rgba(139,92,246,0.10));
      border-color: rgba(99,102,241,0.22);
    }

    .about-v2-cal-cell.is-active .cal-day-num {
      color: #4f46e5;
    }

    .about-v2-cal-cell.is-active .cal-day-wd {
      color: rgba(79,70,229,0.6);
    }

    /* Today */
    .about-v2-cal-cell.is-today {
      border-color: rgba(99,102,241,0.45);
      box-shadow: 0 0 0 2px rgba(99,102,241,0.12);
    }

    .about-v2-cal-cell.is-today .cal-day-num {
      color: #4f46e5;
      font-weight: 700;
    }

    /* Calendar summary bar */
    .about-v2-cal-summary {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 14px;
      border-radius: 10px;
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      border: 1px solid var(--color-border, rgba(0,0,0,0.05));
      margin-bottom: 18px;
    }

    .about-v2-cal-summary-left {
      font-size: 12px;
      font-weight: 500;
      color: var(--color-text-secondary, #6b7280);
    }

    .about-v2-cal-summary-left strong {
      color: #4f46e5;
      font-weight: 700;
    }

    .about-v2-cal-summary-right {
      font-size: 11px;
      color: var(--color-text-muted, #9ca3af);
    }

    /* Recent logins list */
    .about-v2-logins-title {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--color-text-muted, #9ca3af);
      margin-bottom: 10px;
    }

    .about-v2-logins-list {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .about-v2-login-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      background: var(--color-surface-alt, rgba(0,0,0,0.022));
      border: 1px solid var(--color-border, rgba(0,0,0,0.05));
      transition: background 0.15s;
    }

    .about-v2-login-item:hover {
      background: rgba(99,102,241,0.05);
    }

    .about-v2-login-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #c7d2fe;
      flex-shrink: 0;
    }

    .about-v2-login-item:first-child .about-v2-login-dot {
      background: #6366f1;
      box-shadow: 0 0 0 3px rgba(99,102,241,0.18);
    }

    .about-v2-login-date {
      font-size: 12px;
      font-weight: 600;
      color: var(--color-text, #374151);
      flex: 1;
    }

    .about-v2-login-time {
      font-size: 11px;
      color: var(--color-text-muted, #9ca3af);
    }

    .about-v2-login-meta {
      display: block;
      margin-top: 2px;
      font-size: 10px;
      line-height: 1.4;
      color: var(--color-text-muted, #9ca3af);
    }

    .about-v2-login-tag {
      font-size: 10px;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 100px;
      background: rgba(99,102,241,0.1);
      color: #4f46e5;
      letter-spacing: 0.04em;
    }

    /* ── Empty state ── */
    .about-v2-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 28px 20px;
      text-align: center;
    }

    .about-v2-empty-icon {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.07));
      border: 1px solid rgba(99,102,241,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 4px;
    }

    .about-v2-empty-icon svg {
      width: 20px;
      height: 20px;
      color: #6366f1;
      opacity: 0.7;
    }

    .about-v2-empty-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
      width: 100%;
      opacity: 0.35;
      margin: 8px 0;
    }

    .about-v2-empty-cell {
      height: 36px;
      border-radius: 8px;
      background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.05));
      border: 1px solid rgba(99,102,241,0.1);
    }

    .about-v2-empty h4 {
      font-size: 13px;
      font-weight: 600;
      color: var(--color-text, #374151);
      margin: 0;
    }

    .about-v2-empty p {
      font-size: 12px;
      color: var(--color-text-muted, #9ca3af);
      margin: 0;
      max-width: 28ch;
      line-height: 1.6;
    }

    /* ══════════════════════════════════════════
       SKILLS SECTION — Clean decomposed layout
    ══════════════════════════════════════════ */
    .skills-section-v2 {
      padding: 24px;
    }

    .skills-v2-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
    }

    .skills-v2-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--color-text-heading, #111827);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .skills-v2-title svg {
      width: 16px;
      height: 16px;
      color: #6366f1;
    }

    .skills-v2-add-btn {
      font-size: 11px;
      font-weight: 600;
      padding: 5px 12px;
      border-radius: 8px;
      background: rgba(99,102,241,0.08);
      border: 1px solid rgba(99,102,241,0.18);
      color: #4f46e5;
      cursor: pointer;
      transition: all 0.15s;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .skills-v2-add-btn:hover {
      background: rgba(99,102,241,0.14);
    }

    .skills-v2-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 22px;
    }

    .skill-chip-v2 {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      border-radius: 100px;
      font-size: 12px;
      font-weight: 500;
      background: var(--color-surface-alt, rgba(0,0,0,0.035));
      border: 1px solid var(--color-border, rgba(0,0,0,0.07));
      color: var(--color-text, #374151);
      transition: all 0.15s;
      cursor: default;
    }

    .skill-chip-v2:hover {
      background: rgba(99,102,241,0.08);
      border-color: rgba(99,102,241,0.2);
      color: #4f46e5;
    }

    .skill-chip-v2 .chip-dot {
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: currentColor;
      opacity: 0.5;
    }

    /* Cert mini cards */
    .skills-v2-certs-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--color-text-muted, #9ca3af);
      margin-bottom: 10px;
    }

    .skills-v2-cert-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .cert-card-v2 {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: 10px;
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
      transition: all 0.15s;
    }

    .cert-card-v2:hover {
      background: rgba(99,102,241,0.05);
      border-color: rgba(99,102,241,0.15);
    }

    .cert-icon-v2 {
      width: 34px;
      height: 34px;
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      flex-shrink: 0;
    }

    .cert-icon-v2.cert-gold   { background: rgba(245,158,11,0.12); }
    .cert-icon-v2.cert-indigo { background: rgba(99,102,241,0.12); }
    .cert-icon-v2.cert-green  { background: rgba(16,185,129,0.12); }

    .cert-info-v2 { flex: 1; min-width: 0; }
    .cert-name-v2 {
      font-size: 12px;
      font-weight: 600;
      color: var(--color-text-heading, #111827);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .cert-issuer-v2 {
      font-size: 11px;
      color: var(--color-text-muted, #9ca3af);
      margin-top: 1px;
    }
    .cert-year-v2 {
      font-size: 10px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 6px;
      background: rgba(0,0,0,0.04);
      color: var(--color-text-secondary, #6b7280);
      flex-shrink: 0;
    }

    /* ══════════════════════════════════════════
       SECURITY SECTION v2 — Premium redesign
    ══════════════════════════════════════════ */
    .security-v2-wrap { padding: 0; }
    .security-v2-toggles {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-bottom: 24px;
    }
    .security-v2-toggle-row {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px;
      border-radius: 14px;
      border: 1px solid var(--color-border, rgba(0,0,0,0.08));
      background: var(--color-surface-alt, rgba(0,0,0,0.02));
    }
    .security-v2-toggle-icon {
      width: 36px;
      height: 36px;
      border-radius: 11px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .security-v2-toggle-icon.icon-indigo  { background: rgba(99,102,241,0.12); color: #6366f1; }
    .security-v2-toggle-icon.icon-emerald { background: rgba(16,185,129,0.12); color: #10b981; }
    .security-v2-toggle-icon.icon-amber   { background: rgba(245,158,11,0.12); color: #f59e0b; }
    .security-v2-toggle-body { flex: 1; min-width: 0; }
    .security-v2-toggle-name { font-size: 13px; font-weight: 600; color: var(--color-text-heading, #111827); }
    .security-v2-toggle-desc { font-size: 11px; color: var(--color-text-muted, #9ca3af); margin-top: 2px; }

    .security-v2-pwd-block {
      margin-top: 6px;
      padding: 20px;
      border: 1px solid var(--color-border, rgba(0,0,0,0.08));
      border-radius: 16px;
      background: linear-gradient(180deg, rgba(99,102,241,0.03), rgba(99,102,241,0.01));
    }
    .security-v2-pwd-label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--color-accent, #6366f1);
      margin-bottom: 14px;
    }
    .security-v2-pwd-label svg { width: 13px; height: 13px; opacity: .9; }

    .security-v2-hint {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 12px;
      background: rgba(99,102,241,0.05);
      border: 1px solid rgba(99,102,241,0.16);
      font-size: 12px;
      color: var(--color-text-secondary, #6b7280);
      line-height: 1.55;
      margin-bottom: 18px;
    }
    .security-v2-hint svg { width: 14px; height: 14px; color: #6366f1; flex-shrink: 0; margin-top: 1px; }

    .sec-field { position: relative; margin-bottom: 14px; }
    .sec-input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .sec-input-icon {
      position: absolute;
      left: 14px;
      width: 14px;
      height: 14px;
      color: #94a3b8;
      pointer-events: none;
      transition: color .2s ease;
      z-index: 2;
    }
    .sec-field input {
      width: 100%;
      padding: 14px 16px 10px 38px;
      border-radius: 11px;
      border: 1.5px solid var(--color-border, rgba(0,0,0,0.1));
      background: var(--color-surface-alt, rgba(0,0,0,0.02));
      font-size: 13px;
      font-family: 'Poppins', sans-serif;
      font-weight: 500;
      color: var(--color-text-heading, #111827);
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      box-sizing: border-box;
    }
    .sec-field label {
      position: absolute;
      top: 50%;
      left: 38px;
      transform: translateY(-50%);
      font-size: 12px;
      font-weight: 500;
      color: var(--color-text-muted, #9ca3af);
      pointer-events: auto;
      cursor: text;
      transition: all .18s ease;
      font-family: 'Poppins', sans-serif;
      z-index: 3;
      background: transparent;
      padding: 0 2px;
    }
    .sec-field input:focus,
    .sec-field.is-active input {
      border-color: rgba(99,102,241,0.62);
      box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }
    .sec-field input:focus ~ label,
    .sec-field input:not(:placeholder-shown) ~ label,
    .sec-field.is-active label {
      top: 8px;
      transform: none;
      font-size: 9.5px;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #6366f1;
      font-weight: 600;
    }
    .sec-field:focus-within .sec-input-icon,
    .sec-field.is-active .sec-input-icon {
      color: #6366f1;
    }
    .sec-field.has-error input { border-color: rgba(225,29,72,0.55) !important; box-shadow: 0 0 0 3px rgba(225,29,72,0.1) !important; }

    .sec-pwd-footer { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 8px; }
    .sec-pwd-btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 10px 20px; border-radius: 10px;
      font-size: 12px; font-weight: 600; font-family: 'Poppins', sans-serif;
      background: #6366f1; color: #fff; border: none; cursor: pointer;
      transition: all .2s; letter-spacing: .01em;
    }
    .sec-pwd-btn:hover { background: #4f46e5; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(99,102,241,0.3); }
    .sec-pwd-btn:disabled { opacity: .55; transform: none; box-shadow: none; cursor: not-allowed; }
    .sec-pwd-btn svg { width: 14px; height: 14px; }
    .sec-strength-bar  { flex: 1; height: 4px; border-radius: 2px; background: rgba(0,0,0,0.07); overflow: hidden; }
    .sec-strength-fill { height: 100%; width: 0; border-radius: 2px; background: linear-gradient(90deg,#ef4444,#f59e0b,#10b981); transition: width .3s ease; }

    /* ══════════════════════════════════════════
       PROFILE HEADER — "Edit Details" only
    ══════════════════════════════════════════ */
    .profile-actions .btn-edit-details-only {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 16px;
      border-radius: 10px;
      font-size: 12px;
      font-weight: 600;
      font-family: 'Poppins', sans-serif;
      background: var(--color-primary, #6366f1);
      color: #fff;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      letter-spacing: 0.01em;
    }

    .profile-actions .btn-edit-details-only:hover {
      background: #4f46e5;
      transform: translateY(-1px);
      box-shadow: 0 4px 14px rgba(99,102,241,0.3);
    }

    /* ══════════════════════════════════════════
       INSIGHTS PANEL — Visitor analytics
    ══════════════════════════════════════════ */
    .premium-insights .insight-kpi-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 18px;
    }

    .premium-insights .insight-kpi {
      padding: 12px 14px;
      border-radius: 10px;
      background: var(--color-surface-alt, rgba(0,0,0,0.025));
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
    }

    .premium-insights .insight-kpi span { font-size: 11px; color: var(--color-text-muted, #9ca3af); display: block; margin-bottom: 4px; }
    .premium-insights .insight-kpi strong { font-size: 22px; font-weight: 700; color: var(--color-text-heading, #111827); display: block; line-height: 1; }
    .premium-insights .insight-kpi em { font-size: 10px; color: #10b981; font-style: normal; font-weight: 600; }

    /* ══════════════════════════════════════════
       DARK MODE ADJUSTMENTS
    ══════════════════════════════════════════ */
    [data-theme="dark"] .about-v2-cal-cell { background: rgba(255,255,255,0.04); }
    [data-theme="dark"] .about-v2-cal-cell.is-active { background: rgba(99,102,241,0.18); }
    [data-theme="dark"] .about-v2-login-item { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .about-v2-pill { background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.18); }
    [data-theme="dark"] .about-v2-cal-summary { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.06); }
    [data-theme="dark"] .skill-chip-v2 { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.08); }
    [data-theme="dark"] .cert-card-v2 { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.07); }
    [data-theme="dark"] .security-v2-hint { background: rgba(99,102,241,0.08); border-color: rgba(99,102,241,0.2); }
    [data-theme="dark"] .sec-field input { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.1); color: #f1f5f9; }
    [data-theme="dark"] .security-v2-toggle-row { border-color: rgba(255,255,255,0.07); background: rgba(255,255,255,0.03); }
    [data-theme="dark"] .security-v2-pwd-block { border-color: rgba(255,255,255,0.07); background: rgba(255,255,255,0.02); }

    body[data-account-blocked="1"] .profile-main,
    body.is-account-blocked .profile-main {
      filter: grayscale(1);
      opacity: 0.58;
      pointer-events: none;
      user-select: none;
    }

    body[data-account-blocked="1"] .navbar,
    body.is-account-blocked .navbar {
      filter: grayscale(1);
      opacity: 0.85;
    }

    body[data-account-blocked="1"] .blocked-allow,
    body.is-account-blocked .blocked-allow {
      pointer-events: auto !important;
      filter: none !important;
      opacity: 1 !important;
    }
  </style>
</head>
<body class="grid-dot-bg home-page-body" data-account-blocked="<?= $displayIsBlocked === 1 ? '1' : '0' ?>" data-delete-request-pending="<?= $hasPendingDeleteRequest ? '1' : '0' ?>">
  <a class="skip-link" href="#profile-main-content">Skip to main content</a>
  <canvas id="gradient-canvas"></canvas>

  <!-- ── Navbar ─────────────────────────────────────── -->
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
            <div class="nav-avatar"><?= htmlspecialchars($initials) ?></div>
          </button>
          <div class="nav-dropdown">
            <div class="nav-dropdown-header">
              <strong><?= htmlspecialchars($displayName) ?></strong>
              <span><?= htmlspecialchars($displayEmail) ?></span>
            </div>
            <a href="profile.php" class="nav-dropdown-item"><i data-lucide="user" class="w-4 h-4"></i> My Profile</a>
            <a href="../BackOffice/dashboard.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
            <a href="#settings" class="nav-dropdown-item"><i data-lucide="settings" class="w-4 h-4"></i> Settings</a>
            <a href="../../index.php?action=logout" class="nav-dropdown-item nav-dropdown-item-danger"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</a>
          </div>
        </div>
      </div>
      <button class="nav-toggle" id="nav-toggle" type="button" aria-label="Toggle mobile menu" aria-controls="navbar-nav" aria-expanded="false"><span></span><span></span><span></span></button>
    </div>
  </nav>

  <!-- ── Main ──────────────────────────────────────── -->
  <main class="profile-main" id="profile-main-content" tabindex="-1">
    <div class="container profile-page-layout">

      <!-- Left sidebar -->
      <aside class="home-left glass-card" aria-label="Quick profile and navigation">
        <div class="left-profile">
          <div class="left-avatar"><?= htmlspecialchars($sidebarInitials) ?></div>
          <div>
            <h4><?= htmlspecialchars($sidebarDisplayName) ?></h4>
            <p><?= htmlspecialchars($profileLevel) ?></p>
          </div>
        </div>
        <nav class="left-nav">
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg><span>Home Feed</span></a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg><span>Social Feed</span></a>
          <a href="brainstormings.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6m-5 3h4M12 2a7 7 0 00-7 7c0 2.5 1.5 4.5 3 6v1a2 2 0 002 2h4a2 2 0 002-2v-1c1.5-1.5 3-3.5 3-6a7 7 0 00-7-7z"/></svg><span>Brainstorming</span></a>
          <a href="events.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg><span>Events</span></a>

          <a class="active" href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg><span>Profile</span></a>
        </nav>
        <div class="left-gamification">
          <div class="mini-score"><span>Reputation</span><strong><?= (int) $reputationScore ?></strong></div>
          <div class="mini-score"><span>Daily Streak 🔥</span><strong><?= (int) $streakDays ?> days</strong></div>
        </div>
      </aside>

      <!-- Main content -->
      <section class="profile-content-area">

        <!-- ── Profile Header Card ──────────────────────── -->
        <section class="profile-header glass-card fade-in-section" aria-labelledby="profile-name-heading">
          <div class="profile-cover-banner"></div>
          <div class="profile-header-glow"></div>
          <div class="profile-header-inner">

            <!-- Avatar -->
            <div class="profile-avatar-wrap">
              <div class="profile-avatar">
                <img src="<?= htmlspecialchars($displayAvatarResolved) ?>" alt="Profile" id="profileAvatar">
                <button class="avatar-upload-btn" id="avatarUploadBtn" title="Change Photo">
                  <i data-lucide="camera" class="w-5 h-5"></i>
                </button>
                <input type="file" id="avatarInput" accept="image/*" hidden>
              </div>
              <button class="avatar-random-btn" id="profileRandomAvatarBtn" title="Random Avatar">
                <i data-lucide="dices" class="w-3.5 h-3.5"></i>
              </button>
              <div class="avatar-verified" title="Verified Account">
                <i data-lucide="badge-check" class="w-4 h-4"></i>
              </div>
            </div>

            <!-- Info -->
            <div class="profile-info">
              <div class="profile-info-top">
                <h1 class="profile-name" id="profile-name-heading"><?= htmlspecialchars($displayName) ?></h1>
                <span class="profile-badge badge-legend"><i data-lucide="crown" class="w-3 h-3"></i> Legend</span>
                <?php if ($isOwner): ?>
                <span class="profile-badge badge-owner" id="previewModeBadge">
                  <i data-lucide="eye" class="w-3 h-3"></i> Owner View
                </span>
                <?php endif; ?>
              </div>
              <div class="profile-meta-row">
                <span class="profile-meta-item"><i data-lucide="mail" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($displayEmail) ?></span>
                <span class="profile-meta-dot"></span>
                <span class="profile-meta-item" id="profileLocationText"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($displayCountry !== '' ? $displayCountry : 'No country set') ?></span>
                <span class="profile-meta-dot"></span>
                <span class="profile-meta-item"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Joined <?= htmlspecialchars($joinedLabel) ?></span>
                <span class="profile-meta-dot"></span>
                <span class="profile-meta-item"><i data-lucide="clock-3" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($lastSeenLabel) ?></span>
              </div>
              <div class="profile-tags">
                <span class="profile-tag" id="profileTitleTag"><i data-lucide="code-2" class="w-3 h-3"></i> <?= htmlspecialchars($displayTitle !== '' ? $displayTitle : 'Member') ?></span>
                <span class="profile-tag tag-accent"><i data-lucide="rocket" class="w-3 h-3"></i> Innovator</span>
                <span class="profile-tag tag-green"><i data-lucide="users" class="w-3 h-3"></i> Community Builder</span>
                <span class="profile-tag tag-open"><i data-lucide="badge-check" class="w-3 h-3"></i> Open to Collaborate</span>
              </div>
              <div class="profile-social-row">
                <a href="#" class="social-link-mini"><i data-lucide="linkedin" class="w-3.5 h-3.5"></i> LinkedIn</a>
                <a href="#" class="social-link-mini"><i data-lucide="github" class="w-3.5 h-3.5"></i> GitHub</a>
                <a href="#" class="social-link-mini"><i data-lucide="globe" class="w-3.5 h-3.5"></i> Portfolio</a>
              </div>
            </div>

            <!-- Reputation ring -->
            <div class="profile-reputation-core">
              <div class="profile-rep-ring" style="--progress: <?= (int) $reputationScore ?>;" aria-label="Reputation score <?= (int) $reputationScore ?> out of 100">
                <div class="profile-rep-inner">
                  <strong><?= (int) $reputationScore ?></strong>
                  <span>REP</span>
                </div>
              </div>
              <p class="rep-level"><?= htmlspecialchars($profileLevel) ?></p>
              <p class="rep-growth"><?= htmlspecialchars($reputationGrowth) ?> · 🔥 <?= (int) $streakDays ?> day streak</p>
            </div>

            <!-- Actions — Edit Details only -->
            <div class="profile-actions">
              <button class="btn btn-primary btn-sm" id="editToggleBtn">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Details
              </button>
              <button class="profile-action-icon" title="Export Data">
                <i data-lucide="download" class="w-4 h-4"></i>
              </button>
              <button class="profile-action-icon" id="shareProfileBtn" title="Share Profile">
                <i data-lucide="qr-code" class="w-4 h-4"></i>
              </button>
              <?php if ($isOwner): ?>
              <button class="profile-action-icon" id="togglePreviewBtn" title="Preview as public">
                <i data-lucide="switch-camera" class="w-4 h-4"></i>
              </button>
              <?php else: ?>
              <button class="btn btn-secondary btn-sm"><i data-lucide="send" class="w-3.5 h-3.5"></i> Message</button>
              <button class="btn btn-primary btn-sm"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Connect</button>
              <?php endif; ?>
              <button class="profile-action-icon action-danger" title="Sign Out">
                <i data-lucide="log-out" class="w-4 h-4"></i>
              </button>
            </div>
          </div>
        </section>

        <!-- ── Mes Favoris (Special for Freelancers) ── -->
        <?php 
        $isFreelancer = strtolower($displayRole) === 'freelancer' || strtolower($displayTitle) === 'freelancer';
        if ($isFreelancer): 
          $favorites = $eventController->listFavorites($sessionUser['id'] ?? 0);
        ?>
        <section class="glass-card panel fade-in-section" style="padding: 28px; margin-top: 30px;" aria-labelledby="fav-heading">
          <div class="about-v2-label" id="fav-heading">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l8.78-8.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            Mes Favoris
          </div>
          
          <?php if (empty($favorites)): ?>
            <div class="about-v2-empty">
              <div class="about-v2-empty-icon"><i data-lucide="heart"></i></div>
              <h4>No favorites yet</h4>
              <p>Explore events and click the heart icon to save them here for quick access.</p>
              <a href="events.php" class="btn btn-secondary btn-sm" style="margin-top: 15px;">Explore Events</a>
            </div>
          <?php else: ?>
            <div class="grid grid-3" style="gap: 20px; margin-top: 20px;">
              <?php foreach ($favorites as $fav): ?>
              <div class="project-card glass-card" style="padding: 15px; background: rgba(255,255,255,0.03);">
                <span class="text-small" style="color: var(--color-accent); font-weight: 600;"><?= htmlspecialchars($fav['category']) ?></span>
                <h4 style="font-size: 14px; margin: 8px 0;"><?= htmlspecialchars($fav['title']) ?></h4>
                <div class="text-small" style="opacity: 0.7; margin-bottom: 12px;">
                    <i data-lucide="calendar" class="w-3 h-3"></i> <?= date('M d, Y', strtotime($fav['event_date'])) ?>
                </div>
                <a href="event_details.php?id=<?= $fav['id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%; font-size: 11px;">Details</a>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ── Top grid: About + Insights ─────────────── -->
        <div class="profile-top-grid">

          <!-- ════════════════════════════════════════════
               ABOUT SECTION v2 — Fully redesigned
          ═════════════════════════════════════════════ -->
          <section class="glass-card panel fade-in-section about-section-v2" id="about-section" aria-labelledby="about-v2-heading">
            <div class="about-v2-inner">

              <!-- Bio -->
              <div class="about-v2-bio-block">
                <div class="about-v2-label">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  About
                </div>
                <p class="about-v2-bio-text" id="aboutBioText">
                  <?php if ($displayBio !== ''): ?>
                    <?= htmlspecialchars($displayBio) ?>
                  <?php else: ?>
                    <strong>Driven member of Diversity.is</strong> focused on building inclusive digital products, shipping reliable work, and collaborating across design, engineering, and strategy to deliver measurable impact.
                  <?php endif; ?>
                </p>
              </div>

              <!-- Meta pills -->
              <div class="about-v2-meta">
                <?php if ($displayTitle !== ''): ?>
                <span class="about-v2-pill">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                  <strong><?= htmlspecialchars($displayTitle) ?></strong>
                </span>
                <?php endif; ?>
                <span class="about-v2-pill">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <strong><?= htmlspecialchars($displayCountry !== '' ? $displayCountry : 'Global') ?></strong>
                </span>
                <span class="about-v2-pill">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                  Joined <?= htmlspecialchars($joinedLabel) ?>
                </span>
                <span class="about-v2-pill">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?= htmlspecialchars($lastSeenLabel) ?>
                </span>
              </div>

              <!-- ── Sign-in Calendar ─────────────────── -->
              <div class="about-v2-calendar" aria-labelledby="about-cal-title">

                <div class="about-v2-cal-header">
                  <div class="about-v2-cal-title" id="about-cal-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Login Calendar
                  </div>
                  <span class="about-v2-cal-badge"><?= (int) $calendarRangeDays ?> days</span>
                </div>

                <?php if ($hasSignInData): ?>
                  <!-- Calendar grid -->
                  <div class="about-v2-cal-grid" role="list" aria-label="Last <?= (int) $calendarRangeDays ?> days login activity">
                    <?php foreach ($calendarDays as $calDay):
                      $hasSess   = (int) ($calDay['sessions'] ?? 0) > 0;
                      $isTod     = (bool) ($calDay['is_today'] ?? false);
                      $cellClass = 'about-v2-cal-cell';
                      if ($hasSess) { $cellClass .= ' is-active'; }
                      if ($isTod)   { $cellClass .= ' is-today'; }
                      $tipLabel  = date('M d, Y', strtotime((string)$calDay['key'])) . ($hasSess ? ' · '.(int)$calDay['sessions'].' session(s)' : ' · no logins');
                    ?>
                      <div class="<?= htmlspecialchars($cellClass) ?>" role="listitem" title="<?= htmlspecialchars($tipLabel) ?>">
                        <span class="cal-day-num"><?= htmlspecialchars((string) $calDay['day']) ?></span>
                        <span class="cal-day-wd"><?= htmlspecialchars((string) $calDay['weekday']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>

                  <!-- Summary bar -->
                  <div class="about-v2-cal-summary">
                    <div class="about-v2-cal-summary-left">
                      <strong><?= (int) $activeDaysCount ?></strong> active day<?= $activeDaysCount !== 1 ? 's' : '' ?> in the last <?= (int) $calendarRangeDays ?> days
                    </div>
                    <?php if (!empty($recentSignIns)): ?>
                    <div class="about-v2-cal-summary-right">
                      Latest: <?= htmlspecialchars(date('M d · h:i A', (int) ($recentSignIns[0]['timestamp'] ?? time()))) ?>
                    </div>
                    <?php endif; ?>
                  </div>

                  <!-- Recent logins list -->
                  <?php if (!empty($recentSignIns)): ?>
                  <div class="about-v2-logins-title">Recent logins</div>
                  <div class="about-v2-logins-list">
                    <?php foreach ($recentSignIns as $li => $entry): ?>
                    <div class="about-v2-login-item">
                      <div class="about-v2-login-dot"></div>
                      <div class="about-v2-login-date"><?= htmlspecialchars(date('D, M d Y', (int) ($entry['timestamp'] ?? time()))) ?></div>
                      <div class="about-v2-login-time"><?= htmlspecialchars(date('h:i A', (int) ($entry['timestamp'] ?? time()))) ?></div>
                      <span class="about-v2-login-meta">
                        <?= htmlspecialchars((string) ($entry['device_type'] !== '' ? $entry['device_type'] : 'Unknown')) ?>
                        ·
                        <?= htmlspecialchars((string) ($entry['ip_address'] !== '' ? $entry['ip_address'] : 'IP unavailable')) ?>
                      </span>
                      <?php if ($li === 0): ?><span class="about-v2-login-tag">Now</span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>

                <?php else: ?>
                  <!-- Empty state — no login data -->
                  <div class="about-v2-empty">
                    <div class="about-v2-empty-icon">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 16l2 2 4-4" stroke-dasharray="5 3" opacity="0.4"/></svg>
                    </div>
                    <!-- Decorative empty grid -->
                    <div class="about-v2-empty-grid" aria-hidden="true">
                      <?php for ($i = 0; $i < 21; $i++): ?>
                        <div class="about-v2-empty-cell"></div>
                      <?php endfor; ?>
                    </div>
                    <h4>No login history yet</h4>
                    <p>Your sign-in activity will appear here automatically after each session.</p>
                  </div>
                  <!-- Still show the day labels even when empty, dimmed -->
                  <div class="about-v2-cal-grid" aria-hidden="true" style="opacity:0.3; margin-top: 10px;">
                    <?php foreach ($calendarDays as $calDay): ?>
                      <div class="about-v2-cal-cell <?= !empty($calDay['is_today']) ? 'is-today' : '' ?>">
                        <span class="cal-day-num"><?= htmlspecialchars((string) $calDay['day']) ?></span>
                        <span class="cal-day-wd"><?= htmlspecialchars((string) $calDay['weekday']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

              </div><!-- /about-v2-calendar -->
            </div><!-- /about-v2-inner -->
          </section>

          <!-- Insights sidebar -->
          <aside class="glass-card panel fade-in-section profile-insights-side premium-insights" aria-label="Profile insights">
            <div class="panel-header">
              <h3 class="panel-title">Visitor Insights</h3>
              <span class="insight-chip">Owner analytics</span>
            </div>
            <div class="insight-kpi-grid">
              <div class="insight-kpi"><span>Profile views today</span><strong>29</strong><em>↑12%</em></div>
              <div class="insight-kpi"><span>Recruiter visits</span><strong>6</strong><em>Last 7 days</em></div>
              <div class="insight-kpi"><span>Connection CTR</span><strong>18%</strong><em>Benchmark 9%</em></div>
              <div class="insight-kpi"><span>Suggested edits</span><strong>3</strong><em>AI-powered</em></div>
            </div>
            <div class="insight-spark premium-spark">
              <div class="spark-title">Reputation Growth · 30 days</div>
              <div class="spark-track spark-track-lg"><div class="spark-fill" id="repSparkFill"></div></div>
              <div class="spark-caption">+240 this month • Peak from project completion • Level up in 3 reviews</div>
            </div>
          </aside>
        </div><!-- /profile-top-grid -->

        <!-- ── Content Grid ────────────────────────────── -->
        <div class="profile-grid">

          <!-- Left column -->
          <div class="profile-left">

            <!-- Engagement Stats -->
            <div class="glass-card panel fade-in-section">
              <div class="panel-header">
                <h3 class="panel-title">Engagement</h3>
                <button class="panel-action"><i data-lucide="arrow-up-right" class="w-3.5 h-3.5"></i></button>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-icon stat-icon-indigo"><i data-lucide="zap" class="w-4 h-4"></i></div>
                  <div class="stat-value">47</div>
                  <div class="stat-label">Projects</div>
                </div>
                <div class="stat-item">
                  <div class="stat-icon stat-icon-cyan"><i data-lucide="git-pull-request" class="w-4 h-4"></i></div>
                  <div class="stat-value">189</div>
                  <div class="stat-label">Contributions</div>
                </div>
                <div class="stat-item">
                  <div class="stat-icon stat-icon-emerald"><i data-lucide="star" class="w-4 h-4"></i></div>
                  <div class="stat-value">4.9</div>
                  <div class="stat-label">Rating</div>
                </div>
                <div class="stat-item">
                  <div class="stat-icon stat-icon-amber"><i data-lucide="trophy" class="w-4 h-4"></i></div>
                  <div class="stat-value">12</div>
                  <div class="stat-label">Badges</div>
                </div>
              </div>
            </div>

            <!-- ── Skills & Certificates ──────────────── -->
            <div class="glass-card panel fade-in-section">
              <div class="skills-section-v2">
                <div class="skills-v2-header">
                  <div class="skills-v2-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    Skills
                  </div>
                  <button class="skills-v2-add-btn" type="button" onclick="window.location.href='skills.php'">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    Manage
                  </button>
                </div>

                <div class="skills-v2-chips">
                  <?php foreach ($skillItems as $skill): ?>
                  <span class="skill-chip-v2">
                    <span class="chip-dot"></span>
                    <?= htmlspecialchars($skill) ?>
                  </span>
                  <?php endforeach; ?>
                </div>

                <!-- Certificates -->
                <div class="skills-v2-certs-label">Certificates</div>
                <div class="skills-v2-cert-list">
                  <div class="cert-card-v2">
                    <div class="cert-icon-v2 cert-gold">🏅</div>
                    <div class="cert-info-v2">
                      <div class="cert-name-v2">Full-Stack Web Development</div>
                      <div class="cert-issuer-v2">Meta · Professional Certificate</div>
                    </div>
                    <span class="cert-year-v2">2024</span>
                  </div>
                  <div class="cert-card-v2">
                    <div class="cert-icon-v2 cert-indigo">🎖️</div>
                    <div class="cert-info-v2">
                      <div class="cert-name-v2">AWS Solutions Architect</div>
                      <div class="cert-issuer-v2">Amazon Web Services</div>
                    </div>
                    <span class="cert-year-v2">2023</span>
                  </div>
                  <div class="cert-card-v2">
                    <div class="cert-icon-v2 cert-green">✅</div>
                    <div class="cert-info-v2">
                      <div class="cert-name-v2">UX Design Fundamentals</div>
                      <div class="cert-issuer-v2">Google · Coursera</div>
                    </div>
                    <span class="cert-year-v2">2023</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Connections -->
            <div class="glass-card panel fade-in-section">
              <div class="panel-header">
                <h3 class="panel-title">Connections</h3>
              </div>
              <div class="connections-list">
                <div class="connection-item">
                  <div class="connection-icon connection-discord"><i data-lucide="message-square" class="w-3.5 h-3.5"></i></div>
                  <div class="connection-info"><span class="connection-name">Discord</span><span class="connection-status">Connected</span></div>
                  <div class="connection-dot dot-active"></div>
                </div>
                <div class="connection-item">
                  <div class="connection-icon connection-github"><i data-lucide="github" class="w-3.5 h-3.5"></i></div>
                  <div class="connection-info"><span class="connection-name">GitHub</span><span class="connection-status">Connected</span></div>
                  <div class="connection-dot dot-active"></div>
                </div>
                <div class="connection-item">
                  <div class="connection-icon"><i data-lucide="linkedin" class="w-3.5 h-3.5"></i></div>
                  <div class="connection-info"><span class="connection-name">LinkedIn</span><span class="connection-status">Not connected</span></div>
                  <button class="connection-connect-btn">Connect</button>
                </div>
              </div>
            </div>

            <!-- Achievements -->
            <div class="glass-card panel fade-in-section">
              <div class="panel-header">
                <h3 class="panel-title">Achievements & Gamification</h3>
                <button class="panel-action"><i data-lucide="share-2" class="w-3.5 h-3.5"></i></button>
              </div>
              <div class="badge-wall">
                <span class="badge-chip">🏆 Team Player</span>
                <span class="badge-chip">⚡ Sprint Master</span>
                <span class="badge-chip">🧠 Mentor</span>
                <span class="badge-chip">🔥 7-Day Streak</span>
                <span class="badge-chip">✅ Top Reviewer</span>
              </div>
              <div class="next-level-box">
                <p>Progress to next level</p>
                <div class="spark-track"><div class="spark-fill" style="width:72%"></div></div>
                <small>+180 XP to reach Level 13</small>
              </div>
            </div>

          </div><!-- /profile-left -->

          <!-- Right column -->
          <div class="profile-right">

            <!-- AI Insight -->
            <div class="ai-insight-card glass-card fade-in-section">
              <div class="ai-insight-glow"></div>
              <div class="ai-insight-inner">
                <div class="ai-insight-header">
                  <div class="ai-insight-title">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                    <span>AI IMPACT ANALYSIS</span>
                  </div>
                  <span class="ai-insight-updated">Updated 2h ago</span>
                </div>
                <h3 class="ai-insight-headline">Top 5% Collaborator</h3>
                <p class="ai-insight-description">Your collaboration score is <strong>trending up 23%</strong> this month. You've positively impacted <strong>12 team projects</strong> and mentored <strong>3 new developers</strong>.</p>
                <div class="ai-insight-bar">
                  <div class="ai-bar-labels"><span>Impact Score</span><span>Top 5%</span></div>
                  <div class="ai-bar-track"><div class="ai-bar-fill" id="aiBarFill"></div></div>
                </div>
              </div>
            </div>

            <!-- Tabs -->
            <div class="profile-tabs fade-in-section">
              <button class="profile-tab active" data-tab="settings"><i data-lucide="settings" class="w-4 h-4"></i> Settings</button>
              <button class="profile-tab" data-tab="history"><i data-lucide="clock" class="w-4 h-4"></i> History</button>
              <button class="profile-tab" data-tab="security"><i data-lucide="shield" class="w-4 h-4"></i> Security</button>
            </div>

            <!-- Settings Panel -->
            <div class="tab-content active" data-content="settings">
              <div class="settings-grid fade-in-section">
                <div class="glass-card panel">
                  <h4 class="panel-sub-title"><i data-lucide="bell" class="w-4 h-4"></i> Notifications</h4>
                  <p class="panel-sub-desc">Manage exactly when and where you get updates.</p>
                  <div class="toggle-list">
                    <div class="toggle-row"><span>Weekly Digest</span><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="toggle-row"><span>Project Alerts</span><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="toggle-row"><span>Mentor Requests</span><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div>
                  </div>
                </div>
                <div class="glass-card panel">
                  <h4 class="panel-sub-title"><i data-lucide="eye" class="w-4 h-4"></i> Privacy</h4>
                  <p class="panel-sub-desc">Control profile visibility and data usage preferences.</p>
                  <div class="toggle-list">
                    <div class="toggle-row"><span>Public Profile</span><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="toggle-row"><span>Share Activity Status</span><label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label></div>
                    <div class="toggle-row"><span>Allow AI Analysis</span><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                    <div class="toggle-row"><span>Show Last Seen</span><label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- History Panel -->
            <div class="tab-content" data-content="history">
              <div class="glass-card panel fade-in-section">
                <h4 class="panel-sub-title">Recent Activity</h4>
                <div class="activity-timeline">
                  <div class="activity-item">
                    <div class="activity-dot dot-blue"></div>
                    <div class="activity-body"><p>Updated profile details and bio</p><span>Just now · Web</span></div>
                  </div>
                  <div class="activity-item">
                    <div class="activity-dot dot-green"></div>
                    <div class="activity-body"><p>Completed <strong>API Design Sprint</strong> challenge</p><span>2 hours ago · +300 XP</span></div>
                  </div>
                  <div class="activity-item">
                    <div class="activity-dot dot-purple"></div>
                    <div class="activity-body"><p>Reviewed <strong>Sarah Kim's</strong> project</p><span>Yesterday, 14:00 · Review</span></div>
                  </div>
                  <div class="activity-item">
                    <div class="activity-dot dot-amber"></div>
                    <div class="activity-body"><p>Last account activity captured</p><span><?= htmlspecialchars($lastSeenLabel) ?></span></div>
                  </div>
                </div>
                <button class="panel-full-btn"><i data-lucide="history" class="w-3.5 h-3.5"></i> View Full History</button>
              </div>
            </div>

            <!-- ── Security Panel v2 — Premium Redesign ── -->
            <div class="tab-content" data-content="security">
              <div class="glass-card panel fade-in-section">

                <div class="panel-header" style="margin-bottom:20px;">
                  <h4 class="panel-sub-title" style="margin:0;"><i data-lucide="shield-check" class="w-4 h-4"></i> Security Settings</h4>
                </div>

                <div class="security-v2-wrap">
                  <div class="security-v2-toggles">

                    <div class="security-v2-toggle-row">
                      <div class="security-v2-toggle-icon icon-indigo">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                      </div>
                      <div class="security-v2-toggle-body">
                        <div class="security-v2-toggle-name">Two-Factor Authentication</div>
                        <div class="security-v2-toggle-desc">Add an extra layer of security to your account</div>
                      </div>
                      <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>

                    <div class="security-v2-toggle-row">
                      <div class="security-v2-toggle-icon icon-emerald">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                      </div>
                      <div class="security-v2-toggle-body">
                        <div class="security-v2-toggle-name">Login Notifications</div>
                        <div class="security-v2-toggle-desc">Receive alerts for every new sign-in to your account</div>
                      </div>
                      <label class="toggle"><input type="checkbox" checked><span class="toggle-slider"></span></label>
                    </div>

                    <div class="security-v2-toggle-row">
                      <div class="security-v2-toggle-icon icon-amber">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                      </div>
                      <div class="security-v2-toggle-body">
                        <div class="security-v2-toggle-name">Biometric Login</div>
                        <div class="security-v2-toggle-desc">Sign in faster with face ID or fingerprint</div>
                      </div>
                      <label class="toggle"><input type="checkbox"><span class="toggle-slider"></span></label>
                    </div>

                  </div>

                  <!-- Change Password -->
                  <div class="security-v2-pwd-block">
                    <div class="security-v2-pwd-label">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                      Change Password
                    </div>
                    <div class="security-v2-hint">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                      Use at least 10 characters with a mix of letters, numbers, and symbols for best security.
                    </div>

                    <form id="changePasswordForm" autocomplete="off" novalidate>
                      <div class="sec-field" id="secFieldCurrent">
                        <div class="sec-input-wrap">
                          <svg class="sec-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                          <input type="password" id="securityCurrentPassword" name="current_password" placeholder=" " autocomplete="current-password" required>
                          <label for="securityCurrentPassword">Current Password</label>
                        </div>
                      </div>
                      <div class="sec-field" id="secFieldNew">
                        <div class="sec-input-wrap">
                          <svg class="sec-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 11V7a4 4 0 0 0-8 0v4"/><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M12 15v2"/></svg>
                          <input type="password" id="securityNewPassword" name="new_password" placeholder=" " autocomplete="new-password" required>
                          <label for="securityNewPassword">New Password</label>
                        </div>
                      </div>
                      <div class="sec-field" id="secFieldConfirm">
                        <div class="sec-input-wrap">
                          <svg class="sec-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
                          <input type="password" id="securityConfirmPassword" name="confirm_password" placeholder=" " autocomplete="new-password" required>
                          <label for="securityConfirmPassword">Confirm New Password</label>
                        </div>
                      </div>
                      <div class="sec-pwd-footer">
                        <button type="submit" class="sec-pwd-btn" id="secUpdatePwdBtn">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                          Update Password
                        </button>
                        <div class="sec-strength-bar" title="Password strength indicator">
                          <div class="sec-strength-fill" id="secStrengthFill"></div>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>

              </div>
            </div>

            <!-- Danger zone -->
            <div class="profile-bottom-actions fade-in-section">
              <button class="btn btn-<?= $hasPendingDeleteRequest ? 'success' : 'danger' ?> btn-sm blocked-allow" id="requestDeleteAccountBtn" data-delete-request-state="<?= $hasPendingDeleteRequest ? 'pending' : 'ready' ?>">
                <i data-lucide="<?= $hasPendingDeleteRequest ? 'rotate-ccw' : 'trash-2' ?>" class="w-3.5 h-3.5"></i> <?= $hasPendingDeleteRequest ? 'Cancel Deletion Request' : 'Request Permanent Account Deletion' ?>
              </button>
            </div>

            <!-- Reviews snapshot -->
            <div class="glass-card panel fade-in-section profile-reviews-mini" style="margin-top: 24px;">
              <div class="panel-header">
                <h3 class="panel-title">Reviews Snapshot</h3>
                <button class="panel-action" id="viewAllReviewsBtn"><i data-lucide="external-link" class="w-3.5 h-3.5"></i></button>
              </div>
              <div class="rating-overview">
                <strong>4.9</strong>
                <span>Overall Rating · 128 reviews</span>
              </div>
              <div class="review-bars">
                <div><span>5★</span><div class="bar"><i style="width:84%"></i></div></div>
                <div><span>4★</span><div class="bar"><i style="width:12%"></i></div></div>
                <div><span>3★</span><div class="bar"><i style="width:4%"></i></div></div>
              </div>
            </div>

          </div><!-- /profile-right -->
        </div><!-- /profile-grid -->

        <!-- ═══════════════════════════════════════════════════
             EDIT PROFILE MODAL — Polished, dashboard-grade
        ════════════════════════════════════════════════════ -->
        <div class="uf-overlay" id="editModal" role="dialog" aria-modal="true" aria-labelledby="profileModalTitle">
          <div class="uf-card">

            <div class="uf-header">
              <div class="uf-header-left">
                <p class="uf-title" id="profileModalTitle">Edit Profile</p>
                <p class="uf-subtitle">Keep your Diversity.is profile polished and trustworthy for every collaboration. <strong class="uf-member-since">Member since <?= htmlspecialchars($memberSinceFullLabel) ?></strong>.</p>
              </div>
              <button type="button" id="closeEditModal" class="uf-close" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
              </button>
            </div>

            <div class="uf-body">
              <form id="userForm" autocomplete="off">
                <input type="hidden" id="formId" name="id" value="<?= (int) ($sessionUser['id'] ?? 0) ?>">
                <input type="hidden" id="formAvatarUrl" name="avatar_url" value="<?= htmlspecialchars($displayAvatarUrl) ?>">

                <!-- Avatar row -->
                <div class="uf-avatar-row">
                  <div class="uf-avatar-shell">
                    <img id="formAvatarPreview" class="uf-avatar-img"
                         src="<?= htmlspecialchars($displayAvatarResolved) ?>"
                         alt="Avatar preview">
                    <div id="formAvatarFallback" class="uf-avatar-fallback" aria-hidden="true">
                      <svg viewBox="0 0 80 80" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Default avatar">
                        <defs>
                          <linearGradient id="avatarGradProfile" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#60a5fa"/>
                            <stop offset="100%" stop-color="#6366f1"/>
                          </linearGradient>
                        </defs>
                        <circle cx="40" cy="40" r="38" fill="url(#avatarGradProfile)" opacity="0.22"/>
                        <circle cx="40" cy="30" r="13" fill="#1e3a8a" opacity="0.9"/>
                        <path d="M18 63c4-10 13-16 22-16s18 6 22 16" fill="#1e3a8a" opacity="0.9"/>
                      </svg>
                    </div>
                    <div class="uf-avatar-overlay">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    </div>
                    <div class="uf-avatar-shine"></div>
                  </div>
                  <div class="uf-avatar-info">
                    <span class="uf-avatar-label">Profile photo</span>
                    <span class="uf-avatar-hint">Upload a file or use the camera.</span>
                    <div class="uf-avatar-btns">
                      <input id="formAvatarFile" type="file" accept="image/*" hidden>
                      <button type="button" class="uf-btn uf-btn-ghost" id="pickAvatarFromFileBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload
                      </button>
                      <button type="button" class="uf-btn uf-btn-accent" id="openAvatarCameraBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        Camera
                      </button>
                      <button type="button" class="uf-btn uf-btn-ghost" id="captureAvatarBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M3 9a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 10.07 4h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 18.07 7H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/></svg>
                        Capture
                      </button>
                      <button type="button" class="uf-btn uf-btn-ghost" id="formRandomAvatarBtn" title="Generate random avatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5"/><path d="M4 20L21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
                        Random
                      </button>
                    </div>
                    <video id="avatarCameraVideo" class="uf-camera-feed" autoplay playsinline muted></video>
                    <canvas id="avatarCaptureCanvas" style="display:none"></canvas>
                  </div>
                </div>

                <!-- Fields -->
                <div class="uf-grid">
                  <div class="uf-group has-value">
                    <label class="uf-label" for="formFirstName">First name</label>
                    <input class="uf-input" id="formFirstName" name="first_name" type="text" value="<?= htmlspecialchars($displayFirstName) ?>" placeholder=" ">
                  </div>
                  <div class="uf-group has-value">
                    <label class="uf-label" for="formLastName">Last name</label>
                    <input class="uf-input" id="formLastName" name="last_name" type="text" value="<?= htmlspecialchars($displayLastName) ?>" placeholder=" ">
                  </div>
                  <div class="uf-group uf-span-2 has-value">
                    <label class="uf-label" for="formEmail">Email address</label>
                    <input class="uf-input" id="formEmail" name="email" type="email" value="<?= htmlspecialchars($displayEmail) ?>" placeholder=" ">
                  </div>
                  <div class="uf-group <?= $displayPhone !== '' ? 'has-value' : '' ?>">
                    <label class="uf-label" for="formPhone">Phone</label>
                    <input class="uf-input" id="formPhone" name="phone" type="tel" value="<?= htmlspecialchars($displayPhone) ?>" placeholder=" " required inputmode="tel" autocomplete="tel" minlength="9" maxlength="20">
                  </div>

                  <div class="uf-section-sep uf-span-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                    Account
                  </div>
                  <div class="uf-group has-value">
                    <label class="uf-label" for="formRole">Role</label>
                    <select class="uf-input uf-select" id="formRole" name="role">
                      <option value="client" <?= $displayRoleNormalized === 'client' ? 'selected' : '' ?>>Client</option>
                      <option value="freelancer" <?= $displayRoleNormalized === 'freelancer' ? 'selected' : '' ?>>Freelancer</option>
                    </select>
                  </div>
                  <div class="uf-group has-value">
                    <label class="uf-label" for="formStatus">Status</label>
                    <select class="uf-input uf-select" id="formStatus" name="status">
                      <option value="1" <?= $displayStatus === 1 ? 'selected' : '' ?>>Active</option>
                      <option value="0" <?= $displayStatus === 0 ? 'selected' : '' ?>>Offline</option>
                    </select>
                  </div>
                  <div class="uf-group <?= $displayCountry !== '' ? 'has-value' : '' ?>">
                    <label class="uf-label" for="formCountry">Country</label>
                    <input class="uf-input" id="formCountry" name="country" type="text" value="<?= htmlspecialchars($displayCountry) ?>" placeholder=" " readonly>
                    <div class="uf-country-meta" aria-live="polite">
                      <span class="uf-country-flag">🌍</span>
                      <span class="uf-country-prefix">No prefix detected</span>
                    </div>
                  </div>
                  <div class="uf-span-2 uf-globe-wrap" id="profileGlobeWrap">
                    <label class="uf-map-title" for="profileUserGlobeMap">Pick country on globe</label>
                    <div class="uf-globe" id="profileUserGlobeMap" aria-label="Profile country picker map"></div>
                  </div>

                  <div class="uf-section-sep uf-span-2">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    Profile
                  </div>
                  <div class="uf-group uf-span-2 is-textarea <?= $displayBio !== '' ? 'has-value' : '' ?>">
                    <label class="uf-label" for="formBio">Bio</label>
                    <textarea class="uf-input uf-textarea" id="formBio" name="bio" rows="3" placeholder=" "><?= htmlspecialchars($displayBio) ?></textarea>
                  </div>

                  <!-- Hidden -->
                  <input type="hidden" id="formFaceEnrolled" name="face_enrolled" value="<?= (int) $displayFaceEnrolled ?>">
                  <input type="hidden" id="formFaceDescriptor" name="face_descriptor" value="">
                  <input type="hidden" id="formFaceImagesPath" name="face_images_path" value="">
                  <input type="hidden" id="formLatitude">
                  <input type="hidden" id="formLongitude">
                  <input type="hidden" id="formMapAddress">
                </div>

              </form>
            </div><!-- /uf-body -->

            <div class="uf-actions">
              <button type="button" class="uf-btn uf-btn-ghost" id="cancelUserModal">Cancel</button>
              <button type="submit" form="userForm" class="uf-btn uf-btn-primary" id="saveUserBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                Save Profile
              </button>
            </div>

          </div>
        </div><!-- /editModal -->

      </section><!-- /profile-content-area -->
    </div>
  </main>

  <!-- FAB -->
  <button class="profile-fab" id="profileFab" aria-label="Quick profile actions">
    <i data-lucide="plus" class="w-5 h-5"></i>
  </button>
  <div class="profile-fab-menu" id="profileFabMenu">
    <button id="fabEditProfile"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Profile</button>
    <button><i data-lucide="folder-plus" class="w-4 h-4"></i> Add Project</button>
    <button><i data-lucide="badge-check" class="w-4 h-4"></i> Add Certificate</button>
  </div>

  <div class="profile-toast-stack" id="profileToastStack" aria-live="polite"></div>

  <div class="modal-overlay" id="aboutPillModal">
    <div class="modal glass-card">
      <div class="modal-header">
        <h3 id="aboutPillTitle">Highlight details</h3>
        <button class="modal-close" id="closeAboutPillModal"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
      <div class="modal-form">
        <p id="aboutPillBody" class="about-pill-modal-text"></p>
      </div>
    </div>
  </div>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/globe-explorer.js"></script>
  <script src="../../assets/js/user.js"></script>
  <script src="../../assets/js/profile.js"></script>
</body>
</html>