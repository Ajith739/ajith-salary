<?php
/**
 * API — Income CRUD
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

    $stmt = $db->prepare(
        'SELECT * FROM income WHERE user_id = ? AND YEAR(income_date) = ? AND MONTH(income_date) = ? ORDER BY income_date DESC'
    );
    $stmt->execute([$userId, $year, $month]);
    $incomes = $stmt->fetchAll();

    jsonResponse(['success' => true, 'data' => $incomes]);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM income WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        jsonResponse(['success' => true, 'message' => 'Income record removed.']);
    } elseif ($action === 'edit') {
        $id = (int)($input['id'] ?? 0);
        $type = sanitize($input['income_type'] ?? 'freelance');
        $amount = (float)($input['amount'] ?? 0);
        $incomeDate = sanitize($input['income_date'] ?? date('Y-m-d'));
        $description = sanitize($input['description'] ?? '');

        if ($id <= 0 || $amount <= 0 || !isValidDate($incomeDate)) {
            jsonResponse(['success' => false, 'message' => 'Invalid income parameters'], 422);
        }

        $stmt = $db->prepare('SELECT income_date FROM income WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        $oldInc = $stmt->fetch();

        if (!$oldInc) {
            jsonResponse(['success' => false, 'message' => 'Income record not found'], 404);
        }

        $stmt = $db->prepare(
            'UPDATE income SET income_type = ?, amount = ?, income_date = ?, description = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$type, $amount, $incomeDate, $description, $id, $userId]);

        $oldM = (int)date('n', strtotime($oldInc['income_date']));
        $oldY = (int)date('Y', strtotime($oldInc['income_date']));
        generateMonthlyFinancialRecord($userId, $oldY, $oldM);

        $newM = (int)date('n', strtotime($incomeDate));
        $newY = (int)date('Y', strtotime($incomeDate));
        generateMonthlyFinancialRecord($userId, $newY, $newM);

        jsonResponse(['success' => true, 'message' => 'Income updated successfully']);
    } else {
        $type = sanitize($input['income_type'] ?? 'freelance');
        $amount = (float)($input['amount'] ?? 0);
        $incomeDate = sanitize($input['income_date'] ?? date('Y-m-d'));
        $description = sanitize($input['description'] ?? '');

        if ($amount <= 0 || !isValidDate($incomeDate)) {
            jsonResponse(['success' => false, 'message' => 'Invalid income parameters'], 422);
        }

        $stmt = $db->prepare(
            'INSERT INTO income (user_id, income_type, amount, income_date, description) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $amount, $incomeDate, $description]);

        // Refresh monthly
        $m = (int)date('n', strtotime($incomeDate));
        $y = (int)date('Y', strtotime($incomeDate));
        generateMonthlyFinancialRecord($userId, $y, $m);

        jsonResponse(['success' => true, 'message' => 'Income logged successfully']);
    }
}
