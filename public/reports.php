<?php
/**
 * REPORTS — Financial Statements & CSV / PDF Export
 */
define('PAGE_TITLE', 'Reports');
define('PAGE_ID', 'reports');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $db->prepare(
        'SELECT expense_date, title, category, payment_method, amount, notes 
         FROM expenses 
         WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
         ORDER BY expense_date ASC'
    );
    $stmt->execute([$userId, $year, $month]);
    $exportRows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=MyFinance_Expenses_' . $year . '_' . $month . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Title', 'Category', 'Payment Method', 'Amount (INR)', 'Notes']);

    foreach ($exportRows as $row) {
        fputcsv($output, [
            $row['expense_date'],
            $row['title'],
            $row['category'],
            strtoupper($row['payment_method']),
            $row['amount'],
            $row['notes']
        ]);
    }
    fclose($output);
    exit;
}

// Generate monthly record
$monthly = generateMonthlyFinancialRecord($userId, $year, $month);
$settings = getUserSettings($userId);

// Fetch categories spent this month
$stmt = $db->prepare(
    'SELECT category, SUM(amount) as total FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     GROUP BY category ORDER BY total DESC'
);
$stmt->execute([$userId, $year, $month]);
$categoryExpenses = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER & ACTIONS ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Monthly Financial Statement</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Detailed accounting of income, fixed obligations, and variable spending</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="?export=csv&year=<?= $year ?>&month=<?= $month ?>" class="btn-secondary-custom">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
        <button class="btn-primary-custom" onclick="window.print()">
            <i class="fas fa-print"></i> Print Statement
        </button>
    </div>
</div>

<!-- ─── PERIOD SELECTOR ─── -->
<div class="table-card" style="padding: 1rem 1.5rem; margin-bottom: 1.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <form method="GET" action="" style="display: flex; gap: 0.75rem; align-items: center;">
        <select name="month" class="form-group-custom" style="width: auto; margin-bottom: 0; padding: 0.5rem 1rem;">
            <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= getMonthName($m) ?></option>
            <?php endfor; ?>
        </select>
        <select name="year" class="form-group-custom" style="width: auto; margin-bottom: 0; padding: 0.5rem 1rem;">
            <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
            <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn-primary-custom btn-sm-custom"><i class="fas fa-search"></i> Generate</button>
    </form>
    
    <div style="font-size: 0.9rem; font-weight: 600;">
        Statement for <strong><?= getMonthName($month) ?> <?= $year ?></strong>
    </div>
</div>

<!-- ─── PRINTABLE STATEMENT CARD ─── -->
<div class="table-card" style="padding: 2.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--border-color); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 900; margin: 0;"><?= APP_NAME ?> Financial Report</h1>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.25rem;">Generated on <?= date('d F Y') ?> for User #<?= $userId ?></p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary-light);"><?= getMonthName($month) ?> <?= $year ?></div>
            <div style="font-size: 0.85rem; color: var(--text-muted);"><?= $monthly['working_days'] ?> Working Days Recorded</div>
        </div>
    </div>

    <!-- 1. Income Section -->
    <div style="margin-bottom: 1.75rem;">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--success); margin-bottom: 0.75rem;">
            <i class="fas fa-plus-circle"></i> 1. Gross Income
        </h3>
        <table class="custom-table">
            <tbody>
                <tr>
                    <td>Primary Salary Take-Home</td>
                    <td style="text-align: right; font-weight: 700; color: var(--success);"><?= formatINR($monthly['salary']) ?></td>
                </tr>
                <tr>
                    <td>Additional Income (Freelance / Bonus / Refunds)</td>
                    <td style="text-align: right; font-weight: 700; color: var(--success);"><?= formatINR($monthly['additional_income']) ?></td>
                </tr>
                <tr style="background: rgba(16, 185, 129, 0.08); font-weight: 800;">
                    <td>Total Income</td>
                    <td style="text-align: right; font-size: 1.05rem; color: var(--success);"><?= formatINR($monthly['total_income']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 2. Fixed Obligations -->
    <div style="margin-bottom: 1.75rem;">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--warning-light); margin-bottom: 0.75rem;">
            <i class="fas fa-shield-alt"></i> 2. Fixed & Recurring Commitments
        </h3>
        <table class="custom-table">
            <tbody>
                <tr>
                    <td>Workplace Commute (<?= $monthly['working_days'] ?> days × <?= formatINR((float)$settings['daily_travel_cost']) ?>)</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger-light);">-<?= formatINR($monthly['travel_expense']) ?></td>
                </tr>
                <tr>
                    <td>Mobile Recharge Plan Allocation</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger-light);">-<?= formatINR($monthly['recharge_expense']) ?></td>
                </tr>
                <tr>
                    <td>Active Subscriptions & Recurring Bills</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger-light);">-<?= formatINR($monthly['recurring_expense']) ?></td>
                </tr>
                <tr>
                    <td>Active Loan EMI Installments</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger-light);">-<?= formatINR($monthly['emi_total']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 3. Variable Discretionary Expenses -->
    <div style="margin-bottom: 1.75rem;">
        <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--danger-light); margin-bottom: 0.75rem;">
            <i class="fas fa-shopping-cart"></i> 3. Variable Expenses & Friend Loans
        </h3>
        <table class="custom-table">
            <tbody>
                <?php if (empty($categoryExpenses)): ?>
                <tr>
                    <td colspan="2" style="color: var(--text-muted); text-align: center;">No categorized variable expenses recorded.</td>
                </tr>
                <?php else: foreach ($categoryExpenses as $c): ?>
                <tr>
                    <td><?= e($c['category']) ?></td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger-light);">-<?= formatINR((float)$c['total']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <tr>
                    <td>Money Lent to Friends This Month</td>
                    <td style="text-align: right; font-weight: 600; color: var(--danger-light);">-<?= formatINR($monthly['friend_money']) ?></td>
                </tr>
                <tr style="background: rgba(244, 63, 94, 0.08); font-weight: 800;">
                    <td>Total Outflow (Fixed + Variable)</td>
                    <td style="text-align: right; font-size: 1.05rem; color: var(--danger-light);">-<?= formatINR($monthly['total_expenses']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- 4. Summary & Net Savings -->
    <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1.5rem; margin-top: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); display: block;">
                    Net Monthly Savings
                </span>
                <div style="font-size: 2rem; font-weight: 900; color: <?= $monthly['savings'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= formatINR($monthly['savings']) ?>
                </div>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.85rem; color: var(--text-muted); display: block;">Savings Rate</span>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary-light);">
                    <?= $monthly['savings_rate'] ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
