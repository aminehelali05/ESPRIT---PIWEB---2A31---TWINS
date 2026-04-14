<?php
include_once(__DIR__ . '/../config.php');
include_once(__DIR__ . '/../Models/User.php');

class UserController
{
<<<<<<< Updated upstream
    public function addUser(User $user)
    {
        $sql = "INSERT INTO user (first_name, last_name, email, password, phone, role, status, created_at)
                VALUES (:first_name, :last_name, :email, :password, :phone, :role, :status, :created_at)";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'phone' => $user->getPhone(),
                'role' => $user->getRole(),
                'status' => $user->getStatus(),
                'created_at' => $user->getCreatedAt() ?: date('Y-m-d H:i:s')
=======
    private $modelState;

    public function __construct()
    {
        $this->modelState = new User();
        $this->modelState->setTableName($this->resolveTableName());
        $this->modelState->setLastError(null);
        $this->modelState->clearColumnCache();
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

    public function getTableName(): string  { return $this->modelState->getTableName(); }
    public function getLastError()          { return $this->modelState->getLastError(); }

    public function __get($name)
    {
        if ($name === 'table') {
            return $this->modelState->getTableName();
        }
        if ($name === 'lastError') {
            return $this->modelState->getLastError();
        }
        return null;
    }

    public function __set($name, $value): void
    {
        if ($name === 'table') {
            $this->modelState->setTableName((string) $value);
            return;
        }
        if ($name === 'lastError') {
            $this->modelState->setLastError($value);
        }
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
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            $row['password'],
            $row['phone']      ?? '',
            $row['role']       ?? 'user',
            $row['status']     ?? 1,
            $row['created_at'] ?? null
        );
        $user->setId($row['id']);
        $user->setAvatarUrl($row['avatar_url']         ?? null);
        $user->setBadge($row['badge']                  ?? null);
        $user->setCountry($row['country']              ?? null);
        $user->setBio($row['bio']                      ?? null);
        $user->setTitle($row['title']                  ?? null);
        $user->setSkills($row['skills']                ?? null);
        $user->setXp($row['xp']                        ?? 0);
        $user->setIsBlocked($row['is_blocked']         ?? 0);
        $user->setLastSeen($row['last_seen']            ?? null);
        $user->setFaceDescriptor($row['face_descriptor']   ?? null);
        $user->setFaceImagesPath($row['face_images_path']  ?? null);
        $user->setFaceEnrolled($row['face_enrolled']       ?? 0);
        $user->setFaceEnrolledAt($row['face_enrolled_at']  ?? null);
        $user->setUpdatedAt($row['updated_at']         ?? null);
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
        $this->lastError = null;
        $sql = "INSERT INTO {$this->table}
                    (first_name, last_name, email, password, phone, role, status,
                     avatar_url, badge, country, bio, title, skills,
                     xp, is_blocked, last_seen, created_at)
                VALUES
                    (:first_name, :last_name, :email, :password, :phone, :role, :status,
                     :avatar_url, :badge, :country, :bio, :title, :skills,
                     :xp, :is_blocked, :last_seen, :created_at)";
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            $q->execute([
                'first_name'  => $user->getFirstName(),
                'last_name'   => $user->getLastName(),
                'email'       => $user->getEmail(),
                'password'    => $user->getPassword(),
                'phone'       => $user->getPhone(),
                'role'        => $user->getRole(),
                'status'      => $user->getStatus(),
                'avatar_url'  => $user->getAvatarUrl(),
                'badge'       => $user->getBadge(),
                'country'     => $user->getCountry(),
                'bio'         => $user->getBio(),
                'title'       => $user->getTitle(),
                'skills'      => $user->getSkills(),
                'xp'          => $user->getXp(),
                'is_blocked'  => $user->getIsBlocked(),
                'last_seen'   => $user->getLastSeen(),
                'created_at'  => $user->getCreatedAt() ?: date('Y-m-d H:i:s'),
>>>>>>> Stashed changes
            ]);
        } catch (Exception $e) {
<<<<<<< Updated upstream
            echo 'Error: ' . $e->getMessage();
=======
            $this->lastError = $e->getMessage();
            error_log('UserController::addUser — ' . $e->getMessage());
            return false;
>>>>>>> Stashed changes
        }
    }

