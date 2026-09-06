<?php
/**
 * API — Calendar Days & Daily Expenses
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/calendar.php';
require_once __DIR__ . '/../../includes/finance.php';

startSecureSession();

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = getCurrentUserId();
$db = getDB();
$settings = getUserSettings($userId);

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$calData = getCalendarData($year, $month, $settings);
$workingDays = getWorkingDays($year, $month, $settings);

// Expenses for days (totals)
$stmt = $db->prepare(
    'SELECT DATE(expense_date) as exp_date, SUM(amount) as total 
     FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     GROUP BY DATE(expense_date)'
);
$stmt->execute([$userId, $year, $month]);
$expenses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Itemized expenses by day
$stmt = $db->prepare(
    'SELECT * FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     ORDER BY expense_date ASC, created_at ASC'
);
$stmt->execute([$userId, $year, $month]);
$allExp = $stmt->fetchAll();
$expensesByDayItems = [];
foreach ($allExp as $e) {
    $d = date('Y-m-d', strtotime($e['expense_date']));
    $expensesByDayItems[$d][] = $e;
}

jsonResponse([
    'success' => true,
    'data' => [
        'calendar' => $calData,
        'working_days' => $workingDays,
        'daily_travel' => (float)($settings['daily_travel_cost'] ?? 40),
        'expenses_by_day' => $expenses,
        'expenses_by_day_items' => $expensesByDayItems
    ]
]);
