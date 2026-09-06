<?php
/**
 * API — Expenses CRUD
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
    $year = (int)($_GET['year'] ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    $category = sanitize($_GET['category'] ?? '');

    $sql = 'SELECT * FROM expenses WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?';
    $params = [$userId, $year, $month];

    if (!empty($category)) {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }

    $sql .= ' ORDER BY expense_date DESC, created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $expenses]);

} elseif ($method === 'POST') {
    // Parse json or post body
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        jsonResponse(['success' => true, 'message' => 'Expense deleted.']);
    } else {
        $title = sanitize($input['title'] ?? '');
        $amount = (float)($input['amount'] ?? 0);
        $category = sanitize($input['category'] ?? 'Other');
        $expenseDate = sanitize($input['expense_date'] ?? date('Y-m-d'));
        $paymentMethod = sanitize($input['payment_method'] ?? 'cash');
        $notes = sanitize($input['notes'] ?? '');

        if (empty($title) || $amount <= 0 || !isValidDate($expenseDate)) {
            jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO expenses (user_id, category, title, amount, expense_date, payment_method, notes) 
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $category, $title, $amount, $expenseDate, $paymentMethod, $notes]);
        $id = (int)$db->lastInsertId();

        // Refresh monthly
        $m = (int)date('n', strtotime($expenseDate));
        $y = (int)date('Y', strtotime($expenseDate));
        generateMonthlyFinancialRecord($userId, $y, $m);

        jsonResponse(['success' => true, 'id' => $id, 'message' => 'Expense created successfully']);
    }
}
