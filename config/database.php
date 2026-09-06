<?php
/**
 * Database Configuration — PDO Connection
 * Dynamically reads environment variables for cloud deployment (Vercel / Docker / VPS)
 * with automatic fallback to local XAMPP defaults.
 */

// ── Database credentials with Environment Variable support ──
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost'));
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int)(getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306)));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'myfinance'));
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: ($_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? '')));
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}
if (!defined('DB_SSL')) {
    $sslVal = getenv('DB_SSL') ?: ($_ENV['DB_SSL'] ?? '');
    define('DB_SSL', in_array(strtolower((string)$sslVal), ['1', 'true', 'yes', 'on'], true));
}

/**
 * Get PDO database connection (singleton pattern)
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = null;
        $isLocalhost = (DB_HOST === 'localhost' || DB_HOST === '127.0.0.1');

        // Only search for unix sockets when running locally on localhost
        if ($isLocalhost) {
            $socketPaths = [
                '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
                '/tmp/mysql.sock',
                '/var/mysql/mysql.sock',
                '/var/run/mysqld/mysqld.sock'
            ];
            foreach ($socketPaths as $sp) {
                if (file_exists($sp)) {
                    $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $sp, DB_NAME, DB_CHARSET);
                    break;
                }
            }
        }

        // Standard TCP connection for remote / online databases or if socket not found
        if (!$dsn) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        }
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        // Enable SSL options for cloud MySQL providers (e.g. TiDB Cloud, Aiven)
        if (DB_SSL && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed [' . DB_HOST . ':' . DB_PORT . ']: ' . $e->getMessage());
            
            $isApi = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) 
                     || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
            
            $hint = '';
            if (strpos(DB_HOST, 'vercel.app') !== false) {
                $hint = 'Vercel is a web host and does not run a MySQL database on port 3306. You need a free cloud MySQL database (such as TiDB Cloud Serverless or Aiven).';
            }

            if ($isApi) {
                http_response_code(500);
                header('Content-Type: application/json');
                die(json_encode([
                    'success' => false,
                    'message' => 'Database connection failed. ' . $hint,
                    'error'   => $e->getMessage()
                ]));
            }
            
            die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Connection Error — MyFinance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Inter", sans-serif; background: #0b0f19; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #151d2f; padding: 36px; border-radius: 16px; max-width: 580px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.08); }
        h2 { color: #f87171; margin-top: 0; font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        p { color: #94a3b8; line-height: 1.6; font-size: 0.95rem; }
        .hint-box { background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 14px 16px; border-radius: 0 8px 8px 0; margin: 18px 0; color: #fbbf24; font-size: 0.9rem; }
        code { background: #0b0f19; color: #38bdf8; padding: 3px 8px; border-radius: 6px; font-size: 0.88em; border: 1px solid rgba(255,255,255,0.08); }
        .err-detail { background: #0b0f19; color: #e2e8f0; padding: 12px 14px; border-radius: 8px; font-family: monospace; font-size: 0.82rem; word-break: break-all; margin-top: 15px; border: 1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
    <div class="card">
        <h2>Database Connection Error</h2>
        <p>Could not connect to MySQL at <code>' . htmlspecialchars(DB_HOST . ':' . DB_PORT) . '</code> for database <code>' . htmlspecialchars(DB_NAME) . '</code>.</p>
        ' . ($hint ? '<div class="hint-box"><strong>Configuration Note:</strong><br>' . htmlspecialchars($hint) . '</div>' : '') . '
        <p>Configure your environment variables in Vercel (<strong>Settings → Environment Variables</strong>):<br>
           <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code>, <code>DB_NAME</code>, <code>DB_PORT</code>
        </p>
        <div class="err-detail">' . htmlspecialchars($e->getMessage()) . '</div>
    </div>
</body>
</html>');
        }
    }
    
    return $pdo;
}