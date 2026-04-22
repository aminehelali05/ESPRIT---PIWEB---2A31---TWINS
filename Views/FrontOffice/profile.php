<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include_once(__DIR__ . '/../../Controllers/UserController.php');

if (!UserController::isAuthenticated()) {
  $_SESSION['flash_error'] = 'Please sign in to access your profile.';
  header('Location: auth.php');
  exit;
}

$userController = new UserController();
$db = config::getConnexion();

$tableHasColumn = static function (PDO $dbConn, string $table, string $column): bool {
  static $cache = [];
  $cacheKey = strtolower($table . ':' . $column);
  if (array_key_exists($cacheKey, $cache)) {
    return (bool) $cache[$cacheKey];
  }
  try {
    $stmt = $dbConn->query("SHOW COLUMNS FROM {$table} LIKE '" . addslashes($column) . "'");
    $cache[$cacheKey] = (bool) ($stmt && $stmt->fetch());
  } catch (Exception $e) {
    $cache[$cacheKey] = false;
  }
  return (bool) $cache[$cacheKey];
};

$normalizeCountryKey = static function (string $value): string {
  $normalized = trim(strtolower($value));
  if ($normalized === '') {
    return '';
  }
  if (function_exists('iconv')) {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
    if ($ascii !== false) {
      $normalized = strtolower($ascii);
    }
  }
  $normalized = preg_replace('/\(.*?\)/', ' ', $normalized);
  $normalized = preg_replace('/[^a-z\s]/', ' ', $normalized);
  $normalized = preg_replace('/\s+/', ' ', $normalized);
  return trim((string) $normalized);
};

$toCanonicalCountryName = static function (string $value) use ($normalizeCountryKey): string {
  $raw = trim($value);
  if ($raw === '') {
    return '';
  }

  $upper = strtoupper($raw);
  $codeMap = [
    'US' => 'United States',
    'USA' => 'United States',
    'GB' => 'United Kingdom',
    'UK' => 'United Kingdom',
    'AE' => 'United Arab Emirates',
    'UAE' => 'United Arab Emirates',
    'KR' => 'South Korea',
    'RU' => 'Russia',
  ];
  if (isset($codeMap[$upper])) {
    return $codeMap[$upper];
  }

  $normalized = $normalizeCountryKey($raw);
  $aliases = [
    'united states of america' => 'United States',
    'america' => 'United States',
    'uk' => 'United Kingdom',
    'england' => 'United Kingdom',
    'great britain' => 'United Kingdom',
    'uae' => 'United Arab Emirates',
    'korea republic of' => 'South Korea',
    'russian federation' => 'Russia',
    'turkiye' => 'Turkey',
  ];

  return $aliases[$normalized] ?? $raw;
};

$resolveCountryCoordinates = static function (string $countryValue) use ($toCanonicalCountryName, $normalizeCountryKey): ?array {
  $country = $toCanonicalCountryName($countryValue);
  $key = $normalizeCountryKey($country);
  if ($key === '') {
    return null;
  }

  $coordinates = [
    'argentina' => ['lat' => -34.6037, 'lng' => -58.3816],
    'australia' => ['lat' => -33.8688, 'lng' => 151.2093],
    'brazil' => ['lat' => -15.7939, 'lng' => -47.8828],
    'canada' => ['lat' => 45.4215, 'lng' => -75.6972],
    'chile' => ['lat' => -33.4489, 'lng' => -70.6693],
    'china' => ['lat' => 39.9042, 'lng' => 116.4074],
    'egypt' => ['lat' => 30.0444, 'lng' => 31.2357],
    'france' => ['lat' => 48.8566, 'lng' => 2.3522],
    'germany' => ['lat' => 52.52, 'lng' => 13.405],
    'ghana' => ['lat' => 5.6037, 'lng' => -0.187],
    'india' => ['lat' => 28.6139, 'lng' => 77.209],
    'ireland' => ['lat' => 53.3498, 'lng' => -6.2603],
    'italy' => ['lat' => 41.9028, 'lng' => 12.4964],
    'japan' => ['lat' => 35.6762, 'lng' => 139.6503],
    'mexico' => ['lat' => 19.4326, 'lng' => -99.1332],
    'peru' => ['lat' => -12.0464, 'lng' => -77.0428],
    'portugal' => ['lat' => 38.7223, 'lng' => -9.1393],
    'russia' => ['lat' => 55.7558, 'lng' => 37.6173],
    'senegal' => ['lat' => 14.7167, 'lng' => -17.4677],
    'south africa' => ['lat' => -33.9249, 'lng' => 18.4241],
    'south korea' => ['lat' => 37.5665, 'lng' => 126.978],
    'spain' => ['lat' => 40.4168, 'lng' => -3.7038],
    'sweden' => ['lat' => 59.3293, 'lng' => 18.0686],
    'tunisia' => ['lat' => 36.8065, 'lng' => 10.1815],
    'united arab emirates' => ['lat' => 24.4539, 'lng' => 54.3773],
    'united kingdom' => ['lat' => 51.5072, 'lng' => -0.1276],
    'united states' => ['lat' => 39.8283, 'lng' => -98.5795],
  ];

  return $coordinates[$key] ?? null;
};

$deriveLocationCoordinates = static function (string $countryValue, $latitudeRaw, $longitudeRaw) use ($toCanonicalCountryName, $resolveCountryCoordinates): array {
  $country = $toCanonicalCountryName($countryValue);

  $latitude = (is_numeric($latitudeRaw) && (float) $latitudeRaw >= -90 && (float) $latitudeRaw <= 90)
    ? (float) $latitudeRaw
    : null;
  $longitude = (is_numeric($longitudeRaw) && (float) $longitudeRaw >= -180 && (float) $longitudeRaw <= 180)
    ? (float) $longitudeRaw
    : null;

  if (($latitude === null || $longitude === null) && $country !== '') {
    $fallback = $resolveCountryCoordinates($country);
    if ($fallback) {
      $latitude = (float) $fallback['lat'];
      $longitude = (float) $fallback['lng'];
    }
  }

  return [
    'country' => $country,
    'latitude' => $latitude,
    'longitude' => $longitude,
  ];
};

$hostMatchesDomain = static function (string $host, string $domain): bool {
  $host = strtolower(trim($host));
  $domain = strtolower(trim($domain));
  if ($host === '' || $domain === '') {
    return false;
  }
  if ($host === $domain) {
    return true;
  }
  $suffix = '.' . $domain;
  return strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix;
};

$linkedAccountAllowedDomains = [
  'linkedin' => ['linkedin.com'],
  'github' => ['github.com'],
  'discord' => ['discord.com', 'discordapp.com'],
  'twitter' => ['twitter.com', 'x.com'],
  'portfolio' => [],
];

$verifyLinkedAccountUrl = static function (string $url): int {
  $target = trim($url);
  if ($target === '') {
    return 0;
  }

  if (function_exists('curl_init')) {
    $ch = curl_init($target);
    if ($ch !== false) {
      curl_setopt($ch, CURLOPT_NOBODY, true);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($ch, CURLOPT_TIMEOUT, 4);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
      curl_setopt($ch, CURLOPT_USERAGENT, 'Diversity.is Link Verification/1.0');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_exec($ch);
      $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      curl_close($ch);
      return ($statusCode >= 200 && $statusCode < 400) ? 1 : 0;
    }
  }

  $headers = @get_headers($target);
  if (!is_array($headers) || empty($headers[0])) {
    return 0;
  }
  $line = is_array($headers[0]) ? (string) end($headers[0]) : (string) $headers[0];
  if (!preg_match('/\s(\d{3})\s/', $line, $m)) {
    return 0;
  }
  $statusCode = (int) ($m[1] ?? 0);
  return ($statusCode >= 200 && $statusCode < 400) ? 1 : 0;
};

$buildGeneratedAvatarUrl = static function (string $firstName, string $lastName, string $email = ''): string {
  $seed = trim($firstName . '-' . $lastName . '-' . $email);
  if ($seed === '') {
    $seed = 'diversity-user-' . date('YmdHis');
  }
  return 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . rawurlencode($seed);
};

$normalizeMetadataJson = static function ($value): ?string {
  if ($value === null || $value === '') {
    return null;
  }
  if (is_string($value)) {
    $trimmed = trim($value);
    if ($trimmed === '') {
      return null;
    }
    json_decode($trimmed, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $trimmed;
    }
    return json_encode(['value' => $trimmed], JSON_UNESCAPED_UNICODE);
  }
  if (is_array($value) || is_object($value)) {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE);
    return $json === false ? null : $json;
  }
  return json_encode(['value' => $value], JSON_UNESCAPED_UNICODE);
};

$storeBase64ImageAsset = static function ($rawImageData, string $relativeDir, string $prefix = 'asset_'): ?string {
  $raw = trim((string) ($rawImageData ?? ''));
  if ($raw === '') {
    return null;
  }

  $mime = 'image/png';
  $binary = '';
  if (preg_match('/^data:(image\/(png|jpeg|jpg|webp|gif));base64,(.+)$/i', $raw, $matches)) {
    $mime = strtolower((string) ($matches[1] ?? 'image/png'));
    $binary = base64_decode((string) ($matches[3] ?? ''), true) ?: '';
  } else {
    $binary = base64_decode($raw, true) ?: '';
  }
  if ($binary === '') {
    return null;
  }

  $ext = 'png';
  if (strpos($mime, 'jpeg') !== false || strpos($mime, 'jpg') !== false) {
    $ext = 'jpg';
  } elseif (strpos($mime, 'webp') !== false) {
    $ext = 'webp';
  } elseif (strpos($mime, 'gif') !== false) {
    $ext = 'gif';
  }

  $assetsRoot = realpath(__DIR__ . '/../../assets');
  if (!$assetsRoot) {
    return null;
  }

  $relativeDir = trim(str_replace(['..', '\\'], ['', '/'], $relativeDir), '/');
  if ($relativeDir === '') {
    return null;
  }

  $targetDir = $assetsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
  if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0775, true);
  }
  if (!is_dir($targetDir) || !is_writable($targetDir)) {
    return null;
  }

  $fileName = uniqid($prefix, true) . '.' . $ext;
  $absolutePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
  if (@file_put_contents($absolutePath, $binary) === false) {
    return null;
  }

  return '../../assets/' . $relativeDir . '/' . $fileName;
};

