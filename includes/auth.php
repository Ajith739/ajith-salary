<?php
/**
 * Authentication Helper Functions
 * Supports standard PHP sessions with cryptographic HMAC auth cookie fallback
 * for stateless serverless environments like Vercel.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Detect whether current connection is HTTPS (including Vercel / Cloudflare reverse proxies)
 */
function isHttps(): bool {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
}

/**
 * Start secure session
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Set cryptographic auth cookie for serverless persistence
 */
function setAuthCookie(int $userId, string $name, string $email, string $theme): void {
    $payload = json_encode([
        'uid'   => $userId,
        'name'  => $name,
        'email' => $email,
        'theme' => $theme,
        'exp'   => time() + (SESSION_LIFETIME * 7) // 7 days
    ]);
    
    $encoded = base64_encode($payload);
    $sig = hash_hmac('sha256', $encoded, APP_SECRET);
    $val = $encoded . '.' . $sig;
    
    if (!headers_sent()) {
        setcookie('myfinance_auth', $val, [
            'expires'  => time() + (SESSION_LIFETIME * 7),
            'path'     => '/',
            'secure'   => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    $_COOKIE['myfinance_auth'] = $val;
}

/**
 * Verify and restore session from auth cookie if session was lost in serverless lambda
 */
function restoreAuthFromCookie(): bool {
    if (empty($_COOKIE['myfinance_auth'])) {
        return false;
    }
    
    $parts = explode('.', $_COOKIE['myfinance_auth'], 2);
    if (count($parts) !== 2) {
        return false;
    }
    
    [$encoded, $sig] = $parts;
    $expectedSig = hash_hmac('sha256', $encoded, APP_SECRET);
    if (!hash_equals($expectedSig, $sig)) {
        return false;
    }
    
    $data = json_decode(base64_decode($encoded), true);
    if (!is_array($data) || empty($data['uid']) || empty($data['exp'])) {
        return false;
    }
    
    if (time() > (int)$data['exp']) {
        clearAuthCookie();
        return false;
    }
    
    $_SESSION['user_id'] = (int)$data['uid'];
    $_SESSION['user_name'] = $data['name'] ?? '';
    $_SESSION['user_email'] = $data['email'] ?? '';
    $_SESSION['user_theme'] = $data['theme'] ?? 'light';
    $_SESSION['login_time'] = time();
    
    return true;
}

/**
 * Clear auth cookie
 */
function clearAuthCookie(): void {
    if (!headers_sent()) {
        setcookie('myfinance_auth', '', [
            'expires'  => time() - 86400,
            'path'     => '/',
            'secure'   => isHttps(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    unset($_COOKIE['myfinance_auth']);
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    startSecureSession();
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    return restoreAuthFromCookie();
}

/**
 * Require authentication — redirect to login if not authenticated
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId(): int {
    if (!isLoggedIn()) {
        return 0;
    }
    return (int)($_SESSION['user_id'] ?? 0);
}

/**
 * Get current user data
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, email, theme, created_at FROM users WHERE id = ?');
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch() ?: null;
}

/**
 * Register a new user
 */
function registerUser(string $name, string $email, string $password): array {
    $db = getDB();
    
    // Check if email exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email already registered.'];
    }
    
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $db->beginTransaction();
    try {
        // Create user
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $hash]);
        $userId = (int)$db->lastInsertId();
        
        // Create default financial settings
        $stmt = $db->prepare(
            "INSERT INTO financial_settings (user_id, salary, salary_date, daily_travel_cost, 
             recharge_amount, recharge_interval_days, recharge_provider, next_recharge_date, current_cash)
             VALUES (?, 18000, 1, 40, 349, 90, 'Jio', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 2000)"
        );
        $stmt->execute([$userId]);
        
        // Create default wallet accounts
        $wallets = [
            ['Cash in Hand', 'cash', 2000, 'fa-money-bill-wave', '#10b981'],
            ['Bank Account', 'bank', 0, 'fa-university', '#3b82f6'],
            ['UPI', 'upi', 0, 'fa-mobile-alt', '#8b5cf6']
        ];
        $stmt = $db->prepare(
            'INSERT INTO wallet_accounts (user_id, name, type, balance, icon, color) VALUES (?,?,?,?,?,?)'
        );
        foreach ($wallets as $w) {
            $stmt->execute([$userId, $w[0], $w[1], $w[2], $w[3], $w[4]]);
        }
        
        $db->commit();
        return ['success' => true, 'user_id' => $userId];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Registration error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

/**
 * Login user
 */
function loginUser(string $email, string $password): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, email, password_hash, theme FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    
    // Regenerate session
    startSecureSession();
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_theme'] = $user['theme'] ?? 'light';
    $_SESSION['login_time'] = time();
    
    // Set persistent signed auth cookie for serverless compatibility
    setAuthCookie((int)$user['id'], (string)$user['name'], (string)$user['email'], (string)($user['theme'] ?? 'light'));
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    clearAuthCookie();
}