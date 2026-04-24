<?php
class config
{
    private static $pdo = null;
    private static array $settings = [];
    private static bool $environmentLoaded = false;

    private static function loadEnvironmentSettings(): array
    {
        if (self::$environmentLoaded) {
            return self::$settings;
        }

        self::$environmentLoaded = true;

        $envPath = __DIR__ . '/.env';
        if (!is_file($envPath)) {
            return [];
        }

        $rawLines = file($envPath, FILE_IGNORE_NEW_LINES);
        if ($rawLines === false) {
            return [];
        }

        $settings = [];
        foreach ($rawLines as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            if ($key === '') {
                continue;
            }

            $value = trim(substr($line, $separator + 1));
            if ($value !== '') {
                $firstChar = $value[0];
                $lastChar = substr($value, -1);
                if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                    $value = substr($value, 1, -1);
                    if ($firstChar === '"') {
                        $value = stripcslashes($value);
                    }
                }
            }

            $existing = getenv($key);
            if ($existing === false || $existing === '') {
                putenv($key . '=' . $value);
                $existing = $value;
            }

            $_ENV[$key] = $existing;
            $_SERVER[$key] = $existing;
            $settings[$key] = $existing;
        }

        return is_array($settings) ? $settings : [];
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
            $username = (string) self::get('DB_USERNAME', 'root');
            $password = (string) self::get('DB_PASSWORD', '');
            $dbname = (string) self::get('DB_NAME', 'projet');
            $charset = (string) self::get('DB_CHARSET', 'utf8mb4');
            try {
                self::$pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=$charset",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
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
