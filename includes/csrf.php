<?php
/**
 * CSRF Token Management
 * Implements standard Session Token + Double-Submit Cookie pattern
 * for robust protection in serverless environments like Vercel.
 */

require_once __DIR__ . '/auth.php';

// Ensure secure session is initialized with proper cookie parameters
startSecureSession();

/**
 * Generate or retrieve CSRF token
 */
function generateCSRFToken(): string {
    startSecureSession();
    
    // Use existing token from session or cookie if present
    if (!empty($_SESSION['csrf_token'])) {
        $token = $_SESSION['csrf_token'];
    } elseif (!empty($_COOKIE['myfinance_csrf']) && strlen($_COOKIE['myfinance_csrf']) === 64) {
        $token = $_COOKIE['myfinance_csrf'];
    } else {
        $token = bin2hex(random_bytes(32));
    }
    
    $_SESSION['csrf_token'] = $token;
    
    // Set double-submit cookie so serverless lambda instances can validate
    if (empty($_COOKIE['myfinance_csrf']) || $_COOKIE['myfinance_csrf'] !== $token) {
        if (!headers_sent()) {
            setcookie('myfinance_csrf', $token, [
                'expires'  => time() + 86400,
                'path'     => '/',
                'secure'   => isHttps(),
                'httponly' => false, // Accessible to client JavaScript for AJAX if needed
                'samesite' => 'Lax'
            ]);
        }
        $_COOKIE['myfinance_csrf'] = $token;
    }
    
    return $token;
}

/**
 * Get CSRF token (alias)
 */
function csrf_token(): string {
    return generateCSRFToken();
}

/**
 * Get CSRF hidden input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') .
        '">';
}

/**
 * Validate given CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (!$token) {
        return false;
    }
    
    startSecureSession();
    
    // 1. Validate against session token
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    
    // 2. Validate against double-submit cookie (vital for serverless containers)
    if (!empty($_COOKIE['myfinance_csrf']) && hash_equals($_COOKIE['myfinance_csrf'], $token)) {
        $_SESSION['csrf_token'] = $token;
        return true;
    }
    
    return false;
}

/**
 * Validate CSRF token from POST or HTTP header
 */
function validateCSRF(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token)) {
        return false;
    }
    return verify_csrf_token($token);
}

/**
 * Require valid CSRF token or die
 */
function requireCSRF(): void {
    if (!validateCSRF()) {
        http_response_code(403);
        header('Content-Type: application/json');
        die(json_encode(['success' => false, 'message' => 'Security token expired. Please refresh the page and try again.']));
    }
}