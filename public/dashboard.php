<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
/**
 * DASHBOARD — Main Financial Overview
 */
define('PAGE_TITLE', 'Dashboard');
define('PAGE_ID', 'dashboard');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$settings = getUserSettings($userId);
$db = getDB();

// Current period
$view = $_GET['view'] ?? (isset($_GET['year']) && !isset($_GET['month']) ? 'year' : 'month');
$isYearView = ($view === 'year');
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

if ($isYearView) {
    // Generate/sync monthly records for all months of the year
    $maxM = ($year == (int)date('Y')) ? (int)date('n') : 12;
    for ($m = 1; $m <= $maxM; $m++) {
        generateMonthlyFinancialRecord($userId, $year, $m);
    }
    
    // Aggregated annual stats from monthly_financials
    $stmt = $db->prepare(
        'SELECT 
            SUM(salary) as salary,
            SUM(additional_income) as additional_income,
            SUM(total_income) as total_income,
            SUM(travel_expense) as travel_expense,
            SUM(recharge_expense) as recharge_expense,
            SUM(recurring_expense) as recurring_expense,
            SUM(personal_expense) as personal_expense,
            SUM(friend_money) as friend_money,
            SUM(emi_total) as emi_total,
            SUM(total_expenses) as total_expenses,
            SUM(savings) as savings,
            SUM(working_days) as working_days
         FROM monthly_financials WHERE user_id = ? AND year = ?'
    );
    $stmt->execute([$userId, $year]);
    $yearRow = $stmt->fetch();
    
    $totalInc = (float)($yearRow['total_income'] ?? 0);
    $totalExp = (float)($yearRow['total_expenses'] ?? 0);
    $totalSav = (float)($yearRow['savings'] ?? 0);
    $savingsRate = $totalInc > 0 ? round(($totalSav / $totalInc) * 100, 1) : 0;
    
    $displayMonthly = [
        'salary' => (float)($yearRow['salary'] ?? 0),
        'additional_income' => (float)($yearRow['additional_income'] ?? 0),
        'total_income' => $totalInc,
        'travel_expense' => (float)($yearRow['travel_expense'] ?? 0),
        'recharge_expense' => (float)($yearRow['recharge_expense'] ?? 0),
        'recurring_expense' => (float)($yearRow['recurring_expense'] ?? 0),
        'personal_expense' => (float)($yearRow['personal_expense'] ?? 0),
        'friend_money' => (float)($yearRow['friend_money'] ?? 0),
        'emi_total' => (float)($yearRow['emi_total'] ?? 0),
        'total_expenses' => $totalExp,
        'savings' => $totalSav,
        'savings_rate' => $savingsRate,
        'working_days' => (int)($yearRow['working_days'] ?? 0),
    ];
    $monthly = $displayMonthly;

    // Monthly data for all 12 months in this year
    $chartData = [];
    for ($m = 1; $m <= 12; $m++) {
        $stmt = $db->prepare(
            'SELECT * FROM monthly_financials WHERE user_id = ? AND year = ? AND month = ?'
        );
        $stmt->execute([$userId, $year, $m]);
        $mf = $stmt->fetch();
        $chartData[] = [
            'label' => getShortMonthName($m),
            'income' => (float)($mf['total_income'] ?? 0),
            'expenses' => (float)($mf['total_expenses'] ?? 0),
            'savings' => (float)($mf['savings'] ?? 0)
        ];
    }

    // Expense categories for the entire year
    $stmt = $db->prepare(
        'SELECT category, SUM(amount) as total FROM expenses 
         WHERE user_id = ? AND YEAR(expense_date) = ?
         GROUP BY category ORDER BY total DESC'
    );
    $stmt->execute([$userId, $year]);
    $expenseCategories = $stmt->fetchAll();

} else {
    // Generate/update monthly record for the selected month
    $monthly = generateMonthlyFinancialRecord($userId, $year, $month);
    $displayMonthly = $monthly;

    // Monthly data for charts (last 6 months)
    $chartData = [];
    for ($i = 5; $i >= 0; $i--) {
        $cm = $month - $i;
        $cy = $year;
        while ($cm < 1) { $cm += 12; $cy--; }
        
        $stmt = $db->prepare(
            'SELECT * FROM monthly_financials WHERE user_id = ? AND year = ? AND month = ?'
        );
        $stmt->execute([$userId, $cy, $cm]);
        $mf = $stmt->fetch();
        
        $chartData[] = [
            'label' => getShortMonthName($cm) . ' ' . $cy,
            'income' => (float)($mf['total_income'] ?? 0),
            'expenses' => (float)($mf['total_expenses'] ?? 0),
            'savings' => (float)($mf['savings'] ?? 0)
        ];
    }

    // Expense categories for current month
    $stmt = $db->prepare(
        'SELECT category, SUM(amount) as total FROM expenses 
         WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?
         GROUP BY category ORDER BY total DESC'
    );
    $stmt->execute([$userId, $year, $month]);
    $expenseCategories = $stmt->fetchAll();
}