$ensureRealtimeCommunicationSchema = static function (PDO $dbConn): void {
  static $ready = false;
  if ($ready) {
    return;
  }

  try {
    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS call_sessions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        thread_type ENUM("private","group") NOT NULL,
        thread_id INT NOT NULL,
        caller_id INT NOT NULL,
        callee_id INT DEFAULT NULL,
        call_type ENUM("audio","video") NOT NULL DEFAULT "video",
        status ENUM("ringing","accepted","rejected","ended","missed") NOT NULL DEFAULT "ringing",
        started_at DATETIME DEFAULT NULL,
        answered_at DATETIME DEFAULT NULL,
        ended_at DATETIME DEFAULT NULL,
        ended_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_call_sessions_thread (thread_type, thread_id),
        INDEX idx_call_sessions_callee (callee_id, status),
        INDEX idx_call_sessions_caller (caller_id, status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS call_signals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id BIGINT UNSIGNED NOT NULL,
        sender_id INT NOT NULL,
        signal_type ENUM("offer","answer","candidate","renegotiate","bye") NOT NULL,
        payload LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_call_signals_session (session_id, id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS group_reports (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        group_chat_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reported_user_id INT DEFAULT NULL,
        message_id BIGINT DEFAULT NULL,
        reason VARCHAR(190) NOT NULL,
        details TEXT DEFAULT NULL,
        status ENUM("pending","reviewed","resolved","dismissed") NOT NULL DEFAULT "pending",
        moderator_id INT DEFAULT NULL,
        moderation_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_group_reports_group (group_chat_id, status),
        INDEX idx_group_reports_reporter (reporter_id, created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
  } catch (Exception $e) {
    // Keep profile endpoints alive even if migration support is limited.
  }

  $ready = true;
};

$ensureLiveStreamingSchema = static function (PDO $dbConn): void {
  static $ready = false;
  if ($ready) {
    return;
  }

  try {
    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS live_streams (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        host_user_id INT NOT NULL,
        title VARCHAR(180) NOT NULL,
        description VARCHAR(500) DEFAULT NULL,
        category VARCHAR(80) DEFAULT NULL,
        visibility ENUM("public","friends") NOT NULL DEFAULT "public",
        allow_recording TINYINT(1) NOT NULL DEFAULT 0,
        cover_image_url VARCHAR(1024) DEFAULT NULL,
        status ENUM("live","ended") NOT NULL DEFAULT "live",
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ended_at DATETIME DEFAULT NULL,
        heartbeat_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        viewer_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_live_streams_host_status (host_user_id, status),
        INDEX idx_live_streams_status_heartbeat (status, heartbeat_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS live_stream_viewers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stream_id BIGINT UNSIGNED NOT NULL,
        user_id INT NOT NULL,
        joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        left_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_live_stream_viewers_stream (stream_id, left_at, last_seen_at),
        INDEX idx_live_stream_viewers_user (user_id, left_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS live_stream_signals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stream_id BIGINT UNSIGNED NOT NULL,
        sender_id INT NOT NULL,
        target_user_id INT DEFAULT NULL,
        signal_type ENUM("offer","answer","candidate","bye","ping") NOT NULL,
        payload LONGTEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_live_stream_signals_stream (stream_id, id),
        INDEX idx_live_stream_signals_target (target_user_id, stream_id, id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $dbConn->exec(
      'CREATE TABLE IF NOT EXISTS live_stream_chat (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stream_id BIGINT UNSIGNED NOT NULL,
        user_id INT NOT NULL,
        body TEXT NOT NULL,
        is_deleted TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_live_stream_chat_stream (stream_id, id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
  } catch (Exception $e) {
    // Keep profile endpoints available even if table creation is restricted.
  }

  $ready = true;
};

$fetchAccessibleLiveStreams = static function (PDO $dbConn, int $currentUserId, int $limit = 80): array {
  if ($currentUserId <= 0) {
    return [];
  }

  $safeLimit = max(1, min(200, (int) $limit));
  $rows = [];
  try {
    $q = $dbConn->prepare(
      "SELECT ls.id, ls.host_user_id, ls.title, ls.description, ls.category, ls.visibility, ls.allow_recording,
              ls.cover_image_url, ls.started_at, ls.heartbeat_at,
              u.first_name, u.last_name, u.avatar_url, u.role,
              COALESCE(vc.viewer_count, 0) AS viewer_count
       FROM live_streams ls
       INNER JOIN users u ON u.id = ls.host_user_id
       LEFT JOIN (
         SELECT stream_id, COUNT(*) AS viewer_count
         FROM live_stream_viewers
         WHERE left_at IS NULL
           AND last_seen_at >= (NOW() - INTERVAL 90 SECOND)
         GROUP BY stream_id
       ) vc ON vc.stream_id = ls.id
       WHERE ls.status = 'live'
         AND ls.heartbeat_at >= (NOW() - INTERVAL 90 SECOND)
         AND (
           ls.visibility = 'public'
           OR ls.host_user_id = :uid
           OR EXISTS (
             SELECT 1
             FROM friends f
             WHERE (f.user_one_id = :uid AND f.user_two_id = ls.host_user_id)
                OR (f.user_two_id = :uid AND f.user_one_id = ls.host_user_id)
           )
         )
       ORDER BY ls.started_at DESC
       LIMIT {$safeLimit}"
    );
    $q->execute(['uid' => $currentUserId]);
    $rows = (array) $q->fetchAll();
  } catch (Exception $e) {
    return [];
  }

  $streams = [];
  foreach ($rows as $row) {
    $streamId = (int) ($row['id'] ?? 0);
    $hostId = (int) ($row['host_user_id'] ?? 0);
    if ($streamId <= 0 || $hostId <= 0) {
      continue;
    }

    $streams[] = [
      'id' => $streamId,
      'host_user_id' => $hostId,
      'title' => (string) ($row['title'] ?? ''),
      'description' => (string) ($row['description'] ?? ''),
      'category' => (string) ($row['category'] ?? ''),
      'visibility' => (string) ($row['visibility'] ?? 'public'),
      'allow_recording' => (int) ($row['allow_recording'] ?? 0),
      'cover_image_url' => (string) ($row['cover_image_url'] ?? ''),
      'started_at' => (string) ($row['started_at'] ?? ''),
      'heartbeat_at' => (string) ($row['heartbeat_at'] ?? ''),
      'viewer_count' => (int) ($row['viewer_count'] ?? 0),
      'host' => [
        'id' => $hostId,
        'first_name' => (string) ($row['first_name'] ?? ''),
        'last_name' => (string) ($row['last_name'] ?? ''),
        'avatar_url' => (string) ($row['avatar_url'] ?? ''),
        'role' => (string) ($row['role'] ?? 'user'),
      ],
    ];
  }

  return $streams;
};

$resolveGroupMemberRole = static function (PDO $dbConn, int $groupId, int $userId): ?string {
  if ($groupId <= 0 || $userId <= 0) {
    return null;
  }
  try {
    $q = $dbConn->prepare(
      'SELECT role
       FROM group_chat_members
       WHERE group_chat_id = :gid AND user_id = :uid AND left_at IS NULL
       LIMIT 1'
    );
    $q->execute(['gid' => $groupId, 'uid' => $userId]);
    $row = $q->fetch();
    if (!$row) {
      return null;
    }
    return strtolower((string) ($row['role'] ?? 'member')) ?: 'member';
  } catch (Exception $e) {
    return null;
  }
};

$isGroupModerator = static function (PDO $dbConn, int $groupId, int $userId) use ($resolveGroupMemberRole): bool {
  $role = $resolveGroupMemberRole($dbConn, $groupId, $userId);
  return in_array($role, ['owner', 'admin'], true);
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
  $imageData = trim((string) ($jsonInput['image_data'] ?? ($jsonInput['image'] ?? '')));

  $sanitizeFileName = static function (string $fileName): string {
    $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    return trim((string) $normalized, '._-') ?: ('avatar_' . date('Ymd_His'));
  };

  if ($imageData === '') { $respond(['success' => false, 'message' => 'No image provided'], 400); }

  $binary = '';
  $mime = 'image/png';
  if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $imageData, $m)) {
    $mime = strtolower($m[1]);
    $binary = base64_decode($m[2], true) ?: '';
  } else {
    $binary = base64_decode($imageData, true) ?: '';
  }
  if ($binary === '') { $respond(['success' => false, 'message' => 'Invalid image data'], 400); }

  $ext = 'png';
  if (stripos($mime, 'jpeg') !== false || stripos($mime, 'jpg') !== false) { $ext = 'jpg'; }
  elseif (stripos($mime, 'webp') !== false) { $ext = 'webp'; }
  elseif (stripos($mime, 'svg') !== false) { $ext = 'svg'; }

  $baseName = $sanitizeFileName(trim((string) $existingForUpload->getFullName()) ?: ('user_' . $currentUserId));
  if (stripos($baseName, '.'.$ext) === false) { $baseName .= '.' . $ext; }

  $facesDir = realpath(__DIR__ . '/../../assets') . DIRECTORY_SEPARATOR . 'faces';
  if (!is_dir($facesDir)) { @mkdir($facesDir, 0775, true); }
  if (!is_dir($facesDir) || !is_writable($facesDir)) { $respond(['success' => false, 'message' => 'Storage unavailable'], 500); }

  $filePath = $facesDir . DIRECTORY_SEPARATOR . $baseName;
  if (file_put_contents($filePath, $binary) === false) { $respond(['success' => false, 'message' => 'Could not save file'], 500); }

  // Try to update user's avatar path if controller supports it
  try {
    if (method_exists($userController, 'updateAvatarById')) {
      $userController->updateAvatarById($currentUserId, 'assets/faces/' . $baseName);
    }
  } catch (Exception $e) {
    // ignore update errors
  }

  $respond(['success' => true, 'avatar' => 'assets/faces/' . $baseName, 'avatar_url' => 'assets/faces/' . $baseName]);
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
  if ($avatarUrl === '') { $respond(['success' => false, 'message' => 'Avatar path is required.'], 400); }

  if (!$userController->updateAvatarById($currentUserId, $avatarUrl)) {
    $respond(['success' => false, 'message' => 'Could not save avatar.'], 500);
  }

  $_SESSION['auth_user']['avatar_url'] = $avatarUrl;
  $respond(['success' => true, 'avatar_url' => $avatarUrl]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_update') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForProfileUpdate = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForProfileUpdate['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForProfileUpdate = $userController->getUserById($currentUserId);
  if (!$existingForProfileUpdate) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForProfileUpdate->getIsBlocked() ?? 0) === 1) { $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423); }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $firstName = trim((string) ($jsonInput['first_name'] ?? ''));
  $lastName = trim((string) ($jsonInput['last_name'] ?? ''));
  $email = trim((string) ($jsonInput['email'] ?? ''));
  $phone = trim((string) ($jsonInput['phone'] ?? ''));
  $role = trim((string) ($jsonInput['role'] ?? 'client'));
  $status = (string) ($jsonInput['status'] ?? '1');
  $country = trim((string) ($jsonInput['country'] ?? ''));
  $exactLocation = trim((string) ($jsonInput['exact_location'] ?? ''));
  $mapAddress = trim((string) ($jsonInput['map_address'] ?? ''));
  $bio = trim((string) ($jsonInput['bio'] ?? ''));
  $avatarUrl = trim((string) ($jsonInput['avatar_url'] ?? ''));
  $latitude = $jsonInput['latitude'] ?? null;
  $longitude = $jsonInput['longitude'] ?? null;

  if ($firstName === '' || $lastName === '' || $email === '') {
    $respond(['success' => false, 'message' => 'First name, last name, and email are required.'], 400);
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
  }

  if ($exactLocation === '') {
    $exactLocation = $mapAddress !== '' ? $mapAddress : $country;
  }

  if ($country === '' && $exactLocation !== '') {
    $parts = array_values(array_filter(array_map('trim', explode(',', $exactLocation)), static fn ($part): bool => $part !== ''));
    if (!empty($parts)) {
      $country = (string) end($parts);
    }
  }

  $updatePayload = [
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'role' => $role,
    'status' => $status,
    'country' => $country,
    'exact_location' => $exactLocation,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'bio' => $bio,
    'avatar_url' => $avatarUrl,
  ];

  if (!$userController->updateProfile($currentUserId, $updatePayload)) {
    $respond(['success' => false, 'message' => 'Could not update profile.'], 500);
  }

  $freshUser = $userController->getUserById($currentUserId);
  if (!$freshUser) {
    $respond(['success' => false, 'message' => 'Profile was updated but could not be reloaded.'], 500);
  }

  $_SESSION['auth_user']['first_name'] = (string) $freshUser->getFirstName();
  $_SESSION['auth_user']['last_name'] = (string) $freshUser->getLastName();
  $_SESSION['auth_user']['email'] = (string) $freshUser->getEmail();
  $_SESSION['auth_user']['role'] = (string) $freshUser->getRole();
  $_SESSION['auth_user']['avatar_url'] = (string) ($freshUser->getAvatarUrl() ?: ($_SESSION['auth_user']['avatar_url'] ?? ''));

  $respond([
    'success' => true,
    'message' => 'Profile updated successfully.',
    'user' => [
      'id' => (int) $freshUser->getId(),
      'first_name' => (string) $freshUser->getFirstName(),
      'last_name' => (string) $freshUser->getLastName(),
      'email' => (string) $freshUser->getEmail(),
      'phone' => (string) ($freshUser->getPhone() ?? ''),
      'role' => (string) $freshUser->getRole(),
      'status' => (int) $freshUser->getStatus(),
      'country' => (string) ($freshUser->getCountry() ?? ''),
      'exact_location' => (string) ($freshUser->getExactLocation() ?? ''),
      'latitude' => $freshUser->getLatitude() !== null ? (float) $freshUser->getLatitude() : null,
      'longitude' => $freshUser->getLongitude() !== null ? (float) $freshUser->getLongitude() : null,
      'bio' => (string) ($freshUser->getBio() ?? ''),
      'avatar_url' => (string) ($freshUser->getAvatarUrl() ?? ''),
    ],
  ]);
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

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $reason = trim((string) ($jsonInput['reason'] ?? ''));

  $requestId = $userController->createDeleteRequest($currentUserId, $currentUserId, $reason);
  if ($requestId === 'pending_exists') {
    $respond(['success' => false, 'message' => 'A delete request is already pending.'], 409);
  }
  if (!$requestId) {
    $respond(['success' => false, 'message' => 'Could not submit delete request.'], 500);
  }

  $respond([
    'success' => true,
    'blocked' => true,
    'request_state' => 'pending',
    'message' => 'Delete request sent. Please wait for admin approval.',
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

$collectSocialUserIds = static function (PDO $dbConn, int $currentUserId): array {
  $ids = [$currentUserId];

  try {
    $q = $dbConn->prepare(
      'SELECT CASE WHEN user_one_id = :uid THEN user_two_id ELSE user_one_id END AS friend_id
       FROM friends
       WHERE user_one_id = :uid OR user_two_id = :uid'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $fid = (int) ($row['friend_id'] ?? 0);
      if ($fid > 0) { $ids[] = $fid; }
    }
  } catch (Exception $e) {
  }

  try {
    $q = $dbConn->prepare(
      'SELECT sender_id, receiver_id
       FROM friend_requests
       WHERE status = "pending" AND (sender_id = :uid OR receiver_id = :uid)'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $senderId = (int) ($row['sender_id'] ?? 0);
      $receiverId = (int) ($row['receiver_id'] ?? 0);
      if ($senderId > 0) { $ids[] = $senderId; }
      if ($receiverId > 0) { $ids[] = $receiverId; }
    }
  } catch (Exception $e) {
  }

  $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($v): bool => $v > 0)));
  sort($ids);
  return $ids;
};

$fetchLinkedAccountsByUser = static function (PDO $dbConn, array $userIds) use ($tableHasColumn): array {
  $byUser = [];
  if (empty($userIds)) {
    return $byUser;
  }

  $placeholders = implode(',', array_fill(0, count($userIds), '?'));
  try {
    $platformExpr = $tableHasColumn($dbConn, 'linked_accounts', 'platform')
      ? 'platform'
      : ($tableHasColumn($dbConn, 'linked_accounts', 'provider') ? 'provider AS platform' : "'' AS platform");
    $accountLabelExpr = $tableHasColumn($dbConn, 'linked_accounts', 'account_label')
      ? 'account_label'
      : ($tableHasColumn($dbConn, 'linked_accounts', 'provider') ? 'provider AS account_label' : "'' AS account_label");
    $usernameExpr = $tableHasColumn($dbConn, 'linked_accounts', 'username')
      ? 'username'
      : ($tableHasColumn($dbConn, 'linked_accounts', 'provider_username') ? 'provider_username AS username' : "'' AS username");
    $profileUrlExpr = $tableHasColumn($dbConn, 'linked_accounts', 'profile_url')
      ? 'profile_url'
      : ($tableHasColumn($dbConn, 'linked_accounts', 'url') ? 'url AS profile_url' : "'' AS profile_url");
    $isPrimaryExpr = $tableHasColumn($dbConn, 'linked_accounts', 'is_primary') ? 'is_primary' : '0 AS is_primary';
    $isPublicExpr = $tableHasColumn($dbConn, 'linked_accounts', 'is_public') ? 'is_public' : '1 AS is_public';

    $selectColumns = ['id', 'user_id', $platformExpr, $accountLabelExpr, $usernameExpr, $profileUrlExpr, $isPrimaryExpr, $isPublicExpr];
    if ($tableHasColumn($dbConn, 'linked_accounts', 'verified')) {
      $selectColumns[] = 'verified';
    }
    if ($tableHasColumn($dbConn, 'linked_accounts', 'verification_checked_at')) {
      $selectColumns[] = 'verification_checked_at';
    }

    $q = $dbConn->prepare(
      "SELECT " . implode(', ', $selectColumns) . "
       FROM linked_accounts
       WHERE user_id IN ({$placeholders})
       ORDER BY is_primary DESC, created_at ASC"
    );
    $q->execute($userIds);
    foreach ((array) $q->fetchAll() as $row) {
      $uid = (int) ($row['user_id'] ?? 0);
      if ($uid <= 0) { continue; }
      if (!isset($byUser[$uid])) {
        $byUser[$uid] = [];
      }
      $byUser[$uid][] = [
        'id' => (int) ($row['id'] ?? 0),
        'platform' => (string) ($row['platform'] ?? ''),
        'account_label' => (string) ($row['account_label'] ?? ''),
        'username' => (string) ($row['username'] ?? ''),
        'profile_url' => (string) ($row['profile_url'] ?? ''),
        'is_primary' => (int) ($row['is_primary'] ?? 0),
        'is_public' => (int) ($row['is_public'] ?? 1),
        'verified' => (int) ($row['verified'] ?? 0),
        'verification_checked_at' => (string) ($row['verification_checked_at'] ?? ''),
      ];
    }
  } catch (Exception $e) {
  }

  return $byUser;
};

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_save_linked_accounts') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForAccounts = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForAccounts['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForAccounts = $userController->getUserById($currentUserId);
  if (!$existingForAccounts) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForAccounts->getIsBlocked() ?? 0) === 1) {
    $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423);
  }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $rawAccounts = $jsonInput['accounts'] ?? [];
  if (!is_array($rawAccounts)) { $rawAccounts = []; }

  $hasVerifiedColumn = $tableHasColumn($db, 'linked_accounts', 'verified');
  $hasVerificationCheckedAtColumn = $tableHasColumn($db, 'linked_accounts', 'verification_checked_at');

  $invalidAccounts = [];
  $normalizedAccounts = [];
  $hasPrimary = false;

  foreach ($rawAccounts as $row) {
    if (!is_array($row)) { continue; }

    $platform = strtolower(trim((string) ($row['platform'] ?? '')));
    if ($platform === 'x') { $platform = 'twitter'; }

    $profileUrl = trim((string) ($row['profile_url'] ?? ''));
    $accountLabel = trim((string) ($row['account_label'] ?? ''));
    $username = trim((string) ($row['username'] ?? ''));
    $isPublic = (int) (!array_key_exists('is_public', $row) || (int) !!$row['is_public']);

    if ($platform === '' && $profileUrl === '') {
      continue;
    }

    if ($platform === '' || $profileUrl === '') {
      $invalidAccounts[] = [
        'platform' => $platform,
        'profile_url' => $profileUrl,
        'message' => 'Platform and URL are required.',
      ];
      continue;
    }

    $parsed = parse_url($profileUrl);
    $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
    $host = strtolower((string) ($parsed['host'] ?? ''));
    if (substr($host, 0, 4) === 'www.') {
      $host = substr($host, 4);
    }

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
      $invalidAccounts[] = [
        'platform' => $platform,
        'profile_url' => $profileUrl,
        'message' => 'URL must start with http:// or https:// and include a valid host.',
      ];
      continue;
    }

    $allowedDomains = $linkedAccountAllowedDomains[$platform] ?? [];
    if (!empty($allowedDomains)) {
      $allowed = false;
      foreach ($allowedDomains as $domain) {
        if ($hostMatchesDomain($host, (string) $domain)) {
          $allowed = true;
          break;
        }
      }
      if (!$allowed) {
        $invalidAccounts[] = [
          'platform' => $platform,
          'profile_url' => $profileUrl,
          'message' => 'URL domain does not match the selected platform.',
        ];
        continue;
      }
    }

    if ($username === '') {
      $path = trim((string) ($parsed['path'] ?? ''), '/');
      if ($path !== '') {
        $parts = array_values(array_filter(explode('/', $path), static fn ($p): bool => trim((string) $p) !== ''));
        $username = !empty($parts) ? (string) end($parts) : '';
      }
    }

    $verified = $verifyLinkedAccountUrl($profileUrl);

    $normalizedAccounts[] = [
      'platform' => substr($platform, 0, 50),
      'account_label' => $accountLabel !== '' ? substr($accountLabel, 0, 80) : null,
      'username' => $username !== '' ? substr($username, 0, 120) : null,
      'profile_url' => substr($profileUrl, 0, 255),
      'is_primary' => $hasPrimary ? 0 : 1,
      'is_public' => $isPublic,
      'verified' => $verified,
      'verification_checked_at' => date('Y-m-d H:i:s'),
    ];

    if (!$hasPrimary) {
      $hasPrimary = true;
    }

    if (count($normalizedAccounts) >= 12) {
      break;
    }
  }

  if (!empty($invalidAccounts)) {
    $respond([
      'success' => false,
      'message' => 'Some linked account URLs are invalid. Please fix them and retry.',
      'invalid_accounts' => $invalidAccounts,
    ], 422);
  }

  try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM linked_accounts WHERE user_id = :uid')->execute(['uid' => $currentUserId]);
    if (!empty($normalizedAccounts)) {
      $platformColumn = $tableHasColumn($db, 'linked_accounts', 'platform')
        ? 'platform'
        : ($tableHasColumn($db, 'linked_accounts', 'provider') ? 'provider' : null);
      $profileUrlColumn = $tableHasColumn($db, 'linked_accounts', 'profile_url')
        ? 'profile_url'
        : ($tableHasColumn($db, 'linked_accounts', 'url') ? 'url' : null);

      if ($platformColumn === null || $profileUrlColumn === null) {
        throw new RuntimeException('linked_accounts table is missing required URL/platform columns.');
      }

      $accountLabelColumn = $tableHasColumn($db, 'linked_accounts', 'account_label') ? 'account_label' : null;
      $usernameColumn = $tableHasColumn($db, 'linked_accounts', 'username')
        ? 'username'
        : ($tableHasColumn($db, 'linked_accounts', 'provider_username') ? 'provider_username' : null);
      $hasProviderUsernameColumn = $tableHasColumn($db, 'linked_accounts', 'provider_username') && $usernameColumn !== 'provider_username';
      $hasUrlColumn = $tableHasColumn($db, 'linked_accounts', 'url') && $profileUrlColumn !== 'url';
      $hasProviderIdColumn = $tableHasColumn($db, 'linked_accounts', 'provider_id');
      $hasIsPrimaryColumn = $tableHasColumn($db, 'linked_accounts', 'is_primary');
      $hasIsPublicColumn = $tableHasColumn($db, 'linked_accounts', 'is_public');

      $insertColumns = ['user_id', $platformColumn, $profileUrlColumn];
      if ($accountLabelColumn !== null) {
        $insertColumns[] = $accountLabelColumn;
      }
      if ($usernameColumn !== null) {
        $insertColumns[] = $usernameColumn;
      }
      if ($hasProviderUsernameColumn) {
        $insertColumns[] = 'provider_username';
      }
      if ($hasUrlColumn) {
        $insertColumns[] = 'url';
      }
      if ($hasProviderIdColumn) {
        $insertColumns[] = 'provider_id';
      }
      if ($hasIsPrimaryColumn) {
        $insertColumns[] = 'is_primary';
      }
      if ($hasIsPublicColumn) {
        $insertColumns[] = 'is_public';
      }
      if ($hasVerifiedColumn) {
        $insertColumns[] = 'verified';
      }
      if ($hasVerificationCheckedAtColumn) {
        $insertColumns[] = 'verification_checked_at';
      }

      $insertPlaceholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
      $ins = $db->prepare(
        'INSERT INTO linked_accounts (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')'
      );

      foreach ($normalizedAccounts as $account) {
        $params = ['user_id' => $currentUserId];
        $params[$platformColumn] = (string) ($account['platform'] ?? '');
        $params[$profileUrlColumn] = (string) ($account['profile_url'] ?? '');

        if ($accountLabelColumn !== null) {
          $params[$accountLabelColumn] = $account['account_label'];
        }
        if ($usernameColumn !== null) {
          $params[$usernameColumn] = $account['username'];
        }
        if ($hasProviderUsernameColumn) {
          $params['provider_username'] = $account['username'];
        }
        if ($hasUrlColumn) {
          $params['url'] = (string) ($account['profile_url'] ?? '');
        }
        if ($hasProviderIdColumn) {
          $params['provider_id'] = substr(hash('sha256', (string) ($account['profile_url'] ?? '')), 0, 64);
        }
        if ($hasIsPrimaryColumn) {
          $params['is_primary'] = (int) $account['is_primary'];
        }
        if ($hasIsPublicColumn) {
          $params['is_public'] = (int) $account['is_public'];
        }

        if ($hasVerifiedColumn) {
          $params['verified'] = (int) ($account['verified'] ?? 0);
        }
        if ($hasVerificationCheckedAtColumn) {
          $params['verification_checked_at'] = (string) ($account['verification_checked_at'] ?? date('Y-m-d H:i:s'));
        }

        $ins->execute($params);
      }
    }
    $db->commit();
  } catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    $respond(['success' => false, 'message' => 'Could not save linked accounts.'], 500);
  }

  $savedAccountsByUser = $fetchLinkedAccountsByUser($db, [$currentUserId]);
  $savedAccounts = $savedAccountsByUser[$currentUserId] ?? [];
  $verifiedCount = count(array_filter($savedAccounts, static fn ($item): bool => (int) ($item['verified'] ?? 0) === 1));

  $respond([
    'success' => true,
    'accounts_saved' => count($normalizedAccounts),
    'verified_count' => $verifiedCount,
    'linked_accounts' => $savedAccounts,
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_create_story') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForStories = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForStories['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForStories = $userController->getUserById($currentUserId);
  if (!$existingForStories) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForStories->getIsBlocked() ?? 0) === 1) {
    $respond(['success' => false, 'message' => 'Account is blocked. Story publishing is disabled until admin review.'], 423);
  }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $storyType = strtolower(trim((string) ($jsonInput['story_type'] ?? 'image')));
  if (!in_array($storyType, ['image', 'video', 'text'], true)) {
    $storyType = 'image';
  }
  $mediaUrl = trim((string) ($jsonInput['media_url'] ?? ''));
  $metadata = null;
  if (isset($jsonInput['metadata'])) {
    $metadata = is_string($jsonInput['metadata']) ? $jsonInput['metadata'] : json_encode($jsonInput['metadata']);
  }
  $caption = trim((string) ($jsonInput['caption'] ?? ''));
  if (($storyType === 'image' || $storyType === 'video') && $mediaUrl === '') {
    $respond(['success' => false, 'message' => 'Media URL is required for image/video stories.'], 400);
  }
  if ($storyType === 'text' && $caption === '' && $mediaUrl === '') {
    $respond(['success' => false, 'message' => 'Add text content for text stories.'], 400);
  }

  $payload = [
    'story_type' => $storyType,
    'media_url' => $mediaUrl !== '' ? $mediaUrl : null,
    'caption' => $caption !== '' ? $caption : null,
    'music_url' => trim((string) ($jsonInput['music_url'] ?? '')),
    'music_title' => trim((string) ($jsonInput['music_title'] ?? '')),
    'drawing_data' => $jsonInput['drawing_data'] ?? null,
    'text_layers' => $jsonInput['text_layers'] ?? null,
    'sticker_layers' => $jsonInput['sticker_layers'] ?? null,
    'filter_css' => trim((string) ($jsonInput['filter_css'] ?? '')),
    'gradient_bg' => trim((string) ($jsonInput['gradient_bg'] ?? '')),
    'duration' => (int) ($jsonInput['duration'] ?? 5),
    'visibility' => trim((string) ($jsonInput['visibility'] ?? 'public')),
    'location_label' => trim((string) ($jsonInput['location_label'] ?? '')),
    'expires_at' => trim((string) ($jsonInput['expires_at'] ?? '')),
  ];

  $storyId = $userController->createStory($currentUserId, $payload);
  if (!$storyId) {
    $respond(['success' => false, 'message' => 'Could not publish story.'], 500);
  }

  $respond(['success' => true, 'story_id' => (int) $storyId]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_story_view') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForStoryView = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForStoryView['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $storyId = (int) ($jsonInput['story_id'] ?? 0);
  if ($storyId <= 0) {
    $respond(['success' => false, 'message' => 'Invalid story id.'], 400);
  }

  try {
    $ownerQuery = $db->prepare('SELECT user_id FROM stories WHERE id = :id LIMIT 1');
    $ownerQuery->execute(['id' => $storyId]);
    $ownerRow = $ownerQuery->fetch();
    if (!$ownerRow) {
      $respond(['success' => false, 'message' => 'Story not found.'], 404);
    }
    if ((int) ($ownerRow['user_id'] ?? 0) !== $currentUserId) {
      $userController->recordStoryView($storyId, $currentUserId);
    }
  } catch (Exception $e) {
    $respond(['success' => false, 'message' => 'Could not record story view.'], 500);
  }

  $respond(['success' => true]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_delete_story') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForStoryDelete = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForStoryDelete['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $storyId = (int) ($jsonInput['story_id'] ?? 0);
  if ($storyId <= 0) {
    $respond(['success' => false, 'message' => 'Invalid story id.'], 400);
  }

  if (!$userController->deleteStory($storyId, $currentUserId)) {
    $respond(['success' => false, 'message' => 'Could not delete story.'], 500);
  }

  $respond(['success' => true]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_get_stories') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };

  $sessionUserForStoriesList = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForStoriesList['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $targetUserId = (int) ($_GET['user_id'] ?? $currentUserId);
  if ($targetUserId <= 0) {
    $targetUserId = $currentUserId;
  }

  $storyExtraColumns = [];
  foreach (['music_url', 'music_title', 'drawing_data', 'text_layers', 'sticker_layers', 'filter_css', 'gradient_bg', 'duration', 'visibility'] as $storyColumn) {
    if ($tableHasColumn($db, 'stories', $storyColumn)) {
      $storyExtraColumns[] = 's.' . $storyColumn;
    }
  }
  $storyExtraSelect = !empty($storyExtraColumns) ? ', ' . implode(', ', $storyExtraColumns) : '';
  $storyExtraGroupBy = !empty($storyExtraColumns) ? ', ' . implode(', ', $storyExtraColumns) : '';

  $stories = [];
  try {
    $q = $db->prepare(
      'SELECT s.id, s.user_id, s.story_type, s.media_url, s.caption, s.location_label, s.expires_at, s.is_archived, s.created_at' . $storyExtraSelect . ',
              u.first_name, u.last_name, u.avatar_url, u.role,
              COUNT(sv.id) AS views_count
       FROM stories s
       INNER JOIN users u ON u.id = s.user_id
       LEFT JOIN story_views sv ON sv.story_id = s.id
       WHERE s.user_id = :uid
         AND s.is_archived = 0
         AND (s.expires_at IS NULL OR s.expires_at > NOW())
       GROUP BY s.id, s.user_id, s.story_type, s.media_url, s.caption, s.location_label, s.expires_at, s.is_archived, s.created_at' . $storyExtraGroupBy . ',
                u.first_name, u.last_name, u.avatar_url, u.role
       ORDER BY s.created_at DESC
       LIMIT 80'
    );
    $q->execute(['uid' => $targetUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $stories[] = [
        'id' => (int) ($row['id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'story_type' => (string) ($row['story_type'] ?? 'image'),
        'media_url' => (string) ($row['media_url'] ?? ''),
        'caption' => (string) ($row['caption'] ?? ''),
        'location_label' => (string) ($row['location_label'] ?? ''),
        'expires_at' => (string) ($row['expires_at'] ?? ''),
        'is_archived' => (int) ($row['is_archived'] ?? 0),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'views_count' => (int) ($row['views_count'] ?? 0),
        'music_url' => (string) ($row['music_url'] ?? ''),
        'music_title' => (string) ($row['music_title'] ?? ''),
        'drawing_data' => (string) ($row['drawing_data'] ?? ''),
        'text_layers' => isset($row['text_layers']) && $row['text_layers'] !== null ? json_decode((string) $row['text_layers'], true) : [],
        'sticker_layers' => isset($row['sticker_layers']) && $row['sticker_layers'] !== null ? json_decode((string) $row['sticker_layers'], true) : [],
        'filter_css' => (string) ($row['filter_css'] ?? ''),
        'gradient_bg' => (string) ($row['gradient_bg'] ?? ''),
        'duration' => (int) ($row['duration'] ?? 5),
        'visibility' => (string) ($row['visibility'] ?? 'public'),
        'user' => [
          'id' => (int) ($row['user_id'] ?? 0),
          'first_name' => (string) ($row['first_name'] ?? ''),
          'last_name' => (string) ($row['last_name'] ?? ''),
          'avatar_url' => (string) ($row['avatar_url'] ?? ''),
          'role' => (string) ($row['role'] ?? 'user'),
        ],
      ];
    }
  } catch (Exception $e) {
    $respond(['success' => false, 'message' => 'Could not fetch stories.'], 500);
  }

  $respond(['success' => true, 'stories' => $stories]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_live_stream') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };

  $ensureLiveStreamingSchema($db);

  $sessionUserForLive = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForLive['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  try {
    $db->exec(
      'UPDATE live_stream_viewers
       SET left_at = NOW(), updated_at = NOW()
       WHERE left_at IS NULL
         AND last_seen_at < (NOW() - INTERVAL 120 SECOND)'
    );
    $db->exec(
      'UPDATE live_streams
       SET status = "ended", ended_at = IFNULL(ended_at, NOW()), updated_at = NOW()
       WHERE status = "live"
         AND heartbeat_at < (NOW() - INTERVAL 180 SECOND)'
    );
  } catch (Exception $e) {
  }

  $payload = [];
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $payload = $_GET;
  } else {
    $jsonInput = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($jsonInput)) {
      $payload = $jsonInput;
    }
  }

  $mode = strtolower(trim((string) ($payload['mode'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'list' : 'start'))));

  $normalizeSignalPayload = static function ($value): string {
    if ($value === null || $value === '') {
      return '{}';
    }
    if (is_string($value)) {
      $trimmed = trim($value);
      if ($trimmed === '') {
        return '{}';
      }
      json_decode($trimmed, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $trimmed;
      }
      return json_encode(['value' => $trimmed], JSON_UNESCAPED_UNICODE);
    }
    if (is_array($value) || is_object($value)) {
      $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
      return $encoded === false ? '{}' : $encoded;
    }
    return json_encode(['value' => $value], JSON_UNESCAPED_UNICODE);
  };

  $findLiveStream = static function (PDO $dbConn, int $streamId): ?array {
    if ($streamId <= 0) {
      return null;
    }
    try {
      $q = $dbConn->prepare(
        'SELECT ls.id, ls.host_user_id, ls.title, ls.description, ls.category, ls.visibility, ls.allow_recording,
                ls.cover_image_url, ls.status, ls.started_at, ls.ended_at, ls.heartbeat_at,
                u.first_name, u.last_name, u.avatar_url, u.role,
                COALESCE(vc.viewer_count, 0) AS viewer_count
         FROM live_streams ls
         INNER JOIN users u ON u.id = ls.host_user_id
         LEFT JOIN (
           SELECT stream_id, COUNT(*) AS viewer_count
           FROM live_stream_viewers
           WHERE left_at IS NULL
             AND last_seen_at >= (NOW() - INTERVAL 90 SECOND)
           GROUP BY stream_id
         ) vc ON vc.stream_id = ls.id
         WHERE ls.id = :id
         LIMIT 1'
      );
      $q->execute(['id' => $streamId]);
      $row = $q->fetch();
      return is_array($row) ? $row : null;
    } catch (Exception $e) {
      return null;
    }
  };

  $canAccessLiveStream = static function (PDO $dbConn, array $streamRow, int $uid): bool {
    if ($uid <= 0) {
      return false;
    }
    $hostId = (int) ($streamRow['host_user_id'] ?? 0);
    if ($hostId === $uid) {
      return true;
    }
    $visibility = strtolower((string) ($streamRow['visibility'] ?? 'public'));
    if ($visibility !== 'friends') {
      return true;
    }

    try {
      $q = $dbConn->prepare(
        'SELECT id
         FROM friends
         WHERE (user_one_id = :uid AND user_two_id = :hid)
            OR (user_two_id = :uid AND user_one_id = :hid)
         LIMIT 1'
      );
      $q->execute(['uid' => $uid, 'hid' => $hostId]);
      return (bool) $q->fetch();
    } catch (Exception $e) {
      return false;
    }
  };

  $isLiveParticipant = static function (PDO $dbConn, int $streamId, int $uid, int $hostId): bool {
    if ($uid <= 0 || $streamId <= 0) {
      return false;
    }
    if ($uid === $hostId) {
      return true;
    }
    try {
      $q = $dbConn->prepare(
        'SELECT id
         FROM live_stream_viewers
         WHERE stream_id = :sid
           AND user_id = :uid
           AND left_at IS NULL
           AND last_seen_at >= (NOW() - INTERVAL 120 SECOND)
         ORDER BY id DESC
         LIMIT 1'
      );
      $q->execute(['sid' => $streamId, 'uid' => $uid]);
      return (bool) $q->fetch();
    } catch (Exception $e) {
      return false;
    }
  };

  $refreshLiveViewerCount = static function (PDO $dbConn, int $streamId): int {
    if ($streamId <= 0) {
      return 0;
    }
    try {
      $dbConn->prepare(
        'UPDATE live_stream_viewers
         SET left_at = NOW(), updated_at = NOW()
         WHERE stream_id = :sid
           AND left_at IS NULL
           AND last_seen_at < (NOW() - INTERVAL 120 SECOND)'
      )->execute(['sid' => $streamId]);

      $q = $dbConn->prepare(
        'SELECT COUNT(*) AS c
         FROM live_stream_viewers
         WHERE stream_id = :sid
           AND left_at IS NULL
           AND last_seen_at >= (NOW() - INTERVAL 90 SECOND)'
      );
      $q->execute(['sid' => $streamId]);
      $count = (int) (($q->fetch()['c'] ?? 0));

      $dbConn->prepare('UPDATE live_streams SET viewer_count = :count, updated_at = NOW() WHERE id = :sid')
        ->execute(['count' => $count, 'sid' => $streamId]);
      return $count;
    } catch (Exception $e) {
      return 0;
    }
  };

  $streamRowToPayload = static function (array $row): array {
    return [
      'id' => (int) ($row['id'] ?? 0),
      'host_user_id' => (int) ($row['host_user_id'] ?? 0),
      'title' => (string) ($row['title'] ?? ''),
      'description' => (string) ($row['description'] ?? ''),
      'category' => (string) ($row['category'] ?? ''),
      'visibility' => (string) ($row['visibility'] ?? 'public'),
      'allow_recording' => (int) ($row['allow_recording'] ?? 0),
      'cover_image_url' => (string) ($row['cover_image_url'] ?? ''),
      'status' => (string) ($row['status'] ?? 'live'),
      'started_at' => (string) ($row['started_at'] ?? ''),
      'ended_at' => (string) ($row['ended_at'] ?? ''),
      'heartbeat_at' => (string) ($row['heartbeat_at'] ?? ''),
      'viewer_count' => (int) ($row['viewer_count'] ?? 0),
      'host' => [
        'id' => (int) ($row['host_user_id'] ?? 0),
        'first_name' => (string) ($row['first_name'] ?? ''),
        'last_name' => (string) ($row['last_name'] ?? ''),
        'avatar_url' => (string) ($row['avatar_url'] ?? ''),
        'role' => (string) ($row['role'] ?? 'user'),
      ],
    ];
  };

  if ($mode === 'list') {
    $streams = $fetchAccessibleLiveStreams($db, $currentUserId, 120);
    $respond(['success' => true, 'streams' => $streams]);
  }

  if ($mode === 'current') {
    $streams = $fetchAccessibleLiveStreams($db, $currentUserId, 40);
    $mine = null;
    foreach ($streams as $streamEntry) {
      if ((int) ($streamEntry['host_user_id'] ?? 0) === $currentUserId) {
        $mine = $streamEntry;
        break;
      }
    }
    $respond(['success' => true, 'stream' => $mine]);
  }

  if ($mode === 'get') {
    $streamId = (int) ($payload['stream_id'] ?? 0);
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }
    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow) {
      $respond(['success' => false, 'message' => 'Stream not found.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }
    $refreshLiveViewerCount($db, $streamId);
    $fresh = $findLiveStream($db, $streamId) ?: $streamRow;
    $respond(['success' => true, 'stream' => $streamRowToPayload($fresh)]);
  }

  if ($mode === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $existingForLiveStart = $userController->getUserById($currentUserId);
    if (!$existingForLiveStart) {
      $respond(['success' => false, 'message' => 'User not found.'], 404);
    }
    if ((int) ($existingForLiveStart->getIsBlocked() ?? 0) === 1) {
      $respond(['success' => false, 'message' => 'Account is blocked. Live streaming is disabled until admin review.'], 423);
    }

    $title = trim((string) ($payload['title'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $category = trim((string) ($payload['category'] ?? 'General'));
    $visibility = strtolower(trim((string) ($payload['visibility'] ?? 'public')));
    $allowRecording = (int) ((int) ($payload['allow_recording'] ?? 0) === 1 ? 1 : 0);
    $coverImageUrl = trim((string) ($payload['cover_image_url'] ?? ''));

    if ($visibility !== 'friends') {
      $visibility = 'public';
    }
    if ($title === '') {
      $fallbackName = trim((string) ($existingForLiveStart->getFirstName() ?? '') . ' ' . (string) ($existingForLiveStart->getLastName() ?? ''));
      $title = ($fallbackName !== '' ? $fallbackName : 'Member') . "'s live stream";
    }

    try {
      $db->beginTransaction();

      $db->prepare(
        'UPDATE live_streams
         SET status = "ended", ended_at = NOW(), updated_at = NOW()
         WHERE host_user_id = :uid
           AND status = "live"'
      )->execute(['uid' => $currentUserId]);

      $db->prepare(
        'UPDATE live_stream_viewers
         SET left_at = NOW(), updated_at = NOW()
         WHERE user_id = :uid
           AND left_at IS NULL'
      )->execute(['uid' => $currentUserId]);

      $ins = $db->prepare(
        'INSERT INTO live_streams
          (host_user_id, title, description, category, visibility, allow_recording, cover_image_url, status, started_at, heartbeat_at)
         VALUES
          (:host_user_id, :title, :description, :category, :visibility, :allow_recording, :cover_image_url, "live", NOW(), NOW())'
      );
      $ins->execute([
        'host_user_id' => $currentUserId,
        'title' => substr($title, 0, 180),
        'description' => $description !== '' ? substr($description, 0, 500) : null,
        'category' => $category !== '' ? substr($category, 0, 80) : null,
        'visibility' => $visibility,
        'allow_recording' => $allowRecording,
        'cover_image_url' => $coverImageUrl !== '' ? substr($coverImageUrl, 0, 1024) : null,
      ]);
      $newStreamId = (int) $db->lastInsertId();

      $db->prepare(
        'INSERT INTO live_stream_viewers (stream_id, user_id, joined_at, last_seen_at, left_at)
         VALUES (:sid, :uid, NOW(), NOW(), NULL)'
      )->execute(['sid' => $newStreamId, 'uid' => $currentUserId]);

      $db->commit();

      $refreshLiveViewerCount($db, $newStreamId);
      $streamRow = $findLiveStream($db, $newStreamId);
      if (!$streamRow) {
        $respond(['success' => false, 'message' => 'Could not start stream.'], 500);
      }
      $respond(['success' => true, 'stream' => $streamRowToPayload($streamRow)]);
    } catch (Exception $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      $respond(['success' => false, 'message' => 'Could not start stream.'], 500);
    }
  }

  if ($mode === 'end') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $streamId = (int) ($payload['stream_id'] ?? 0);
    $streamRow = null;
    if ($streamId > 0) {
      $streamRow = $findLiveStream($db, $streamId);
    } else {
      try {
        $q = $db->prepare(
          'SELECT id
           FROM live_streams
           WHERE host_user_id = :uid
             AND status = "live"
           ORDER BY started_at DESC
           LIMIT 1'
        );
        $q->execute(['uid' => $currentUserId]);
        $candidateId = (int) (($q->fetch()['id'] ?? 0));
        if ($candidateId > 0) {
          $streamRow = $findLiveStream($db, $candidateId);
        }
      } catch (Exception $e) {
      }
    }

    if (!$streamRow) {
      $respond(['success' => false, 'message' => 'Stream not found.'], 404);
    }
    if ((int) ($streamRow['host_user_id'] ?? 0) !== $currentUserId) {
      $respond(['success' => false, 'message' => 'Only the host can end this stream.'], 403);
    }

    $targetStreamId = (int) ($streamRow['id'] ?? 0);
    try {
      $db->beginTransaction();

      $db->prepare(
        'UPDATE live_streams
         SET status = "ended", ended_at = NOW(), updated_at = NOW()
         WHERE id = :sid'
      )->execute(['sid' => $targetStreamId]);

      $db->prepare(
        'UPDATE live_stream_viewers
         SET left_at = NOW(), updated_at = NOW()
         WHERE stream_id = :sid
           AND left_at IS NULL'
      )->execute(['sid' => $targetStreamId]);

      $db->commit();
    } catch (Exception $e) {
      if ($db->inTransaction()) {
        $db->rollBack();
      }
      $respond(['success' => false, 'message' => 'Could not end stream.'], 500);
    }

    $respond(['success' => true, 'stream_id' => $targetStreamId, 'status' => 'ended']);
  }

  if ($mode === 'join') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $streamId = (int) ($payload['stream_id'] ?? 0);
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }

    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow || strtolower((string) ($streamRow['status'] ?? '')) !== 'live') {
      $respond(['success' => false, 'message' => 'Stream is not active.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    try {
      if ((int) ($streamRow['host_user_id'] ?? 0) !== $currentUserId) {
        $q = $db->prepare(
          'SELECT id
           FROM live_stream_viewers
           WHERE stream_id = :sid
             AND user_id = :uid
             AND left_at IS NULL
           ORDER BY id DESC
           LIMIT 1'
        );
        $q->execute(['sid' => $streamId, 'uid' => $currentUserId]);
        $existing = $q->fetch();

        if ($existing) {
          $db->prepare(
            'UPDATE live_stream_viewers
             SET last_seen_at = NOW(), updated_at = NOW()
             WHERE id = :id'
          )->execute(['id' => (int) ($existing['id'] ?? 0)]);
        } else {
          $db->prepare(
            'INSERT INTO live_stream_viewers (stream_id, user_id, joined_at, last_seen_at, left_at)
             VALUES (:sid, :uid, NOW(), NOW(), NULL)'
          )->execute(['sid' => $streamId, 'uid' => $currentUserId]);
        }
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not join stream.'], 500);
    }

    $refreshLiveViewerCount($db, $streamId);
    $freshRow = $findLiveStream($db, $streamId) ?: $streamRow;
    $respond(['success' => true, 'stream' => $streamRowToPayload($freshRow)]);
  }

  if ($mode === 'leave') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $streamId = (int) ($payload['stream_id'] ?? 0);
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }

    try {
      $db->prepare(
        'UPDATE live_stream_viewers
         SET left_at = NOW(), updated_at = NOW()
         WHERE stream_id = :sid
           AND user_id = :uid
           AND left_at IS NULL'
      )->execute(['sid' => $streamId, 'uid' => $currentUserId]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not leave stream.'], 500);
    }

    $viewerCount = $refreshLiveViewerCount($db, $streamId);
    $respond(['success' => true, 'stream_id' => $streamId, 'viewer_count' => $viewerCount]);
  }

  if ($mode === 'heartbeat') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $streamId = (int) ($payload['stream_id'] ?? 0);
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }

    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow || strtolower((string) ($streamRow['status'] ?? '')) !== 'live') {
      $respond(['success' => false, 'message' => 'Stream is not active.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    try {
      $hostId = (int) ($streamRow['host_user_id'] ?? 0);
      if ($hostId === $currentUserId) {
        $db->prepare('UPDATE live_streams SET heartbeat_at = NOW(), updated_at = NOW() WHERE id = :sid')
          ->execute(['sid' => $streamId]);
      } else {
        $q = $db->prepare(
          'SELECT id
           FROM live_stream_viewers
           WHERE stream_id = :sid
             AND user_id = :uid
             AND left_at IS NULL
           ORDER BY id DESC
           LIMIT 1'
        );
        $q->execute(['sid' => $streamId, 'uid' => $currentUserId]);
        $activeViewerRow = $q->fetch();
        if ($activeViewerRow) {
          $db->prepare('UPDATE live_stream_viewers SET last_seen_at = NOW(), updated_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) ($activeViewerRow['id'] ?? 0)]);
        } else {
          $db->prepare(
            'INSERT INTO live_stream_viewers (stream_id, user_id, joined_at, last_seen_at, left_at)
             VALUES (:sid, :uid, NOW(), NOW(), NULL)'
          )->execute(['sid' => $streamId, 'uid' => $currentUserId]);
        }
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not keep stream alive.'], 500);
    }

    $refreshLiveViewerCount($db, $streamId);
    $freshRow = $findLiveStream($db, $streamId) ?: $streamRow;
    $respond([
      'success' => true,
      'stream' => $streamRowToPayload($freshRow),
      'server_time' => date('Y-m-d H:i:s'),
    ]);
  }

  if ($mode === 'signal') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $streamId = (int) ($payload['stream_id'] ?? 0);
    $targetUserId = (int) ($payload['target_user_id'] ?? 0);
    $signalType = strtolower(trim((string) ($payload['signal_type'] ?? 'candidate')));
    $signalPayload = $payload['payload'] ?? null;
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }
    if (!in_array($signalType, ['offer', 'answer', 'candidate', 'bye', 'ping'], true)) {
      $signalType = 'candidate';
    }

    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow || strtolower((string) ($streamRow['status'] ?? '')) !== 'live') {
      $respond(['success' => false, 'message' => 'Stream is not active.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }
    if (!$isLiveParticipant($db, $streamId, $currentUserId, (int) ($streamRow['host_user_id'] ?? 0))) {
      $respond(['success' => false, 'message' => 'Join the stream before sending signaling data.'], 403);
    }

    try {
      $ins = $db->prepare(
        'INSERT INTO live_stream_signals (stream_id, sender_id, target_user_id, signal_type, payload)
         VALUES (:stream_id, :sender_id, :target_user_id, :signal_type, :payload)'
      );
      $ins->execute([
        'stream_id' => $streamId,
        'sender_id' => $currentUserId,
        'target_user_id' => $targetUserId > 0 ? $targetUserId : null,
        'signal_type' => $signalType,
        'payload' => $normalizeSignalPayload($signalPayload),
      ]);

      $respond([
        'success' => true,
        'signal_id' => (int) $db->lastInsertId(),
        'stream_id' => $streamId,
      ]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not relay signaling data.'], 500);
    }
  }

  if ($mode === 'signals') {
    $streamId = (int) ($payload['stream_id'] ?? 0);
    $lastSignalId = max(0, (int) ($payload['last_signal_id'] ?? 0));
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }

    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow || strtolower((string) ($streamRow['status'] ?? '')) !== 'live') {
      $respond(['success' => false, 'message' => 'Stream is not active.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }
    if (!$isLiveParticipant($db, $streamId, $currentUserId, (int) ($streamRow['host_user_id'] ?? 0))) {
      $respond(['success' => false, 'message' => 'Join the stream before polling signals.'], 403);
    }

    $signals = [];
    try {
      $q = $db->prepare(
        'SELECT id, stream_id, sender_id, target_user_id, signal_type, payload, created_at
         FROM live_stream_signals
         WHERE stream_id = :stream_id
           AND id > :last_signal_id
           AND sender_id <> :uid
           AND (target_user_id IS NULL OR target_user_id = :uid)
         ORDER BY id ASC
         LIMIT 200'
      );
      $q->execute([
        'stream_id' => $streamId,
        'last_signal_id' => $lastSignalId,
        'uid' => $currentUserId,
      ]);
      foreach ((array) $q->fetchAll() as $row) {
        $decodedPayload = null;
        if (isset($row['payload']) && $row['payload'] !== null && $row['payload'] !== '') {
          $decoded = json_decode((string) $row['payload'], true);
          $decodedPayload = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
        }
        $signals[] = [
          'id' => (int) ($row['id'] ?? 0),
          'stream_id' => (int) ($row['stream_id'] ?? 0),
          'sender_id' => (int) ($row['sender_id'] ?? 0),
          'target_user_id' => (int) ($row['target_user_id'] ?? 0),
          'signal_type' => (string) ($row['signal_type'] ?? 'candidate'),
          'payload' => $decodedPayload,
          'created_at' => (string) ($row['created_at'] ?? ''),
        ];
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not fetch stream signals.'], 500);
    }

    $respond(['success' => true, 'signals' => $signals]);
  }

  if ($mode === 'chat_send') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $streamId = (int) ($payload['stream_id'] ?? 0);
    $body = trim((string) ($payload['body'] ?? ''));
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }
    if ($body === '') {
      $respond(['success' => false, 'message' => 'Message body is required.'], 400);
    }

    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow || strtolower((string) ($streamRow['status'] ?? '')) !== 'live') {
      $respond(['success' => false, 'message' => 'Stream is not active.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    if (!$isLiveParticipant($db, $streamId, $currentUserId, (int) ($streamRow['host_user_id'] ?? 0))) {
      try {
        $db->prepare(
          'INSERT INTO live_stream_viewers (stream_id, user_id, joined_at, last_seen_at, left_at)
           VALUES (:sid, :uid, NOW(), NOW(), NULL)'
        )->execute(['sid' => $streamId, 'uid' => $currentUserId]);
      } catch (Exception $e) {
      }
    }

    try {
      $ins = $db->prepare(
        'INSERT INTO live_stream_chat (stream_id, user_id, body, is_deleted)
         VALUES (:stream_id, :user_id, :body, 0)'
      );
      $ins->execute([
        'stream_id' => $streamId,
        'user_id' => $currentUserId,
        'body' => substr($body, 0, 2000),
      ]);
      $chatId = (int) $db->lastInsertId();

      $q = $db->prepare(
        'SELECT c.id, c.stream_id, c.user_id, c.body, c.created_at,
                u.first_name, u.last_name, u.avatar_url, u.role
         FROM live_stream_chat c
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.id = :id
         LIMIT 1'
      );
      $q->execute(['id' => $chatId]);
      $row = $q->fetch();

      $respond([
        'success' => true,
        'message' => [
          'id' => (int) ($row['id'] ?? $chatId),
          'stream_id' => (int) ($row['stream_id'] ?? $streamId),
          'user_id' => (int) ($row['user_id'] ?? $currentUserId),
          'body' => (string) ($row['body'] ?? $body),
          'created_at' => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
          'user' => [
            'id' => (int) ($row['user_id'] ?? $currentUserId),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'role' => (string) ($row['role'] ?? 'user'),
          ],
        ],
      ]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not send chat message.'], 500);
    }
  }

  if ($mode === 'chat_list') {
    $streamId = (int) ($payload['stream_id'] ?? 0);
    $afterId = max(0, (int) ($payload['after_id'] ?? 0));
    if ($streamId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid stream id.'], 400);
    }

    $streamRow = $findLiveStream($db, $streamId);
    if (!$streamRow) {
      $respond(['success' => false, 'message' => 'Stream not found.'], 404);
    }
    if (!$canAccessLiveStream($db, $streamRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    $messages = [];
    try {
      $q = $db->prepare(
        'SELECT c.id, c.stream_id, c.user_id, c.body, c.created_at,
                u.first_name, u.last_name, u.avatar_url, u.role
         FROM live_stream_chat c
         INNER JOIN users u ON u.id = c.user_id
         WHERE c.stream_id = :stream_id
           AND c.is_deleted = 0
           AND c.id > :after_id
         ORDER BY c.id ASC
         LIMIT 180'
      );
      $q->execute([
        'stream_id' => $streamId,
        'after_id' => $afterId,
      ]);
      foreach ((array) $q->fetchAll() as $row) {
        $messages[] = [
          'id' => (int) ($row['id'] ?? 0),
          'stream_id' => (int) ($row['stream_id'] ?? 0),
          'user_id' => (int) ($row['user_id'] ?? 0),
          'body' => (string) ($row['body'] ?? ''),
          'created_at' => (string) ($row['created_at'] ?? ''),
          'user' => [
            'id' => (int) ($row['user_id'] ?? 0),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'role' => (string) ($row['role'] ?? 'user'),
          ],
        ];
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not load chat messages.'], 500);
    }

    $respond(['success' => true, 'messages' => $messages]);
  }

  $respond(['success' => false, 'message' => 'Unsupported live stream mode.'], 400);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_social_data') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };

  $sessionUserForSocial = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForSocial['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $ensureLiveStreamingSchema($db);

  try {
    $db->exec('UPDATE stories SET is_archived = 1 WHERE is_archived = 0 AND expires_at IS NOT NULL AND expires_at <= NOW()');
  } catch (Exception $e) {
  }

  $incomingRequests = [];
  $outgoingRequests = [];
  $friendIds = [];

  try {
    $q = $db->prepare(
      'SELECT fr.id, fr.sender_id, fr.request_message, fr.created_at,
              u.first_name, u.last_name, u.avatar_url, u.role, u.country, u.exact_location
       FROM friend_requests fr
       INNER JOIN users u ON u.id = fr.sender_id
       WHERE fr.receiver_id = :uid AND fr.status = "pending"
       ORDER BY fr.created_at DESC
       LIMIT 60'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $incomingRequests[] = [
        'id' => (int) ($row['id'] ?? 0),
        'sender_id' => (int) ($row['sender_id'] ?? 0),
        'request_message' => (string) ($row['request_message'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'user' => [
          'id' => (int) ($row['sender_id'] ?? 0),
          'first_name' => (string) ($row['first_name'] ?? ''),
          'last_name' => (string) ($row['last_name'] ?? ''),
          'avatar_url' => (string) ($row['avatar_url'] ?? ''),
          'role' => (string) ($row['role'] ?? 'user'),
          'country' => (string) ($row['country'] ?? ''),
          'exact_location' => (string) ($row['exact_location'] ?? ''),
        ],
      ];
    }
  } catch (Exception $e) {
  }

  try {
    $q = $db->prepare(
      'SELECT fr.id, fr.receiver_id, fr.request_message, fr.created_at,
              u.first_name, u.last_name, u.avatar_url, u.role, u.country, u.exact_location
       FROM friend_requests fr
       INNER JOIN users u ON u.id = fr.receiver_id
       WHERE fr.sender_id = :uid AND fr.status = "pending"
       ORDER BY fr.created_at DESC
       LIMIT 60'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $outgoingRequests[] = [
        'id' => (int) ($row['id'] ?? 0),
        'receiver_id' => (int) ($row['receiver_id'] ?? 0),
        'request_message' => (string) ($row['request_message'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'user' => [
          'id' => (int) ($row['receiver_id'] ?? 0),
          'first_name' => (string) ($row['first_name'] ?? ''),
          'last_name' => (string) ($row['last_name'] ?? ''),
          'avatar_url' => (string) ($row['avatar_url'] ?? ''),
          'role' => (string) ($row['role'] ?? 'user'),
          'country' => (string) ($row['country'] ?? ''),
          'exact_location' => (string) ($row['exact_location'] ?? ''),
        ],
      ];
    }
  } catch (Exception $e) {
  }

  try {
    $q = $db->prepare(
      'SELECT CASE WHEN user_one_id = :uid THEN user_two_id ELSE user_one_id END AS friend_id
       FROM friends
       WHERE user_one_id = :uid OR user_two_id = :uid'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $fid = (int) ($row['friend_id'] ?? 0);
      if ($fid > 0) { $friendIds[] = $fid; }
    }
  } catch (Exception $e) {
  }

  $liveStreams = $fetchAccessibleLiveStreams($db, $currentUserId, 120);
  $liveByHost = [];
  foreach ($liveStreams as $streamEntry) {
    $hostId = (int) ($streamEntry['host_user_id'] ?? 0);
    if ($hostId <= 0 || isset($liveByHost[$hostId])) {
      continue;
    }
    $liveByHost[$hostId] = $streamEntry;
  }

  $userIdsForGraph = $collectSocialUserIds($db, $currentUserId);

  $profiles = [];
  if (!empty($userIdsForGraph)) {
    $placeholders = implode(',', array_fill(0, count($userIdsForGraph), '?'));
    try {
      $q = $db->prepare(
        "SELECT id, first_name, last_name, role, country, exact_location, phone, xp, bio, avatar_url
         FROM users
         WHERE id IN ({$placeholders})"
      );
      $q->execute($userIdsForGraph);
      foreach ((array) $q->fetchAll() as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid <= 0) { continue; }
        $profiles[$uid] = [
          'id' => $uid,
          'first_name' => (string) ($row['first_name'] ?? ''),
          'last_name' => (string) ($row['last_name'] ?? ''),
          'role' => (string) ($row['role'] ?? 'user'),
          'country' => (string) ($row['country'] ?? ''),
          'exact_location' => (string) ($row['exact_location'] ?? ''),
          'phone' => (string) ($row['phone'] ?? ''),
          'xp' => (int) ($row['xp'] ?? 0),
          'bio' => (string) ($row['bio'] ?? ''),
          'avatar_url' => (string) ($row['avatar_url'] ?? ''),
        ];
      }
    } catch (Exception $e) {
    }
  }

  $linkedAccountsByUser = $fetchLinkedAccountsByUser($db, array_keys($profiles));
  foreach ($profiles as $uid => &$profile) {
    $profile['linked_accounts'] = $linkedAccountsByUser[$uid] ?? [];
  }
  unset($profile);

  $privateConversations = [];
  try {
    $q = $db->prepare(
      'SELECT
          pc.id,
          pc.user_one_id,
          pc.user_two_id,
          pc.last_message_at,
          u.id AS peer_id,
          u.first_name AS peer_first_name,
          u.last_name AS peer_last_name,
          u.avatar_url AS peer_avatar_url,
          u.role AS peer_role,
          lm.id AS last_message_id,
          lm.body AS last_message_body,
          lm.message_type AS last_message_type,
          lm.created_at AS last_message_created_at,
          lm.sender_id AS last_message_sender_id,
          COALESCE(unread.unread_count, 0) AS unread_count,
          (
            SELECT MAX(mr_peer.read_at)
            FROM message_reads mr_peer
            WHERE lm.id IS NOT NULL
              AND mr_peer.message_id = lm.id
              AND mr_peer.user_id = u.id
          ) AS last_message_seen_at
       FROM private_conversations pc
       INNER JOIN users u ON u.id = IF(pc.user_one_id = :uid, pc.user_two_id, pc.user_one_id)
       LEFT JOIN messages lm ON lm.id = (
          SELECT m2.id
          FROM messages m2
          WHERE m2.private_conversation_id = pc.id
          ORDER BY m2.created_at DESC, m2.id DESC
          LIMIT 1
       )
       LEFT JOIN (
          SELECT m.private_conversation_id, COUNT(*) AS unread_count
          FROM messages m
          LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
          WHERE m.private_conversation_id IS NOT NULL
            AND m.sender_id <> :uid
            AND mr.id IS NULL
          GROUP BY m.private_conversation_id
       ) unread ON unread.private_conversation_id = pc.id
       WHERE pc.user_one_id = :uid OR pc.user_two_id = :uid
       ORDER BY COALESCE(pc.last_message_at, pc.updated_at, pc.created_at) DESC
       LIMIT 120'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $peerId = (int) ($row['peer_id'] ?? 0);
      $privateConversations[] = [
        'id' => (int) ($row['id'] ?? 0),
        'last_message_at' => (string) ($row['last_message_created_at'] ?? ($row['last_message_at'] ?? '')),
        'last_message_body' => (string) ($row['last_message_body'] ?? ''),
        'last_message_type' => (string) ($row['last_message_type'] ?? 'text'),
        'last_message_sender_id' => (int) ($row['last_message_sender_id'] ?? 0),
        'last_message_seen_at' => (string) ($row['last_message_seen_at'] ?? ''),
        'unread_count' => (int) ($row['unread_count'] ?? 0),
        'peer' => [
          'id' => $peerId,
          'first_name' => (string) ($row['peer_first_name'] ?? ''),
          'last_name' => (string) ($row['peer_last_name'] ?? ''),
          'avatar_url' => (string) ($row['peer_avatar_url'] ?? ''),
          'role' => (string) ($row['peer_role'] ?? 'user'),
          'linked_accounts' => $linkedAccountsByUser[$peerId] ?? [],
          'is_live' => isset($liveByHost[$peerId]),
          'live_stream_id' => (int) ($liveByHost[$peerId]['id'] ?? 0),
          'live_title' => (string) ($liveByHost[$peerId]['title'] ?? ''),
          'live_viewer_count' => (int) ($liveByHost[$peerId]['viewer_count'] ?? 0),
        ],
      ];
    }
  } catch (Exception $e) {
  }

  if (!empty($groupChats)) {
    $groupIds = array_values(array_unique(array_filter(array_map(static fn ($g): int => (int) ($g['id'] ?? 0), $groupChats))));
    if (!empty($groupIds)) {
      try {
        $groupMembers = [];
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $q = $db->prepare(
          "SELECT gcm.group_chat_id, gcm.user_id, gcm.role,
                  u.first_name, u.last_name, u.avatar_url, u.role AS user_role
           FROM group_chat_members gcm
           INNER JOIN users u ON u.id = gcm.user_id
           WHERE gcm.group_chat_id IN ({$placeholders})
             AND gcm.left_at IS NULL
           ORDER BY gcm.group_chat_id ASC,
                    CASE gcm.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END ASC,
                    u.first_name ASC"
        );
        $q->execute($groupIds);
        foreach ((array) $q->fetchAll() as $row) {
          $gid = (int) ($row['group_chat_id'] ?? 0);
          if ($gid <= 0) {
            continue;
          }
          if (!isset($groupMembers[$gid])) {
            $groupMembers[$gid] = [];
          }
          $groupMembers[$gid][] = [
            'group_chat_id' => $gid,
            'user_id' => (int) ($row['user_id'] ?? 0),
            'role' => (string) ($row['role'] ?? 'member'),
            'user' => [
              'id' => (int) ($row['user_id'] ?? 0),
              'first_name' => (string) ($row['first_name'] ?? ''),
              'last_name' => (string) ($row['last_name'] ?? ''),
              'avatar_url' => (string) ($row['avatar_url'] ?? ''),
              'role' => (string) ($row['user_role'] ?? 'user'),
            ],
          ];
        }

        foreach ($groupChats as &$groupChat) {
          $gid = (int) ($groupChat['id'] ?? 0);
          $groupChat['members'] = $groupMembers[$gid] ?? [];
        }
        unset($groupChat);
      } catch (Exception $e) {
      }
    }
  }

  $groupChats = [];
  try {
    $q = $db->prepare(
      'SELECT
          gc.id,
          gc.name,
          gc.description,
          gc.avatar_url,
          gc.last_message_at,
          gcm.role AS member_role,
          lm.body AS last_message_body,
          lm.message_type AS last_message_type,
          lm.created_at AS last_message_created_at,
          lm.sender_id AS last_message_sender_id,
          COALESCE(unread.unread_count, 0) AS unread_count
       FROM group_chat_members gcm
       INNER JOIN group_chats gc ON gc.id = gcm.group_chat_id
       LEFT JOIN messages lm ON lm.id = (
          SELECT m2.id
          FROM messages m2
          WHERE m2.group_chat_id = gc.id
          ORDER BY m2.created_at DESC, m2.id DESC
          LIMIT 1
       )
       LEFT JOIN (
          SELECT m.group_chat_id, COUNT(*) AS unread_count
          FROM messages m
          INNER JOIN group_chat_members gm ON gm.group_chat_id = m.group_chat_id AND gm.user_id = :uid AND gm.left_at IS NULL
          LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
          WHERE m.group_chat_id IS NOT NULL
            AND m.sender_id <> :uid
            AND mr.id IS NULL
          GROUP BY m.group_chat_id
       ) unread ON unread.group_chat_id = gc.id
       WHERE gcm.user_id = :uid AND gcm.left_at IS NULL
       ORDER BY COALESCE(gc.last_message_at, gc.updated_at, gc.created_at) DESC
       LIMIT 120'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $groupChats[] = [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'avatar_url' => (string) ($row['avatar_url'] ?? ''),
        'member_role' => (string) ($row['member_role'] ?? 'member'),
        'last_message_at' => (string) ($row['last_message_created_at'] ?? ($row['last_message_at'] ?? '')),
        'last_message_body' => (string) ($row['last_message_body'] ?? ''),
        'last_message_type' => (string) ($row['last_message_type'] ?? 'text'),
        'last_message_sender_id' => (int) ($row['last_message_sender_id'] ?? 0),
        'unread_count' => (int) ($row['unread_count'] ?? 0),
        'members' => [],
      ];
    }
  } catch (Exception $e) {
  }

  $storiesActive = [];
  $storiesArchive = [];

  $storyScopeIds = array_values(array_unique(array_filter(
    array_map('intval', array_merge([$currentUserId], $friendIds, $userIdsForGraph)),
    static fn ($id): bool => $id > 0
  )));

  if (!empty($storyScopeIds)) {
    $storyExtraColumns = [];
    foreach (['music_url', 'music_title', 'drawing_data', 'text_layers', 'sticker_layers', 'filter_css', 'gradient_bg', 'duration', 'visibility'] as $storyColumn) {
      if ($tableHasColumn($db, 'stories', $storyColumn)) {
        $storyExtraColumns[] = 's.' . $storyColumn;
      }
    }
    $storyExtraSelect = !empty($storyExtraColumns) ? ', ' . implode(', ', $storyExtraColumns) : '';
    $storyExtraGroupBy = !empty($storyExtraColumns) ? ', ' . implode(', ', $storyExtraColumns) : '';

    try {
      $placeholders = implode(',', array_fill(0, count($storyScopeIds), '?'));
      $q = $db->prepare(
        "SELECT s.id, s.user_id, s.story_type, s.media_url, s.caption, s.location_label, s.expires_at, s.is_archived, s.created_at{$storyExtraSelect},
                u.first_name, u.last_name, u.avatar_url, u.role,
                COUNT(sv.id) AS views_count
         FROM stories s
         INNER JOIN users u ON u.id = s.user_id
         LEFT JOIN story_views sv ON sv.story_id = s.id
         WHERE s.user_id IN ({$placeholders})
           AND s.is_archived = 0
           AND (s.expires_at IS NULL OR s.expires_at > NOW())
         GROUP BY s.id, s.user_id, s.story_type, s.media_url, s.caption, s.location_label, s.expires_at, s.is_archived, s.created_at{$storyExtraGroupBy},
                  u.first_name, u.last_name, u.avatar_url, u.role
         ORDER BY s.created_at DESC
         LIMIT 240"
      );
      $q->execute($storyScopeIds);
      foreach ((array) $q->fetchAll() as $row) {
        $storiesActive[] = [
          'id' => (int) ($row['id'] ?? 0),
          'user_id' => (int) ($row['user_id'] ?? 0),
          'story_type' => (string) ($row['story_type'] ?? 'image'),
          'media_url' => (string) ($row['media_url'] ?? ''),
          'caption' => (string) ($row['caption'] ?? ''),
          'location_label' => (string) ($row['location_label'] ?? ''),
          'expires_at' => (string) ($row['expires_at'] ?? ''),
          'is_archived' => (int) ($row['is_archived'] ?? 0),
          'created_at' => (string) ($row['created_at'] ?? ''),
          'views_count' => (int) ($row['views_count'] ?? 0),
          'music_url' => (string) ($row['music_url'] ?? ''),
          'music_title' => (string) ($row['music_title'] ?? ''),
          'drawing_data' => (string) ($row['drawing_data'] ?? ''),
          'text_layers' => isset($row['text_layers']) && $row['text_layers'] !== null ? json_decode((string) $row['text_layers'], true) : [],
          'sticker_layers' => isset($row['sticker_layers']) && $row['sticker_layers'] !== null ? json_decode((string) $row['sticker_layers'], true) : [],
          'filter_css' => (string) ($row['filter_css'] ?? ''),
          'gradient_bg' => (string) ($row['gradient_bg'] ?? ''),
          'duration' => (int) ($row['duration'] ?? 5),
          'visibility' => (string) ($row['visibility'] ?? 'public'),
          'user' => [
            'id' => (int) ($row['user_id'] ?? 0),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'role' => (string) ($row['role'] ?? 'user'),
          ],
        ];
      }
    } catch (Exception $e) {
    }
  }

  try {
    $storyExtraColumns = [];
    foreach (['music_url', 'music_title', 'drawing_data', 'text_layers', 'sticker_layers', 'filter_css', 'gradient_bg', 'duration', 'visibility'] as $storyColumn) {
      if ($tableHasColumn($db, 'stories', $storyColumn)) {
        $storyExtraColumns[] = 's.' . $storyColumn;
      }
    }
    $storyExtraSelect = !empty($storyExtraColumns) ? ', ' . implode(', ', $storyExtraColumns) : '';
    $storyExtraGroupBy = !empty($storyExtraColumns) ? ', ' . implode(', ', $storyExtraColumns) : '';

    $q = $db->prepare(
      'SELECT s.id, s.user_id, s.story_type, s.media_url, s.caption, s.location_label, s.expires_at, s.is_archived, s.created_at' . $storyExtraSelect . ',
              COUNT(sv.id) AS views_count
       FROM stories s
       LEFT JOIN story_views sv ON sv.story_id = s.id
       WHERE s.user_id = :uid
         AND (s.is_archived = 1 OR (s.expires_at IS NOT NULL AND s.expires_at <= NOW()))
       GROUP BY s.id, s.user_id, s.story_type, s.media_url, s.caption, s.location_label, s.expires_at, s.is_archived, s.created_at' . $storyExtraGroupBy . '
       ORDER BY s.created_at DESC
       LIMIT 140'
    );
    $q->execute(['uid' => $currentUserId]);
    foreach ((array) $q->fetchAll() as $row) {
      $storiesArchive[] = [
        'id' => (int) ($row['id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'story_type' => (string) ($row['story_type'] ?? 'image'),
        'media_url' => (string) ($row['media_url'] ?? ''),
        'caption' => (string) ($row['caption'] ?? ''),
        'location_label' => (string) ($row['location_label'] ?? ''),
        'expires_at' => (string) ($row['expires_at'] ?? ''),
        'is_archived' => (int) ($row['is_archived'] ?? 1),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'views_count' => (int) ($row['views_count'] ?? 0),
        'music_url' => (string) ($row['music_url'] ?? ''),
        'music_title' => (string) ($row['music_title'] ?? ''),
        'drawing_data' => (string) ($row['drawing_data'] ?? ''),
        'text_layers' => isset($row['text_layers']) && $row['text_layers'] !== null ? json_decode((string) $row['text_layers'], true) : [],
        'sticker_layers' => isset($row['sticker_layers']) && $row['sticker_layers'] !== null ? json_decode((string) $row['sticker_layers'], true) : [],
        'filter_css' => (string) ($row['filter_css'] ?? ''),
        'gradient_bg' => (string) ($row['gradient_bg'] ?? ''),
        'duration' => (int) ($row['duration'] ?? 5),
        'visibility' => (string) ($row['visibility'] ?? 'public'),
      ];
    }
  } catch (Exception $e) {
  }

  $activeStoryUserLookup = [];
  foreach ($storiesActive as $storyEntry) {
    $storyUserId = (int) ($storyEntry['user_id'] ?? 0);
    if ($storyUserId > 0) {
      $activeStoryUserLookup[$storyUserId] = true;
    }
  }

  $friendsDetailed = [];
  foreach (array_values(array_unique(array_map('intval', $friendIds))) as $friendId) {
    if (!isset($profiles[$friendId])) { continue; }
    $friendProfile = $profiles[$friendId];
    $friendProfile['is_live'] = isset($liveByHost[$friendId]);
    $friendProfile['live_stream_id'] = (int) ($liveByHost[$friendId]['id'] ?? 0);
    $friendProfile['live_title'] = (string) ($liveByHost[$friendId]['title'] ?? '');
    $friendProfile['live_viewer_count'] = (int) ($liveByHost[$friendId]['viewer_count'] ?? 0);
    $friendsDetailed[] = $friendProfile;
  }

  $mapUsers = [];
  $hasLatitudeColumn = $tableHasColumn($db, 'users', 'latitude');
  $hasLongitudeColumn = $tableHasColumn($db, 'users', 'longitude');

  if ($hasLatitudeColumn && $hasLongitudeColumn) {
    try {
      $missingCoordsRows = [];
      $q = $db->query(
        'SELECT id, country, exact_location
         FROM users
         WHERE (latitude IS NULL OR longitude IS NULL)
           AND (
             (country IS NOT NULL AND TRIM(country) <> "")
             OR (exact_location IS NOT NULL AND TRIM(exact_location) <> "")
           )
         LIMIT 800'
      );
      if ($q) {
        $missingCoordsRows = (array) $q->fetchAll();
      }

      if (!empty($missingCoordsRows)) {
        $upd = $db->prepare('UPDATE users SET country = :country, latitude = :latitude, longitude = :longitude WHERE id = :id');
        foreach ($missingCoordsRows as $row) {
          $uid = (int) ($row['id'] ?? 0);
          if ($uid <= 0) { continue; }
          $countrySeed = trim((string) ($row['country'] ?? ''));
          if ($countrySeed === '') {
            $exact = trim((string) ($row['exact_location'] ?? ''));
            if ($exact !== '') {
              $parts = array_values(array_filter(array_map('trim', explode(',', $exact)), static fn ($p): bool => $p !== ''));
              $countrySeed = !empty($parts) ? (string) end($parts) : $exact;
            }
          }

          $geo = $deriveLocationCoordinates($countrySeed, null, null);
          if ($geo['latitude'] === null || $geo['longitude'] === null) { continue; }

          $upd->execute([
            'id' => $uid,
            'country' => (string) ($geo['country'] !== '' ? $geo['country'] : $countrySeed),
            'latitude' => (float) $geo['latitude'],
            'longitude' => (float) $geo['longitude'],
          ]);
        }
      }
    } catch (Exception $e) {
    }

    try {
      $q = $db->prepare(
        'SELECT id, first_name, last_name, role, country, exact_location, phone, xp, bio, avatar_url, latitude, longitude
         FROM users
         WHERE latitude IS NOT NULL AND longitude IS NOT NULL
         ORDER BY CASE WHEN id = :uid THEN 0 ELSE 1 END, xp DESC, id ASC
         LIMIT 600'
      );
      $q->execute(['uid' => $currentUserId]);
      $mapRows = (array) $q->fetchAll();

      $mapUserIds = [];
      foreach ($mapRows as $row) {
        $uid = (int) ($row['id'] ?? 0);
        if ($uid <= 0) { continue; }
        $mapUserIds[] = $uid;
        $mapUsers[] = [
          'id' => $uid,
          'first_name' => (string) ($row['first_name'] ?? ''),
          'last_name' => (string) ($row['last_name'] ?? ''),
          'role' => (string) ($row['role'] ?? 'user'),
          'country' => (string) ($row['country'] ?? ''),
          'exact_location' => (string) ($row['exact_location'] ?? ''),
          'phone' => (string) ($row['phone'] ?? ''),
          'xp' => (int) ($row['xp'] ?? 0),
          'bio' => (string) ($row['bio'] ?? ''),
          'avatar_url' => (string) ($row['avatar_url'] ?? ''),
          'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
          'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
          'has_story' => isset($activeStoryUserLookup[$uid]),
          'is_live' => isset($liveByHost[$uid]),
          'live_stream_id' => (int) ($liveByHost[$uid]['id'] ?? 0),
          'live_viewer_count' => (int) ($liveByHost[$uid]['viewer_count'] ?? 0),
          'live_title' => (string) ($liveByHost[$uid]['title'] ?? ''),
          'linked_accounts' => [],
        ];
      }

      $mapLinkedAccounts = $fetchLinkedAccountsByUser($db, array_values(array_unique($mapUserIds)));
      foreach ($mapUsers as &$mapProfile) {
        $mapProfileId = (int) ($mapProfile['id'] ?? 0);
        $mapProfile['linked_accounts'] = $mapLinkedAccounts[$mapProfileId] ?? [];
      }
      unset($mapProfile);
    } catch (Exception $e) {
    }
  }

  $unreadTotal = 0;
  foreach ($privateConversations as $conv) {
    $unreadTotal += (int) ($conv['unread_count'] ?? 0);
  }
  foreach ($groupChats as $groupChat) {
    $unreadTotal += (int) ($groupChat['unread_count'] ?? 0);
  }

  $respond([
    'success' => true,
    'linked_accounts' => $linkedAccountsByUser[$currentUserId] ?? [],
    'friends' => $friendsDetailed,
    'incoming_requests' => $incomingRequests,
    'outgoing_requests' => $outgoingRequests,
    'private_conversations' => $privateConversations,
    'group_chats' => $groupChats,
    'stories' => [
      'active' => $storiesActive,
      'archive' => $storiesArchive,
    ],
    'live_streams' => $liveStreams,
    'map_users' => $mapUsers,
    'unread_total' => $unreadTotal,
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_export_summary') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };

  $sessionUserForExport = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForExport['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $summary = $userController->getProfileExportSummary($currentUserId);
  if (empty($summary)) {
    $respond(['success' => false, 'message' => 'Could not build export summary.'], 500);
  }

  $respond([
    'success' => true,
    'summary' => $summary,
    'templates' => [
      ['id' => 'modern', 'label' => 'Modern'],
      ['id' => 'minimal', 'label' => 'Minimal'],
      ['id' => 'dark', 'label' => 'Dark'],
    ],
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_friend_request') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForFriends = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForFriends['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForFriends = $userController->getUserById($currentUserId);
  if (!$existingForFriends) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForFriends->getIsBlocked() ?? 0) === 1) {
    $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423);
  }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $mode = strtolower(trim((string) ($jsonInput['mode'] ?? 'send')));
  if ($mode === 'send') {
    $targetUserId = (int) ($jsonInput['target_user_id'] ?? 0);
    $requestMessage = trim((string) ($jsonInput['request_message'] ?? ''));

    if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
      $respond(['success' => false, 'message' => 'Invalid target user.'], 400);
    }

    $targetUser = $userController->getUserById($targetUserId);
    if (!$targetUser) {
      $respond(['success' => false, 'message' => 'Target user not found.'], 404);
    }
    if ((int) ($targetUser->getIsBlocked() ?? 0) === 1) {
      $respond(['success' => false, 'message' => 'This account is currently unavailable.'], 423);
    }

    try {
      $checkFriend = $db->prepare(
        'SELECT id FROM friends
         WHERE user_one_id = :u1 AND user_two_id = :u2
         LIMIT 1'
      );
      $checkFriend->execute([
        'u1' => min($currentUserId, $targetUserId),
        'u2' => max($currentUserId, $targetUserId),
      ]);
      if ($checkFriend->fetch()) {
        $respond(['success' => false, 'message' => 'You are already connected with this user.'], 409);
      }
    } catch (Exception $e) {
    }

    try {
      $checkPending = $db->prepare(
        'SELECT sender_id, receiver_id
         FROM friend_requests
         WHERE status = "pending"
           AND ((sender_id = :sender_id AND receiver_id = :receiver_id)
             OR (sender_id = :receiver_id AND receiver_id = :sender_id))
         LIMIT 1'
      );
      $checkPending->execute([
        'sender_id' => $currentUserId,
        'receiver_id' => $targetUserId,
      ]);
      $pending = $checkPending->fetch();
      if ($pending) {
        $pendingSenderId = (int) ($pending['sender_id'] ?? 0);
        if ($pendingSenderId === $currentUserId) {
          $respond(['success' => false, 'message' => 'A friend request is already pending for this user.'], 409);
        }
        $respond(['success' => false, 'message' => 'This user has already sent you a request. Check incoming requests.'], 409);
      }
    } catch (Exception $e) {
    }

    try {
      $ins = $db->prepare(
        'INSERT INTO friend_requests (sender_id, receiver_id, request_message, status)
         VALUES (:sender_id, :receiver_id, :request_message, "pending")'
      );
      $ins->execute([
        'sender_id' => $currentUserId,
        'receiver_id' => $targetUserId,
        'request_message' => $requestMessage !== '' ? substr($requestMessage, 0, 255) : null,
      ]);
    } catch (Exception $e) {
      if ((string) $e->getCode() === '23000') {
        $respond(['success' => false, 'message' => 'A friend request is already pending for this user.'], 409);
      }
      $respond(['success' => false, 'message' => 'Could not send friend request.'], 500);
    }

    $respond(['success' => true, 'message' => 'Friend request sent.']);
  }

  $requestId = (int) ($jsonInput['request_id'] ?? 0);
  if ($requestId <= 0) {
    $respond(['success' => false, 'message' => 'Invalid request id.'], 400);
  }

  if ($mode === 'accept' || $mode === 'decline') {
    $targetStatus = $mode === 'accept' ? 'accepted' : 'declined';
    try {
      $upd = $db->prepare(
        'UPDATE friend_requests
         SET status = :status, responded_at = NOW(), updated_at = NOW()
         WHERE id = :id AND receiver_id = :uid AND status = "pending"'
      );
      $upd->execute(['status' => $targetStatus, 'id' => $requestId, 'uid' => $currentUserId]);
      if ((int) $upd->rowCount() <= 0) {
        $respond(['success' => false, 'message' => 'Request not found or already processed.'], 404);
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not process friend request.'], 500);
    }
    $respond(['success' => true, 'message' => $mode === 'accept' ? 'Friend request accepted.' : 'Friend request declined.']);
  }

  if ($mode === 'cancel') {
    try {
      $upd = $db->prepare(
        'UPDATE friend_requests
         SET status = "canceled", responded_at = NOW(), updated_at = NOW()
         WHERE id = :id AND sender_id = :uid AND status = "pending"'
      );
      $upd->execute(['id' => $requestId, 'uid' => $currentUserId]);
      if ((int) $upd->rowCount() <= 0) {
        $respond(['success' => false, 'message' => 'Request not found or already processed.'], 404);
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not cancel friend request.'], 500);
    }
    $respond(['success' => true, 'message' => 'Friend request canceled.']);
  }

  $respond(['success' => false, 'message' => 'Unsupported friend request mode.'], 400);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_create_group_chat') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForGroups = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForGroups['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForGroups = $userController->getUserById($currentUserId);
  if (!$existingForGroups) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForGroups->getIsBlocked() ?? 0) === 1) {
    $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423);
  }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $name = trim((string) ($jsonInput['name'] ?? ''));
  $description = trim((string) ($jsonInput['description'] ?? ''));
  $avatarData = (string) ($jsonInput['avatar_data'] ?? '');
  $avatarUrlInput = trim((string) ($jsonInput['avatar_url'] ?? ''));
  $membersRaw = $jsonInput['members'] ?? [];
  if (!is_array($membersRaw)) { $membersRaw = []; }

  if ($name === '') {
    $respond(['success' => false, 'message' => 'Group name is required.'], 400);
  }

  $memberIds = [];
  foreach ($membersRaw as $memberId) {
    $mid = (int) $memberId;
    if ($mid > 0 && $mid !== $currentUserId) {
      $memberIds[] = $mid;
    }
  }
  $memberIds = array_values(array_unique($memberIds));

  if (!empty($memberIds)) {
    try {
      $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
      $q = $db->prepare(
        "SELECT CASE WHEN user_one_id = ? THEN user_two_id ELSE user_one_id END AS friend_id
         FROM friends
         WHERE (user_one_id = ? OR user_two_id = ?)
           AND (CASE WHEN user_one_id = ? THEN user_two_id ELSE user_one_id END) IN ({$placeholders})"
      );
      $q->execute(array_merge([
        $currentUserId,
        $currentUserId,
        $currentUserId,
        $currentUserId,
      ], $memberIds));
      $allowedFriendIds = [];
      foreach ((array) $q->fetchAll() as $row) {
        $fid = (int) ($row['friend_id'] ?? 0);
        if ($fid > 0) {
          $allowedFriendIds[] = $fid;
        }
      }
      $memberIds = array_values(array_intersect($memberIds, array_unique($allowedFriendIds)));
    } catch (Exception $e) {
      // If validation query fails, keep safe behavior by creating owner-only group.
      $memberIds = [];
    }
  }

  $groupAvatarUrl = '';
  if ($avatarData !== '') {
    $groupAvatarUrl = (string) ($storeBase64ImageAsset($avatarData, 'uploads/group_avatars', 'group_avatar_') ?? '');
  } elseif ($avatarUrlInput !== '') {
    $groupAvatarUrl = substr($avatarUrlInput, 0, 1024);
  }

  $newGroupId = 0;
  try {
    $db->beginTransaction();
    $insGroup = $db->prepare(
      'INSERT INTO group_chats (name, description, avatar_url, created_by, is_private)
       VALUES (:name, :description, :avatar_url, :created_by, 0)'
    );
    $insGroup->execute([
      'name' => substr($name, 0, 120),
      'description' => $description !== '' ? substr($description, 0, 255) : null,
      'avatar_url' => $groupAvatarUrl !== '' ? $groupAvatarUrl : null,
      'created_by' => $currentUserId,
    ]);
    $newGroupId = (int) $db->lastInsertId();

    $insMember = $db->prepare(
      'INSERT IGNORE INTO group_chat_members (group_chat_id, user_id, role)
       VALUES (:group_chat_id, :user_id, :role)'
    );
    $insMember->execute(['group_chat_id' => $newGroupId, 'user_id' => $currentUserId, 'role' => 'owner']);
    foreach ($memberIds as $memberId) {
      $insMember->execute(['group_chat_id' => $newGroupId, 'user_id' => $memberId, 'role' => 'member']);
    }

    $insMessage = $db->prepare(
      'INSERT INTO messages (sender_id, group_chat_id, message_type, body)
       VALUES (:sender_id, :group_chat_id, "system", :body)'
    );
    $insMessage->execute([
      'sender_id' => $currentUserId,
      'group_chat_id' => $newGroupId,
      'body' => 'Group chat created.',
    ]);

    $db->commit();
  } catch (Exception $e) {
    if ($db->inTransaction()) { $db->rollBack(); }
    $respond(['success' => false, 'message' => 'Could not create group chat.'], 500);
  }

  $respond([
    'success' => true,
    'group_chat_id' => $newGroupId,
    'group_chat' => [
      'id' => $newGroupId,
      'name' => substr($name, 0, 120),
      'description' => $description !== '' ? substr($description, 0, 255) : '',
      'avatar_url' => $groupAvatarUrl,
    ],
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_messages') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };

  $sessionUserForMessages = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForMessages['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $threadType = strtolower(trim((string) ($_GET['thread_type'] ?? 'private')));
  $threadId = (int) ($_GET['thread_id'] ?? 0);
  if ($threadId <= 0 || !in_array($threadType, ['private', 'group'], true)) {
    $respond(['success' => false, 'message' => 'Invalid thread parameters.'], 400);
  }

  $privatePeerId = 0;
  $groupMembers = [];

  if ($threadType === 'private') {
    $q = $db->prepare(
      'SELECT id,
              IF(user_one_id = :uid, user_two_id, user_one_id) AS peer_id
       FROM private_conversations
       WHERE id = :id AND (user_one_id = :uid OR user_two_id = :uid)
       LIMIT 1'
    );
    $q->execute(['id' => $threadId, 'uid' => $currentUserId]);
    $row = $q->fetch();
    if (!$row) { $respond(['success' => false, 'message' => 'Private conversation not found.'], 404); }
    $privatePeerId = (int) ($row['peer_id'] ?? 0);
  } else {
    $q = $db->prepare('SELECT id FROM group_chat_members WHERE group_chat_id = :id AND user_id = :uid AND left_at IS NULL LIMIT 1');
    $q->execute(['id' => $threadId, 'uid' => $currentUserId]);
    if (!$q->fetch()) { $respond(['success' => false, 'message' => 'Group chat not found.'], 404); }

    try {
      $m = $db->prepare(
        'SELECT gcm.group_chat_id, gcm.user_id, gcm.role,
                u.first_name, u.last_name, u.avatar_url, u.role AS user_role
         FROM group_chat_members gcm
         INNER JOIN users u ON u.id = gcm.user_id
         WHERE gcm.group_chat_id = :gid AND gcm.left_at IS NULL
         ORDER BY CASE gcm.role WHEN "owner" THEN 0 WHEN "admin" THEN 1 ELSE 2 END ASC,
                  u.first_name ASC'
      );
      $m->execute(['gid' => $threadId]);
      foreach ((array) $m->fetchAll() as $memberRow) {
        $groupMembers[] = [
          'group_chat_id' => (int) ($memberRow['group_chat_id'] ?? 0),
          'user_id' => (int) ($memberRow['user_id'] ?? 0),
          'role' => (string) ($memberRow['role'] ?? 'member'),
          'user' => [
            'id' => (int) ($memberRow['user_id'] ?? 0),
            'first_name' => (string) ($memberRow['first_name'] ?? ''),
            'last_name' => (string) ($memberRow['last_name'] ?? ''),
            'avatar_url' => (string) ($memberRow['avatar_url'] ?? ''),
            'role' => (string) ($memberRow['user_role'] ?? 'user'),
          ],
        ];
      }
    } catch (Exception $e) {
    }
  }

  $hasMessageEditedAtColumn = $tableHasColumn($db, 'messages', 'edited_at');
  $editedAtSelect = $hasMessageEditedAtColumn ? ', m.edited_at' : '';

  $messages = [];
  try {
    if ($threadType === 'private') {
      $q = $db->prepare(
        'SELECT m.id, m.sender_id, m.message_type, m.body, m.media_url, m.metadata, m.created_at, m.updated_at, m.is_edited, m.is_deleted' . $editedAtSelect . ',
                u.first_name, u.last_name, u.avatar_url, u.role
         FROM messages m
         INNER JOIN users u ON u.id = m.sender_id
         WHERE m.private_conversation_id = :thread_id
         ORDER BY m.created_at ASC, m.id ASC
         LIMIT 200'
      );
      $q->execute(['thread_id' => $threadId]);

      $mark = $db->prepare(
        'INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
         SELECT m.id, :uid, NOW()
         FROM messages m
         LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
         WHERE m.private_conversation_id = :thread_id
           AND m.sender_id <> :uid
           AND mr.id IS NULL'
      );
      $mark->execute(['uid' => $currentUserId, 'thread_id' => $threadId]);
    } else {
      $q = $db->prepare(
        'SELECT m.id, m.sender_id, m.message_type, m.body, m.media_url, m.metadata, m.created_at, m.updated_at, m.is_edited, m.is_deleted' . $editedAtSelect . ',
                u.first_name, u.last_name, u.avatar_url, u.role
         FROM messages m
         INNER JOIN users u ON u.id = m.sender_id
         WHERE m.group_chat_id = :thread_id
         ORDER BY m.created_at ASC, m.id ASC
         LIMIT 300'
      );
      $q->execute(['thread_id' => $threadId]);

      $mark = $db->prepare(
        'INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
         SELECT m.id, :uid, NOW()
         FROM messages m
         LEFT JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = :uid
         WHERE m.group_chat_id = :thread_id
           AND m.sender_id <> :uid
           AND mr.id IS NULL'
      );
      $mark->execute(['uid' => $currentUserId, 'thread_id' => $threadId]);
    }

    foreach ((array) $q->fetchAll() as $row) {
      $messages[] = [
        'id' => (int) ($row['id'] ?? 0),
        'sender_id' => (int) ($row['sender_id'] ?? 0),
        'message_type' => (string) ($row['message_type'] ?? 'text'),
        'body' => (string) ($row['body'] ?? ''),
        'media_url' => (string) ($row['media_url'] ?? ''),
        'metadata' => isset($row['metadata']) && $row['metadata'] !== null ? json_decode($row['metadata'], true) : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'edited_at' => (string) ($row['edited_at'] ?? ''),
        'is_edited' => (int) ($row['is_edited'] ?? 0),
        'is_deleted' => (int) ($row['is_deleted'] ?? 0),
        'seen_at' => '',
        'sender' => [
          'id' => (int) ($row['sender_id'] ?? 0),
          'first_name' => (string) ($row['first_name'] ?? ''),
          'last_name' => (string) ($row['last_name'] ?? ''),
          'avatar_url' => (string) ($row['avatar_url'] ?? ''),
          'role' => (string) ($row['role'] ?? 'user'),
        ],
      ];
    }
  } catch (Exception $e) {
    $respond(['success' => false, 'message' => 'Could not fetch messages.'], 500);
  }

  if ($threadType === 'private' && $privatePeerId > 0 && !empty($messages)) {
    try {
      $messageIds = array_values(array_unique(array_filter(array_map(static fn ($m): int => (int) ($m['id'] ?? 0), $messages))));
      if (!empty($messageIds)) {
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $q = $db->prepare(
          "SELECT message_id, MAX(read_at) AS seen_at
           FROM message_reads
           WHERE user_id = ?
             AND message_id IN ({$placeholders})
           GROUP BY message_id"
        );
        $q->execute(array_merge([$privatePeerId], $messageIds));
        $seenLookup = [];
        foreach ((array) $q->fetchAll() as $row) {
          $mid = (int) ($row['message_id'] ?? 0);
          if ($mid > 0) {
            $seenLookup[$mid] = (string) ($row['seen_at'] ?? '');
          }
        }
        foreach ($messages as &$messageEntry) {
          $mid = (int) ($messageEntry['id'] ?? 0);
          $messageEntry['seen_at'] = $seenLookup[$mid] ?? '';
        }
        unset($messageEntry);
      }
    } catch (Exception $e) {
    }
  }

  $respond(['success' => true, 'messages' => $messages, 'group_members' => $groupMembers]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_send_message') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForMessages = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForMessages['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForMessages = $userController->getUserById($currentUserId);
  if (!$existingForMessages) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForMessages->getIsBlocked() ?? 0) === 1) {
    $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423);
  }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $threadType = strtolower(trim((string) ($jsonInput['thread_type'] ?? 'private')));
  $threadId = (int) ($jsonInput['thread_id'] ?? 0);
  $messageType = strtolower(trim((string) ($jsonInput['message_type'] ?? 'text')));
  $body = trim((string) ($jsonInput['body'] ?? ''));
  $mediaUrl = trim((string) ($jsonInput['media_url'] ?? ''));
  $metadata = $normalizeMetadataJson($jsonInput['metadata'] ?? null);

  if (!in_array($threadType, ['private', 'group'], true) || $threadId <= 0) {
    $respond(['success' => false, 'message' => 'Invalid thread parameters.'], 400);
  }
  if ($body === '' && $mediaUrl === '') {
    $respond(['success' => false, 'message' => 'Message content is required.'], 400);
  }
  if (!in_array($messageType, ['text', 'image', 'video', 'audio', 'file', 'system'], true)) {
    $messageType = 'text';
  }

  if ($threadType === 'private') {
    $q = $db->prepare('SELECT id FROM private_conversations WHERE id = :id AND (user_one_id = :uid OR user_two_id = :uid) LIMIT 1');
    $q->execute(['id' => $threadId, 'uid' => $currentUserId]);
    if (!$q->fetch()) { $respond(['success' => false, 'message' => 'Private conversation not found.'], 404); }
  } else {
    $q = $db->prepare('SELECT id FROM group_chat_members WHERE group_chat_id = :id AND user_id = :uid AND left_at IS NULL LIMIT 1');
    $q->execute(['id' => $threadId, 'uid' => $currentUserId]);
    if (!$q->fetch()) { $respond(['success' => false, 'message' => 'Group chat not found.'], 404); }
  }

  $hasMessageEditedAtColumn = $tableHasColumn($db, 'messages', 'edited_at');
  $editedAtSelect = $hasMessageEditedAtColumn ? ', m.edited_at' : '';

  $newMessageId = 0;
  try {
    if ($threadType === 'private') {
      $ins = $db->prepare(
        'INSERT INTO messages (sender_id, private_conversation_id, message_type, body, media_url, metadata)
         VALUES (:sender_id, :private_conversation_id, :message_type, :body, :media_url, :metadata)'
      );
      $ins->execute([
        'sender_id' => $currentUserId,
        'private_conversation_id' => $threadId,
        'message_type' => $messageType,
        'body' => $body !== '' ? $body : null,
        'media_url' => $mediaUrl !== '' ? substr($mediaUrl, 0, 1024) : null,
        'metadata' => $metadata !== null && $metadata !== '' ? $metadata : null,
      ]);

      $updThread = $db->prepare('UPDATE private_conversations SET last_message_at = NOW(), updated_at = NOW() WHERE id = :id');
      $updThread->execute(['id' => $threadId]);
    } else {
      $ins = $db->prepare(
        'INSERT INTO messages (sender_id, group_chat_id, message_type, body, media_url, metadata)
         VALUES (:sender_id, :group_chat_id, :message_type, :body, :media_url, :metadata)'
      );
      $ins->execute([
        'sender_id' => $currentUserId,
        'group_chat_id' => $threadId,
        'message_type' => $messageType,
        'body' => $body !== '' ? $body : null,
        'media_url' => $mediaUrl !== '' ? substr($mediaUrl, 0, 1024) : null,
        'metadata' => $metadata !== null && $metadata !== '' ? $metadata : null,
      ]);

      $updThread = $db->prepare('UPDATE group_chats SET last_message_at = NOW(), updated_at = NOW() WHERE id = :id');
      $updThread->execute(['id' => $threadId]);
    }

    $newMessageId = (int) $db->lastInsertId();
  } catch (Exception $e) {
    $respond(['success' => false, 'message' => 'Could not send message.'], 500);
  }

  $messageRow = null;
  try {
    $q = $db->prepare(
      'SELECT m.id, m.sender_id, m.message_type, m.body, m.media_url, m.metadata, m.created_at, m.updated_at, m.is_edited, m.is_deleted' . $editedAtSelect . ',
              u.first_name, u.last_name, u.avatar_url, u.role
       FROM messages m
       INNER JOIN users u ON u.id = m.sender_id
       WHERE m.id = :id
       LIMIT 1'
    );
    $q->execute(['id' => $newMessageId]);
    $messageRow = $q->fetch();
  } catch (Exception $e) {
  }

  $respond([
    'success' => true,
    'message' => [
      'id' => (int) ($messageRow['id'] ?? $newMessageId),
      'sender_id' => (int) ($messageRow['sender_id'] ?? $currentUserId),
      'message_type' => (string) ($messageRow['message_type'] ?? $messageType),
      'body' => (string) ($messageRow['body'] ?? $body),
      'media_url' => (string) ($messageRow['media_url'] ?? $mediaUrl),
      'metadata' => isset($messageRow['metadata']) && $messageRow['metadata'] !== null ? json_decode($messageRow['metadata'], true) : null,
      'created_at' => (string) ($messageRow['created_at'] ?? date('Y-m-d H:i:s')),
      'updated_at' => (string) ($messageRow['updated_at'] ?? date('Y-m-d H:i:s')),
      'edited_at' => (string) ($messageRow['edited_at'] ?? ''),
      'is_edited' => (int) ($messageRow['is_edited'] ?? 0),
      'is_deleted' => (int) ($messageRow['is_deleted'] ?? 0),
      'seen_at' => '',
      'sender' => [
        'id' => (int) ($messageRow['sender_id'] ?? $currentUserId),
        'first_name' => (string) ($messageRow['first_name'] ?? ''),
        'last_name' => (string) ($messageRow['last_name'] ?? ''),
        'avatar_url' => (string) ($messageRow['avatar_url'] ?? ''),
        'role' => (string) ($messageRow['role'] ?? 'user'),
      ],
    ],
  ]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_edit_message') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForEdit = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForEdit['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForEdit = $userController->getUserById($currentUserId);
  if (!$existingForEdit) { $respond(['success' => false, 'message' => 'User not found.'], 404); }
  if ((int) ($existingForEdit->getIsBlocked() ?? 0) === 1) {
    $respond(['success' => false, 'message' => 'Account is blocked. Changes are disabled until admin review.'], 423);
  }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }

  $messageId = (int) ($jsonInput['message_id'] ?? 0);
  $body = trim((string) ($jsonInput['body'] ?? ''));
  if ($messageId <= 0) {
    $respond(['success' => false, 'message' => 'Invalid message id.'], 400);
  }
  if ($body === '') {
    $respond(['success' => false, 'message' => 'Message body is required.'], 400);
  }

  $hasMessageEditedAtColumn = $tableHasColumn($db, 'messages', 'edited_at');
  $editedAtSelect = $hasMessageEditedAtColumn ? ', m.edited_at' : '';

  try {
    $q = $db->prepare(
      'SELECT id, sender_id, message_type, is_deleted
       FROM messages
       WHERE id = :id
       LIMIT 1'
    );
    $q->execute(['id' => $messageId]);
    $messageRow = $q->fetch();
    if (!$messageRow) {
      $respond(['success' => false, 'message' => 'Message not found.'], 404);
    }

    if ((int) ($messageRow['sender_id'] ?? 0) !== $currentUserId) {
      $respond(['success' => false, 'message' => 'You can only edit your own messages.'], 403);
    }
    if ((int) ($messageRow['is_deleted'] ?? 0) === 1) {
      $respond(['success' => false, 'message' => 'Deleted messages cannot be edited.'], 409);
    }

    $messageType = strtolower((string) ($messageRow['message_type'] ?? 'text'));
    if ($messageType === 'system') {
      $respond(['success' => false, 'message' => 'System messages cannot be edited.'], 409);
    }

    $updateSql =
      'UPDATE messages
       SET body = :body,
           is_edited = 1,
           updated_at = NOW()' .
      ($hasMessageEditedAtColumn ? ', edited_at = NOW()' : '') .
      ' WHERE id = :id';

    $upd = $db->prepare($updateSql);
    $upd->execute([
      'body' => substr($body, 0, 5000),
      'id' => $messageId,
    ]);

    $q = $db->prepare(
      'SELECT m.id, m.sender_id, m.message_type, m.body, m.media_url, m.metadata, m.created_at, m.updated_at, m.is_edited, m.is_deleted' . $editedAtSelect . ',
              u.first_name, u.last_name, u.avatar_url, u.role
       FROM messages m
       INNER JOIN users u ON u.id = m.sender_id
       WHERE m.id = :id
       LIMIT 1'
    );
    $q->execute(['id' => $messageId]);
    $fresh = $q->fetch();

    $respond([
      'success' => true,
      'message' => [
        'id' => (int) ($fresh['id'] ?? $messageId),
        'sender_id' => (int) ($fresh['sender_id'] ?? $currentUserId),
        'message_type' => (string) ($fresh['message_type'] ?? 'text'),
        'body' => (string) ($fresh['body'] ?? $body),
        'media_url' => (string) ($fresh['media_url'] ?? ''),
        'metadata' => isset($fresh['metadata']) && $fresh['metadata'] !== null ? json_decode($fresh['metadata'], true) : null,
        'created_at' => (string) ($fresh['created_at'] ?? ''),
        'updated_at' => (string) ($fresh['updated_at'] ?? ''),
        'edited_at' => (string) ($fresh['edited_at'] ?? ''),
        'is_edited' => (int) ($fresh['is_edited'] ?? 1),
        'is_deleted' => (int) ($fresh['is_deleted'] ?? 0),
        'seen_at' => '',
        'sender' => [
          'id' => (int) ($fresh['sender_id'] ?? $currentUserId),
          'first_name' => (string) ($fresh['first_name'] ?? ''),
          'last_name' => (string) ($fresh['last_name'] ?? ''),
          'avatar_url' => (string) ($fresh['avatar_url'] ?? ''),
          'role' => (string) ($fresh['role'] ?? 'user'),
        ],
      ],
    ]);
  } catch (Exception $e) {
    $respond(['success' => false, 'message' => 'Could not edit message.'], 500);
  }
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_delete_message') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForDelete = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForDelete['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $messageId = (int) ($jsonInput['message_id'] ?? 0);
  if ($messageId <= 0) { $respond(['success' => false, 'message' => 'Invalid message id.'], 400); }

  try {
    $q = $db->prepare(
      'SELECT id, sender_id, group_chat_id
       FROM messages
       WHERE id = :id
       LIMIT 1'
    );
    $q->execute(['id' => $messageId]);
    $messageRow = $q->fetch();
    if (!$messageRow) {
      $respond(['success' => false, 'message' => 'Message not found.'], 404);
    }

    $senderId = (int) ($messageRow['sender_id'] ?? 0);
    $groupChatId = (int) ($messageRow['group_chat_id'] ?? 0);
    $canDelete = ($senderId === $currentUserId);
    if (!$canDelete && $groupChatId > 0) {
      $canDelete = $isGroupModerator($db, $groupChatId, $currentUserId);
    }
    if (!$canDelete) {
      $respond(['success' => false, 'message' => 'Not allowed to delete this message.'], 403);
    }

    $upd = $db->prepare(
      'UPDATE messages
       SET body = :body,
           media_url = NULL,
           metadata = NULL,
           is_deleted = 1,
           is_edited = 0,
           updated_at = NOW()
       WHERE id = :id'
    );
    $upd->execute([
      'id' => $messageId,
      'body' => 'Message deleted',
    ]);
  } catch (Exception $e) {
    $respond(['success' => false, 'message' => 'Could not delete message.'], 500);
  }

  $respond(['success' => true, 'message_id' => $messageId]);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_group_manage') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

  $sessionUserForGroupManage = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForGroupManage['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $existingForGroupManage = $userController->getUserById($currentUserId);
  if (!$existingForGroupManage) { $respond(['success' => false, 'message' => 'User not found.'], 404); }

  $jsonInput = json_decode((string) file_get_contents('php://input'), true);
  if (!is_array($jsonInput)) { $jsonInput = []; }
  $mode = strtolower(trim((string) ($jsonInput['mode'] ?? '')));
  $groupId = (int) ($jsonInput['group_chat_id'] ?? 0);
  $isGlobalModerator = strtolower(trim((string) ($existingForGroupManage->getEmail() ?? ($sessionUserForGroupManage['email'] ?? '')))) === 'admin@diversity.is';

  if ($mode === 'reports_list') {
    $ensureRealtimeCommunicationSchema($db);
    if (!$isGlobalModerator && ($groupId <= 0 || !$isGroupModerator($db, $groupId, $currentUserId))) {
      $respond(['success' => false, 'message' => 'Not allowed to view reports.'], 403);
    }

    $statusFilter = strtolower(trim((string) ($jsonInput['status'] ?? 'pending')));
    if (!in_array($statusFilter, ['pending', 'reviewed', 'resolved', 'dismissed', 'all'], true)) {
      $statusFilter = 'pending';
    }

    try {
      $query =
        'SELECT gr.id, gr.group_chat_id, gr.reporter_id, gr.reported_user_id, gr.message_id,
                gr.reason, gr.details, gr.status, gr.moderator_id, gr.moderation_note, gr.created_at, gr.updated_at,
                gc.name AS group_name,
                ru.first_name AS reporter_first_name, ru.last_name AS reporter_last_name,
                tu.first_name AS target_first_name, tu.last_name AS target_last_name
         FROM group_reports gr
         INNER JOIN group_chats gc ON gc.id = gr.group_chat_id
         INNER JOIN users ru ON ru.id = gr.reporter_id
         LEFT JOIN users tu ON tu.id = gr.reported_user_id
         WHERE 1=1';
      $params = [];

      if (!$isGlobalModerator) {
        $query .= ' AND gr.group_chat_id = :gid';
        $params['gid'] = $groupId;
      } elseif ($groupId > 0) {
        $query .= ' AND gr.group_chat_id = :gid';
        $params['gid'] = $groupId;
      }

      if ($statusFilter !== 'all') {
        $query .= ' AND gr.status = :status';
        $params['status'] = $statusFilter;
      }

      $query .= ' ORDER BY gr.created_at DESC LIMIT 150';

      $q = $db->prepare($query);
      $q->execute($params);
      $reports = [];
      foreach ((array) $q->fetchAll() as $row) {
        $reports[] = [
          'id' => (int) ($row['id'] ?? 0),
          'group_chat_id' => (int) ($row['group_chat_id'] ?? 0),
          'group_name' => (string) ($row['group_name'] ?? ''),
          'reporter_id' => (int) ($row['reporter_id'] ?? 0),
          'reported_user_id' => (int) ($row['reported_user_id'] ?? 0),
          'message_id' => (int) ($row['message_id'] ?? 0),
          'reason' => (string) ($row['reason'] ?? ''),
          'details' => (string) ($row['details'] ?? ''),
          'status' => (string) ($row['status'] ?? 'pending'),
          'moderator_id' => (int) ($row['moderator_id'] ?? 0),
          'moderation_note' => (string) ($row['moderation_note'] ?? ''),
          'created_at' => (string) ($row['created_at'] ?? ''),
          'updated_at' => (string) ($row['updated_at'] ?? ''),
          'reporter_name' => trim((string) (($row['reporter_first_name'] ?? '') . ' ' . ($row['reporter_last_name'] ?? ''))),
          'target_name' => trim((string) (($row['target_first_name'] ?? '') . ' ' . ($row['target_last_name'] ?? ''))),
        ];
      }
      $respond(['success' => true, 'reports' => $reports]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not load reports.'], 500);
    }
  }

  if ($groupId <= 0) {
    $respond(['success' => false, 'message' => 'Invalid group id.'], 400);
  }

  $memberRole = $resolveGroupMemberRole($db, $groupId, $currentUserId);
  if (!$memberRole && !$isGlobalModerator) {
    $respond(['success' => false, 'message' => 'Group not found or access denied.'], 404);
  }

  if ($mode === 'leave') {
    try {
      if ($memberRole === 'owner') {
        $q = $db->prepare(
          'SELECT user_id, role
           FROM group_chat_members
           WHERE group_chat_id = :gid
             AND user_id <> :uid
             AND left_at IS NULL
           ORDER BY CASE role WHEN "admin" THEN 0 WHEN "member" THEN 1 ELSE 2 END ASC,
                    joined_at ASC
           LIMIT 1'
        );
        $q->execute(['gid' => $groupId, 'uid' => $currentUserId]);
        $candidate = $q->fetch();
        if ($candidate) {
          $promote = $db->prepare(
            'UPDATE group_chat_members
             SET role = "owner"
             WHERE group_chat_id = :gid AND user_id = :uid AND left_at IS NULL'
          );
          $promote->execute([
            'gid' => $groupId,
            'uid' => (int) ($candidate['user_id'] ?? 0),
          ]);
        }
      }

      $upd = $db->prepare(
        'UPDATE group_chat_members
         SET left_at = NOW(), updated_at = NOW()
         WHERE group_chat_id = :gid AND user_id = :uid AND left_at IS NULL'
      );
      $upd->execute(['gid' => $groupId, 'uid' => $currentUserId]);

      $msg = $db->prepare(
        'INSERT INTO messages (sender_id, group_chat_id, message_type, body)
         VALUES (:sender_id, :group_chat_id, "system", :body)'
      );
      $msg->execute([
        'sender_id' => $currentUserId,
        'group_chat_id' => $groupId,
        'body' => 'A member left the group.',
      ]);

      $updGroup = $db->prepare('UPDATE group_chats SET last_message_at = NOW(), updated_at = NOW() WHERE id = :id');
      $updGroup->execute(['id' => $groupId]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not leave group.'], 500);
    }

    $respond(['success' => true, 'group_chat_id' => $groupId]);
  }

  if ($mode === 'delete') {
    if (!in_array($memberRole, ['owner', 'admin'], true) && !$isGlobalModerator) {
      $respond(['success' => false, 'message' => 'Only admins can delete this group.'], 403);
    }

    try {
      $db->beginTransaction();

      $updMembers = $db->prepare(
        'UPDATE group_chat_members
         SET left_at = NOW(), updated_at = NOW()
         WHERE group_chat_id = :gid AND left_at IS NULL'
      );
      $updMembers->execute(['gid' => $groupId]);

      $updGroup = $db->prepare(
        'UPDATE group_chats
         SET name = CASE WHEN name LIKE "[Deleted] %" THEN name ELSE CONCAT("[Deleted] ", name) END,
             last_message_at = NOW(),
             updated_at = NOW()
         WHERE id = :id'
      );
      $updGroup->execute(['id' => $groupId]);

      $msg = $db->prepare(
        'INSERT INTO messages (sender_id, group_chat_id, message_type, body)
         VALUES (:sender_id, :group_chat_id, "system", :body)'
      );
      $msg->execute([
        'sender_id' => $currentUserId,
        'group_chat_id' => $groupId,
        'body' => 'This group has been deleted by a moderator.',
      ]);

      $db->commit();
    } catch (Exception $e) {
      if ($db->inTransaction()) { $db->rollBack(); }
      $respond(['success' => false, 'message' => 'Could not delete group.'], 500);
    }

    $respond(['success' => true, 'group_chat_id' => $groupId]);
  }

  if ($mode === 'remove_member') {
    if (!in_array($memberRole, ['owner', 'admin'], true) && !$isGlobalModerator) {
      $respond(['success' => false, 'message' => 'Only admins can remove members.'], 403);
    }

    $targetUserId = (int) ($jsonInput['target_user_id'] ?? 0);
    if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
      $respond(['success' => false, 'message' => 'Invalid target member.'], 400);
    }

    try {
      $targetRoleQuery = $db->prepare(
        'SELECT role
         FROM group_chat_members
         WHERE group_chat_id = :gid AND user_id = :uid AND left_at IS NULL
         LIMIT 1'
      );
      $targetRoleQuery->execute(['gid' => $groupId, 'uid' => $targetUserId]);
      $targetRoleRow = $targetRoleQuery->fetch();
      if (!$targetRoleRow) {
        $respond(['success' => false, 'message' => 'Member not found in this group.'], 404);
      }

      $targetRole = strtolower((string) ($targetRoleRow['role'] ?? 'member'));
      if (!$isGlobalModerator && $memberRole === 'admin' && in_array($targetRole, ['admin', 'owner'], true)) {
        $respond(['success' => false, 'message' => 'Admins can only remove regular members.'], 403);
      }

      $upd = $db->prepare(
        'UPDATE group_chat_members
         SET left_at = NOW(), updated_at = NOW()
         WHERE group_chat_id = :gid AND user_id = :uid AND left_at IS NULL'
      );
      $upd->execute(['gid' => $groupId, 'uid' => $targetUserId]);
      if ((int) $upd->rowCount() <= 0) {
        $respond(['success' => false, 'message' => 'Member not found in this group.'], 404);
      }

      $msg = $db->prepare(
        'INSERT INTO messages (sender_id, group_chat_id, message_type, body)
         VALUES (:sender_id, :group_chat_id, "system", :body)'
      );
      $msg->execute([
        'sender_id' => $currentUserId,
        'group_chat_id' => $groupId,
        'body' => 'A member has been removed by a moderator.',
      ]);

      $updGroup = $db->prepare('UPDATE group_chats SET last_message_at = NOW(), updated_at = NOW() WHERE id = :id');
      $updGroup->execute(['id' => $groupId]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not remove member.'], 500);
    }

    $respond(['success' => true, 'group_chat_id' => $groupId, 'target_user_id' => $targetUserId]);
  }

  if ($mode === 'set_role') {
    if ($memberRole !== 'owner' && !$isGlobalModerator) {
      $respond(['success' => false, 'message' => 'Only owners can change roles.'], 403);
    }

    $targetUserId = (int) ($jsonInput['target_user_id'] ?? 0);
    $targetRole = strtolower(trim((string) ($jsonInput['target_role'] ?? 'member')));
    if (!in_array($targetRole, ['member', 'admin'], true)) {
      $targetRole = 'member';
    }
    if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
      $respond(['success' => false, 'message' => 'Invalid target member.'], 400);
    }

    try {
      $upd = $db->prepare(
        'UPDATE group_chat_members
         SET role = :role, updated_at = NOW()
         WHERE group_chat_id = :gid AND user_id = :uid AND left_at IS NULL'
      );
      $upd->execute([
        'gid' => $groupId,
        'uid' => $targetUserId,
        'role' => $targetRole,
      ]);
      if ((int) $upd->rowCount() <= 0) {
        $respond(['success' => false, 'message' => 'Member not found in this group.'], 404);
      }
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not update member role.'], 500);
    }

    $respond(['success' => true, 'group_chat_id' => $groupId, 'target_user_id' => $targetUserId, 'target_role' => $targetRole]);
  }

  if ($mode === 'report') {
    $ensureRealtimeCommunicationSchema($db);
    if (!$memberRole && !$isGlobalModerator) {
      $respond(['success' => false, 'message' => 'Only group members can report content.'], 403);
    }

    $reason = trim((string) ($jsonInput['reason'] ?? ''));
    $details = trim((string) ($jsonInput['details'] ?? ''));
    $reportedUserId = (int) ($jsonInput['reported_user_id'] ?? 0);
    $messageId = (int) ($jsonInput['message_id'] ?? 0);
    if ($reason === '') {
      $respond(['success' => false, 'message' => 'Report reason is required.'], 400);
    }

    try {
      $ins = $db->prepare(
        'INSERT INTO group_reports (group_chat_id, reporter_id, reported_user_id, message_id, reason, details)
         VALUES (:group_chat_id, :reporter_id, :reported_user_id, :message_id, :reason, :details)'
      );
      $ins->execute([
        'group_chat_id' => $groupId,
        'reporter_id' => $currentUserId,
        'reported_user_id' => $reportedUserId > 0 ? $reportedUserId : null,
        'message_id' => $messageId > 0 ? $messageId : null,
        'reason' => substr($reason, 0, 190),
        'details' => $details !== '' ? substr($details, 0, 2000) : null,
      ]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not submit report.'], 500);
    }

    $respond(['success' => true, 'group_chat_id' => $groupId]);
  }

  if ($mode === 'moderate_report') {
    $ensureRealtimeCommunicationSchema($db);
    $reportId = (int) ($jsonInput['report_id'] ?? 0);
    if ($reportId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid report id.'], 400);
    }

    $moderationStatus = strtolower(trim((string) ($jsonInput['moderation_status'] ?? 'reviewed')));
    if (!in_array($moderationStatus, ['reviewed', 'resolved', 'dismissed'], true)) {
      $moderationStatus = 'reviewed';
    }
    $moderationNote = trim((string) ($jsonInput['moderation_note'] ?? ''));

    try {
      $q = $db->prepare('SELECT group_chat_id FROM group_reports WHERE id = :id LIMIT 1');
      $q->execute(['id' => $reportId]);
      $row = $q->fetch();
      if (!$row) {
        $respond(['success' => false, 'message' => 'Report not found.'], 404);
      }

      $reportGroupId = (int) ($row['group_chat_id'] ?? 0);
      if (!$isGlobalModerator && !$isGroupModerator($db, $reportGroupId, $currentUserId)) {
        $respond(['success' => false, 'message' => 'Not allowed to moderate this report.'], 403);
      }

      $upd = $db->prepare(
        'UPDATE group_reports
         SET status = :status,
             moderator_id = :moderator_id,
             moderation_note = :moderation_note,
             updated_at = NOW()
         WHERE id = :id'
      );
      $upd->execute([
        'status' => $moderationStatus,
        'moderator_id' => $currentUserId,
        'moderation_note' => $moderationNote !== '' ? substr($moderationNote, 0, 2000) : null,
        'id' => $reportId,
      ]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not update report.'], 500);
    }

    $respond(['success' => true, 'report_id' => $reportId, 'status' => $moderationStatus]);
  }

  $respond(['success' => false, 'message' => 'Unsupported group action mode.'], 400);
}

if (isset($_GET['action']) && strtolower((string) $_GET['action']) === 'profile_call_relay') {
  header('Content-Type: application/json; charset=utf-8');
  $respond = static function (array $payload, int $code = 200): void { http_response_code($code); echo json_encode($payload); exit; };

  $ensureRealtimeCommunicationSchema($db);

  $sessionUserForCalls = UserController::currentUser() ?? [];
  $currentUserId = (int) ($sessionUserForCalls['id'] ?? 0);
  if ($currentUserId <= 0) { $respond(['success' => false, 'message' => 'Invalid session user.'], 401); }

  $payload = [];
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $payload = $_GET;
  } else {
    $jsonInput = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($jsonInput)) {
      $payload = $jsonInput;
    }
  }

  $mode = strtolower(trim((string) ($payload['mode'] ?? ($_SERVER['REQUEST_METHOD'] === 'GET' ? 'poll' : 'start'))));

  $normalizeSignalPayload = static function ($value): string {
    if ($value === null || $value === '') {
      return '{}';
    }
    if (is_string($value)) {
      $trimmed = trim($value);
      if ($trimmed === '') {
        return '{}';
      }
      json_decode($trimmed, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        return $trimmed;
      }
      return json_encode(['value' => $trimmed], JSON_UNESCAPED_UNICODE);
    }
    if (is_array($value) || is_object($value)) {
      $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
      return $encoded === false ? '{}' : $encoded;
    }
    return json_encode(['value' => $value], JSON_UNESCAPED_UNICODE);
  };

  $findCallSession = static function (PDO $dbConn, int $sessionId): ?array {
    if ($sessionId <= 0) {
      return null;
    }
    try {
      $q = $dbConn->prepare('SELECT * FROM call_sessions WHERE id = :id LIMIT 1');
      $q->execute(['id' => $sessionId]);
      $row = $q->fetch();
      return is_array($row) ? $row : null;
    } catch (Exception $e) {
      return null;
    }
  };

  $canAccessCallSession = static function (PDO $dbConn, array $sessionRow, int $uid) use ($resolveGroupMemberRole): bool {
    $threadType = strtolower((string) ($sessionRow['thread_type'] ?? ''));
    if ($threadType === 'private') {
      return $uid > 0 && ($uid === (int) ($sessionRow['caller_id'] ?? 0) || $uid === (int) ($sessionRow['callee_id'] ?? 0));
    }
    if ($threadType === 'group') {
      $gid = (int) ($sessionRow['thread_id'] ?? 0);
      return $gid > 0 && $resolveGroupMemberRole($dbConn, $gid, $uid) !== null;
    }
    return false;
  };

  if ($mode === 'start') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $threadType = strtolower(trim((string) ($payload['thread_type'] ?? 'private')));
    $threadId = (int) ($payload['thread_id'] ?? 0);
    $callType = strtolower(trim((string) ($payload['call_type'] ?? 'video')));
    $offerPayload = $payload['offer'] ?? null;

    if (!in_array($threadType, ['private', 'group'], true) || $threadId <= 0) {
      $respond(['success' => false, 'message' => 'Invalid thread parameters.'], 400);
    }
    if (!in_array($callType, ['audio', 'video'], true)) {
      $callType = 'video';
    }

    $calleeId = null;
    try {
      if ($threadType === 'private') {
        $q = $db->prepare(
          'SELECT id, IF(user_one_id = :uid, user_two_id, user_one_id) AS peer_id
           FROM private_conversations
           WHERE id = :id AND (user_one_id = :uid OR user_two_id = :uid)
           LIMIT 1'
        );
        $q->execute(['id' => $threadId, 'uid' => $currentUserId]);
        $threadRow = $q->fetch();
        if (!$threadRow) {
          $respond(['success' => false, 'message' => 'Private conversation not found.'], 404);
        }
        $calleeId = (int) ($threadRow['peer_id'] ?? 0);
        if ($calleeId <= 0) {
          $respond(['success' => false, 'message' => 'Could not resolve recipient.'], 400);
        }
      } else {
        $q = $db->prepare('SELECT id FROM group_chat_members WHERE group_chat_id = :id AND user_id = :uid AND left_at IS NULL LIMIT 1');
        $q->execute(['id' => $threadId, 'uid' => $currentUserId]);
        if (!$q->fetch()) {
          $respond(['success' => false, 'message' => 'Group chat not found.'], 404);
        }
      }

      $expireRinging = $db->prepare(
        'UPDATE call_sessions
         SET status = "missed", ended_at = NOW(), ended_by = :uid, updated_at = NOW()
         WHERE thread_type = :thread_type
           AND thread_id = :thread_id
           AND status = "ringing"'
      );
      $expireRinging->execute([
        'uid' => $currentUserId,
        'thread_type' => $threadType,
        'thread_id' => $threadId,
      ]);

      $ins = $db->prepare(
        'INSERT INTO call_sessions (thread_type, thread_id, caller_id, callee_id, call_type, status, started_at)
         VALUES (:thread_type, :thread_id, :caller_id, :callee_id, :call_type, "ringing", NOW())'
      );
      $ins->execute([
        'thread_type' => $threadType,
        'thread_id' => $threadId,
        'caller_id' => $currentUserId,
        'callee_id' => $calleeId,
        'call_type' => $callType,
      ]);
      $sessionId = (int) $db->lastInsertId();

      if ($offerPayload !== null) {
        $insSignal = $db->prepare(
          'INSERT INTO call_signals (session_id, sender_id, signal_type, payload)
           VALUES (:session_id, :sender_id, "offer", :payload)'
        );
        $insSignal->execute([
          'session_id' => $sessionId,
          'sender_id' => $currentUserId,
          'payload' => $normalizeSignalPayload($offerPayload),
        ]);
      }

      $respond([
        'success' => true,
        'session' => [
          'id' => $sessionId,
          'thread_type' => $threadType,
          'thread_id' => $threadId,
          'caller_id' => $currentUserId,
          'callee_id' => $calleeId,
          'call_type' => $callType,
          'status' => 'ringing',
          'created_at' => date('Y-m-d H:i:s'),
        ],
      ]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not start call.'], 500);
    }
  }

  if ($mode === 'poll') {
    $incoming = [];
    $active = [];

    try {
      $q = $db->prepare(
        'SELECT cs.id, cs.thread_type, cs.thread_id, cs.caller_id, cs.callee_id, cs.call_type, cs.status,
                cs.started_at, cs.answered_at, cs.ended_at, cs.created_at,
                cu.first_name AS caller_first_name, cu.last_name AS caller_last_name, cu.avatar_url AS caller_avatar_url
         FROM call_sessions cs
         INNER JOIN users cu ON cu.id = cs.caller_id
         WHERE cs.thread_type = "private"
           AND cs.callee_id = :uid
           AND cs.status = "ringing"
         ORDER BY cs.created_at DESC
         LIMIT 8'
      );
      $q->execute(['uid' => $currentUserId]);
      foreach ((array) $q->fetchAll() as $row) {
        $incoming[] = [
          'id' => (int) ($row['id'] ?? 0),
          'thread_type' => 'private',
          'thread_id' => (int) ($row['thread_id'] ?? 0),
          'caller_id' => (int) ($row['caller_id'] ?? 0),
          'callee_id' => (int) ($row['callee_id'] ?? 0),
          'call_type' => (string) ($row['call_type'] ?? 'video'),
          'status' => (string) ($row['status'] ?? 'ringing'),
          'created_at' => (string) ($row['created_at'] ?? ''),
          'caller' => [
            'id' => (int) ($row['caller_id'] ?? 0),
            'first_name' => (string) ($row['caller_first_name'] ?? ''),
            'last_name' => (string) ($row['caller_last_name'] ?? ''),
            'avatar_url' => (string) ($row['caller_avatar_url'] ?? ''),
          ],
        ];
      }
    } catch (Exception $e) {
    }

    try {
      $q = $db->prepare(
        'SELECT cs.id, cs.thread_type, cs.thread_id, cs.caller_id, cs.callee_id, cs.call_type, cs.status,
                cs.started_at, cs.answered_at, cs.ended_at, cs.created_at,
                cu.first_name AS caller_first_name, cu.last_name AS caller_last_name, cu.avatar_url AS caller_avatar_url,
                gc.name AS group_name, gc.avatar_url AS group_avatar_url
         FROM call_sessions cs
         INNER JOIN group_chat_members gcm ON gcm.group_chat_id = cs.thread_id AND gcm.user_id = :uid AND gcm.left_at IS NULL
         INNER JOIN users cu ON cu.id = cs.caller_id
         INNER JOIN group_chats gc ON gc.id = cs.thread_id
         WHERE cs.thread_type = "group"
           AND cs.caller_id <> :uid
           AND cs.status = "ringing"
         ORDER BY cs.created_at DESC
         LIMIT 8'
      );
      $q->execute(['uid' => $currentUserId]);
      foreach ((array) $q->fetchAll() as $row) {
        $incoming[] = [
          'id' => (int) ($row['id'] ?? 0),
          'thread_type' => 'group',
          'thread_id' => (int) ($row['thread_id'] ?? 0),
          'caller_id' => (int) ($row['caller_id'] ?? 0),
          'callee_id' => (int) ($row['callee_id'] ?? 0),
          'call_type' => (string) ($row['call_type'] ?? 'video'),
          'status' => (string) ($row['status'] ?? 'ringing'),
          'created_at' => (string) ($row['created_at'] ?? ''),
          'group_name' => (string) ($row['group_name'] ?? ''),
          'group_avatar_url' => (string) ($row['group_avatar_url'] ?? ''),
          'caller' => [
            'id' => (int) ($row['caller_id'] ?? 0),
            'first_name' => (string) ($row['caller_first_name'] ?? ''),
            'last_name' => (string) ($row['caller_last_name'] ?? ''),
            'avatar_url' => (string) ($row['caller_avatar_url'] ?? ''),
          ],
        ];
      }
    } catch (Exception $e) {
    }

    try {
      $q = $db->prepare(
        'SELECT cs.id, cs.thread_type, cs.thread_id, cs.caller_id, cs.callee_id, cs.call_type, cs.status,
                cs.started_at, cs.answered_at, cs.ended_at, cs.created_at
         FROM call_sessions cs
         WHERE cs.thread_type = "private"
           AND (cs.caller_id = :uid OR cs.callee_id = :uid)
           AND cs.status IN ("ringing", "accepted")
         ORDER BY cs.created_at DESC
         LIMIT 12'
      );
      $q->execute(['uid' => $currentUserId]);
      foreach ((array) $q->fetchAll() as $row) {
        $active[] = [
          'id' => (int) ($row['id'] ?? 0),
          'thread_type' => (string) ($row['thread_type'] ?? 'private'),
          'thread_id' => (int) ($row['thread_id'] ?? 0),
          'caller_id' => (int) ($row['caller_id'] ?? 0),
          'callee_id' => (int) ($row['callee_id'] ?? 0),
          'call_type' => (string) ($row['call_type'] ?? 'video'),
          'status' => (string) ($row['status'] ?? 'ringing'),
          'started_at' => (string) ($row['started_at'] ?? ''),
          'answered_at' => (string) ($row['answered_at'] ?? ''),
          'ended_at' => (string) ($row['ended_at'] ?? ''),
          'created_at' => (string) ($row['created_at'] ?? ''),
        ];
      }
    } catch (Exception $e) {
    }

    try {
      $q = $db->prepare(
        'SELECT cs.id, cs.thread_type, cs.thread_id, cs.caller_id, cs.callee_id, cs.call_type, cs.status,
                cs.started_at, cs.answered_at, cs.ended_at, cs.created_at
         FROM call_sessions cs
         INNER JOIN group_chat_members gcm ON gcm.group_chat_id = cs.thread_id AND gcm.user_id = :uid AND gcm.left_at IS NULL
         WHERE cs.thread_type = "group"
           AND cs.status IN ("ringing", "accepted")
         ORDER BY cs.created_at DESC
         LIMIT 12'
      );
      $q->execute(['uid' => $currentUserId]);
      foreach ((array) $q->fetchAll() as $row) {
        $active[] = [
          'id' => (int) ($row['id'] ?? 0),
          'thread_type' => (string) ($row['thread_type'] ?? 'group'),
          'thread_id' => (int) ($row['thread_id'] ?? 0),
          'caller_id' => (int) ($row['caller_id'] ?? 0),
          'callee_id' => (int) ($row['callee_id'] ?? 0),
          'call_type' => (string) ($row['call_type'] ?? 'video'),
          'status' => (string) ($row['status'] ?? 'ringing'),
          'started_at' => (string) ($row['started_at'] ?? ''),
          'answered_at' => (string) ($row['answered_at'] ?? ''),
          'ended_at' => (string) ($row['ended_at'] ?? ''),
          'created_at' => (string) ($row['created_at'] ?? ''),
        ];
      }
    } catch (Exception $e) {
    }

    $seenSessionIds = [];
    $dedupedActive = [];
    foreach ($active as $entry) {
      $sid = (int) ($entry['id'] ?? 0);
      if ($sid <= 0 || isset($seenSessionIds[$sid])) {
        continue;
      }
      $seenSessionIds[$sid] = true;
      $dedupedActive[] = $entry;
    }

    $respond(['success' => true, 'incoming' => $incoming, 'active' => $dedupedActive]);
  }

  if ($mode === 'answer') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $sessionId = (int) ($payload['session_id'] ?? 0);
    $decision = strtolower(trim((string) ($payload['decision'] ?? 'accept')));
    $answerPayload = $payload['answer'] ?? null;
    if (!in_array($decision, ['accept', 'reject'], true)) {
      $decision = 'accept';
    }

    $sessionRow = $findCallSession($db, $sessionId);
    if (!$sessionRow) {
      $respond(['success' => false, 'message' => 'Call session not found.'], 404);
    }
    if (!$canAccessCallSession($db, $sessionRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    if ((string) ($sessionRow['thread_type'] ?? '') === 'private' && (int) ($sessionRow['callee_id'] ?? 0) !== $currentUserId) {
      $respond(['success' => false, 'message' => 'Only recipient can answer this call.'], 403);
    }

    try {
      if ($decision === 'accept') {
        $upd = $db->prepare(
          'UPDATE call_sessions
           SET status = "accepted", answered_at = NOW(), updated_at = NOW()
           WHERE id = :id AND status = "ringing"'
        );
        $upd->execute(['id' => $sessionId]);

        if ($answerPayload !== null) {
          $insSignal = $db->prepare(
            'INSERT INTO call_signals (session_id, sender_id, signal_type, payload)
             VALUES (:session_id, :sender_id, "answer", :payload)'
          );
          $insSignal->execute([
            'session_id' => $sessionId,
            'sender_id' => $currentUserId,
            'payload' => $normalizeSignalPayload($answerPayload),
          ]);
        }

        $respond(['success' => true, 'session_id' => $sessionId, 'status' => 'accepted']);
      }

      $upd = $db->prepare(
        'UPDATE call_sessions
         SET status = "rejected", ended_at = NOW(), ended_by = :uid, updated_at = NOW()
         WHERE id = :id AND status = "ringing"'
      );
      $upd->execute(['id' => $sessionId, 'uid' => $currentUserId]);
      $respond(['success' => true, 'session_id' => $sessionId, 'status' => 'rejected']);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not process call response.'], 500);
    }
  }

  if ($mode === 'signal') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $sessionId = (int) ($payload['session_id'] ?? 0);
    $signalType = strtolower(trim((string) ($payload['signal_type'] ?? 'candidate')));
    $signalPayload = $payload['payload'] ?? null;
    if (!in_array($signalType, ['offer', 'answer', 'candidate', 'renegotiate', 'bye'], true)) {
      $signalType = 'candidate';
    }

    $sessionRow = $findCallSession($db, $sessionId);
    if (!$sessionRow) {
      $respond(['success' => false, 'message' => 'Call session not found.'], 404);
    }
    if (!$canAccessCallSession($db, $sessionRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    try {
      $ins = $db->prepare(
        'INSERT INTO call_signals (session_id, sender_id, signal_type, payload)
         VALUES (:session_id, :sender_id, :signal_type, :payload)'
      );
      $ins->execute([
        'session_id' => $sessionId,
        'sender_id' => $currentUserId,
        'signal_type' => $signalType,
        'payload' => $normalizeSignalPayload($signalPayload),
      ]);
      $signalId = (int) $db->lastInsertId();

      if ($signalType === 'bye') {
        $upd = $db->prepare(
          'UPDATE call_sessions
           SET status = "ended", ended_at = NOW(), ended_by = :uid, updated_at = NOW()
           WHERE id = :id AND status IN ("ringing", "accepted")'
        );
        $upd->execute(['id' => $sessionId, 'uid' => $currentUserId]);
      }

      $respond(['success' => true, 'signal_id' => $signalId]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not relay signal.'], 500);
    }
  }

  if ($mode === 'signals') {
    $sessionId = (int) ($payload['session_id'] ?? 0);
    $lastSignalId = (int) ($payload['last_signal_id'] ?? 0);

    $sessionRow = $findCallSession($db, $sessionId);
    if (!$sessionRow) {
      $respond(['success' => false, 'message' => 'Call session not found.'], 404);
    }
    if (!$canAccessCallSession($db, $sessionRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    try {
      $q = $db->prepare(
        'SELECT id, session_id, sender_id, signal_type, payload, created_at
         FROM call_signals
         WHERE session_id = :session_id
           AND id > :last_signal_id
           AND sender_id <> :sender_id
         ORDER BY id ASC
         LIMIT 180'
      );
      $q->execute([
        'session_id' => $sessionId,
        'last_signal_id' => $lastSignalId,
        'sender_id' => $currentUserId,
      ]);

      $signals = [];
      foreach ((array) $q->fetchAll() as $row) {
        $decodedPayload = null;
        $rawPayload = (string) ($row['payload'] ?? '');
        if ($rawPayload !== '') {
          $decodedPayload = json_decode($rawPayload, true);
          if (json_last_error() !== JSON_ERROR_NONE) {
            $decodedPayload = ['value' => $rawPayload];
          }
        }
        $signals[] = [
          'id' => (int) ($row['id'] ?? 0),
          'session_id' => (int) ($row['session_id'] ?? 0),
          'sender_id' => (int) ($row['sender_id'] ?? 0),
          'signal_type' => (string) ($row['signal_type'] ?? 'candidate'),
          'payload' => $decodedPayload,
          'created_at' => (string) ($row['created_at'] ?? ''),
        ];
      }

      $respond(['success' => true, 'signals' => $signals]);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not fetch call signals.'], 500);
    }
  }

  if ($mode === 'end') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $respond(['success' => false, 'message' => 'Method not allowed.'], 405); }

    $sessionId = (int) ($payload['session_id'] ?? 0);
    $reason = trim((string) ($payload['reason'] ?? 'Call ended'));
    $sessionRow = $findCallSession($db, $sessionId);
    if (!$sessionRow) {
      $respond(['success' => false, 'message' => 'Call session not found.'], 404);
    }
    if (!$canAccessCallSession($db, $sessionRow, $currentUserId)) {
      $respond(['success' => false, 'message' => 'Access denied.'], 403);
    }

    try {
      $upd = $db->prepare(
        'UPDATE call_sessions
         SET status = "ended", ended_at = NOW(), ended_by = :uid, updated_at = NOW()
         WHERE id = :id AND status IN ("ringing", "accepted")'
      );
      $upd->execute(['id' => $sessionId, 'uid' => $currentUserId]);

      $ins = $db->prepare(
        'INSERT INTO call_signals (session_id, sender_id, signal_type, payload)
         VALUES (:session_id, :sender_id, "bye", :payload)'
      );
      $ins->execute([
        'session_id' => $sessionId,
        'sender_id' => $currentUserId,
        'payload' => $normalizeSignalPayload(['reason' => $reason]),
      ]);

      $respond(['success' => true, 'session_id' => $sessionId, 'status' => 'ended']);
    } catch (Exception $e) {
      $respond(['success' => false, 'message' => 'Could not end call.'], 500);
    }
  }

  $respond(['success' => false, 'message' => 'Unsupported call mode.'], 400);
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
$displayExactLocationRaw = (string) ($userModel ? ($userModel->getExactLocation() ?? '') : '');
$displayExactLocation    = trim($displayExactLocationRaw) !== '' ? trim($displayExactLocationRaw) : $displayCountry;
$displayLatitude         = $userModel ? $userModel->getLatitude() : null;
$displayLongitude        = $userModel ? $userModel->getLongitude() : null;
$displayBio             = (string) ($userModel ? ($userModel->getBio()    ?? '') : '');
$displaySkills          = (string) ($userModel ? ($userModel->getSkills() ?? '') : '');
$displayPhone           = (string) ($userModel ? ($userModel->getPhone()  ?? '') : '');
$displayAvatarUrl       = (string) ($userModel ? ($userModel->getAvatarUrl() ?? '') : ($sessionUser['avatar_url'] ?? ''));
$displayRole            = (string) ($userModel ? ($userModel->getRole() ?? 'user') : ($sessionUser['role'] ?? 'user'));
$displayRoleNormalized  = in_array(strtolower($displayRole), ['client', 'freelancer'], true) ? strtolower($displayRole) : 'client';
$displayTitle           = $displayRoleNormalized === 'freelancer' ? 'Freelancer' : 'Client';
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
$selfLinkedAccounts     = [];

try {
  $selfLinkedSelectColumns = ['platform', 'account_label', 'username', 'profile_url', 'is_primary', 'is_public'];
  if ($tableHasColumn($db, 'linked_accounts', 'verified')) {
    $selfLinkedSelectColumns[] = 'verified';
  }
  if ($tableHasColumn($db, 'linked_accounts', 'verification_checked_at')) {
    $selfLinkedSelectColumns[] = 'verification_checked_at';
  }

  $q = $db->prepare(
    'SELECT ' . implode(', ', $selfLinkedSelectColumns) . '
     FROM linked_accounts
     WHERE user_id = :uid
     ORDER BY is_primary DESC, created_at ASC'
  );
  $q->execute(['uid' => (int) ($sessionUser['id'] ?? 0)]);
  foreach ((array) $q->fetchAll() as $row) {
    $selfLinkedAccounts[] = [
      'platform' => (string) ($row['platform'] ?? ''),
      'account_label' => (string) ($row['account_label'] ?? ''),
      'username' => (string) ($row['username'] ?? ''),
      'profile_url' => (string) ($row['profile_url'] ?? ''),
      'is_primary' => (int) ($row['is_primary'] ?? 0),
      'is_public' => (int) ($row['is_public'] ?? 1),
      'verified' => (int) ($row['verified'] ?? 0),
      'verification_checked_at' => (string) ($row['verification_checked_at'] ?? ''),
    ];
  }
} catch (Exception $e) {
}

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
       FRIENDS + STORIES SECTION — Horizontal friends rail
    ══════════════════════════════════════════ */
    .friends-section-v2 {
      padding: 18px 18px 14px;
    }

    .friends-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
    }

    .friends-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--color-text-heading, #111827);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .friends-title svg { width: 16px; height: 16px; color: #6366f1; }

    .friends-action {
      font-size: 12px;
      color: var(--color-text-secondary, #6b7280);
      text-decoration: none;
      padding: 6px 10px;
      border-radius: 8px;
      transition: background .18s, color .18s;
    }

    .friends-action:hover { background: rgba(99,102,241,0.04); color: #4f46e5; }

    .friends-rail {
      display: flex;
      gap: 12px;
      overflow-x: auto;
      padding-bottom: 6px;
      -webkit-overflow-scrolling: touch;
      scroll-snap-type: x mandatory;
    }

    .friend-card {
      flex: 0 0 auto;
      width: 84px;
      text-align: center;
      scroll-snap-align: start;
      cursor: pointer;
      transition: transform .18s ease, box-shadow .18s ease;
      border-radius: 12px;
      padding: 6px 4px;
      background: transparent;
    }

    .friend-card:hover { transform: translateY(-6px); }

    .friend-avatar-wrap { position: relative; width: 72px; height: 72px; margin: 0 auto 8px; }

    .friend-avatar {
      width: 72px; height: 72px; border-radius: 50%; display: block; object-fit: cover; border: 3px solid white;
      box-shadow: 0 6px 18px rgba(15,23,42,0.08);
      transition: transform .18s ease;
    }

    .friend-name { font-size: 12px; font-weight: 600; color: var(--color-text, #374151); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .status-indicator {
      position: absolute; bottom: 6px; right: 10px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.12); background: #94a3b8;
    }

    .status-indicator.is-online { background: #10b981; }

    /* story aura ring */
    .story-aura {
      position: absolute; inset: -6px; border-radius: 50%; display: flex; align-items: center; justify-content: center; pointer-events: none;
      background: conic-gradient(from 0deg, rgba(165,94,255,0.95), rgba(99,102,241,0.95), rgba(165,94,255,0.95));
      opacity: 0; transform: scale(0.96);
      transition: opacity .25s ease, transform .25s ease;
    }

    .story-aura.has-story { opacity: 1; transform: scale(1); animation: aura-rotate 3.5s linear infinite; }

    @keyframes aura-rotate { from { transform: rotate(0deg) scale(1); } to { transform: rotate(360deg) scale(1); } }

    .friend-card .story-inner { width: calc(100% - 22px); height: calc(100% - 22px); border-radius: 50%; display:flex; align-items:center; justify-content:center; }

    .friends-loading { color: var(--color-text-muted, #9ca3af); padding: 18px; }

    /* Smooth scrollbar (subtle) */
    .friends-rail::-webkit-scrollbar { height: 8px; }
    .friends-rail::-webkit-scrollbar-thumb { background: rgba(15,23,42,0.08); border-radius: 999px; }

    [data-theme="dark"] .friend-name { color: #e6eef8; }
    [data-theme="dark"] .story-aura { filter: brightness(1.05); }

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

    /* Avatar Story Button V2 */
    .avatar-story-btn-v2 {
      position: absolute;
      bottom: -4px;
      left: -4px;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: linear-gradient(135deg, #6366f1, #a855f7);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 3px solid #ffffff;
      box-shadow: 0 4px 10px rgba(99,102,241,0.3);
      cursor: pointer;
      z-index: 10;
      transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    [data-theme="dark"] .avatar-story-btn-v2 {
      border-color: #0f172a;
    }
    .avatar-story-btn-v2:hover {
      transform: scale(1.15) rotate(90deg);
    }

    /* Premium Network Map */
    .premium-network-map {
      margin-bottom: 24px;
      padding: 24px;
      border-radius: 16px;
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
      background: var(--color-surface-alt, rgba(255,255,255,0.7));
    }
    .network-map-header {
      margin-bottom: 20px;
    }
    .network-map-title-area {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 8px;
    }
    .network-map-h2 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--color-text-heading, #111827);
      margin: 0;
    }
    .network-map-badge {
      font-size: 0.65rem;
      font-weight: 600;
      text-transform: uppercase;
      padding: 3px 8px;
      border-radius: 100px;
      background: rgba(16, 185, 129, 0.1);
      color: #10b981;
      border: 1px solid rgba(16, 185, 129, 0.2);
    }
    .network-map-description {
      font-size: 0.85rem;
      color: var(--color-text-secondary, #6b7280);
      margin: 0;
      max-width: 600px;
    }
    .network-map-canvas-premium {
      height: 400px;
      border-radius: 12px;
      background: var(--color-surface-base, #e5e7eb);
      overflow: hidden;
      border: 1px solid var(--color-border, rgba(0,0,0,0.08));
    }
    
    /* Premium Skills Grid */
    .skills-premium-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: 12px;
      margin-bottom: 24px;
      margin-top: 10px;
    }
    .skill-premium-card {
      padding: 12px;
      border-radius: 12px;
      background: var(--color-surface-alt, rgba(0,0,0,0.02));
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
      transition: all 0.2s ease;
    }
    .skill-premium-card:hover {
      background: rgba(99,102,241,0.04);
      border-color: rgba(99,102,241,0.2);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(99,102,241,0.1);
    }
    .skill-premium-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }
    .skill-premium-name {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--color-text-heading, #111827);
    }
    .skill-premium-pct {
      font-size: 0.7rem;
      font-weight: 700;
      color: var(--color-accent, #6366f1);
    }
    .skill-premium-bar-bg {
      height: 6px;
      border-radius: 3px;
      background: var(--color-border, rgba(0,0,0,0.06));
      overflow: hidden;
    }
    .skill-premium-bar-fill {
      height: 100%;
      border-radius: 3px;
      background: linear-gradient(90deg, #6366f1, #a855f7);
      transform-origin: left;
      transition: transform 1s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .skill-premium-card:hover .skill-premium-bar-fill {
      filter: brightness(1.2);
    }

    /* Premium Certs Grid */
    .certs-premium-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px;
      margin-top: 10px;
    }
    .cert-premium-card {
      position: relative;
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px;
      border-radius: 12px;
      background: var(--color-surface-alt, rgba(0,0,0,0.02));
      border: 1px solid var(--color-border, rgba(0,0,0,0.06));
      transition: all 0.2s ease;
    }
    .cert-premium-card:hover {
      background: rgba(99,102,241,0.03);
      border-color: rgba(99,102,241,0.15);
      transform: translateX(4px);
    }
    .cert-icon-wrapper {
      width: 44px;
      height: 44px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .cert-icon-wrapper.cert-gold { background: rgba(245,158,11,0.15); color: #d97706; }
    .cert-icon-wrapper.cert-indigo { background: rgba(99,102,241,0.15); color: #4f46e5; }
    .cert-icon-wrapper.cert-green { background: rgba(16,185,129,0.15); color: #059669; }
    
    .cert-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .cert-title {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--color-text-heading, #111827);
    }
    .cert-issuer {
      margin: 0;
      font-size: 0.75rem;
      color: var(--color-text-secondary, #6b7280);
    }
    .cert-year {
      position: absolute;
      right: 16px;
      top: 16px;
      font-size: 0.7rem;
      font-weight: 700;
      padding: 4px 8px;
      border-radius: 6px;
      background: var(--color-border, rgba(0,0,0,0.06));
      color: var(--color-text-muted, #9ca3af);
      transition: background 0.2s;
    }
    .cert-premium-card:hover .cert-year {
      background: rgba(99,102,241,0.1);
      color: #6366f1;
    }
    
    /* Global Dark Mode Fixes */
    [data-theme="dark"] .premium-network-map { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.08); }
    [data-theme="dark"] .network-map-h2 { color: #f1f5f9; }
    [data-theme="dark"] .network-map-description { color: #94a3b8; }
    [data-theme="dark"] .network-map-canvas-premium { border-color: rgba(255,255,255,0.1); background: #1e293b; }
    
    [data-theme="dark"] .skill-premium-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
    [data-theme="dark"] .skill-premium-name { color: #f1f5f9; }
    [data-theme="dark"] .skill-premium-bar-bg { background: rgba(255,255,255,0.1); }
    
    [data-theme="dark"] .cert-premium-card { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); }
    [data-theme="dark"] .cert-title { color: #f1f5f9; }
    [data-theme="dark"] .cert-issuer { color: #94a3b8; }
    [data-theme="dark"] .cert-year { background: rgba(255,255,255,0.1); color: #cbd5e1; }

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

    /* Premium Message Button */
    .btn-premium-message {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      border-radius: 999px; /* pill shape */
      background: linear-gradient(135deg, #6C5CE7, #A29BFE);
      color: #fff;
      font-size: 0.85rem;
      font-weight: 600;
      border: none;
      cursor: pointer;
      box-shadow: 0 4px 15px rgba(108, 92, 231, 0.3);
      position: relative;
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      text-decoration: none;
    }
    .btn-premium-message svg {
      width: 16px;
      height: 16px;
      transition: transform 0.3s ease;
    }
    .btn-premium-message:hover {
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 8px 25px rgba(108, 92, 231, 0.45);
      background: linear-gradient(135deg, #7b6df2, #b1aaff);
    }
    .btn-premium-message:hover svg {
      transform: translateX(2px);
    }
    /* Ripple Effect */
    .btn-premium-message::after {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      width: 150%;
      height: 150%;
      background: rgba(255,255,255,0.2);
      transform: translate(-50%, -50%) scale(0);
      border-radius: 50%;
      opacity: 0;
      transition: transform 0.4s ease-out, opacity 0.4s ease-out;
    }
    .btn-premium-message:active::after {
      transform: translate(-50%, -50%) scale(1);
      opacity: 1;
      transition: 0s;
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
            <a href="../BackOffice/dashboardUser.php" class="nav-dropdown-item"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard</a>
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
          <a href="home.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg> Home Feed</a>
          <a href="social.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg> Social</a>
          <a class="active" href="profile.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> Profile</a>
          <a href="JobOffer.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Job Offers</a>
          <a href="projects.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg> Projects & Collaborators</a>
          <a href="reviews.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l2.8 5.7 6.3.9-4.6 4.4 1.1 6.3L12 17.3 6.4 20.3l1.1-6.3L2.9 9.6l6.3-.9L12 3z"/></svg> Contracts</a>
          <a href="challenges.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg> Challenges & Daily Quiz</a>
          <?php if ($isAdminSidebar): ?>
            <div style="margin: 12px 0 6px 12px; font-size: 0.7rem; font-weight: 700; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.08em;">BackOffice</div>
            <a href="../BackOffice/dashboardUser.php"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Dashboard</a>
          <?php endif; ?>
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

              <!-- story CTA placed on the wrap so it can sit outside the avatar frame -->
              <a class="avatar-story-btn-v2" id="openStoryPageBtn" title="Create Story" href="story.php" role="button" aria-label="Create Story">
                <i data-lucide="plus" class="w-4 h-4"></i>
              </a>

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
                <span class="profile-meta-item" id="profileLocationText"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> <?= htmlspecialchars($displayExactLocation !== '' ? $displayExactLocation : 'No location set') ?></span>
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

            <!-- Actions — Edit Details + utilities (restore legacy header actions) -->
            <div class="profile-actions">
              <button class="btn btn-primary btn-sm" id="editToggleBtn">
                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit Details
              </button>
              <button class="profile-action-icon" id="exportProfileBtn" title="Export Data">
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
              <a href="messages.php" class="btn-premium-message">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                Message
              </a>
              <button class="btn btn-primary btn-sm"><i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Connect</button>
              <?php endif; ?>
              <a href="../../index.php?action=logout" class="profile-action-icon action-danger" title="Sign Out">
                <i data-lucide="log-out" class="w-4 h-4"></i>
              </a>
            </div>
          </div>
        </section>

        <!-- ── Top grid: About + Insights ─────────────── -->
        <section class="profile-top-grid" aria-label="Profile overview grid">

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

              <!-- Removed the old Network Map section, now a dedicated premium module full-width -->
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
        </section><!-- /profile-top-grid -->

        <!-- ── Premium Network Map ─────────────────────────── -->
        <section class="premium-network-map fade-in-section" aria-label="Global Network Map">
          <div class="network-map-header">
            <div class="network-map-title-area">
              <h2 class="network-map-h2">Global Network Map</h2>
              <span class="network-map-badge">Live users</span>
            </div>
            <p class="network-map-description">Explore every visible member across the globe. Tap any marker to open a quick profile card and connect instantly.</p>
          </div>
          <div id="snapSocialMap" class="network-map-canvas-premium" role="region" aria-label="Interactive network map"></div>
        </section>

        <!-- ── Content Grid ────────────────────────────── -->
        <section class="profile-grid" aria-label="Profile detail sections">

          <!-- Left column -->
          <article class="profile-left" aria-label="Primary profile content">

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

            <!-- ── Friends & Stories ──────────────── -->
            <div class="glass-card panel fade-in-section">
              <div class="friends-section-v2">
                <div class="friends-header">
                  <div class="friends-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/></svg>
                    Friends
                  </div>
                  <a class="friends-action" href="social.php">See all</a>
                </div>

                <div class="friends-rail" id="friendsRail" role="list" aria-label="Friends with stories">
                  <div class="friends-loading">Loading friends...</div>
                </div>
              </div>
            </div>

            <!-- Connections -->
            <div class="glass-card panel fade-in-section">
              <div class="panel-header">
                <h3 class="panel-title">Connections</h3>
                <button class="panel-action" id="openLinkedAccountsEditorBtn" title="Manage linked accounts"><i data-lucide="link-2" class="w-3.5 h-3.5"></i></button>
              </div>
              <div class="connections-list">
                <?php if (!empty($selfLinkedAccounts)): ?>
                  <?php foreach ($selfLinkedAccounts as $account):
                    $platform = strtolower((string) ($account['platform'] ?? 'link'));
                    $icon = 'link-2';
                    if ($platform === 'github') $icon = 'github';
                    elseif ($platform === 'linkedin') $icon = 'linkedin';
                    elseif ($platform === 'discord') $icon = 'message-square';
                    elseif ($platform === 'portfolio' || $platform === 'website') $icon = 'globe';
                    elseif ($platform === 'twitter') $icon = 'at-sign';
                    $displayLabel = trim((string) ($account['account_label'] ?? ''));
                    if ($displayLabel === '') { $displayLabel = ucfirst($platform); }
                    $displayUsername = trim((string) ($account['username'] ?? ''));
                  ?>
                  <div class="connection-item">
                    <div class="connection-icon"><i data-lucide="<?= htmlspecialchars($icon) ?>" class="w-3.5 h-3.5"></i></div>
                    <div class="connection-info">
                      <span class="connection-name"><?= htmlspecialchars($displayLabel) ?></span>
                      <span class="connection-status"><?= $displayUsername !== '' ? '@' . htmlspecialchars($displayUsername) : 'Linked account' ?></span>
                    </div>
                    <a class="connection-connect-btn" href="<?= htmlspecialchars((string) ($account['profile_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">Open</a>
                  </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="connection-item">
                    <div class="connection-icon"><i data-lucide="link-2" class="w-3.5 h-3.5"></i></div>
                    <div class="connection-info"><span class="connection-name">No linked accounts yet</span><span class="connection-status">Add links to boost profile trust</span></div>
                    <button class="connection-connect-btn" id="quickLinkAccountBtn">Add</button>
                  </div>
                <?php endif; ?>
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

          </article><!-- /profile-left -->

          <!-- Right column -->
          <aside class="profile-right" aria-label="Secondary profile panels">

            <div class="glass-card panel fade-in-section stories-panel" id="storiesPanel">
              <div class="panel-header">
                <h3 class="panel-title">Stories</h3>
                <div class="stories-head-actions">
                  <button class="panel-action blocked-allow" id="openLiveFromStoriesBtn" title="Go live"><i data-lucide="radio" class="w-4 h-4"></i></button>
                  <button class="panel-action blocked-allow" id="openMessagesFromStoriesBtn" title="Open messages"><i data-lucide="messages-square" class="w-4 h-4"></i></button>
                </div>
              </div>
              <div class="stories-active-rail" id="storiesActiveRail">
                <div class="stories-empty">No active stories. Create your first story.</div>
              </div>
              <div class="stories-archive-head">
                <span>Archive</span>
                <button type="button" id="toggleStoriesArchiveBtn">Show</button>
              </div>
              <div class="stories-archive-list" id="storiesArchiveList" hidden>
                <div class="stories-empty">No archived stories yet.</div>
              </div>
            </div>

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

          </aside><!-- /profile-right -->
        </section><!-- /profile-grid -->

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
                    <input class="uf-input" id="formCountry" name="country" type="text" value="<?= htmlspecialchars($displayCountry) ?>" placeholder=" ">
                    <div class="uf-country-meta" aria-live="polite">
                      <span class="uf-country-flag">🌍</span>
                      <span class="uf-country-prefix">No prefix detected</span>
                    </div>
                  </div>
                  <div class="uf-group <?= $displayExactLocation !== '' ? 'has-value' : '' ?>">
                    <label class="uf-label" for="formExactLocation">Exact location</label>
                    <input class="uf-input" id="formExactLocation" name="exact_location" type="text" value="<?= htmlspecialchars($displayExactLocation) ?>" placeholder="City, district, street...">
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
                    <textarea class="uf-input uf-textarea" id="formBio" name="bio" rows="3" maxlength="<?= User::BIO_MAX_LENGTH ?>" placeholder=" "><?= htmlspecialchars($displayBio) ?></textarea>
                  </div>

                  <!-- Hidden -->
                  <input type="hidden" id="formLatitude" name="latitude" value="<?= $displayLatitude !== null ? htmlspecialchars((string) $displayLatitude) : '' ?>">
                  <input type="hidden" id="formLongitude" name="longitude" value="<?= $displayLongitude !== null ? htmlspecialchars((string) $displayLongitude) : '' ?>">
                  <input type="hidden" id="formMapAddress" name="map_address" value="<?= htmlspecialchars($displayExactLocation) ?>">
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

  <!-- Floating messages FAB (Projet-2A) -->
  <button class="messages-fab" id="messagesFab" title="Messages" aria-label="Open Messages">
    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
      <path d="M8 10h8"/>
      <path d="M8 14h4"/>
    </svg>
  </button>

  <div class="linked-accounts-modal" id="linkedAccountsModal" hidden>
    <div class="linked-accounts-card">
      <div class="linked-accounts-head">
        <h3>Linked Accounts</h3>
        <button type="button" class="messages-icon-btn" id="closeLinkedAccountsModalBtn"><i data-lucide="x" class="w-4 h-4"></i></button>
      </div>
      <p class="linked-accounts-sub">Manage your social proof links for map cards and profile credibility.</p>
      <div class="linked-accounts-grid">
        <div class="linked-account-row">
          <label>LinkedIn</label>
          <input type="url" class="form-input linked-account-url" data-platform="linkedin" placeholder="https://linkedin.com/in/username">
        </div>
        <div class="linked-account-row">
          <label>GitHub</label>
          <input type="url" class="form-input linked-account-url" data-platform="github" placeholder="https://github.com/username">
        </div>
        <div class="linked-account-row">
          <label>Discord</label>
          <input type="url" class="form-input linked-account-url" data-platform="discord" placeholder="https://discord.com/users/...">
        </div>
        <div class="linked-account-row">
          <label>Portfolio</label>
          <input type="url" class="form-input linked-account-url" data-platform="portfolio" placeholder="https://your-portfolio.com">
        </div>
      </div>
      <div class="linked-accounts-actions">
        <button type="button" class="btn btn-secondary btn-sm" id="cancelLinkedAccountsBtn">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="saveLinkedAccountsBtn">Save Accounts</button>
      </div>
    </div>
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

  <script>
    window.profileSocialBootstrap = {
      currentUserId: <?= (int) ($sessionUser['id'] ?? 0) ?>,
      currentUserName: <?= json_encode($displayName, JSON_UNESCAPED_SLASHES) ?>,
      currentUserRole: <?= json_encode($displayRoleNormalized, JSON_UNESCAPED_SLASHES) ?>,
      currentUserAvatar: <?= json_encode($displayAvatarResolved, JSON_UNESCAPED_SLASHES) ?>,
      currentUserExactLocation: <?= json_encode($displayExactLocation, JSON_UNESCAPED_SLASHES) ?>,
      linkedAccounts: <?= json_encode($selfLinkedAccounts, JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
  <script>
    (function () {
      document.addEventListener('DOMContentLoaded', function () {
        const rail = document.getElementById('friendsRail');
        if (!rail) { return; }

        function safeText(s){ return String(s ?? ''); }

        fetch('profile.php?action=profile_social_data')
          .then(r => r.json())
          .then(data => {
            const friends = Array.isArray(data.friends) ? data.friends : [];
            const stories = (data.stories && Array.isArray(data.stories.active) ? data.stories.active : (data.stories && data.stories.active) || []);
            const storyUserSet = new Set();
            (stories || []).forEach(s => {
              const uid = Number((s.user && s.user.id) || s.user_id || 0);
              if (uid > 0) storyUserSet.add(uid);
            });

            friends.sort((a,b) => {
              const aHas = storyUserSet.has(Number(a.id)) ? 0 : 1;
              const bHas = storyUserSet.has(Number(b.id)) ? 0 : 1;
              if (aHas !== bHas) return aHas - bHas;
              return safeText(a.first_name || a.firstName || '').localeCompare(safeText(b.first_name || b.firstName || ''));
            });

            rail.innerHTML = '';
            if (!friends.length) {
              rail.innerHTML = '<div class="friends-loading">No friends yet</div>';
              return;
            }

            friends.forEach(f => {
              const id = Number(f.id || 0);
              const first = safeText(f.first_name || f.firstName || '');
              const last = safeText(f.last_name || f.lastName || '');
              const name = (first + ' ' + last).trim() || 'User';
              const avatar = (f.avatar_url && f.avatar_url !== '') ? f.avatar_url : ('https://api.dicebear.com/9.x/adventurer/svg?seed=' + encodeURIComponent(name));
              const hasStory = storyUserSet.has(id);

              const card = document.createElement('div');
              card.className = 'friend-card';
              card.setAttribute('data-user-id', String(id));
              card.innerHTML = '\n                <div class="friend-avatar-wrap">\n                  <div class="story-aura ' + (hasStory ? 'has-story' : '') + '"><div class="story-inner"></div></div>\n                  <img class="friend-avatar" src="' + avatar + '" alt="' + name.replace(/"/g,'') + '" loading="lazy">\n                  <span class="status-indicator ' + ((f.is_online || f.online) ? 'is-online' : '') + '" aria-hidden="true"></span>\n                </div>\n                <div class="friend-name" title="' + name.replace(/"/g,'') + '">' + name + '</div>\n              ';

              const img = card.querySelector('.friend-avatar');
              if (img) {
                img.addEventListener('error', function () { this.src = 'https://api.dicebear.com/9.x/adventurer/svg?seed=' + encodeURIComponent(name); });
              }

              card.querySelector('.friend-avatar').addEventListener('click', function (ev) {
                ev.stopPropagation();
                if (hasStory) {
                  card.classList.add('opening');
                  setTimeout(function () { window.location.href = 'story.php?user_id=' + encodeURIComponent(id); }, 160);
                } else {
                  window.location.href = 'profile.php?user_id=' + encodeURIComponent(id);
                }
              });

              card.querySelector('.friend-name').addEventListener('click', function () {
                window.location.href = 'profile.php?user_id=' + encodeURIComponent(id);
              });

              rail.appendChild(card);
            });
          })
          .catch(err => {
            rail.innerHTML = '<div class="friends-loading">Could not load friends</div>';
            console.error('Friends rail error', err);
          });
      });
    })();
  </script>

  <script src="../../assets/js/main.js"></script>
  <script src="../../assets/js/mouse-tracking.js"></script>
  <script src="../../assets/js/globe-explorer.js"></script>
  <script src="../../assets/js/user.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="../../assets/js/profile.js"></script>
</body>
</html>

