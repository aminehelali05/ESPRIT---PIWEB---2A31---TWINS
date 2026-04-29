<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/User.php');

class UserController
{
    private $modelState;

    public function __construct()
    {
        $this->modelState = new User();
        $this->modelState->setTableName($this->resolveTableName());
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  INTERNALS
    // ═══════════════════════════════════════════════════════════════════════

    private function resolveTableName(): string
    {
        $db = config::getConnexion();
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'users'");
            if ($stmt && $stmt->fetch()) return 'users';
        } catch (Exception $e) {}
        return 'user';
    }

    // ── Column presence helper (cached per column name) ────────────────────
    private function historyHasColumn(string $column): bool
    {
        if ($this->modelState->hasColumnInCache($column)) {
            return $this->modelState->getColumnFromCache($column);
        }
        $db = config::getConnexion();
        try {
            $stmt = $db->query(
                "SHOW COLUMNS FROM user_signin_history LIKE '" . addslashes($column) . "'"
            );
            $this->modelState->setColumnInCache($column, (bool)($stmt && $stmt->fetch()));
        } catch (Exception $e) {
            $this->modelState->setColumnInCache($column, false);
        }
        return $this->modelState->getColumnFromCache($column);
    }

    private function userHasColumn(string $column): bool
    {
        $cacheKey = 'users:' . $column;
        if ($this->modelState->hasColumnInCache($cacheKey)) {
            return $this->modelState->getColumnFromCache($cacheKey);
        }

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $stmt = $db->query(
                "SHOW COLUMNS FROM {$table} LIKE '" . addslashes($column) . "'"
            );
            $this->modelState->setColumnInCache($cacheKey, (bool)($stmt && $stmt->fetch()));
        } catch (Exception $e) {
            $this->modelState->setColumnInCache($cacheKey, false);
        }
        return $this->modelState->getColumnFromCache($cacheKey);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . ':' . $column;
        if ($this->modelState->hasColumnInCache($cacheKey)) {
            return $this->modelState->getColumnFromCache($cacheKey);
        }

        $db = config::getConnexion();
        try {
            $stmt = $db->query(
                "SHOW COLUMNS FROM {$table} LIKE '" . addslashes($column) . "'"
            );
            $this->modelState->setColumnInCache($cacheKey, (bool)($stmt && $stmt->fetch()));
        } catch (Exception $e) {
            $this->modelState->setColumnInCache($cacheKey, false);
        }