// Financial health
$health = calculateFinancialHealth($userId);

// Friend summary
$friendSummary = getFriendMoneySummary($userId);

// Goal projections
$goalProjections = getGoalProjections($userId);

// Alerts
$alerts = generateAlerts($userId);

// Recent expenses
$stmt = $db->prepare(
    'SELECT * FROM expenses WHERE user_id = ? ORDER BY expense_date DESC, created_at DESC LIMIT 5'
);
$stmt->execute([$userId]);
$recentExpenses = $stmt->fetchAll();

// Add travel as a category for the chart
$expenseCatChart = $expenseCategories;
if ($displayMonthly['travel_expense'] > 0) {
    array_unshift($expenseCatChart, ['category' => 'Travel', 'total' => $displayMonthly['travel_expense']]);
}
if ($displayMonthly['recharge_expense'] > 0) {
    $expenseCatChart[] = ['category' => 'Recharge', 'total' => $displayMonthly['recharge_expense']];
}
if ($displayMonthly['emi_total'] > 0) {
    $expenseCatChart[] = ['category' => 'EMI', 'total' => $displayMonthly['emi_total']];
}

// Recharge info
$nextRecharge = $settings['next_recharge_date'] ? new DateTime($settings['next_recharge_date']) : null;
$rechargeDaysLeft = $nextRecharge ? max(0, (int)(new DateTime())->diff($nextRecharge)->format('%r%a')) : 0;
$rechargeMonthlyEquiv = getRechargeMonthlyEquivalent(
    (float)$settings['recharge_amount'], 
    (int)$settings['recharge_interval_days']
);

// Total available money
$totalAvailable = (float)$settings['current_cash'] + (float)$settings['bank_balance'] + (float)$settings['upi_balance'];

// Active loans
$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? AND active = 1');
$stmt->execute([$userId]);
$activeLoans = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PERIOD SELECTOR ─── -->
<div class="period-selector" id="periodSelector">
    <div class="period-tabs">
        <a href="?view=month&year=<?= date('Y') ?>&month=<?= date('n') ?>" 
           class="period-tab <?= !$isYearView ? 'active' : '' ?>">
            This Month
        </a>
        <a href="?view=year&year=<?= $year ?>" class="period-tab <?= $isYearView ? 'active' : '' ?>">This Year</a>
    </div>
    <div class="period-controls">
        <?php if ($isYearView): ?>
        <a href="?view=year&year=<?= $year - 1 ?>" 
           class="btn-icon" title="Previous year">
            <i class="fas fa-chevron-left"></i>
        </a>
        <span class="period-current">Year <?= $year ?></span>
        <a href="?view=year&year=<?= $year + 1 ?>"
           class="btn-icon" title="Next year">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php else: ?>
        <a href="?view=month&year=<?= $month == 1 ? $year-1 : $year ?>&month=<?= $month == 1 ? 12 : $month-1 ?>" 
           class="btn-icon" title="Previous month">
            <i class="fas fa-chevron-left"></i>
        </a>
        <span class="period-current"><?= getMonthName($month) ?> <?= $year ?></span>
        <a href="?view=month&year=<?= $month == 12 ? $year+1 : $year ?>&month=<?= $month == 12 ? 1 : $month+1 ?>"
           class="btn-icon" title="Next month">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ─── ALERTS ─── -->
<?php if (!empty($alerts)): ?>
<div class="alerts-strip" id="alertsStrip">
    <?php foreach (array_slice($alerts, 0, 3) as $alert): ?>
    <div class="alert-item alert-<?= $alert['type'] ?>">
        <i class="fas <?= $alert['icon'] ?>"></i>
        <span><?= e($alert['message']) ?></span>
        <?php if (!empty($alert['action'])): ?>
        <a href="<?= $alert['action'] ?>" class="alert-action">View</a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ─── OVERVIEW CARDS ─── -->
