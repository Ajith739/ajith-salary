<?php
/**
 * API — Monthly Financial Record Recompute & Fetch
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

$year = (int)($_REQUEST['year'] ?? date('Y'));
$month = (int)($_REQUEST['month'] ?? date('n'));

if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    jsonResponse(['success' => false, 'message' => 'Invalid month or year'], 422);
}

// Generate or regenerate monthly record
$record = generateMonthlyFinancialRecord($userId, $year, $month);

jsonResponse([
    'success' => true,
    'data' => [
        'year' => $year,
        'month' => $month,
        'record' => $record
    ]
]);
