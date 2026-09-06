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

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Error reporting (disable display in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);