        return $this->modelState->getColumnFromCache($cacheKey);
    }

    private function storiesHasColumn(string $column): bool
    {
        return $this->tableHasColumn('stories', $column);
    }

    private function linkedAccountsHasColumn(string $column): bool
    {
        return $this->tableHasColumn('linked_accounts', $column);
    }

    private function passwordResetsHasColumn(string $column): bool
    {
        return $this->tableHasColumn('password_resets', $column);
    }

    private function ensureAuthSchema(): void
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();

        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_password_resets_email (email),
                    INDEX idx_password_resets_token (token),
                    INDEX idx_password_resets_expires_at (expires_at)
                )"
            );
            $this->modelState->setColumnInCache('password_resets:token', true);
        } catch (Exception $e) {
            error_log('UserController::ensureAuthSchema password_resets - ' . $e->getMessage());
        }

        $userColumnDefinitions = [
            'google_id' => "VARCHAR(191) DEFAULT NULL",
            'google_avatar_url' => "VARCHAR(255) DEFAULT NULL",
            'invitation_code' => "VARCHAR(100) DEFAULT NULL",
            'referred_by' => "INT DEFAULT NULL",
        ];

        foreach ($userColumnDefinitions as $column => $definition) {
            if ($this->userHasColumn($column)) {
                continue;
            }

            try {
                $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
                $this->modelState->setColumnInCache('users:' . $column, true);
            } catch (Exception $e) {
                error_log('UserController::ensureAuthSchema ' . $column . ' - ' . $e->getMessage());
            }
        }
    }

    private function generateUniqueInvitationCode(string $firstName = '', string $lastName = ''): string
    {
        $seed = strtoupper(preg_replace('/[^A-Z0-9]+/', '', substr($firstName, 0, 3) . substr($lastName, 0, 3)));
        if ($seed === '') {
            $seed = 'LARP';
        }

        do {
            $candidate = $seed . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $existing = $this->getUserByInvitationCode($candidate);
        } while ($existing instanceof User);

        return $candidate;
    }

    public function getUserByInvitationCode(string $invitationCode): ?User
    {
        $code = trim($invitationCode);
        if ($code === '' || !$this->userHasColumn('invitation_code')) {
            return null;
        }

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare("SELECT * FROM {$table} WHERE invitation_code = :code LIMIT 1");
            $q->execute(['code' => $code]);
            $row = $q->fetch();
            return $row ? $this->hydrateUser($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function friendshipExists(int $firstUserId, int $secondUserId): bool
    {
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            return true;
        }

        $db = config::getConnexion();
        $userOne = min($firstUserId, $secondUserId);
        $userTwo = max($firstUserId, $secondUserId);

        try {
            $q = $db->prepare(
                'SELECT id
                 FROM friends
                 WHERE (user_one_id = :user_one_id AND user_two_id = :user_two_id)
                    OR (user_id = :first_user_id AND friend_id = :second_user_id)
                    OR (user_id = :second_user_id AND friend_id = :first_user_id)
                 LIMIT 1'
            );
            $q->execute([
                'user_one_id' => $userOne,
                'user_two_id' => $userTwo,
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
            ]);
            return (bool) $q->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    private function createAcceptedFriendship(int $firstUserId, int $secondUserId, ?int $sourceRequestId = null): bool
    {
        if ($firstUserId <= 0 || $secondUserId <= 0 || $firstUserId === $secondUserId) {
            return false;
        }

        $db = config::getConnexion();
        $userOne = min($firstUserId, $secondUserId);
        $userTwo = max($firstUserId, $secondUserId);

        try {
            $q = $db->prepare(
                'INSERT INTO friends (user_id, friend_id, user_one_id, user_two_id, source_request_id, status)
                 VALUES (:first_user_id, :second_user_id, :user_one_id, :user_two_id, :source_request_id, "accepted")
                 ON DUPLICATE KEY UPDATE
                   user_id = VALUES(user_id),
                   friend_id = VALUES(friend_id),
                   user_one_id = VALUES(user_one_id),
                   user_two_id = VALUES(user_two_id),
                   source_request_id = COALESCE(VALUES(source_request_id), source_request_id),
                   status = "accepted"'
            );
            return $q->execute([
                'first_user_id' => $firstUserId,
                'second_user_id' => $secondUserId,
                'user_one_id' => $userOne,
                'user_two_id' => $userTwo,
                'source_request_id' => $sourceRequestId,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    private function countryFromLocale(string $locale): string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return '';
        }

        $region = '';
        if (preg_match('/[_-]([A-Z]{2})$/i', $locale, $matches)) {
            $region = strtoupper((string) ($matches[1] ?? ''));
        }
        if ($region === '') {
            return '';
        }

        $lookup = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'MA' => 'Morocco',
            'DZ' => 'Algeria',
            'TN' => 'Tunisia',
            'EG' => 'Egypt',
            'AE' => 'United Arab Emirates',
            'SA' => 'Saudi Arabia',
            'CA' => 'Canada',
            'BR' => 'Brazil',
            'AR' => 'Argentina',
            'MX' => 'Mexico',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'IN' => 'India',
            'TR' => 'Turkey',
        ];

        return $lookup[$region] ?? '';
    }

    private function downloadRemoteAvatar(string $url, string $prefix = 'google_avatar_'): ?string
    {
        $remoteUrl = trim($url);
        if ($remoteUrl === '' || !filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        $ch = curl_init($remoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['User-Agent: LARPLARP OAuth Avatar Fetcher'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $mimeType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $status >= 400) {
            return null;
        }

        $extension = 'jpg';
        if (stripos($mimeType, 'png') !== false) {
            $extension = 'png';
        } elseif (stripos($mimeType, 'webp') !== false) {
            $extension = 'webp';
        } elseif (stripos($mimeType, 'gif') !== false) {
            $extension = 'gif';
        }

        $relativeDir = 'assets/faces';
        $absoluteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relativeDir;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            return null;
        }

        $filename = $prefix . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($absolutePath, $body) === false) {
            return null;
        }

        return $relativeDir . '/' . $filename;
    }

    private function createAuthSessionFromUser(User $user): void
    {
        session_regenerate_id(true);
        $_SESSION['auth_user'] = [
            'id' => (int) $user->getId(),
            'first_name' => (string) $user->getFirstName(),
            'last_name' => (string) $user->getLastName(),
            'email' => (string) $user->getEmail(),
            'role' => (string) $user->getRole(),
            'avatar_url' => $user->getAvatarUrl()
                ?: self::buildGeneratedAvatarUrl(
                    (string) $user->getFirstName(),
                    (string) $user->getLastName(),
                    (string) $user->getEmail()
                ),
        ];
    }

    private function buildAppUrl(string $path, array $query = []): string
    {
        $url = rtrim(config::getBaseUrl(), '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    private function readSmtpLine($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return $response;
    }

    private function smtpCommand($socket, ?string $command, int $expectedCode): string
    {
        if ($command !== null) {
            fwrite($socket, $command . "\r\n");
        }

        $response = $this->readSmtpLine($socket);
        if ((int) substr($response, 0, 3) !== $expectedCode) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }

        return $response;
    }

    private function sendSmtpMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        $host = trim((string) config::get('SMTP_HOST', ''));
        $port = (int) config::get('SMTP_PORT', 587);
        $username = trim((string) config::get('SMTP_USERNAME', ''));
        $password = trim((string) config::get('SMTP_PASSWORD', ''));
        $fromEmail = trim((string) config::get('SMTP_FROM_EMAIL', $username));
        $fromName = trim((string) config::get('SMTP_FROM_NAME', 'LARPLARP Auth'));

        if ($host === '' || $port <= 0 || $username === '' || $password === '' || $fromEmail === '') {
            throw new RuntimeException('SMTP configuration is incomplete.');
        }

        $socket = stream_socket_client($host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException('SMTP connection failed: ' . $errstr);
        }

        try {
            stream_set_timeout($socket, 20);
            $this->smtpCommand($socket, null, 220);
            $this->smtpCommand($socket, 'EHLO localhost', 250);
            $this->smtpCommand($socket, 'STARTTLS', 220);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to enable TLS for SMTP.');
            }

            $this->smtpCommand($socket, 'EHLO localhost', 250);
            $this->smtpCommand($socket, 'AUTH LOGIN', 334);
            $this->smtpCommand($socket, base64_encode($username), 334);
            $this->smtpCommand($socket, base64_encode($password), 235);
            $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', 250);
            $this->smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', 250);
            $this->smtpCommand($socket, 'DATA', 354);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
                'To: ' . sprintf('"%s" <%s>', addslashes($toName !== '' ? $toName : $toEmail), $toEmail),
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $message = implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody . "\r\n.";
            $this->smtpCommand($socket, $message, 250);
            $this->smtpCommand($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function sendPasswordResetEmail(User $user, string $plainToken): void
    {
        $fullName = trim($user->getFullName()) ?: $user->getEmail();
        $resetUrl = $this->buildAppUrl('Views/FrontOffice/reset-password.php', ['token' => $plainToken]);
        $subject = 'Reset your password';
        $body = '<p>Hello ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>We received a request to reset your password.</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Reset your password</a></p>'
            . '<p>This link expires in 1 hour. If you did not request it, you can ignore this email.</p>';

        $this->sendSmtpMail((string) $user->getEmail(), $fullName, $subject, $body);
    }

    private function httpPostForm(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_POSTFIELDS => http_build_query($payload),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response received from remote server.');
        }

        $decoded['_http_status'] = $status;
        return $decoded;
    }

    private function httpGetJson(string $url, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response received from remote server.');
        }

        $decoded['_http_status'] = $status;
        return $decoded;
    }

    private function getGoogleRedirectUri(): string
    {
        $configured = trim((string) config::get('GOOGLE_REDIRECT_URI', ''));
        if ($configured !== '') {
            return $configured;
        }

        return rtrim(config::getBaseUrl(), '/') . '/index.php?action=google_callback';
    }

    private function getUserByGoogleId(string $googleId): ?User
    {
        if ($googleId === '' || !$this->userHasColumn('google_id')) {
            return null;
        }

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare("SELECT * FROM {$table} WHERE google_id = :google_id LIMIT 1");
            $q->execute(['google_id' => $googleId]);
            $row = $q->fetch();
            return $row ? $this->hydrateUser($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function linkGoogleIdentity(int $userId, string $googleId, ?string $googleAvatarUrl = null): bool
    {
        if ($userId <= 0 || $googleId === '' || !$this->userHasColumn('google_id')) {
            return false;
        }

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        $set = ['google_id = :google_id'];
        $params = ['id' => $userId, 'google_id' => $googleId];

        if ($this->userHasColumn('google_avatar_url')) {
            $set[] = 'google_avatar_url = :google_avatar_url';
            $params['google_avatar_url'] = $googleAvatarUrl;
        }
        if ($this->userHasColumn('updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        try {
            $q = $db->prepare("UPDATE {$table} SET " . implode(', ', $set) . " WHERE id = :id");
            return $q->execute($params);
        } catch (Exception $e) {
            return false;
        }
    }

    private function normalizeCountryKey(string $value): string
    {
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

        return trim((string)$normalized);
    }

    private function toCanonicalCountryName(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $upper = strtoupper($raw);
        $codeMap = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'UK' => 'United Kingdom',
            'AE' => 'United Arab Emirates',
            'UAE' => 'United Arab Emirates',
            'KR' => 'South Korea',
            'RU' => 'Russia',
            'TN' => 'Tunisia',
            'FR' => 'France',
            'DE' => 'Germany',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'PT' => 'Portugal',
            'AU' => 'Australia',
            'CA' => 'Canada',
            'MX' => 'Mexico',
            'BR' => 'Brazil',
            'JP' => 'Japan',
            'IN' => 'India',
            'ZA' => 'South Africa',
            'EG' => 'Egypt',
            'SE' => 'Sweden',
            'IE' => 'Ireland',
            'CL' => 'Chile',
            'PE' => 'Peru',
            'CN' => 'China',
            'SN' => 'Senegal',
            'GH' => 'Ghana',
            'SK' => 'South Korea',
            'KOR' => 'South Korea',
        ];
        if (isset($codeMap[$upper])) {
            return $codeMap[$upper];
        }

        $normalized = $this->normalizeCountryKey($raw);
        if ($normalized === '') {
            return $raw;
        }

        $aliasMap = [
            'usa' => 'United States',
            'us' => 'United States',
            'united states of america' => 'United States',
            'uk' => 'United Kingdom',
            'england' => 'United Kingdom',
            'britain' => 'United Kingdom',
            'great britain' => 'United Kingdom',
            'uae' => 'United Arab Emirates',
            'south korea' => 'South Korea',
            'korea republic of' => 'South Korea',
            'russian federation' => 'Russia',
            'turkiye' => 'Turkey',
        ];

        return $aliasMap[$normalized] ?? $raw;
    }

    private function resolveCountryCoordinates(string $countryValue): ?array
    {
        $country = $this->toCanonicalCountryName($countryValue);
        $key = $this->normalizeCountryKey($country);
        if ($key === '') {
            return null;
        }

        $coordinates = [
            'algeria' => ['lat' => 28.0339, 'lng' => 1.6596],
            'argentina' => ['lat' => -34.6037, 'lng' => -58.3816],
            'australia' => ['lat' => -33.8688, 'lng' => 151.2093],
            'austria' => ['lat' => 48.2082, 'lng' => 16.3738],
            'belgium' => ['lat' => 50.8503, 'lng' => 4.3517],
            'brazil' => ['lat' => -15.7939, 'lng' => -47.8828],
            'canada' => ['lat' => 45.4215, 'lng' => -75.6972],
            'chile' => ['lat' => -33.4489, 'lng' => -70.6693],
            'china' => ['lat' => 39.9042, 'lng' => 116.4074],
            'colombia' => ['lat' => 4.7110, 'lng' => -74.0721],
            'denmark' => ['lat' => 55.6761, 'lng' => 12.5683],
            'egypt' => ['lat' => 30.0444, 'lng' => 31.2357],
            'finland' => ['lat' => 60.1699, 'lng' => 24.9384],
            'france' => ['lat' => 48.8566, 'lng' => 2.3522],
            'germany' => ['lat' => 52.5200, 'lng' => 13.4050],
            'ghana' => ['lat' => 5.6037, 'lng' => -0.1870],
            'india' => ['lat' => 28.6139, 'lng' => 77.2090],
            'ireland' => ['lat' => 53.3498, 'lng' => -6.2603],
            'italy' => ['lat' => 41.9028, 'lng' => 12.4964],
            'japan' => ['lat' => 35.6762, 'lng' => 139.6503],
            'mexico' => ['lat' => 19.4326, 'lng' => -99.1332],
            'morocco' => ['lat' => 34.0209, 'lng' => -6.8416],
            'netherlands' => ['lat' => 52.3676, 'lng' => 4.9041],
            'norway' => ['lat' => 59.9139, 'lng' => 10.7522],
            'peru' => ['lat' => -12.0464, 'lng' => -77.0428],
            'poland' => ['lat' => 52.2297, 'lng' => 21.0122],
            'portugal' => ['lat' => 38.7223, 'lng' => -9.1393],
            'russia' => ['lat' => 55.7558, 'lng' => 37.6173],
            'saudi arabia' => ['lat' => 24.7136, 'lng' => 46.6753],
            'senegal' => ['lat' => 14.7167, 'lng' => -17.4677],
            'south africa' => ['lat' => -33.9249, 'lng' => 18.4241],
            'south korea' => ['lat' => 37.5665, 'lng' => 126.9780],
            'spain' => ['lat' => 40.4168, 'lng' => -3.7038],
            'sweden' => ['lat' => 59.3293, 'lng' => 18.0686],
            'switzerland' => ['lat' => 46.9480, 'lng' => 7.4474],
            'tunisia' => ['lat' => 36.8065, 'lng' => 10.1815],
            'turkey' => ['lat' => 39.9334, 'lng' => 32.8597],
            'united arab emirates' => ['lat' => 24.4539, 'lng' => 54.3773],
            'united kingdom' => ['lat' => 51.5072, 'lng' => -0.1276],
            'united states' => ['lat' => 39.8283, 'lng' => -98.5795],
            'usa' => ['lat' => 39.8283, 'lng' => -98.5795],
        ];

        return $coordinates[$key] ?? null;
    }

    private function normalizeGeoPayload(string $countryValue, $latitudeRaw, $longitudeRaw): array
    {
        $canonicalCountry = $this->toCanonicalCountryName($countryValue);

        $latitude = null;
        if ($latitudeRaw !== null && $latitudeRaw !== '' && is_numeric($latitudeRaw)) {
            $parsedLat = (float)$latitudeRaw;
            if ($parsedLat >= -90 && $parsedLat <= 90) {
                $latitude = $parsedLat;
            }
        }

        $longitude = null;
        if ($longitudeRaw !== null && $longitudeRaw !== '' && is_numeric($longitudeRaw)) {
            $parsedLng = (float)$longitudeRaw;
            if ($parsedLng >= -180 && $parsedLng <= 180) {
                $longitude = $parsedLng;
            }
        }

        if (($latitude === null || $longitude === null) && $canonicalCountry !== '') {
            $fallback = $this->resolveCountryCoordinates($canonicalCountry);
            if ($fallback) {
                $latitude = $fallback['lat'];
                $longitude = $fallback['lng'];
            }
        }

        return [
            'country' => $canonicalCountry,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function bioIsTooLong(string $bio): bool
    {
        return function_exists('mb_strlen')
            ? mb_strlen($bio) > User::BIO_MAX_LENGTH
            : strlen($bio) > User::BIO_MAX_LENGTH;
    }

    // ── Real client IP ─────────────────────────────────────────────────────
    /**
     * Returns the most accurate client IP.
     * Skips private / loopback ranges so a real server always returns
     * the visitor's routable IP.  On localhost ::1 / 127.0.0.1 is kept
     * as the only available fallback.
     */
    private function getClientIpAddress(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare (most trusted)
            'HTTP_X_FORWARDED_FOR',    // Load balancer / proxy (comma-list)
            'HTTP_X_REAL_IP',          // Nginx proxy
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',             // Direct connection (last resort)
        ];

        // Returns true for any private / loopback address (IPv4 + IPv6)
        $isPrivate = static function (string $ip): bool {
            $privateRanges = [
                // IPv4
                ['10.0.0.0',   8 ],
                ['172.16.0.0', 12],
                ['192.168.0.0',16],
                ['127.0.0.0',  8 ],
                ['169.254.0.0',16],
                // IPv6 handled separately below
            ];

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $long = ip2long($ip);
                foreach ($privateRanges as [$subnet, $bits]) {
                    $mask = -1 << (32 - $bits);
                    if (($long & $mask) === (ip2long($subnet) & $mask)) {
                        return true;
                    }
                }
                return false;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // Loopback ::1, link-local fe80::/10, ULA fc00::/7
                if ($ip === '::1') return true;
                $packed = inet_pton($ip);
                $fe80   = inet_pton('fe80::');
                $fc00   = inet_pton('fc00::');
                $mask10 = "\xfe\xc0" . str_repeat("\x00", 14); // /10 mask
                $mask7  = "\xfe\x00" . str_repeat("\x00", 14); // /7  mask
                if (($packed & $mask10) === ($fe80 & $mask10)) return true;
                if (($packed & $mask7)  === ($fc00 & $mask7))  return true;
                return false;
            }

            return true; // Unknown format → treat as private
        };

        $fallback = '';

        foreach ($headers as $header) {
            $value = trim((string)($_SERVER[$header] ?? ''));
            if ($value === '') continue;

            // X-Forwarded-For may be "client, proxy1, proxy2"
            $candidates = array_map('trim', explode(',', $value));

            foreach ($candidates as $candidate) {
                if (!filter_var($candidate, FILTER_VALIDATE_IP)) continue;

                if ($isPrivate($candidate)) {
                    // Keep as fallback for pure-localhost environments
                    if ($fallback === '') $fallback = $candidate;
                    continue;
                }

                return $candidate; // First valid public IP wins
            }
        }

        return $fallback; // Localhost / dev environment
    }

    // ── Rich user-agent parser ─────────────────────────────────────────────
    /**
     * Parses a raw User-Agent string.
     *
     * @return array{device_type:string, os:string, browser:string}
     */
    private function parseUserAgent(string $userAgent): array
    {
        $ua = trim($userAgent);

        if ($ua === '') {
            return ['device_type' => 'Unknown', 'os' => 'Unknown', 'browser' => 'Unknown'];
        }

        // ── Device type ───────────────────────────────────────────────────
        if (preg_match('/bot|crawl|spider|slurp|mediapartners|googlebot|bingbot|yandex/i', $ua)) {
            $deviceType = 'Bot';
        } elseif (preg_match('/ipad|tablet|kindle|silk|playbook|nook|(android(?!.*mobile))/i', $ua)) {
            $deviceType = 'Tablet';
        } elseif (preg_match('/mobi|android|iphone|ipod|blackberry|windows phone|opera mini|iemobile/i', $ua)) {
            $deviceType = 'Mobile';
        } else {
            $deviceType = 'Desktop';
        }

        // ── Operating System ──────────────────────────────────────────────
        // Order matters: most-specific patterns first
        $osPatterns = [
            // Windows (map NT version → marketing name)
            '/windows nt 11\.0/i'          => ['Windows 11',    false],
            '/windows nt 10\.0/i'          => ['Windows 10',    false],
            '/windows nt 6\.3/i'           => ['Windows 8.1',   false],
            '/windows nt 6\.2/i'           => ['Windows 8',     false],
            '/windows nt 6\.1/i'           => ['Windows 7',     false],
            '/windows nt 6\.0/i'           => ['Windows Vista', false],
            '/windows nt 5\.1/i'           => ['Windows XP',    false],
            '/windows phone/i'             => ['Windows Phone',  false],
            '/windows/i'                   => ['Windows',        false],
            // Apple — version captured
            '/cpu iphone os ([\d_]+)/i'    => ['iOS',    true],
            '/cpu os ([\d_]+).*ipad/i'     => ['iPadOS', true],
            '/ipad.*os ([\d_]+)/i'         => ['iPadOS', true],
            '/mac os x ([\d_]+)/i'         => ['macOS',  true],
            // Android
            '/android ([\d.]+)/i'          => ['Android', true],
            // Linux distros
            '/ubuntu/i'                    => ['Ubuntu',   false],
            '/fedora/i'                    => ['Fedora',   false],
            '/debian/i'                    => ['Debian',   false],
            '/cros/i'                      => ['ChromeOS', false],
            '/linux/i'                     => ['Linux',    false],
            // Other
            '/blackberry/i'                => ['BlackBerry', false],
        ];

        $os = 'Unknown';
        foreach ($osPatterns as $pattern => [$label, $hasVersion]) {
            if (preg_match($pattern, $ua, $m)) {
                if ($hasVersion && isset($m[1])) {
                    $ver   = str_replace('_', '.', $m[1]);
                    $parts = explode('.', $ver);
                    // Keep max 2 segments (e.g. "14.2" not "14.2.1")
                    $os    = $label . ' ' . implode('.', array_slice($parts, 0, 2));
                } else {
                    $os = $label;
                }
                break;
            }
        }

        // ── Browser (order critical: Edge/OPR before Chrome, Chrome before Safari) ──
        $browserPatterns = [
            '/edg\/([\d.]+)/i'              => 'Edge',
            '/opr\/([\d.]+)/i'              => 'Opera',
            '/opera\/([\d.]+)/i'            => 'Opera',
            '/samsungbrowser\/([\d.]+)/i'   => 'Samsung Browser',
            '/ucbrowser\/([\d.]+)/i'        => 'UC Browser',
            '/firefox\/([\d.]+)/i'          => 'Firefox',
            '/fxios\/([\d.]+)/i'            => 'Firefox iOS',
            '/chrome\/([\d.]+)/i'           => 'Chrome',
            '/crios\/([\d.]+)/i'            => 'Chrome iOS',
            '/version\/([\d.]+).*safari/i'  => 'Safari',
            '/safari\/([\d.]+)/i'           => 'Safari',
            '/msie ([\d.]+)/i'              => 'IE',
            '/trident\/.*rv:([\d.]+)/i'     => 'IE',
        ];

        $browser = 'Unknown';
        foreach ($browserPatterns as $pattern => $label) {
            if (preg_match($pattern, $ua, $m)) {
                $parts   = explode('.', $m[1] ?? '0');
                $ver     = implode('.', array_slice($parts, 0, 2));
                $browser = $label . ' ' . $ver;
                break;
            }
        }

        return [
            'device_type' => $deviceType,
            'os'          => substr($os,      0, 60),
            'browser'     => substr($browser, 0, 100),
        ];
    }

    // ── Hydrate a DB row into a User object ───────────────────────────────
    private function hydrateUser(array $row): User
    {
        $user = new User(
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['email'] ?? '',
            $row['password'] ?? '',
            $row['phone']      ?? '',
            $row['role']       ?? 'user',
            $row['status']     ?? 1,
            $row['created_at'] ?? null
        );
        $user->setId($row['id'] ?? null);
        $user->setAvatarUrl($row['avatar_url']         ?? null);
        $user->setBadge($row['badge']                  ?? null);
        $user->setCountry($row['country']              ?? null);
        $user->setExactLocation($row['exact_location'] ?? null);
        $user->setLatitude($row['latitude']              ?? null);
        $user->setLongitude($row['longitude']            ?? null);
        $user->setBio($row['bio']                      ?? null);
        $user->setTitle($row['title']                  ?? ucfirst((string)($row['role'] ?? 'member')));
        $user->setSkills($row['skills']                ?? null);
        $user->setXp($row['xp']                        ?? 0);
        $user->setIsBlocked($row['is_blocked']         ?? 0);
        $user->setLastSeen($row['last_seen']            ?? null);
        $user->setFaceDescriptor($row['face_descriptor']   ?? null);
        $user->setFaceImagesPath($row['face_images_path']  ?? null);
        $user->setFaceEnrolled($row['face_enrolled']       ?? 0);
        $user->setFaceEnrolledAt($row['face_enrolled_at']  ?? null);
        $user->setUpdatedAt($row['updated_at']         ?? null);
        $user->setInvitationCode($row['invitation_code'] ?? null);
        $user->setReferredBy($row['referred_by'] ?? null);
        return $user;
    }

    private static function buildGeneratedAvatarUrl(
        string $firstName,
        string $lastName,
        string $email = ''
    ): string {
        $seed = trim($firstName . '-' . $lastName . '-' . $email);
        if ($seed === '') {
            $seed = 'diversity-user-' . date('YmdHis');
        }
        return 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . rawurlencode($seed);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  CRUD
    // ═══════════════════════════════════════════════════════════════════════

    public function addUser(User $user)
    {
        $this->modelState->setLastError(null);
        $table = $this->modelState->getTableName();
        $payload = [
            'first_name'     => $user->getFirstName(),
            'last_name'      => $user->getLastName(),
            'email'          => $user->getEmail(),
            'password'       => $user->getPassword(),
            'phone'          => $user->getPhone(),
            'role'           => $user->getRole(),
            'status'         => $user->getStatus(),
            'avatar_url'     => $user->getAvatarUrl(),
            'country'        => $user->getCountry(),
            'exact_location' => $user->getExactLocation(),
            'latitude'       => $user->getLatitude(),
            'longitude'      => $user->getLongitude(),
            'bio'            => $user->getBio(),
            'skills'         => $user->getSkills(),
            'xp'             => $user->getXp(),
            'is_blocked'     => $user->getIsBlocked(),
            'last_seen'      => $user->getLastSeen(),
            'face_images_path' => $user->getFaceImagesPath(),
            'created_at'     => $user->getCreatedAt() ?: date('Y-m-d H:i:s'),
        ];

        // Backward compatibility for installations that still keep legacy columns.
        if ($this->userHasColumn('badge')) {
            $payload['badge'] = $user->getBadge();
        }
        if ($this->userHasColumn('title')) {
            $payload['title'] = $user->getTitle();
        }
        if ($this->userHasColumn('face_descriptor')) {
            $payload['face_descriptor'] = $user->getFaceDescriptor();
        }
        if ($this->userHasColumn('face_enrolled')) {
            $payload['face_enrolled'] = $user->getFaceEnrolled();
        }
        if ($this->userHasColumn('face_enrolled_at')) {
            $payload['face_enrolled_at'] = $user->getFaceEnrolledAt();
        }
        if ($this->userHasColumn('invitation_code')) {
            $payload['invitation_code'] = $user->getInvitationCode();
        }
        if ($this->userHasColumn('referred_by')) {
            $payload['referred_by'] = $user->getReferredBy();
        }

        foreach (array_keys($payload) as $column) {
            if (!$this->userHasColumn($column)) {
                unset($payload[$column]);
            }
        }

        if (empty($payload)) {
            $this->modelState->setLastError('No writable user columns detected.');
            return false;
        }

        $columns = implode(', ', array_keys($payload));
        $placeholders = ':' . implode(', :', array_keys($payload));
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            $q->execute($payload);
            return $db->lastInsertId();
        } catch (Exception $e) {
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::addUser — ' . $e->getMessage());
            return false;
        }
    }

    public function updateUser(User $user, $id): bool
    {
        $this->modelState->setLastError(null);
        $table = $this->modelState->getTableName();
        $payload = [
            'first_name'       => $user->getFirstName(),
            'last_name'        => $user->getLastName(),
            'email'            => $user->getEmail(),
            'password'         => $user->getPassword(),
            'phone'            => $user->getPhone(),
            'role'             => $user->getRole(),
            'status'           => $user->getStatus(),
            'avatar_url'       => $user->getAvatarUrl(),
            'country'          => $user->getCountry(),
            'exact_location'   => $user->getExactLocation(),
            'latitude'         => $user->getLatitude(),
            'longitude'        => $user->getLongitude(),
            'bio'              => $user->getBio(),
            'skills'           => $user->getSkills(),
            'xp'               => $user->getXp(),
            'is_blocked'       => $user->getIsBlocked(),
            'last_seen'        => $user->getLastSeen(),
            'face_images_path' => $user->getFaceImagesPath(),
        ];

        if ($this->userHasColumn('badge')) {
            $payload['badge'] = $user->getBadge();
        }
        if ($this->userHasColumn('title')) {
            $payload['title'] = $user->getTitle();
        }
        if ($this->userHasColumn('face_descriptor')) {
            $payload['face_descriptor'] = $user->getFaceDescriptor();
        }
        if ($this->userHasColumn('face_enrolled')) {
            $payload['face_enrolled'] = $user->getFaceEnrolled();
        }
        if ($this->userHasColumn('face_enrolled_at')) {
            $payload['face_enrolled_at'] = $user->getFaceEnrolledAt();
        }
        if ($this->userHasColumn('invitation_code')) {
            $payload['invitation_code'] = $user->getInvitationCode();
        }
        if ($this->userHasColumn('referred_by')) {
            $payload['referred_by'] = $user->getReferredBy();
        }

        $set = [];
        $params = ['id' => $id];
        foreach ($payload as $column => $value) {
            if (!$this->userHasColumn($column)) {
                continue;
            }
            $set[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        if (empty($set)) {
            $this->modelState->setLastError('No writable user columns detected.');
            return false;
        }

        $sql = "UPDATE {$table} SET\n                    " . implode(",\n                    ", $set) . "\n                WHERE id = :id";
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            $q->execute($params);
            return true;
        } catch (Exception $e) {
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::updateUser — ' . $e->getMessage());
            return false;
        }
    }

    public function deleteUser($id): bool
    {
        $this->modelState->setLastError(null);
        $table = $this->modelState->getTableName();
        $db = config::getConnexion();
        try {
            $q = $db->prepare("DELETE FROM {$table} WHERE id = :id");
            return $q->execute(['id' => $id]);
        } catch (Exception $e) {
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::deleteUser — ' . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  QUERIES
    // ═══════════════════════════════════════════════════════════════════════

    public function listUsers(string $search = ''): array
    {
        $this->modelState->setLastError(null);
        $table  = $this->modelState->getTableName();
        $sql    = "SELECT * FROM {$table}";
        $params = [];
        if ($search !== '') {
            $sql            .= " WHERE first_name LIKE :kw OR last_name LIKE :kw OR email LIKE :kw";
            $params['kw']    = '%' . $search . '%';
        }
        $sql .= ' ORDER BY id DESC';
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            $q->execute($params);
            return array_map([$this, 'hydrateUser'], $q->fetchAll());
        } catch (Exception $e) {
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::listUsers — ' . $e->getMessage());
            return [];
        }
    }

    public function searchUsers(string $keyword): array
    {
        return $this->listUsers(trim($keyword));
    }

    public function getUserById($id): ?User
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare("SELECT * FROM {$table} WHERE id = :id");
            $q->execute(['id' => $id]);
            $row = $q->fetch();
            return $row ? $this->hydrateUser($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getUserByEmail(string $email): ?User
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare("SELECT * FROM {$table} WHERE email = :email LIMIT 1");
            $q->execute(['email' => $email]);
            $row = $q->fetch();
            return $row ? $this->hydrateUser($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function emailExists(string $email, $excludeId = null): bool
    {
        $table  = $this->modelState->getTableName();
        $sql    = "SELECT id FROM {$table} WHERE email = :email";
        $params = ['email' => $email];
        if ($excludeId !== null) {
            $sql             .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            $q->execute($params);
            return (bool)$q->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  AUTH
    // ═══════════════════════════════════════════════════════════════════════

    public function loginUser(string $email, string $password): ?User
    {
        $user = $this->getUserByEmail($email);
        if (!$user || (int)$user->getIsBlocked() === 1) return null;
        if (!password_verify($password, $user->getPassword()))  return null;

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare("UPDATE {$table} SET last_seen = NOW() WHERE id = :id");
            $q->execute(['id' => $user->getId()]);
        } catch (Exception $e) {}

        $this->recordSignIn((int)$user->getId());
        return $this->getUserById($user->getId());
    }

    public function login(string $email, string $password): array
    {
        $email    = trim($email);
        $password = (string)$password;

        if ($email === '' || $password === '') {
            return ['success' => false, 'message' => 'Email and password are required.'];
        }

        if (strtolower($email) === 'admin@diversity.is') {
            if ($password !== 'amineTemplar') {
                return ['success' => false, 'message' => 'Invalid administrator credentials.'];
            }

            $adminAvatar = self::buildGeneratedAvatarUrl('Admin', 'Diversity', 'admin@diversity.is');
            $adminUser   = $this->getUserByEmail('admin@diversity.is');
            $adminId     = $adminUser ? (int)$adminUser->getId() : 1;

            if ($adminUser) {
                $this->recordSignIn($adminId);
            }

            $_SESSION['auth_user'] = [
                'id'         => $adminId,
                'first_name' => 'Admin',
                'last_name'  => 'Diversity',
                'email'      => 'admin@diversity.is',
                'role'       => 'admin',
                'avatar_url' => $adminAvatar,
            ];

            return ['success' => true, 'message' => 'Administrator login successful.'];
        }

        $user = $this->loginUser($email, $password);
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials or blocked account.'];
        }

        $this->createAuthSessionFromUser($user);

        return ['success' => true, 'message' => 'Login successful.'];
    }

    public function register(array $data): array
    {
        $this->ensureAuthSchema();

        $firstName   = trim((string)($data['first_name']   ?? ''));
        $lastName    = trim((string)($data['last_name']    ?? ''));
        $email       = trim((string)($data['email']        ?? ''));
        $password    = (string)($data['password']          ?? '');
        $phone       = trim((string)($data['phone']        ?? ''));
        $country     = trim((string)($data['country']      ?? ''));
        $exactLocation = trim((string)($data['exact_location'] ?? ''));
        $accountType = strtolower(trim((string)($data['account_type'] ?? 'freelancer')));
        $title       = trim((string)($data['title']        ?? ''));
        $bio         = trim((string)($data['bio']          ?? ''));
        $skills      = trim((string)($data['skills']       ?? ''));
        $avatarUrl   = trim((string)($data['avatar_url']   ?? ''));
        $referralCode = strtoupper(trim((string)($data['referral_code'] ?? '')));
        $referrer = $referralCode !== '' ? $this->getUserByInvitationCode($referralCode) : null;

        $geoSeed = $country !== '' ? $country : $exactLocation;
        $geo = $this->normalizeGeoPayload(
            $geoSeed,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null
        );
        if ($country === '' && $geo['country'] !== '') {
            $country = $geo['country'];
        }
        if ($exactLocation === '' && $country !== '') {
            $exactLocation = $country;
        }

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '') {
            return ['success' => false, 'message' => 'All registration fields are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }
        if (!in_array($accountType, ['freelancer', 'client'], true)) {
            $accountType = 'freelancer';
        }
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'This email already exists.'];
        }
        if ($this->bioIsTooLong($bio)) {
            return ['success' => false, 'message' => 'Bio must not exceed ' . User::BIO_MAX_LENGTH . ' characters.'];
        }

        $user = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone,
            $accountType,
            1,
            date('Y-m-d H:i:s')
        );

        $user->setCountry($country !== '' ? $country : null);
        $user->setExactLocation($exactLocation !== '' ? $exactLocation : ($country !== '' ? $country : null));
        $user->setLatitude($geo['latitude']);
        $user->setLongitude($geo['longitude']);
        $user->setTitle($title !== '' ? $title : ($accountType === 'client' ? 'Client' : 'Freelancer'));
        $user->setBio($bio !== ''
            ? $bio
            : 'Proud member of Diversity.is, building meaningful collaborations every day. Member since '
              . date('F d, Y') . '.'
        );
        $user->setSkills($skills !== '' ? $skills : '');

        $finalAvatarUrl = $avatarUrl !== ''
            ? $avatarUrl
            : self::buildGeneratedAvatarUrl($firstName, $lastName, $email);

        $user->setAvatarUrl($finalAvatarUrl);
        $user->setXp(0);
        $user->setIsBlocked(0);
        $user->setInvitationCode($this->generateUniqueInvitationCode($firstName, $lastName));
        if ($referrer instanceof User && (int) $referrer->getId() > 0) {
            $user->setReferredBy((int) $referrer->getId());
        }

        $newId = $this->addUser($user);
        if (!$newId) {
            $details = (string)$this->modelState->getLastError();
            return [
                'success' => false,
                'message' => $details !== ''
                    ? 'Could not create account: ' . $details
                    : 'Could not create account right now.',
            ];
        }

        if ($referrer instanceof User && (int) $referrer->getId() > 0) {
            $this->createAcceptedFriendship((int) $newId, (int) $referrer->getId());
        }

        $this->recordSignIn((int)$newId);

        $freshUser = $this->getUserById((int) $newId);
        if ($freshUser instanceof User) {
            $this->createAuthSessionFromUser($freshUser);
        }

        return ['success' => true, 'message' => 'Account created successfully.'];
    }

    public function requestPasswordReset(string $email): array
    {
        $this->ensureAuthSchema();

        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        $user = $this->getUserByEmail($email);
        if (!$user) {
            return ['success' => true, 'message' => 'If an account exists for that email, a reset link has been sent.'];
        }

        $plainToken = bin2hex(random_bytes(32));
        $storedToken = hash('sha256', $plainToken);
        $db = config::getConnexion();

        try {
            $db->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $email]);
            $db->prepare(
                'INSERT INTO password_resets (email, token, expires_at, created_at)
                 VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())'
            )->execute([
                'email' => $email,
                'token' => $storedToken,
            ]);

            $this->sendPasswordResetEmail($user, $plainToken);
        } catch (Throwable $e) {
            error_log('UserController::requestPasswordReset - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to send the reset email right now.'];
        }

        return ['success' => true, 'message' => 'If an account exists for that email, a reset link has been sent.'];
    }

    public function isPasswordResetTokenValid(string $token): bool
    {
        $this->ensureAuthSchema();
        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                'SELECT id FROM password_resets
                 WHERE token = :token AND expires_at > NOW()
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $q->execute(['token' => hash('sha256', $token)]);
            return (bool) $q->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function resetPasswordWithToken(string $token, string $newPassword): array
    {
        $this->ensureAuthSchema();
        $token = trim($token);
        if ($token === '') {
            return ['success' => false, 'message' => 'Reset token is missing.'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        $db = config::getConnexion();

        try {
            $q = $db->prepare(
                'SELECT email FROM password_resets
                 WHERE token = :token AND expires_at > NOW()
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $q->execute(['token' => hash('sha256', $token)]);
            $resetRow = $q->fetch();
            if (!$resetRow) {
                return ['success' => false, 'message' => 'This reset link is invalid or has expired.'];
            }

            $email = (string) ($resetRow['email'] ?? '');
            $user = $this->getUserByEmail($email);
            if (!$user) {
                return ['success' => false, 'message' => 'The account associated with this reset link was not found.'];
            }

            $db->beginTransaction();
            $updated = $this->changePassword((int) $user->getId(), $newPassword);
            if (!$updated) {
                throw new RuntimeException('Could not update the password.');
            }

            $db->prepare('DELETE FROM password_resets WHERE email = :email')->execute(['email' => $email]);
            $db->commit();

            return ['success' => true, 'message' => 'Your password has been updated.'];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('UserController::resetPasswordWithToken - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to reset the password right now.'];
        }
    }

    public function getGoogleAuthorizationUrl(): array
    {
        $this->ensureAuthSchema();

        $clientId = trim((string) config::get('GOOGLE_CLIENT_ID', ''));
        if ($clientId === '') {
            return ['success' => false, 'message' => 'Google OAuth client ID is not configured.'];
        }

        $state = bin2hex(random_bytes(24));
        $_SESSION['google_oauth_state'] = $state;

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $this->getGoogleRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
            'access_type' => 'online',
            'include_granted_scopes' => 'true',
        ]);

        return ['success' => true, 'url' => $url];
    }

    public function handleGoogleOAuthCallback(string $code, string $state): array
    {
        $this->ensureAuthSchema();

        $expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
        unset($_SESSION['google_oauth_state']);

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            return ['success' => false, 'message' => 'Invalid Google OAuth state.'];
        }

        $clientId = trim((string) config::get('GOOGLE_CLIENT_ID', ''));
        $clientSecret = trim((string) config::get('GOOGLE_CLIENT_SECRET', ''));
        if ($clientId === '' || $clientSecret === '') {
            return ['success' => false, 'message' => 'Google OAuth client secret is not configured yet.'];
        }

        try {
            $tokenResponse = $this->httpPostForm('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $this->getGoogleRedirectUri(),
                'grant_type' => 'authorization_code',
            ]);

            if (!empty($tokenResponse['error'])) {
                throw new RuntimeException((string) ($tokenResponse['error_description'] ?? $tokenResponse['error']));
            }

            $accessToken = (string) ($tokenResponse['access_token'] ?? '');
            if ($accessToken === '') {
                throw new RuntimeException('Google did not return an access token.');
            }

            $profile = $this->httpGetJson(
                'https://www.googleapis.com/oauth2/v3/userinfo',
                ['Authorization: Bearer ' . $accessToken]
            );

            $googleId = trim((string) ($profile['sub'] ?? ''));
            $email = trim((string) ($profile['email'] ?? ''));
            $firstName = trim((string) ($profile['given_name'] ?? 'Google'));
            $lastName = trim((string) ($profile['family_name'] ?? 'User'));
            $avatarUrl = trim((string) ($profile['picture'] ?? ''));
            $country = $this->countryFromLocale((string) ($profile['locale'] ?? ''));
            $storedAvatarUrl = $this->downloadRemoteAvatar($avatarUrl);

            if ($googleId === '' || $email === '') {
                throw new RuntimeException('Google did not return a valid user profile.');
            }

            $user = $this->getUserByGoogleId($googleId);
            if (!$user) {
                $user = $this->getUserByEmail($email);
                if ($user) {
                    $this->linkGoogleIdentity((int) $user->getId(), $googleId, $avatarUrl !== '' ? $avatarUrl : null);
                    $profileUpdates = [];
                    if ($storedAvatarUrl) {
                        $profileUpdates['avatar_url'] = $storedAvatarUrl;
                    }
                    if ($country !== '' && trim((string) $user->getCountry()) === '') {
                        $profileUpdates['country'] = $country;
                        $profileUpdates['exact_location'] = $country;
                    }
                    if (!empty($profileUpdates)) {
                        $this->updateProfile((int) $user->getId(), $profileUpdates);
                    }
                    $user = $this->getUserById((int) $user->getId());
                }
            }

            if (!$user) {
                $newUser = new User(
                    $firstName !== '' ? $firstName : 'Google',
                    $lastName !== '' ? $lastName : 'User',
                    $email,
                    password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
                    '',
                    'freelancer',
                    1,
                    date('Y-m-d H:i:s')
                );
                $newUser->setAvatarUrl($storedAvatarUrl ?: self::buildGeneratedAvatarUrl($firstName, $lastName, $email));
                $newUser->setCountry($country !== '' ? $country : null);
                $newUser->setExactLocation($country !== '' ? $country : null);
                $newUser->setInvitationCode($this->generateUniqueInvitationCode($firstName, $lastName));
                $newId = $this->addUser($newUser);

                if (!$newId) {
                    throw new RuntimeException('Could not create a new account from Google login.');
                }

                $this->linkGoogleIdentity((int) $newId, $googleId, $avatarUrl !== '' ? $avatarUrl : null);

                $user = $this->getUserById((int) $newId);
            }

            if (!$user instanceof User) {
                throw new RuntimeException('Unable to finish Google login.');
            }

            $this->recordSignIn((int) $user->getId());
            $this->createAuthSessionFromUser($user);

            return ['success' => true, 'message' => 'Google login successful.'];
        } catch (Throwable $e) {
            error_log('UserController::handleGoogleOAuthCallback - ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function logout(): void
    {
        unset($_SESSION['auth_user']);
        session_regenerate_id(true);
    }

    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['auth_user']['id']);
    }

    public static function currentUser(): ?array
    {
        return self::isAuthenticated() ? $_SESSION['auth_user'] : null;
    }

    public static function isAdmin(): bool
    {
        if (!self::isAuthenticated()) return false;
        return strtolower(trim((string)($_SESSION['auth_user']['email'] ?? '')))
               === 'admin@diversity.is';
    }

    // ── Sign-in history ────────────────────────────────────────────────────

    /**
     * Saves a sign-in record with real IP, device type, OS and browser.
     */
    public function recordSignIn(int $userId, string $ipAddress = '', string $userAgent = ''): bool
    {
        if ($userId <= 0) return false;

        // Resolve IP and user-agent
        $ipAddress = trim($ipAddress) !== ''
            ? trim($ipAddress)
            : $this->getClientIpAddress();

        $userAgent = trim($userAgent) !== ''
            ? trim($userAgent)
            : (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Parse device / OS / browser
        $parsed = $this->parseUserAgent($userAgent);

        // Check which optional columns exist in this installation
        $hasDevice  = $this->historyHasColumn('device_type');
        $hasOs      = $this->historyHasColumn('os');
        $hasBrowser = $this->historyHasColumn('browser');

        // Build INSERT dynamically so it works even on partial schemas
        $cols   = ['user_id', 'signed_in_at', 'ip_address', 'user_agent'];
        $vals   = [':user_id', 'NOW()',        ':ip_address', ':user_agent'];
        $params = [
            'user_id'    => $userId,
            'ip_address' => $ipAddress !== '' ? $ipAddress : null,
            'user_agent' => $userAgent !== '' ? substr($userAgent, 0, 255) : null,
        ];

        if ($hasDevice) {
            $cols[]                = 'device_type';
            $vals[]                = ':device_type';
            $params['device_type'] = $parsed['device_type'];
        }
        if ($hasOs) {
            $cols[]       = 'os';
            $vals[]       = ':os';
            $params['os'] = $parsed['os'];
        }
        if ($hasBrowser) {
            $cols[]           = 'browser';
            $vals[]           = ':browser';
            $params['browser'] = $parsed['browser'];
        }

        $sql = 'INSERT INTO user_signin_history ('
             . implode(', ', $cols)
             . ') VALUES ('
             . implode(', ', $vals)
             . ')';

        $db = config::getConnexion();
        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            error_log('UserController::recordSignIn — ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns recent sign-in records for a user, including os and browser.
     */
    public function getSignInHistory(int $userId, int $limit = 20): array
    {
        if ($userId <= 0) return [];

        $limit = max(1, min(120, $limit));
        $db    = config::getConnexion();

        // Select only columns that actually exist
        $select = ['signed_in_at', 'ip_address', 'user_agent'];
        if ($this->historyHasColumn('device_type')) $select[] = 'device_type';
        if ($this->historyHasColumn('os'))          $select[] = 'os';
        if ($this->historyHasColumn('browser'))     $select[] = 'browser';

        $sql = 'SELECT ' . implode(', ', $select)
             . ' FROM user_signin_history'
             . ' WHERE user_id = :user_id'
             . ' ORDER BY signed_in_at DESC'
             . " LIMIT {$limit}";

        try {
            $q = $db->prepare($sql);
            $q->execute(['user_id' => $userId]);
            $rows = $q->fetchAll();

            // Back-fill device_type from user_agent when column is missing
            foreach ($rows as &$row) {
                if (empty(trim((string)($row['device_type'] ?? '')))) {
                    $p = $this->parseUserAgent((string)($row['user_agent'] ?? ''));
                    $row['device_type'] = $p['device_type'];
                    $row['os']          = $row['os']      ?? $p['os'];
                    $row['browser']     = $row['browser'] ?? $p['browser'];
                }
            }
            unset($row);

            return is_array($rows) ? $rows : [];
        } catch (Exception $e) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  PAGINATION / COUNTS
    // ═══════════════════════════════════════════════════════════════════════

    public function countUsers(string $search = ''): int
    {
        $table  = $this->modelState->getTableName();
        $sql    = "SELECT COUNT(*) AS cnt FROM {$table}";
        $params = [];
        if ($search !== '') {
            $sql            .= " WHERE first_name LIKE :kw OR last_name LIKE :kw OR email LIKE :kw";
            $params['kw']    = '%' . $search . '%';
        }
        $db = config::getConnexion();
        $q  = $db->prepare($sql);
        $q->execute($params);
        return (int)(($q->fetch())['cnt'] ?? 0);
    }

    public function getUsersPage(int $page = 1, int $perPage = 8, string $search = ''): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);

        $where  = '';
        $params = [];
        if ($search !== '') {
            $where          = " WHERE first_name LIKE :kw OR last_name LIKE :kw OR email LIKE :kw";
            $params['kw']   = '%' . $search . '%';
        }

        $db    = config::getConnexion();
        $table = $this->modelState->getTableName();
        $totalQ = $db->prepare("SELECT COUNT(*) AS cnt FROM {$table}{$where}");
        $totalQ->execute($params);
        $total = (int)(($totalQ->fetch())['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;

        $q = $db->prepare("SELECT * FROM {$table}{$where} ORDER BY id DESC LIMIT {$offset}, {$perPage}");
        $q->execute($params);
        $users = array_map([$this, 'hydrateUser'], $q->fetchAll());

        return compact('users', 'total', 'pages', 'page', 'perPage', 'search');
    }

    public function countByRole(string $role): int
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        $q  = $db->prepare("SELECT COUNT(*) AS cnt FROM {$table} WHERE role = :role");
        $q->execute(['role' => $role]);
        return (int)(($q->fetch())['cnt'] ?? 0);
    }

    public function countActiveUsers(): int
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        $q  = $db->prepare("SELECT COUNT(*) AS cnt FROM {$table} WHERE status = 1");
        $q->execute();
        return (int)(($q->fetch())['cnt'] ?? 0);
    }

    public function getProfileExportSummary(int $userId): array
    {
        $user = $this->getUserById($userId);
        if (!$user instanceof User) {
            return [];
        }

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        $summary = [
            'user' => [
                'id' => (int) $user->getId(),
                'full_name' => trim((string) $user->getFullName()),
                'email' => (string) $user->getEmail(),
                'role' => (string) $user->getRole(),
                'title' => (string) ($user->getTitle() ?? ''),
                'phone' => (string) ($user->getPhone() ?? ''),
                'country' => (string) ($user->getCountry() ?? ''),
                'exact_location' => (string) ($user->getExactLocation() ?? ''),
                'bio' => (string) ($user->getBio() ?? ''),
                'skills' => (string) ($user->getSkills() ?? ''),
                'avatar_url' => (string) ($user->getAvatarUrl() ?? ''),
                'created_at' => (string) ($user->getCreatedAt() ?? ''),
                'last_seen' => (string) ($user->getLastSeen() ?? ''),
                'invitation_code' => (string) ($user->getInvitationCode() ?? ''),
                'referred_by' => (int) ($user->getReferredBy() ?? 0),
            ],
            'stats' => [
                'friends' => 0,
                'messages' => 0,
                'stories' => 0,
                'active_stories' => 0,
                'archived_stories' => 0,
                'private_conversations' => 0,
                'group_chats' => 0,
                'live_sessions' => 0,
                'story_views' => 0,
            ],
            'activity' => [
                'latest_story_at' => '',
                'latest_message_at' => '',
                'latest_live_at' => '',
            ],
        ];

        try {
            $q = $db->prepare(
                'SELECT COUNT(*) AS cnt
                 FROM friends
                 WHERE (user_one_id = :uid OR user_two_id = :uid)
                   AND status = "accepted"'
            );
            $q->execute(['uid' => $userId]);
            $summary['stats']['friends'] = (int) (($q->fetch())['cnt'] ?? 0);
        } catch (Exception $e) {
        }

        try {
            $q = $db->prepare(
                'SELECT
                    SUM(CASE WHEN private_conversation_id IS NOT NULL THEN 1 ELSE 0 END) AS private_messages,
                    SUM(CASE WHEN group_chat_id IS NOT NULL THEN 1 ELSE 0 END) AS group_messages,
                    MAX(created_at) AS latest_message_at
                 FROM messages
                 WHERE sender_id = :uid'
            );
            $q->execute(['uid' => $userId]);
            $row = $q->fetch() ?: [];
            $summary['stats']['messages'] = (int) ($row['private_messages'] ?? 0) + (int) ($row['group_messages'] ?? 0);
            $summary['activity']['latest_message_at'] = (string) ($row['latest_message_at'] ?? '');
        } catch (Exception $e) {
        }

        try {
            $q = $db->prepare(
                'SELECT
                    COUNT(*) AS total_stories,
                    SUM(CASE WHEN is_archived = 0 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS active_stories,
                    SUM(CASE WHEN is_archived = 1 OR (expires_at IS NOT NULL AND expires_at <= NOW()) THEN 1 ELSE 0 END) AS archived_stories,
                    MAX(created_at) AS latest_story_at
                 FROM stories
                 WHERE user_id = :uid'
            );
            $q->execute(['uid' => $userId]);
            $row = $q->fetch() ?: [];
            $summary['stats']['stories'] = (int) ($row['total_stories'] ?? 0);
            $summary['stats']['active_stories'] = (int) ($row['active_stories'] ?? 0);
            $summary['stats']['archived_stories'] = (int) ($row['archived_stories'] ?? 0);
            $summary['activity']['latest_story_at'] = (string) ($row['latest_story_at'] ?? '');
        } catch (Exception $e) {
        }

        try {
            $q = $db->prepare(
                'SELECT COUNT(*) AS cnt
                 FROM private_conversations
                 WHERE user_one_id = :uid OR user_two_id = :uid'
            );
            $q->execute(['uid' => $userId]);
            $summary['stats']['private_conversations'] = (int) (($q->fetch())['cnt'] ?? 0);
        } catch (Exception $e) {
        }

        try {
            $q = $db->prepare(
                'SELECT COUNT(DISTINCT group_chat_id) AS cnt
                 FROM group_chat_members
                 WHERE user_id = :uid AND left_at IS NULL'
            );
            $q->execute(['uid' => $userId]);
            $summary['stats']['group_chats'] = (int) (($q->fetch())['cnt'] ?? 0);
        } catch (Exception $e) {
        }

        try {
            $q = $db->prepare(
                'SELECT COUNT(*) AS cnt, MAX(created_at) AS latest_live_at
                 FROM live_streams
                 WHERE host_user_id = :uid'
            );
            $q->execute(['uid' => $userId]);
            $row = $q->fetch() ?: [];
            $summary['stats']['live_sessions'] = (int) ($row['cnt'] ?? 0);
            $summary['activity']['latest_live_at'] = (string) ($row['latest_live_at'] ?? '');
        } catch (Exception $e) {
        }

        try {
            $q = $db->prepare(
                'SELECT COUNT(sv.id) AS cnt
                 FROM story_views sv
                 INNER JOIN stories s ON s.id = sv.story_id
                 WHERE s.user_id = :uid'
            );
            $q->execute(['uid' => $userId]);
            $summary['stats']['story_views'] = (int) (($q->fetch())['cnt'] ?? 0);
        } catch (Exception $e) {
        }

        if ($summary['user']['referred_by'] > 0) {
            try {
                $q = $db->prepare("SELECT first_name, last_name, email FROM {$table} WHERE id = :id LIMIT 1");
                $q->execute(['id' => $summary['user']['referred_by']]);
                $referrer = $q->fetch() ?: [];
                $summary['user']['referrer_name'] = trim((string) (($referrer['first_name'] ?? '') . ' ' . ($referrer['last_name'] ?? '')));
                $summary['user']['referrer_email'] = (string) ($referrer['email'] ?? '');
            } catch (Exception $e) {
            }
        }

        return $summary;
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  MODERATION
    // ═══════════════════════════════════════════════════════════════════════

    public function toggleBlock($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $user = $this->getUserById($id);
        if (!$user) return false;

        $user->setIsBlocked((int)$user->getIsBlocked() === 1 ? 0 : 1);
        $this->updateUser($user, $id);
        return (int)$user->getIsBlocked();
    }

    public function updateProfile(int $id, array $data): bool
    {
        if ($id <= 0) return false;

        $table = $this->modelState->getTableName();

        $payload = [];
        $roleNormalizer = static function (string $role): string {
            $cleanRole = strtolower(trim($role));
            return in_array($cleanRole, ['client', 'freelancer', 'user', 'admin'], true) ? $cleanRole : 'client';
        };

        $stringFields = ['first_name', 'last_name', 'email', 'phone', 'country', 'exact_location', 'skills'];
        foreach ($stringFields as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = trim((string)$data[$field]);
            }
        }

        if (array_key_exists('role', $data)) {
            $payload['role'] = $roleNormalizer((string)$data['role']);
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = ((string) ($data['status'] ?? '1')) === '0' ? 0 : 1;
        }

        if (array_key_exists('bio', $data)) {
            $bio = trim((string)$data['bio']);
            if ($this->bioIsTooLong($bio)) {
                return false;
            }
            $payload['bio'] = $bio;
        }

        if (array_key_exists('avatar_url', $data)) {
            $avatarValue = trim((string)$data['avatar_url']);
            $payload['avatar_url'] = $avatarValue !== '' ? $avatarValue : null;
        }

        if (array_key_exists('email', $payload)) {
            $email = trim((string) $payload['email']);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
            if ($this->emailExists($email, $id)) {
                return false;
            }
            $payload['email'] = $email;
        }

        if (array_key_exists('latitude', $data)) {
            $payload['latitude'] = (isset($data['latitude']) && $data['latitude'] !== '' && is_numeric($data['latitude']))
                ? (float)$data['latitude']
                : null;
        }

        if (array_key_exists('longitude', $data)) {
            $payload['longitude'] = (isset($data['longitude']) && $data['longitude'] !== '' && is_numeric($data['longitude']))
                ? (float)$data['longitude']
                : null;
        }

        if ($this->userHasColumn('title') && array_key_exists('title', $data)) {
            $payload['title'] = trim((string)$data['title']);
        }

        $shouldRecalculateCoordinates = array_key_exists('country', $payload)
            || array_key_exists('latitude', $payload)
            || array_key_exists('longitude', $payload);

        if ($shouldRecalculateCoordinates) {
            $geoCountrySeed = trim((string) ($payload['country'] ?? ''));
            if ($geoCountrySeed === '') {
                $geoCountrySeed = trim((string) ($payload['exact_location'] ?? ''));
            }
            $geo = $this->normalizeGeoPayload(
                $geoCountrySeed,
                $payload['latitude'] ?? null,
                $payload['longitude'] ?? null
            );

            if (array_key_exists('country', $payload)) {
                if ($geo['country'] !== '') {
                    $payload['country'] = $geo['country'];
                }
            }

            if (array_key_exists('exact_location', $payload)
                && trim((string)$payload['exact_location']) === ''
                && trim((string)($payload['country'] ?? '')) !== '') {
                $payload['exact_location'] = (string)$payload['country'];
            }

            if ($this->userHasColumn('latitude')) {
                $payload['latitude'] = $geo['latitude'];
            }
            if ($this->userHasColumn('longitude')) {
                $payload['longitude'] = $geo['longitude'];
            }
        }

        $set = [];
        $params = ['id' => $id];
        foreach ($payload as $column => $value) {
            if (!$this->userHasColumn($column)) {
                continue;
            }
            $set[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        if ($this->userHasColumn('updated_at')) {
            $set[] = 'updated_at = NOW()';
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE {$table} SET\n                    " . implode(",\n                    ", $set) . "\n                WHERE id = :id";
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            return $q->execute($params);
        } catch (Exception $e) {
            return false;
        }
    }

    public function updateAvatarById(int $id, string $avatarUrl): bool
    {
        if ($id <= 0) {
            return false;
        }

        return $this->updateProfile($id, ['avatar_url' => trim($avatarUrl)]);
    }

    public function changePassword(int $id, string $newPassword): bool
    {
        if ($id <= 0 || strlen($newPassword) < 6) return false;
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare(
                "UPDATE {$table} SET password = :password, updated_at = NOW() WHERE id = :id"
            );
            return $q->execute([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id'       => $id,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  DELETE REQUESTS
    // ═══════════════════════════════════════════════════════════════════════

    public function hasPendingDeleteRequest(int $userId): bool
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                "SELECT id FROM user_delete_requests WHERE user_id = :user_id AND status = 'pending' LIMIT 1"
            );
            $q->execute(['user_id' => $userId]);
            return (bool)$q->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getPendingDeleteRequest(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                "SELECT * FROM user_delete_requests WHERE user_id = :user_id AND status = 'pending' ORDER BY created_at DESC LIMIT 1"
            );
            $q->execute(['user_id' => $userId]);
            $row = $q->fetch();
            return $row ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function createDeleteRequest(int $userId, int $requestedBy, string $reason = '')
    {
        $this->modelState->setLastError(null);
        if ($userId <= 0 || $requestedBy <= 0) return false;
        if ($this->hasPendingDeleteRequest($userId)) return 'pending_exists';

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $db->beginTransaction();

            $ins = $db->prepare(
                "INSERT INTO user_delete_requests (user_id, requested_by, reason, status)
                 VALUES (:user_id, :requested_by, :reason, 'pending')"
            );
            $ins->execute([
                'user_id'      => $userId,
                'requested_by' => $requestedBy,
                'reason'       => trim($reason) ?: null,
            ]);

            $dis = $db->prepare(
                "UPDATE {$table} SET status = 0, is_blocked = 1, updated_at = NOW() WHERE id = :id"
            );
            $dis->execute(['id' => $userId]);

            $requestId = (int)$db->lastInsertId();
            $db->commit();
            return $requestId;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::createDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    public function cancelDeleteRequest(int $userId): bool
    {
        $this->modelState->setLastError(null);
        if ($userId <= 0) return false;

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $db->beginTransaction();

            $q = $db->prepare(
                "SELECT * FROM user_delete_requests WHERE user_id = :user_id AND status = 'pending' ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
            );
            $q->execute(['user_id' => $userId]);
            $request = $q->fetch();

            if (!$request) {
                $db->rollBack();
                return false;
            }

            $db->prepare(
                "UPDATE {$table} SET status = 1, is_blocked = 0, updated_at = NOW() WHERE id = :id"
            )->execute(['id' => $userId]);

            $db->prepare(
                "UPDATE user_delete_requests SET status = 'canceled', reviewed_at = NOW(), updated_at = NOW() WHERE id = :id"
            )->execute(['id' => (int) ($request['id'] ?? 0)]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::cancelDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    public function getDeleteRequests(string $status = 'pending'): array
    {
        $table  = $this->modelState->getTableName();
        $db     = config::getConnexion();
        $sql    = "SELECT r.*, u.first_name, u.last_name, u.email
                   FROM user_delete_requests r
                   LEFT JOIN {$table} u ON u.id = r.user_id";
        $params = [];

        $normalizedStatus = trim($status);
        if ($normalizedStatus !== '' && $normalizedStatus !== 'all') {
            $sql             .= ' WHERE r.status = :status';
            $params['status'] = $normalizedStatus;
        }
        $sql .= ' ORDER BY r.created_at DESC';

        try {
            $q = $db->prepare($sql);
            $q->execute($params);
            return $q->fetchAll() ?: [];
        } catch (Exception $e) {
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::getDeleteRequests — ' . $e->getMessage());
            return [];
        }
    }

    public function approveDeleteRequest(int $requestId, int $adminId): bool
    {
        $this->modelState->setLastError(null);
        if ($requestId <= 0 || $adminId <= 0) return false;

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $db->beginTransaction();

            $q = $db->prepare("SELECT * FROM user_delete_requests WHERE id = :id FOR UPDATE");
            $q->execute(['id' => $requestId]);
            $request = $q->fetch();

            if (!$request || (string)($request['status'] ?? '') !== 'pending') {
                $db->rollBack();
                return false;
            }
            $userId = (int)($request['user_id'] ?? 0);
            if ($userId <= 0) { $db->rollBack(); return false; }

            $db->prepare("DELETE FROM {$table} WHERE id = :id")->execute(['id' => $userId]);

            $db->prepare(
                "UPDATE user_delete_requests
                 SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            )->execute(['admin_id' => $adminId, 'id' => $requestId]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::approveDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    public function rejectDeleteRequest(int $requestId, int $adminId, string $note = ''): bool
    {
        $this->modelState->setLastError(null);
        if ($requestId <= 0 || $adminId <= 0) return false;

        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $db->beginTransaction();

            $q = $db->prepare("SELECT * FROM user_delete_requests WHERE id = :id FOR UPDATE");
            $q->execute(['id' => $requestId]);
            $request = $q->fetch();

            if (!$request || (string)($request['status'] ?? '') !== 'pending') {
                $db->rollBack();
                return false;
            }
            $userId = (int)($request['user_id'] ?? 0);
            if ($userId <= 0) { $db->rollBack(); return false; }

            $db->prepare(
                "UPDATE {$table} SET status = 1, is_blocked = 0, updated_at = NOW() WHERE id = :id"
            )->execute(['id' => $userId]);

            $db->prepare(
                "UPDATE user_delete_requests
                 SET status = 'rejected', admin_note = :admin_note,
                     reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            )->execute([
                'admin_note' => trim($note) ?: null,
                'admin_id'   => $adminId,
                'id'         => $requestId,
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->modelState->setLastError($e->getMessage());
            error_log('UserController::rejectDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  STORIES CRUD
    // ═══════════════════════════════════════════════════════════════════════

    public function createStory(int $userId, array $data): ?int
    {
        if ($userId <= 0) return null;
        $db = config::getConnexion();
        try {
            // Auto-heal legacy schemas so rich Story editor payload can persist.
            $storyColumnDDL = [
                'music_url' => "VARCHAR(255) DEFAULT NULL",
                'music_title' => "VARCHAR(255) DEFAULT NULL",
                'drawing_data' => "LONGTEXT DEFAULT NULL",
                'text_layers' => "JSON DEFAULT NULL",
                'sticker_layers' => "JSON DEFAULT NULL",
                'filter_css' => "VARCHAR(255) DEFAULT NULL",
                'gradient_bg' => "VARCHAR(255) DEFAULT NULL",
                'duration' => "INT NOT NULL DEFAULT 5",
                'visibility' => "ENUM('public','friends') NOT NULL DEFAULT 'public'",
                'location_label' => "VARCHAR(255) DEFAULT NULL",
                'content_json' => "LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(content_json))",
            ];
            foreach ($storyColumnDDL as $column => $ddl) {
                if ($this->storiesHasColumn($column)) {
                    continue;
                }
                try {
                    $db->exec("ALTER TABLE stories ADD COLUMN `{$column}` {$ddl}");
                    $this->modelState->setColumnInCache('stories:' . $column, true);
                } catch (Exception $e) {
                    // Keep backward compatibility even if a column cannot be added.
                }
            }

            $jsonField = static function ($value): ?string {
                if ($value === null || $value === '') {
                    return null;
                }
                if (is_string($value)) {
                    return $value;
                }
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            };

            $storyType = strtolower(trim((string)($data['story_type'] ?? 'image')));
            if (!in_array($storyType, ['image', 'video', 'text'], true)) {
                $storyType = 'image';
            }

            $visibility = strtolower(trim((string)($data['visibility'] ?? 'public')));
            if ($visibility === 'team') {
                $visibility = 'friends';
            } elseif (!in_array($visibility, ['public', 'friends'], true)) {
                $visibility = 'public';
            }

            $duration = (int)($data['duration'] ?? 5);
            if ($duration < 1) $duration = 1;
            if ($duration > 60) $duration = 60;

            $contentJson = $this->buildStoryContentJson($data + [
                'media_url' => isset($data['media_url']) ? trim((string) $data['media_url']) : null,
                'story_type' => $storyType,
            ]);

            $columnValues = [
                'user_id' => $userId,
                'story_type' => $storyType,
                'media_url' => isset($data['media_url']) ? trim((string)$data['media_url']) : null,
                'caption' => isset($data['caption']) ? trim((string)$data['caption']) : null,
                'music_url' => isset($data['music_url']) ? trim((string)$data['music_url']) : null,
                'music_title' => isset($data['music_title']) ? trim((string)$data['music_title']) : null,
                'drawing_data' => isset($data['drawing_data']) ? (string)$data['drawing_data'] : null,
                'text_layers' => $jsonField($data['text_layers'] ?? null),
                'sticker_layers' => $jsonField($data['sticker_layers'] ?? null),
                'filter_css' => isset($data['filter_css']) ? trim((string)$data['filter_css']) : null,
                'gradient_bg' => isset($data['gradient_bg']) ? trim((string)$data['gradient_bg']) : null,
                'duration' => $duration,
                'visibility' => $visibility,
                'location_label' => isset($data['location_label']) ? trim((string)$data['location_label']) : null,
                'content_json' => $contentJson,
            ];

            $columns = ['user_id'];
            $values = [':user_id'];
            $params = ['user_id' => $userId];

            foreach ($columnValues as $column => $value) {
                if ($column === 'user_id') {
                    continue;
                }
                if (!$this->storiesHasColumn($column)) {
                    continue;
                }
                $columns[] = $column;
                $values[] = ':' . $column;
                $params[$column] = $value;
            }

            if ($this->storiesHasColumn('expires_at')) {
                $columns[] = 'expires_at';
                $expiresAtRaw = trim((string)($data['expires_at'] ?? ''));
                if ($expiresAtRaw !== '') {
                    $values[] = ':expires_at';
                    $params['expires_at'] = $expiresAtRaw;
                } else {
                    $values[] = 'DATE_ADD(NOW(), INTERVAL 1 DAY)';
                }
            }

            if (empty($columns) || empty($values)) {
                return null;
            }

            $sql = 'INSERT INTO stories (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
            $q = $db->prepare($sql);
            $q->execute($params);
            return (int)$db->lastInsertId();
        } catch (Exception $e) {
            error_log('UserController::createStory — ' . $e->getMessage());
            return null;
        }
    }

    private function decodeStoryJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function normalizeStoryCoordinate($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        if (preg_match('/-?\d+(?:\.\d+)?/', $raw, $matches)) {
            return (float) $matches[0];
        }

        return 0.0;
    }

    private function normalizeStoryElements(array $elements): array
    {
        $normalized = [];

        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $type = strtolower(trim((string) ($element['type'] ?? '')));

            if ($type === 'text' || isset($element['content']) || isset($element['text'])) {
                $content = trim((string) ($element['content'] ?? $element['text'] ?? ''));
                if ($content === '') {
                    continue;
                }

                $normalized[] = [
                    'type' => 'text',
                    'content' => $content,
                    'x' => $this->normalizeStoryCoordinate($element['x'] ?? 0),
                    'y' => $this->normalizeStoryCoordinate($element['y'] ?? 0),
                    'color' => trim((string) ($element['color'] ?? '#ffffff')) ?: '#ffffff',
                    'font' => trim((string) ($element['font'] ?? $element['fontFamily'] ?? 'Poppins')) ?: 'Poppins',
                ];
                continue;
            }

            if ($type === 'emoji' || isset($element['emoji']) || isset($element['value'])) {
                $value = trim((string) ($element['value'] ?? $element['emoji'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $normalized[] = [
                    'type' => 'emoji',
                    'value' => $value,
                    'x' => $this->normalizeStoryCoordinate($element['x'] ?? 0),
                    'y' => $this->normalizeStoryCoordinate($element['y'] ?? 0),
                ];
                continue;
            }

            if ($type === 'music' || isset($element['track_id']) || isset($element['music_url']) || isset($element['music_title'])) {
                $trackId = trim((string) ($element['track_id'] ?? $element['music_id'] ?? $element['music_title'] ?? $element['music_url'] ?? ''));
                if ($trackId === '') {
                    continue;
                }

                $normalized[] = [
                    'type' => 'music',
                    'track_id' => $trackId,
                    'start' => (int) ($element['start'] ?? 0),
                ];
            }
        }

        return $normalized;
    }

    private function buildStoryContentPayload(array $data): array
    {
        $provided = $this->decodeStoryJsonField($data['content_json'] ?? null);
        $media = trim((string) ($provided['media'] ?? ($data['media_url'] ?? '')));
        $elements = [];

        if (isset($provided['elements']) && is_array($provided['elements'])) {
            $elements = $this->normalizeStoryElements($provided['elements']);
        }

        if (empty($elements)) {
            $textLayers = $this->normalizeStoryElements($this->decodeStoryJsonField($data['text_layers'] ?? null));
            $stickerLayers = $this->normalizeStoryElements($this->decodeStoryJsonField($data['sticker_layers'] ?? null));
            $elements = array_merge($textLayers, $stickerLayers);

            $caption = trim((string) ($data['caption'] ?? ''));
            if ($caption !== '') {
                array_unshift($elements, [
                    'type' => 'text',
                    'content' => $caption,
                    'x' => 120,
                    'y' => 300,
                    'color' => '#ffffff',
                    'font' => 'Poppins',
                ]);
            }
        }

        $musicTrack = trim((string) ($data['music_id'] ?? ''));
        if ($musicTrack === '') {
            $musicTrack = trim((string) ($data['music_title'] ?? ''));
        }
        if ($musicTrack === '') {
            $musicTrack = trim((string) ($data['music_url'] ?? ''));
        }
        if ($musicTrack !== '') {
            $hasMusic = false;
            foreach ($elements as $element) {
                if (($element['type'] ?? '') === 'music') {
                    $hasMusic = true;
                    break;
                }
            }
            if (!$hasMusic) {
                $elements[] = [
                    'type' => 'music',
                    'track_id' => $musicTrack,
                    'start' => 0,
                ];
            }
        }

        return [
            'media' => $media,
            'elements' => $elements,
        ];
    }

    private function buildStoryContentJson(array $data): string
    {
        $payload = $this->buildStoryContentPayload($data);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? '{"media":"","elements":[]}' : $encoded;
    }

    private function normalizeStoryRow(array $row): array
    {
        $row['text_layers'] = $this->decodeStoryJsonField($row['text_layers'] ?? null);
        $row['sticker_layers'] = $this->decodeStoryJsonField($row['sticker_layers'] ?? null);
        $row['content_json'] = $this->buildStoryContentPayload($row);
        $row['drawing_data'] = (string) ($row['drawing_data'] ?? '');
        $row['media_url'] = (string) ($row['media_url'] ?? '');
        $row['caption'] = (string) ($row['caption'] ?? '');
        $row['music_url'] = (string) ($row['music_url'] ?? '');
        $row['music_title'] = (string) ($row['music_title'] ?? '');
        $row['filter_css'] = (string) ($row['filter_css'] ?? '');
        $row['gradient_bg'] = (string) ($row['gradient_bg'] ?? '');
        $row['location_label'] = (string) ($row['location_label'] ?? '');
        $row['story_type'] = (string) ($row['story_type'] ?? 'image');
        $row['duration'] = (int) ($row['duration'] ?? 5);
        return $row;
    }

    private function normalizeStoryRows(array $rows): array
    {
        return array_map(fn(array $row): array => $this->normalizeStoryRow($row), $rows);
    }

    public function getActiveStories(int $userId): array
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                "SELECT * FROM stories
                 WHERE user_id = :user_id AND is_archived = 0 AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY created_at DESC"
            );
            $q->execute(['user_id' => $userId]);
            return $this->normalizeStoryRows($q->fetchAll() ?: []);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getAllActiveStories(): array
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare(
                "SELECT s.*, u.first_name, u.last_name, u.avatar_url, u.email
                 FROM stories s
                 JOIN {$table} u ON u.id = s.user_id
                 WHERE s.is_archived = 0 AND (s.expires_at IS NULL OR s.expires_at > NOW())
                 ORDER BY s.created_at DESC"
            );
            $q->execute();
            return $this->normalizeStoryRows($q->fetchAll() ?: []);
        } catch (Exception $e) {
            return [];
        }
    }

    public function getUsersWithActiveStories(): array
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare(
                "SELECT DISTINCT u.id, u.first_name, u.last_name, u.avatar_url, u.email,
                        COUNT(s.id) as story_count
                 FROM stories s
                 JOIN {$table} u ON u.id = s.user_id
                 WHERE s.is_archived = 0 AND (s.expires_at IS NULL OR s.expires_at > NOW())
                 GROUP BY u.id, u.first_name, u.last_name, u.avatar_url, u.email
                 ORDER BY MAX(s.created_at) DESC"
            );
            $q->execute();
            return $q->fetchAll() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function deleteStory(int $storyId, int $userId): bool
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare("DELETE FROM stories WHERE id = :id AND user_id = :user_id");
            return $q->execute(['id' => $storyId, 'user_id' => $userId]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function recordStoryView(int $storyId, int $viewerId): bool
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                "INSERT IGNORE INTO story_views (story_id, viewer_id) VALUES (:story_id, :viewer_id)"
            );
            return $q->execute(['story_id' => $storyId, 'viewer_id' => $viewerId]);
        } catch (Exception $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  LINKED ACCOUNTS CRUD
    // ═══════════════════════════════════════════════════════════════════════

    public function getLinkedAccountsForUser(int $userId): array
    {
        $db = config::getConnexion();
        try {
            $selectColumns = [
                'id', 'user_id', 'platform', 'account_label', 'username', 'profile_url',
                'is_primary', 'is_public', 'metadata', 'created_at', 'updated_at'
            ];
            if ($this->linkedAccountsHasColumn('verified')) {
                $selectColumns[] = 'verified';
            }
            if ($this->linkedAccountsHasColumn('verification_checked_at')) {
                $selectColumns[] = 'verification_checked_at';
            }

            $q = $db->prepare(
                'SELECT ' . implode(', ', $selectColumns) . ' FROM linked_accounts WHERE user_id = :user_id ORDER BY created_at DESC'
            );
            $q->execute(['user_id' => $userId]);
            return $q->fetchAll() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function addLinkedAccount(int $userId, array $data): ?int
    {
        if ($userId <= 0) return null;
        $db = config::getConnexion();
        try {
            $columns = ['user_id', 'platform', 'account_label', 'username', 'profile_url', 'is_public'];
            $params = [
                'user_id' => $userId,
                'platform' => trim($data['platform'] ?? ''),
                'account_label' => trim($data['account_label'] ?? ''),
                'username' => trim($data['username'] ?? ''),
                'profile_url' => trim($data['profile_url'] ?? ''),
                'is_public' => (int)($data['is_public'] ?? 1),
            ];

            if ($this->linkedAccountsHasColumn('is_primary')) {
                $columns[] = 'is_primary';
                $params['is_primary'] = (int)($data['is_primary'] ?? 0);
            }

            if ($this->linkedAccountsHasColumn('verified')) {
                $columns[] = 'verified';
                $params['verified'] = (int)($data['verified'] ?? 0);
            }

            if ($this->linkedAccountsHasColumn('verification_checked_at')) {
                $columns[] = 'verification_checked_at';
                $params['verification_checked_at'] = date('Y-m-d H:i:s');
            }

            $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
            $q = $db->prepare(
                'INSERT INTO linked_accounts (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')'
            );
            $q->execute($params);
            return (int)$db->lastInsertId();
        } catch (Exception $e) {
            error_log('UserController::addLinkedAccount — ' . $e->getMessage());
            return null;
        }
    }

    public function deleteLinkedAccount(int $accountId, int $userId): bool
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare("DELETE FROM linked_accounts WHERE id = :id AND user_id = :user_id");
            return $q->execute(['id' => $accountId, 'user_id' => $userId]);
        } catch (Exception $e) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  GROUP CHAT CRUD
    // ═══════════════════════════════════════════════════════════════════════

    public function createGroupChat(int $creatorId, string $name, string $description = '', array $memberIds = []): ?int
    {
        if ($creatorId <= 0 || trim($name) === '') return null;
        $db = config::getConnexion();
        try {
            $db->beginTransaction();

            $q = $db->prepare(
                "INSERT INTO group_chats (name, description, created_by) VALUES (:name, :description, :created_by)"
            );
            $q->execute([
                'name'        => trim($name),
                'description' => trim($description),
                'created_by'  => $creatorId,
            ]);
            $groupId = (int)$db->lastInsertId();

            // Add creator as owner
            $db->prepare(
                "INSERT INTO group_chat_members (group_chat_id, user_id, role) VALUES (:gid, :uid, 'owner')"
            )->execute(['gid' => $groupId, 'uid' => $creatorId]);

            // Add members
            $memberStmt = $db->prepare(
                "INSERT IGNORE INTO group_chat_members (group_chat_id, user_id, role) VALUES (:gid, :uid, 'member')"
            );
            foreach ($memberIds as $memberId) {
                $memberId = (int)$memberId;
                if ($memberId > 0 && $memberId !== $creatorId) {
                    $memberStmt->execute(['gid' => $groupId, 'uid' => $memberId]);
                }
            }

            $db->commit();
            return $groupId;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('UserController::createGroupChat — ' . $e->getMessage());
            return null;
        }
    }

    public function getUserGroupChats(int $userId): array
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                "SELECT g.*, gm.role as my_role,
                        (SELECT COUNT(*) FROM group_chat_members WHERE group_chat_id = g.id AND left_at IS NULL) as member_count
                 FROM group_chats g
                 JOIN group_chat_members gm ON gm.group_chat_id = g.id AND gm.user_id = :user_id AND gm.left_at IS NULL
                 ORDER BY g.last_message_at DESC, g.created_at DESC"
            );
            $q->execute(['user_id' => $userId]);
            return $q->fetchAll() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function getGroupChatMembers(int $groupId): array
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.email, gm.role, gm.joined_at
                 FROM group_chat_members gm
                 JOIN {$table} u ON u.id = gm.user_id
                 WHERE gm.group_chat_id = :group_id AND gm.left_at IS NULL
                 ORDER BY FIELD(gm.role, 'owner', 'admin', 'member'), gm.joined_at ASC"
            );
            $q->execute(['group_id' => $groupId]);
            return $q->fetchAll() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  FRIENDS LIST (for group creation & messaging)
    // ═══════════════════════════════════════════════════════════════════════

    public function getFriendsForUser(int $userId): array
    {
        $db = config::getConnexion();
        $table = $this->modelState->getTableName();
        try {
            $q = $db->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.avatar_url, u.email, u.last_seen, u.country
                 FROM friends f
                 JOIN {$table} u ON u.id = CASE WHEN f.user_one_id = :uid THEN f.user_two_id ELSE f.user_one_id END
                 WHERE (f.user_one_id = :uid2 OR f.user_two_id = :uid3)
                   AND f.status = 'accepted'
                 ORDER BY u.first_name ASC"
            );
            $q->execute(['uid' => $userId, 'uid2' => $userId, 'uid3' => $userId]);
            return $q->fetchAll() ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
}
