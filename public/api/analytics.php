<?php
/**
 * API — Analytics Trends & Distribution
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

// 12-month trends
$trendData = [];
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

for ($i = 11; $i >= 0; $i--) {
    $m = $currentMonth - $i;
    $y = $currentYear;
    while ($m < 1) { $m += 12; $y--; }
    
    $stmt = $db->prepare('SELECT * FROM monthly_financials WHERE user_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $y, $m]);
    $mf = $stmt->fetch();
    
    if (!$mf) {
        $mf = generateMonthlyFinancialRecord($userId, $y, $m);
    }
    
    $trendData[] = [
        'label' => getShortMonthName($m) . ' ' . $y,
        'income' => (float)($mf['total_income'] ?? 0),
        'expenses' => (float)($mf['total_expenses'] ?? 0),
        'savings' => (float)($mf['savings'] ?? 0),
        'savings_rate' => (float)($mf['savings_rate'] ?? 0)
    ];
}

// Category breakdown
$stmt = $db->prepare('SELECT category, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY category ORDER BY total DESC');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Payment methods
$stmt = $db->prepare('SELECT payment_method, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY payment_method ORDER BY total DESC');
$stmt->execute([$userId]);
$paymentMethods = $stmt->fetchAll();

jsonResponse([
    'success' => true,
    'data' => [
        'trends' => $trendData,
        'categories' => $categories,
        'payment_methods' => $paymentMethods
    ]
]);
