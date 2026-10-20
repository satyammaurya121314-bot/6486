<?php
// config/database.php
// Database connection configuration for MediToken

// Optional simple .env file loader for environments without composer
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
}

function get_config($key, $default = '') {
    $val = getenv($key);
    if ($val === false) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    return $val;
}

define('DB_HOST', get_config('MYSQLHOST', get_config('DB_HOST', 'localhost')));
define('DB_PORT', get_config('MYSQLPORT', get_config('DB_PORT', '3306')));
define('DB_NAME', get_config('MYSQLDATABASE', get_config('DB_NAME', 'meditoken_db')));
define('DB_USER', get_config('MYSQLUSER', get_config('DB_USER', 'root')));
define('DB_PASS', get_config('MYSQLPASSWORD', get_config('DB_PASS', '')));
define('DB_CHARSET', get_config('DB_CHARSET', 'utf8mb4'));
define('APP_ENV', get_config('APP_ENV', 'development'));

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error safely in production, return user friendly message
            error_log("Database connection failed: " . $e->getMessage());
            if (APP_ENV === 'production') {
                die("Database connection error. Please verify your database configuration and try again later.");
            } else {
                die("Database connection failed: " . htmlspecialchars($e->getMessage()));
            }
        }

        // Auto-initialize schema and default admin if database is brand new
        ensure_schema_initialized($pdo);
    }

    return $pdo;
}

function ensure_schema_initialized($pdo) {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'admins'");
        if ($check->rowCount() === 0) {
            $sqlFile = __DIR__ . '/../database/meditoken_db.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                // Strip database creation/selection commands for cloud compatibility
                $sql = preg_replace('/CREATE\s+DATABASE[^;]+;/i', '', $sql);
                $sql = preg_replace('/USE\s+[`a-zA-Z0-9_-]+;/i', '', $sql);
                $pdo->exec($sql);

                // Ensure initial admin user exists
                $adminCheck = $pdo->query("SELECT COUNT(*) FROM admins");
                if ((int)$adminCheck->fetchColumn() === 0) {
                    $defaultPassword = password_hash('123456', PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                    $ins->execute(['satyam123@gmail.com', $defaultPassword]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Schema auto-initialization error: " . $e->getMessage());
    }
}
