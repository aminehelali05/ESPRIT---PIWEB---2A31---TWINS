<?php

class config
{
    private static $pdo = null;
    private static array $settings = [];
    private static bool $environmentLoaded = false;

    private static function stringify($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        $json = json_encode($value);
        return $json !== false ? $json : '';
    }

    private static function seedEnvironment(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $stringValue = self::stringify($value);
            if (getenv($key) === false || getenv($key) === '') {
                putenv($key . '=' . $stringValue);
            }

            $_ENV[$key] = $stringValue;
            $_SERVER[$key] = $stringValue;
        }
    }

    private static function loadEnvironmentSettings(): array
    {
        if (self::$environmentLoaded) {
            return self::$settings;
        }

        self::$environmentLoaded = true;

        $phpConfigPath = __DIR__ . '/.env.php';
        if (!is_file($phpConfigPath)) {
            return [];
        }

        $loaded = include $phpConfigPath;
        if (!is_array($loaded)) {
            return [];
        }

        $settings = [];
        foreach ($loaded as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $settings[$key] = $value;
        }

        self::seedEnvironment($settings);
        return $settings;
    }

    public static function get(string $key, $default = null)
    {
        if (self::$settings === []) {
            self::$settings = self::loadEnvironmentSettings();
        }

        $envValue = getenv($key);
        if ($envValue !== false && $envValue !== '') {
            return $envValue;
        }

        return self::$settings[$key] ?? $default;
    }

    public static function getConnexion()
    {
        if (!isset(self::$pdo)) {
            $servername = (string) self::get('DB_HOST', 'localhost');
            $username = (string) self::get('DB_USER', self::get('DB_USERNAME', 'root'));
            $password = (string) self::get('DB_PASS', self::get('DB_PASSWORD', ''));
            $dbname = (string) self::get('DB_NAME', 'projet');
            $charset = (string) self::get('DB_CHARSET', 'utf8mb4');

            try {
                self::$pdo = new PDO(
                    "mysql:host={$servername};dbname={$dbname};charset={$charset}",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (Exception $e) {
                die('Erreur : ' . $e->getMessage());
            }
        }

        return self::$pdo;
    }

    public static function getBaseUrl(): string
    {
        $configured = trim((string) self::get('APP_URL', ''));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $scriptName = trim((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $basePath = str_replace('\\', '/', dirname($scriptName));
        $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');

        return $scheme . '://' . $host . $basePath;
    }
}

config::getConnexion();
