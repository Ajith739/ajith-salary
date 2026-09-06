<?php
/**
 * Application Configuration Constants
 */

define('APP_NAME', 'MyFinance');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/finance-app/public');
define('APP_CURRENCY', '₹');
define('APP_TIMEZONE', 'Asia/Kolkata');

// Session settings
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'myfinance_session');

// Security Secret Key
define('APP_SECRET', getenv('APP_SECRET') ?: 'myfinance_jwt_hmac_secret_key_secure_2026');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ASSETS_PATH', PUBLIC_PATH . '/assets');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Exception handler to catch and display diagnostic info instead of blank 500
set_exception_handler(function(Throwable $e) {
    error_log('Uncaught Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Application Error</title><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family:system-ui,sans-serif;background:#0b0f19;color:#f8fafc;padding:30px;display:flex;justify-content:center;align-items:center;min-height:90vh;margin:0;}';
    echo '.card{background:#151d2f;padding:32px;border-radius:14px;border:1px solid #334155;max-width:600px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,0.5);}';
    echo 'h2{color:#f87171;margin-top:0;}code{background:#0b0f19;color:#38bdf8;padding:3px 8px;border-radius:4px;word-break:break-all;}pre{background:#0b0f19;color:#e2e8f0;padding:12px;border-radius:8px;font-size:0.82rem;overflow:auto;}a{color:#38bdf8;text-decoration:none;font-weight:600;margin-right:15px;}</style></head><body>';
    echo '<div class="card"><h2>Application Error (500)</h2>';
    echo '<p style="color:#cbd5e1;"><strong>Error:</strong> <code>' . htmlspecialchars($e->getMessage()) . '</code></p>';
    echo '<p style="color:#94a3b8;font-size:0.9rem;">Location: <code>' . htmlspecialchars(basename($e->getFile())) . ':' . $e->getLine() . '</code></p>';
    echo '<p style="margin-top:20px;"><a href="login.php">← Back to Login</a><a href="db-test.php">Database Test</a></p></div></body></html>';
    exit;
});