<div class="overview-grid" id="overviewGrid">
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-content">
            <div class="stat-label"><?= $isYearView ? 'Annual Salary' : 'Monthly Salary' ?></div>
            <div class="stat-value" data-count="<?= $displayMonthly['salary'] ?>"><?= formatINR($displayMonthly['salary']) ?></div>
            <div class="stat-meta"><?= $displayMonthly['working_days'] ?> <?= $isYearView ? 'total working days' : 'working days' ?></div>
        </div>
    </div>
    
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-content">
            <div class="stat-label"><?= $isYearView ? 'Annual Expenses' : 'Total Expenses' ?></div>
            <div class="stat-value" data-count="<?= $displayMonthly['total_expenses'] ?>"><?= formatINR($displayMonthly['total_expenses']) ?></div>
            <div class="stat-meta"><?= round(calcPercentage($displayMonthly['total_expenses'], $displayMonthly['total_income'])) ?>% of income</div>
        </div>
    </div>
    
    <div class="stat-card stat-savings" data-animate>
        <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
        <div class="stat-content">
            <div class="stat-label"><?= $isYearView ? 'Annual Savings' : 'Monthly Savings' ?></div>
            <div class="stat-value" data-count="<?= $displayMonthly['savings'] ?>"><?= formatINR($displayMonthly['savings']) ?></div>
            <div class="stat-meta savings-rate"><?= $displayMonthly['savings_rate'] ?>% savings rate</div>
        </div>
    </div>
    
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Balance</div>
            <div class="stat-value" data-count="<?= $totalAvailable ?>"><?= formatINR($totalAvailable) ?></div>
            <div class="stat-meta">Cash + Bank + UPI</div>
        </div>
    </div>
    
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div class="stat-content">
            <div class="stat-label">Money Given</div>
            <div class="stat-value" data-count="<?= $friendSummary['total_outstanding'] ?>"><?= formatINR($friendSummary['total_outstanding']) ?></div>
            <div class="stat-meta"><?= count($friendSummary['friends']) ?> friends</div>
        </div>
    </div>
    
    <div class="stat-card stat-emi" data-animate>
        <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
        <div class="stat-content">
            <div class="stat-label"><?= $isYearView ? 'Annual EMI Total' : 'EMI Total' ?></div>
            <div class="stat-value" data-count="<?= $displayMonthly['emi_total'] ?>"><?= formatINR($displayMonthly['emi_total']) ?></div>
            <div class="stat-meta"><?= count($activeLoans) ?> active loan(s)</div>
        </div>
    </div>
</div>

