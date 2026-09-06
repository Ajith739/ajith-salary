<?php
/**
 * API — Financial Settings & User Theme
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/finance.php';

startSecureSession();

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = getCurrentUserId();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $settings = getUserSettings($userId);
    jsonResponse(['success' => true, 'data' => $settings]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'update_theme') {
        $theme = sanitize($input['theme'] ?? 'dark');
        if (in_array($theme, ['dark', 'light'])) {
            $stmt = $db->prepare('UPDATE users SET theme = ? WHERE id = ?');
            $stmt->execute([$theme, $userId]);
            $_SESSION['user_theme'] = $theme;
            jsonResponse(['success' => true, 'theme' => $theme]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid theme'], 422);
        }
    } else {
        // Update general settings
        $salary = (float)($input['salary'] ?? 18000);
        $salaryDate = (int)($input['salary_date'] ?? 1);
        $travel = (float)($input['daily_travel_cost'] ?? 40);

        $stmt = $db->prepare('UPDATE financial_settings SET salary = ?, salary_date = ?, daily_travel_cost = ? WHERE user_id = ?');
        $stmt->execute([$salary, $salaryDate, $travel, $userId]);

        jsonResponse(['success' => true, 'message' => 'Settings updated']);
    }
}
