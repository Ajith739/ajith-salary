<?php
/**
 * API — Dashboard Metrics & Charts
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
$settings = getUserSettings($userId);

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Monthly Record
$monthly = generateMonthlyFinancialRecord($userId, $year, $month);
$health = calculateFinancialHealth($userId);
$friendSummary = getFriendMoneySummary($userId);
$goalProjections = getGoalProjections($userId);
$alerts = generateAlerts($userId);

// Chart Data (6 months)
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $cm = $month - $i;
    $cy = $year;
    while ($cm < 1) { $cm += 12; $cy--; }
    
    $stmt = $db->prepare('SELECT * FROM monthly_financials WHERE user_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $cy, $cm]);
    $mf = $stmt->fetch();
    
    $chartData[] = [
        'label' => getShortMonthName($cm) . ' ' . $cy,
        'income' => (float)($mf['total_income'] ?? 0),
        'expenses' => (float)($mf['total_expenses'] ?? 0),
        'savings' => (float)($mf['savings'] ?? 0)
    ];
}

// Categories Chart
$stmt = $db->prepare(
    'SELECT category, SUM(amount) as total FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     GROUP BY category ORDER BY total DESC'
);
$stmt->execute([$userId, $year, $month]);
$categories = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => [
        'monthly' => $monthly,
        'health' => $health,
        'friend_summary' => $friendSummary,
        'goals' => $goalProjections,
        'alerts' => $alerts,
        'chart_data' => $chartData,
        'categories' => $categories
    ]
]);