<!-- ─── FINANCIAL HEALTH SCORE ─── -->
<div class="health-card" data-animate>
    <div class="health-header">
        <h3><i class="fas fa-heartbeat"></i> Financial Health</h3>
        <div class="health-score-badge" style="background: <?= $health['status_color'] ?>20; color: <?= $health['status_color'] ?>">
            <?= $health['status'] ?>
        </div>
    </div>
    <div class="health-body">
        <div class="health-gauge">
            <div class="gauge-circle" data-score="<?= $health['score'] ?>">
                <svg viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="10"/>
                    <circle cx="60" cy="60" r="52" fill="none" stroke="<?= $health['status_color'] ?>" 
                            stroke-width="10" stroke-linecap="round"
                            stroke-dasharray="<?= 2 * M_PI * 52 ?>"
                            stroke-dashoffset="<?= 2 * M_PI * 52 * (1 - $health['score']/100) ?>"
                            transform="rotate(-90 60 60)"/>
                </svg>
                <div class="gauge-value"><?= $health['score'] ?></div>
                <div class="gauge-label">/ 100</div>
            </div>
        </div>
        <div class="health-factors">
            <?php foreach ($health['factors'] as $key => $factor): ?>
            <div class="factor-row">
                <div class="factor-info">
                    <span class="factor-label"><?= $factor['label'] ?></span>
                    <span class="factor-value"><?= $factor['value'] ?></span>
                </div>
                <div class="factor-bar">
                    <div class="factor-fill" style="width: <?= ($factor['score']/$factor['max'])*100 ?>%; background: <?= $health['status_color'] ?>"></div>
                </div>
                <span class="factor-score"><?= $factor['score'] ?>/<?= $factor['max'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ─── CHARTS ROW ─── -->
<div class="charts-grid">
    <!-- Cash Flow Chart -->
    <div class="chart-card" data-animate>
        <div class="chart-header">
            <h3><i class="fas fa-chart-area"></i> <?= $isYearView ? 'Annual Cash Flow (' . $year . ')' : 'Cash Flow' ?></h3>
        </div>
        <div class="chart-body">
            <canvas id="cashFlowChart" height="280"></canvas>
        </div>
    </div>
    
    <!-- Expense Breakdown -->
    <div class="chart-card" data-animate>
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie"></i> <?= $isYearView ? 'Annual Expense Breakdown (' . $year . ')' : 'Expense Breakdown' ?></h3>
        </div>
        <div class="chart-body">
            <canvas id="expenseDonutChart" height="280"></canvas>
        </div>
    </div>
</div>

<!-- ─── MONTHLY / ANNUAL BREAKDOWN ─── -->
<div class="breakdown-card" data-animate>
    <div class="chart-header">
        <h3><i class="fas fa-list-alt"></i> <?= $isYearView ? 'Annual Breakdown — ' . $year : 'Monthly Breakdown — ' . getMonthName($month) . ' ' . $year ?></h3>
    </div>
    <div class="breakdown-body">
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-arrow-down text-success"></i> <?= $isYearView ? 'Annual Salary' : 'Salary' ?></span>
            <span class="bk-value text-success"><?= formatINR($displayMonthly['salary']) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-plus text-info"></i> Additional Income</span>
            <span class="bk-value text-info"><?= formatINR($displayMonthly['additional_income']) ?></span>
        </div>
        <div class="breakdown-divider"></div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-bus text-warning"></i> Travel (<?= $displayMonthly['working_days'] ?> days<?= !$isYearView ? ' × ' . formatINR((float)$settings['daily_travel_cost']) : '' ?>)</span>
            <span class="bk-value text-danger">-<?= formatINR($displayMonthly['travel_expense']) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-mobile-alt text-purple"></i> Mobile Recharge</span>
            <span class="bk-value text-danger">-<?= formatINR($displayMonthly['recharge_expense']) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-redo text-info"></i> Recurring Expenses</span>
            <span class="bk-value text-danger">-<?= formatINR($displayMonthly['recurring_expense']) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-shopping-bag text-pink"></i> Personal Spending</span>
            <span class="bk-value text-danger">-<?= formatINR($displayMonthly['personal_expense']) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-user-friends text-warning"></i> Money Given to Friends</span>
            <span class="bk-value text-danger">-<?= formatINR($displayMonthly['friend_money']) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-credit-card text-danger"></i> EMI Payments</span>
            <span class="bk-value text-danger">-<?= formatINR($displayMonthly['emi_total']) ?></span>
        </div>
        <div class="breakdown-divider thick"></div>
        <div class="breakdown-row total">
            <span class="bk-label"><i class="fas fa-piggy-bank text-success"></i> <strong><?= $isYearView ? 'Estimated Annual Savings' : 'Estimated Savings' ?></strong></span>
            <span class="bk-value text-success"><strong><?= formatINR($displayMonthly['savings']) ?></strong></span>
        </div>
    </div>
</div>

<!-- ─── GOALS + FRIENDS + UPCOMING ─── -->
<div class="dashboard-grid-3">
    <!-- Goals -->
    <div class="widget-card" data-animate>
        <div class="widget-header">
            <h3><i class="fas fa-bullseye"></i> Financial Goals</h3>
            <a href="goals.php" class="widget-link">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="widget-body">
            <?php if (empty($goalProjections)): ?>
            <div class="empty-state-mini"><i class="fas fa-bullseye"></i><p>No active goals yet</p></div>
            <?php else: foreach ($goalProjections as $goal): ?>
            <div class="goal-mini-card">
                <div class="goal-mini-header">
                    <div class="goal-mini-icon" style="background: <?= $goal['color'] ?>20; color: <?= $goal['color'] ?>">
                        <i class="fas <?= $goal['icon'] ?>"></i>
                    </div>
                    <div class="goal-mini-info">
                        <div class="goal-mini-name"><?= e($goal['name']) ?></div>
                        <div class="goal-mini-amounts">
                            <?= formatINR($goal['saved_amount']) ?> / <?= formatINR($goal['target_amount']) ?>
                        </div>
                    </div>
                    <div class="goal-mini-pct"><?= $goal['percentage'] ?>%</div>
                </div>
                <div class="progress-bar-custom">
                    <div class="progress-fill" style="width: <?= min(100, $goal['percentage']) ?>%; background: <?= $goal['color'] ?>"></div>
                </div>
                <div class="goal-mini-footer">
                    <span>~<?= $goal['months_required'] ?> months left</span>
                    <span><?= $goal['estimated_date'] ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    
    <!-- Friends -->
    <div class="widget-card" data-animate>
        <div class="widget-header">
            <h3><i class="fas fa-user-friends"></i> Friend Money</h3>
            <a href="friends.php" class="widget-link">Manage <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="widget-body">
            <?php foreach ($friendSummary['friends'] as $friend): ?>
            <?php if ($friend['remaining'] > 0): ?>
            <div class="friend-mini-row">
                <div class="friend-avatar"><?= strtoupper(substr($friend['name'], 0, 1)) ?></div>
                <div class="friend-info">
                    <div class="friend-name"><?= e($friend['name']) ?></div>
                    <div class="friend-status-badge status-<?= $friend['status'] ?>">
                        <?= ucfirst($friend['status']) ?>
                    </div>
                </div>
                <div class="friend-amount"><?= formatINR($friend['remaining']) ?></div>
            </div>
            <?php endif; endforeach; ?>
            <div class="friend-total-row">
                <span>Total Outstanding</span>
                <span class="text-warning"><?= formatINR($friendSummary['total_outstanding']) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Upcoming / Recharge -->
    <div class="widget-card" data-animate>
        <div class="widget-header">
            <h3><i class="fas fa-clock"></i> Upcoming</h3>
        </div>
        <div class="widget-body">
            <!-- Next Recharge -->
            <div class="upcoming-item">
                <div class="upcoming-icon bg-green"><i class="fas fa-mobile-alt"></i></div>
                <div class="upcoming-info">
                    <div class="upcoming-title">Mobile Recharge</div>
                    <div class="upcoming-detail">
                        <?= formatINR((float)$settings['recharge_amount']) ?> — <?= $rechargeDaysLeft ?> days left
                    </div>
                    <div class="upcoming-meta">
                        Monthly equiv: <?= formatINR($rechargeMonthlyEquiv) ?>/mo
                    </div>
                </div>
            </div>
            
            <!-- Salary -->
            <div class="upcoming-item">
                <div class="upcoming-icon bg-blue"><i class="fas fa-wallet"></i></div>
                <div class="upcoming-info">
                    <div class="upcoming-title">Next Salary</div>
                    <div class="upcoming-detail">
                        <?= formatINR((float)$settings['salary']) ?> on <?= $settings['salary_date'] ?>
                        <?= getMonthName($month == 12 ? 1 : $month + 1) ?>
                    </div>
                </div>
            </div>
            
            <!-- EMIs -->
            <?php foreach ($activeLoans as $loan): ?>
            <div class="upcoming-item">
                <div class="upcoming-icon bg-red"><i class="fas fa-credit-card"></i></div>
                <div class="upcoming-info">
                    <div class="upcoming-title"><?= e($loan['loan_name']) ?></div>
                    <div class="upcoming-detail">EMI: <?= formatINR((float)$loan['emi_amount']) ?>/month</div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Recent Expenses -->
            <div class="upcoming-divider">Recent Expenses</div>
            <?php foreach (array_slice($recentExpenses, 0, 3) as $exp): ?>
            <div class="upcoming-item compact">
                <div class="upcoming-icon bg-gray"><i class="fas fa-receipt"></i></div>
                <div class="upcoming-info">
                    <div class="upcoming-title"><?= e($exp['title']) ?></div>
                    <div class="upcoming-detail"><?= date('d M', strtotime($exp['expense_date'])) ?></div>
                </div>
                <div class="upcoming-amount">-<?= formatINR((float)$exp['amount']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ─── CHART DATA (JSON for JS) ─── -->
<script>
window.DASHBOARD_DATA = {
    chartData: <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    expenseCategories: <?= json_encode($expenseCatChart, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    health: <?= json_encode($health, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    monthly: <?= json_encode($monthly, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    year: <?= (int)$year ?>,
    month: <?= (int)$month ?>,
    view: '<?= e($view) ?>',
    isYearView: <?= $isYearView ? 'true' : 'false' ?>
};
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>