<?php
/**
 * API — Financial Goals & Contributions CRUD
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
    $goals = getGoalProjections($userId);
    jsonResponse(['success' => true, 'data' => $goals]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'add_goal') {
        $name = sanitize($input['name'] ?? '');
        $targetAmount = (float)($input['target_amount'] ?? 0);
        $savedAmount = (float)($input['saved_amount'] ?? 0);
        $monthlyContrib = (float)($input['monthly_contribution'] ?? 0);
        $targetDate = !empty($input['target_date']) ? sanitize($input['target_date']) : null;
        $priority = sanitize($input['priority'] ?? 'medium');
        $icon = sanitize($input['icon'] ?? 'fa-bullseye');
        $color = sanitize($input['color'] ?? '#3b82f6');
        $notes = sanitize($input['notes'] ?? '');

        if (empty($name) || $targetAmount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid goal parameters'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO financial_goals 
             (user_id, name, target_amount, saved_amount, monthly_contribution, target_date, priority, icon, color, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $name, $targetAmount, $savedAmount, $monthlyContrib, $targetDate, $priority, $icon, $color, $notes]);
        jsonResponse(['success' => true, 'message' => 'Goal created']);

    } elseif ($action === 'add_contribution') {
        $goalId = (int)($input['goal_id'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);
        $date = sanitize($input['contribution_date'] ?? date('Y-m-d'));
        $notes = sanitize($input['notes'] ?? '');

        if ($goalId <= 0 || $amount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid contribution parameters'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO goal_contributions (goal_id, user_id, amount, contribution_date, notes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$goalId, $userId, $amount, $date, $notes]);

        $stmt = $db->prepare('UPDATE financial_goals SET saved_amount = saved_amount + ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$amount, $goalId, $userId]);

        jsonResponse(['success' => true, 'message' => 'Contribution added']);

    } elseif ($action === 'edit_goal') {
        $goalId = (int)($input['goal_id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $targetAmount = (float)($input['target_amount'] ?? 0);
        $savedAmount = (float)($input['saved_amount'] ?? 0);
        $monthlyContrib = (float)($input['monthly_contribution'] ?? 0);
        $targetDate = !empty($input['target_date']) ? sanitize($input['target_date']) : null;
        $priority = sanitize($input['priority'] ?? 'medium');
        $icon = sanitize($input['icon'] ?? 'fa-bullseye');
        $color = sanitize($input['color'] ?? '#3b82f6');
        $notes = sanitize($input['notes'] ?? '');

        if ($goalId <= 0 || empty($name) || $targetAmount <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid goal parameters'], 422);
        }

        $stmt = $db->prepare(
            'UPDATE financial_goals SET 
             name = ?, target_amount = ?, saved_amount = ?, monthly_contribution = ?, 
             target_date = ?, priority = ?, icon = ?, color = ?, notes = ? 
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$name, $targetAmount, $savedAmount, $monthlyContrib, $targetDate, $priority, $icon, $color, $notes, $goalId, $userId]);
        jsonResponse(['success' => true, 'message' => 'Goal updated']);

    } elseif ($action === 'delete_goal') {
        $goalId = (int)($input['goal_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM financial_goals WHERE id = ? AND user_id = ?');
        $stmt->execute([$goalId, $userId]);
        jsonResponse(['success' => true, 'message' => 'Goal removed']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
}
