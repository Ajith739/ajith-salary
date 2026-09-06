<?php
/**
 * Authentication Helper Functions
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Start secure session
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,  // set true if using HTTPS
            'httponly'  => true,
            'samesite'  => 'Strict'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
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
            'INSERT INTO financial_settings (user_id, salary, salary_date, daily_travel_cost, 
             recharge_amount, recharge_interval_days, recharge_provider, next_recharge_date, current_cash)
             VALUES (?, 18000, 1, 40, 349, 90, "Jio", DATE_ADD(CURDATE(), INTERVAL 45 DAY), 2000)'
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
    $_SESSION['user_theme'] = $user['theme'];
    $_SESSION['login_time'] = time();
    
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
}