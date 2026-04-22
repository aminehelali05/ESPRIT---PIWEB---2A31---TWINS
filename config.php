<?php
class config
{
    private static $pdo = null;
    private static array $settings = [];

    private static function loadLocalSettings(): array
    {
        $localPath = __DIR__ . '/config.local.php';
        if (!is_file($localPath)) {
            return [];
        }

        $settings = include $localPath;
        return is_array($settings) ? $settings : [];
    }

    public static function get(string $key, $default = null)
    {
        if (self::$settings === []) {
            self::$settings = self::loadLocalSettings();
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