    public function updateUser(User $user, $id): bool
    {
<<<<<<< Updated upstream
        $sql = "UPDATE user SET first_name=:first_name, last_name=:last_name, email=:email,
                password=:password, phone=:phone, role=:role, status=:status WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute([
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'phone' => $user->getPhone(),
                'role' => $user->getRole(),
                'status' => $user->getStatus(),
                'id' => $id
=======
        $this->lastError = null;
        $sql = "UPDATE {$this->table} SET
                    first_name        = :first_name,
                    last_name         = :last_name,
                    email             = :email,
                    password          = :password,
                    phone             = :phone,
                    role              = :role,
                    status            = :status,
                    avatar_url        = :avatar_url,
                    badge             = :badge,
                    country           = :country,
                    bio               = :bio,
                    title             = :title,
                    skills            = :skills,
                    xp                = :xp,
                    is_blocked        = :is_blocked,
                    last_seen         = :last_seen,
                    face_descriptor   = :face_descriptor,
                    face_images_path  = :face_images_path,
                    face_enrolled     = :face_enrolled,
                    face_enrolled_at  = :face_enrolled_at
                WHERE id = :id";
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            $q->execute([
                'first_name'       => $user->getFirstName(),
                'last_name'        => $user->getLastName(),
                'email'            => $user->getEmail(),
                'password'         => $user->getPassword(),
                'phone'            => $user->getPhone(),
                'role'             => $user->getRole(),
                'status'           => $user->getStatus(),
                'avatar_url'       => $user->getAvatarUrl(),
                'badge'            => $user->getBadge(),
                'country'          => $user->getCountry(),
                'bio'              => $user->getBio(),
                'title'            => $user->getTitle(),
                'skills'           => $user->getSkills(),
                'xp'               => $user->getXp(),
                'is_blocked'       => $user->getIsBlocked(),
                'last_seen'        => $user->getLastSeen(),
                'face_descriptor'  => $user->getFaceDescriptor(),
                'face_images_path' => $user->getFaceImagesPath(),
                'face_enrolled'    => $user->getFaceEnrolled(),
                'face_enrolled_at' => $user->getFaceEnrolledAt(),
                'id'               => $id,
>>>>>>> Stashed changes
            ]);
        } catch (Exception $e) {
<<<<<<< Updated upstream
            echo 'Error: ' . $e->getMessage();
=======
            $this->lastError = $e->getMessage();
            error_log('UserController::updateUser — ' . $e->getMessage());
            return false;
>>>>>>> Stashed changes
        }
    }

    public function deleteUser($id): bool
    {
<<<<<<< Updated upstream
        $sql = "DELETE FROM user WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    }

    public function listUsers()
    {
        $sql = "SELECT * FROM user ORDER BY id DESC";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute();
            $usersData = $query->fetchAll();

            $users = [];
            foreach ($usersData as $row) {
                $user = new User(
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['password'],
                    $row['phone'],
                    $row['role'],
                    $row['status'],
                    $row['created_at']
                );
                $user->setId($row['id']);
                $users[] = $user;
            }
            return $users;
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
=======
        $this->lastError = null;
        $db = config::getConnexion();
        try {
            $q = $db->prepare("DELETE FROM {$this->table} WHERE id = :id");
            return $q->execute(['id' => $id]);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('UserController::deleteUser — ' . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  QUERIES
    // ═══════════════════════════════════════════════════════════════════════

    public function listUsers(string $search = ''): array
    {
        $this->lastError = null;
        $sql    = "SELECT * FROM {$this->table}";
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
            $this->lastError = $e->getMessage();
            error_log('UserController::listUsers — ' . $e->getMessage());
>>>>>>> Stashed changes
            return [];
        }
    }

<<<<<<< Updated upstream
    public function getUserById($id)
    {
        $sql = "SELECT * FROM user WHERE id=:id";
        $db = config::getConnexion();
        try {
            $query = $db->prepare($sql);
            $query->execute(['id' => $id]);
            $row = $query->fetch();

            if ($row) {
                $user = new User(
                    $row['first_name'],
                    $row['last_name'],
                    $row['email'],
                    $row['password'],
                    $row['phone'],
                    $row['role'],
                    $row['status'],
                    $row['created_at']
                );
                $user->setId($row['id']);
                return $user;
            }
            return null;
=======
    public function searchUsers(string $keyword): array
    {
        return $this->listUsers(trim($keyword));
    }

    public function getUserById($id): ?User
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
            $q->execute(['id' => $id]);
            $row = $q->fetch();
            return $row ? $this->hydrateUser($row) : null;
>>>>>>> Stashed changes
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
            return null;
        }
    }

<<<<<<< Updated upstream
    public function emailExists($email, $excludeId = null)
    {
        $sql = "SELECT id FROM user WHERE email = :email";
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
        }

=======
    public function getUserByEmail(string $email): ?User
    {
        $db = config::getConnexion();
        try {
            $q = $db->prepare("SELECT * FROM {$this->table} WHERE email = :email LIMIT 1");
            $q->execute(['email' => $email]);
            $row = $q->fetch();
            return $row ? $this->hydrateUser($row) : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function emailExists(string $email, $excludeId = null): bool
    {
        $sql    = "SELECT id FROM {$this->table} WHERE email = :email";
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
        try {
            $q = $db->prepare("UPDATE {$this->table} SET last_seen = NOW() WHERE id = :id");
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

        $_SESSION['auth_user'] = [
            'id'         => (int)$user->getId(),
            'first_name' => (string)$user->getFirstName(),
            'last_name'  => (string)$user->getLastName(),
            'email'      => (string)$user->getEmail(),
            'role'       => (string)$user->getRole(),
            'avatar_url' => $user->getAvatarUrl()
                ?: self::buildGeneratedAvatarUrl(
                    (string)$user->getFirstName(),
                    (string)$user->getLastName(),
                    (string)$user->getEmail()
                ),
        ];

        return ['success' => true, 'message' => 'Login successful.'];
    }

    public function register(array $data): array
    {
        $firstName   = trim((string)($data['first_name']   ?? ''));
        $lastName    = trim((string)($data['last_name']    ?? ''));
        $email       = trim((string)($data['email']        ?? ''));
        $password    = (string)($data['password']          ?? '');
        $phone       = trim((string)($data['phone']        ?? ''));
        $country     = trim((string)($data['country']      ?? ''));
        $accountType = strtolower(trim((string)($data['account_type'] ?? 'freelancer')));
        $title       = trim((string)($data['title']        ?? ''));
        $bio         = trim((string)($data['bio']          ?? ''));
        $skills      = trim((string)($data['skills']       ?? ''));
        $avatarUrl   = trim((string)($data['avatar_url']   ?? ''));

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

        $user = new User(
            $firstName,
            $lastName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $phone,
            'user',
            1,
            date('Y-m-d H:i:s')
        );

        $user->setCountry($country !== '' ? $country : null);
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

        $newId = $this->addUser($user);
        if (!$newId) {
            $details = (string)$this->getLastError();
            return [
                'success' => false,
                'message' => $details !== ''
                    ? 'Could not create account: ' . $details
                    : 'Could not create account right now.',
            ];
        }

        $this->recordSignIn((int)$newId);

        $_SESSION['auth_user'] = [
            'id'         => (int)$newId,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'role'       => 'user',
            'avatar_url' => $finalAvatarUrl,
        ];

        return ['success' => true, 'message' => 'Account created successfully.'];
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

>>>>>>> Stashed changes
        $db = config::getConnexion();
        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
<<<<<<< Updated upstream
            echo 'Error: ' . $e->getMessage();
=======
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
        $sql    = "SELECT COUNT(*) AS cnt FROM {$this->table}";
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
        $totalQ = $db->prepare("SELECT COUNT(*) AS cnt FROM {$this->table}{$where}");
        $totalQ->execute($params);
        $total = (int)(($totalQ->fetch())['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) { $page = $pages; }
        $offset = ($page - 1) * $perPage;

        $q = $db->prepare("SELECT * FROM {$this->table}{$where} ORDER BY id DESC LIMIT {$offset}, {$perPage}");
        $q->execute($params);
        $users = array_map([$this, 'hydrateUser'], $q->fetchAll());

        return compact('users', 'total', 'pages', 'page', 'perPage', 'search');
    }

    public function countByRole(string $role): int
    {
        $db = config::getConnexion();
        $q  = $db->prepare("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE role = :role");
        $q->execute(['role' => $role]);
        return (int)(($q->fetch())['cnt'] ?? 0);
    }

    public function countActiveUsers(): int
    {
        $db = config::getConnexion();
        $q  = $db->prepare("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE status = 1");
        $q->execute();
        return (int)(($q->fetch())['cnt'] ?? 0);
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

        $sql = "UPDATE {$this->table} SET
                    first_name  = :first_name,
                    last_name   = :last_name,
                    email       = :email,
                    phone       = :phone,
                    role        = :role,
                    country     = :country,
                    bio         = :bio,
                    title       = :title,
                    skills      = :skills,
                    avatar_url  = :avatar_url,
                    updated_at  = NOW()
                WHERE id = :id";
        $db = config::getConnexion();
        try {
            $q = $db->prepare($sql);
            return $q->execute([
                'first_name' => trim((string)($data['first_name'] ?? '')),
                'last_name'  => trim((string)($data['last_name']  ?? '')),
                'email'      => trim((string)($data['email']       ?? '')),
                'phone'      => trim((string)($data['phone']       ?? '')),
                'role'       => trim((string)($data['role']        ?? 'client')),
                'country'    => trim((string)($data['country']     ?? '')),
                'bio'        => trim((string)($data['bio']         ?? '')),
                'title'      => trim((string)($data['title']       ?? '')),
                'skills'     => trim((string)($data['skills']      ?? '')),
                'avatar_url' => trim((string)($data['avatar_url']  ?? '')) ?: null,
                'id'         => $id,
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    public function changePassword(int $id, string $newPassword): bool
    {
        if ($id <= 0 || strlen($newPassword) < 6) return false;
        $db = config::getConnexion();
        try {
            $q = $db->prepare(
                "UPDATE {$this->table} SET password = :password, updated_at = NOW() WHERE id = :id"
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
        $this->lastError = null;
        if ($userId <= 0 || $requestedBy <= 0) return false;
        if ($this->hasPendingDeleteRequest($userId)) return 'pending_exists';

        $db = config::getConnexion();
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
                "UPDATE {$this->table} SET status = 0, is_blocked = 1, updated_at = NOW() WHERE id = :id"
            );
            $dis->execute(['id' => $userId]);

            $requestId = (int)$db->lastInsertId();
            $db->commit();
            return $requestId;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->lastError = $e->getMessage();
            error_log('UserController::createDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    public function cancelDeleteRequest(int $userId): bool
    {
        $this->lastError = null;
        if ($userId <= 0) return false;

        $db = config::getConnexion();
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
                "UPDATE {$this->table} SET status = 1, is_blocked = 0, updated_at = NOW() WHERE id = :id"
            )->execute(['id' => $userId]);

            $db->prepare(
                "UPDATE user_delete_requests SET status = 'canceled', reviewed_at = NOW(), updated_at = NOW() WHERE id = :id"
            )->execute(['id' => (int) ($request['id'] ?? 0)]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->lastError = $e->getMessage();
            error_log('UserController::cancelDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    public function getDeleteRequests(string $status = 'pending'): array
    {
        $db     = config::getConnexion();
        $sql    = "SELECT r.*, u.first_name, u.last_name, u.email
                   FROM user_delete_requests r
                   LEFT JOIN {$this->table} u ON u.id = r.user_id";
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
            $this->lastError = $e->getMessage();
            error_log('UserController::getDeleteRequests — ' . $e->getMessage());
            return [];
        }
    }

    public function approveDeleteRequest(int $requestId, int $adminId): bool
    {
        $this->lastError = null;
        if ($requestId <= 0 || $adminId <= 0) return false;

        $db = config::getConnexion();
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

            $db->prepare("DELETE FROM {$this->table} WHERE id = :id")->execute(['id' => $userId]);

            $db->prepare(
                "UPDATE user_delete_requests
                 SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            )->execute(['admin_id' => $adminId, 'id' => $requestId]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $this->lastError = $e->getMessage();
            error_log('UserController::approveDeleteRequest — ' . $e->getMessage());
            return false;
        }
    }

    public function rejectDeleteRequest(int $requestId, int $adminId, string $note = ''): bool
    {
        $this->lastError = null;
        if ($requestId <= 0 || $adminId <= 0) return false;

        $db = config::getConnexion();
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
                "UPDATE {$this->table} SET status = 1, is_blocked = 0, updated_at = NOW() WHERE id = :id"
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
            $this->lastError = $e->getMessage();
            error_log('UserController::rejectDeleteRequest — ' . $e->getMessage());
>>>>>>> Stashed changes
            return false;
        }
    }
}