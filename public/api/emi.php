<?php
/**
 * API — EMI Calculations & Active Loans
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
    if (isset($_GET['calc'])) {
        $p = (float)($_GET['principal'] ?? 0);
        $r = (float)($_GET['rate'] ?? 0);
        $t = (int)($_GET['tenure'] ?? 0);
        $schedule = generateEMISchedule($p, $r, $t, date('Y-m-d'));
        jsonResponse(['success' => true, 'data' => $schedule]);
    } else {
        $stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY active DESC, created_at DESC');
        $stmt->execute([$userId]);
        $loans = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $loans]);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'add_loan') {
        $name = sanitize($input['loan_name'] ?? '');
        $principal = (float)($input['principal'] ?? 0);
        $rate = (float)($input['annual_interest_rate'] ?? 0);
        $tenure = (int)($input['tenure_months'] ?? 0);
        $startDate = sanitize($input['start_date'] ?? date('Y-m-d'));
        $notes = sanitize($input['notes'] ?? '');

        if (empty($name) || $principal <= 0 || $tenure <= 0 || !isValidDate($startDate)) {
            jsonResponse(['success' => false, 'message' => 'Invalid loan parameters'], 422);
        }

        $emiData = calculateEMI($principal, $rate, $tenure);
        $stmt = $db->prepare(
            'INSERT INTO loans 
             (user_id, loan_name, principal, annual_interest_rate, tenure_months, start_date, emi_amount, total_interest, total_payable, active, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([$userId, $name, $principal, $rate, $tenure, $startDate, $emiData['emi'], $emiData['total_interest'], $emiData['total_payable'], $notes]);
        generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
        jsonResponse(['success' => true, 'message' => 'Loan added']);

    } elseif ($action === 'edit_loan') {
        $loanId = (int)($input['loan_id'] ?? 0);
        $name = sanitize($input['loan_name'] ?? '');
        $principal = (float)($input['principal'] ?? 0);
        $rate = (float)($input['annual_interest_rate'] ?? 0);
        $tenure = (int)($input['tenure_months'] ?? 0);
        $startDate = sanitize($input['start_date'] ?? date('Y-m-d'));
        $active = isset($input['active']) ? (int)$input['active'] : 1;
        $notes = sanitize($input['notes'] ?? '');

        if ($loanId <= 0 || empty($name) || $principal <= 0 || $tenure <= 0 || !isValidDate($startDate)) {
            jsonResponse(['success' => false, 'message' => 'Invalid loan parameters'], 422);
        }

        $emiData = calculateEMI($principal, $rate, $tenure);
        $stmt = $db->prepare(
            'UPDATE loans SET 
             loan_name = ?, principal = ?, annual_interest_rate = ?, tenure_months = ?, 
             start_date = ?, emi_amount = ?, total_interest = ?, total_payable = ?, active = ?, notes = ? 
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $name, $principal, $rate, $tenure, $startDate, 
            $emiData['emi'], $emiData['total_interest'], $emiData['total_payable'], 
            $active, $notes, $loanId, $userId
        ]);

        generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
        jsonResponse(['success' => true, 'message' => 'Loan updated']);

    } elseif ($action === 'delete_loan') {
        $loanId = (int)($input['loan_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM loans WHERE id = ? AND user_id = ?');
        $stmt->execute([$loanId, $userId]);
        generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
        jsonResponse(['success' => true, 'message' => 'Loan removed']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
    }
}
