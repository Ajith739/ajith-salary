<?php
/**
 * API — Friends & Personal Loans CRUD
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
    $summary = getFriendMoneySummary($userId);
    jsonResponse(['success' => true, 'data' => $summary]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'add_friend') {
        $name = sanitize($input['name'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $notes = sanitize($input['notes'] ?? '');

        if (empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Friend name is required'], 422);
        }

        $stmt = $db->prepare('INSERT INTO friends (user_id, name, phone, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $name, $phone, $notes]);
        jsonResponse(['success' => true, 'message' => 'Friend added']);

    } elseif ($action === 'edit_friend') {
        $friendId = (int)($input['friend_id'] ?? 0);
        $name = sanitize($input['name'] ?? '');
        $phone = sanitize($input['phone'] ?? '');
        $notes = sanitize($input['notes'] ?? '');

        if ($friendId <= 0 || empty($name)) {
            jsonResponse(['success' => false, 'message' => 'Friend ID and name are required'], 422);
        }

        $stmt = $db->prepare('UPDATE friends SET name = ?, phone = ?, notes = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $phone, $notes, $friendId, $userId]);
        jsonResponse(['success' => true, 'message' => 'Friend details updated']);

    } elseif ($action === 'add_transaction') {
        $friendId = (int)($input['friend_id'] ?? 0);
        $type = sanitize($input['type'] ?? 'given');
        $amount = (float)($input['amount'] ?? 0);
        $date = sanitize($input['transaction_date'] ?? date('Y-m-d'));
        $desc = sanitize($input['description'] ?? '');

        if ($friendId <= 0 || $amount <= 0 || !isValidDate($date)) {
            jsonResponse(['success' => false, 'message' => 'Invalid transaction data'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO friend_transactions (friend_id, user_id, type, amount, transaction_date, description) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$friendId, $userId, $type, $amount, $date, $desc]);

        $m = (int)date('n', strtotime($date));
        $y = (int)date('Y', strtotime($date));
        generateMonthlyFinancialRecord($userId, $y, $m);

        jsonResponse(['success' => true, 'message' => 'Transaction recorded']);

    } elseif ($action === 'edit_transaction') {
        $txId = (int)($input['transaction_id'] ?? 0);
        $type = sanitize($input['type'] ?? 'given');
        $amount = (float)($input['amount'] ?? 0);
        $date = sanitize($input['transaction_date'] ?? date('Y-m-d'));
        $desc = sanitize($input['description'] ?? '');

        if ($txId <= 0 || $amount <= 0 || !isValidDate($date)) {
            jsonResponse(['success' => false, 'message' => 'Invalid transaction data'], 422);
        }

        $stmt = $db->prepare('SELECT transaction_date FROM friend_transactions WHERE id = ? AND user_id = ?');
        $stmt->execute([$txId, $userId]);
        $oldTx = $stmt->fetch();

        if (!$oldTx) {
            jsonResponse(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $stmt = $db->prepare(
            'UPDATE friend_transactions SET type = ?, amount = ?, transaction_date = ?, description = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$type, $amount, $date, $desc, $txId, $userId]);

        $oldM = (int)date('n', strtotime($oldTx['transaction_date']));
        $oldY = (int)date('Y', strtotime($oldTx['transaction_date']));
        generateMonthlyFinancialRecord($userId, $oldY, $oldM);

        $newM = (int)date('n', strtotime($date));
        $newY = (int)date('Y', strtotime($date));
        generateMonthlyFinancialRecord($userId, $newY, $newM);

        jsonResponse(['success' => true, 'message' => 'Transaction updated']);

    } elseif ($action === 'delete_transaction') {
        $txId = (int)($input['transaction_id'] ?? 0);
        $stmt = $db->prepare('SELECT transaction_date FROM friend_transactions WHERE id = ? AND user_id = ?');
        $stmt->execute([$txId, $userId]);
        $oldTx = $stmt->fetch();

        if ($oldTx) {
            $stmt = $db->prepare('DELETE FROM friend_transactions WHERE id = ? AND user_id = ?');
            $stmt->execute([$txId, $userId]);
            $m = (int)date('n', strtotime($oldTx['transaction_date']));
            $y = (int)date('Y', strtotime($oldTx['transaction_date']));
            generateMonthlyFinancialRecord($userId, $y, $m);
        }
        jsonResponse(['success' => true, 'message' => 'Transaction deleted']);

    } elseif ($action === 'delete_friend') {
        $friendId = (int)($input['friend_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM friends WHERE id = ? AND user_id = ?');
        $stmt->execute([$friendId, $userId]);
        generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
        jsonResponse(['success' => true, 'message' => 'Friend removed']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
}
