<?php
/**
 * ANALYTICS — Deep Financial Analytics & Category Insights
 */
define('PAGE_TITLE', 'Analytics');
define('PAGE_ID', 'analytics');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();

// Fetch 12-month trend data
$trendData = [];
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

for ($i = 11; $i >= 0; $i--) {
    $m = $currentMonth - $i;
    $y = $currentYear;
    while ($m < 1) {
        $m += 12;
        $y--;
    }
    
    $stmt = $db->prepare('SELECT * FROM monthly_financials WHERE user_id = ? AND year = ? AND month = ?');
    $stmt->execute([$userId, $y, $m]);
    $mf = $stmt->fetch();
    
    // If not generated, generate now
    if (!$mf) {
        $mf = generateMonthlyFinancialRecord($userId, $y, $m);
    }
    
    $trendData[] = [
        'label' => getShortMonthName($m) . ' ' . substr((string)$y, 2),
        'income' => (float)($mf['total_income'] ?? 0),
        'expenses' => (float)($mf['total_expenses'] ?? 0),
        'savings' => (float)($mf['savings'] ?? 0),
        'savings_rate' => (float)($mf['savings_rate'] ?? 0)
    ];
}

// Compute 12-Month Cumulative Wealth Accumulation
$cumulativeSavingsData = [];
$runningSavings = 0;
foreach ($trendData as $t) {
    $runningSavings += (float)$t['savings'];
    $cumulativeSavingsData[] = [
        'label' => $t['label'],
        'monthly_savings' => (float)$t['savings'],
        'cumulative_savings' => $runningSavings
    ];
}
$latestCumulative = !empty($cumulativeSavingsData) ? end($cumulativeSavingsData)['cumulative_savings'] : 0;
$avgMonthlySavings = count($trendData) > 0 ? round($runningSavings / count($trendData), 2) : 0;

// Category Breakdown (All-time or last 6 months)
$stmt = $db->prepare(
    'SELECT category, SUM(amount) as total FROM expenses 
     WHERE user_id = ? 
     GROUP BY category ORDER BY total DESC'
);
$stmt->execute([$userId]);
$categoryBreakdown = $stmt->fetchAll();

// Payment Method Breakdown
$stmt = $db->prepare(
    'SELECT payment_method, SUM(amount) as total FROM expenses 
     WHERE user_id = ? 
     GROUP BY payment_method ORDER BY total DESC'
);
$stmt->execute([$userId]);
$paymentMethods = $stmt->fetchAll();

// Top 5 Largest Expenses
$stmt = $db->prepare(
    'SELECT * FROM expenses WHERE user_id = ? ORDER BY amount DESC LIMIT 5'
);
$stmt->execute([$userId]);
$largestExpenses = $stmt->fetchAll();

// All-time totals
$totalAllTimeExpenses = array_sum(array_column($categoryBreakdown, 'total'));
$avgSavingsRate = count($trendData) > 0 ? round(array_sum(array_column($trendData, 'savings_rate')) / count($trendData), 1) : 0;
$maxExpense = !empty($largestExpenses) ? (float)$largestExpenses[0]['amount'] : 0;

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Financial Analytics & Trends</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Historical cashflow patterns, payment channels, and category allocations</p>
    </div>
</div>

<!-- ─── STATS CARDS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-content">
            <div class="stat-label">All-Time Spending</div>
            <div class="stat-value" data-count="<?= $totalAllTimeExpenses ?>"><?= formatINR($totalAllTimeExpenses) ?></div>
            <div class="stat-meta">Recorded expenses</div>
        </div>
    </div>
    <div class="stat-card stat-savings" data-animate>
        <div class="stat-icon"><i class="fas fa-percentage"></i></div>
        <div class="stat-content">
            <div class="stat-label">Avg Savings Rate</div>
            <div class="stat-value"><?= $avgSavingsRate ?>%</div>
            <div class="stat-meta">Across last 12 months</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-trophy"></i></div>
        <div class="stat-content">
            <div class="stat-label">Largest Expense</div>
            <div class="stat-value" data-count="<?= $maxExpense ?>"><?= formatINR($maxExpense) ?></div>
            <div class="stat-meta"><?= !empty($largestExpenses) ? e($largestExpenses[0]['title']) : 'None' ?></div>
        </div>
    </div>
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-tags"></i></div>
        <div class="stat-content">
            <div class="stat-label">Top Category</div>
            <div class="stat-value" style="font-size: 1.35rem;"><?= !empty($categoryBreakdown) ? e($categoryBreakdown[0]['category']) : 'None' ?></div>
            <div class="stat-meta"><?= !empty($categoryBreakdown) ? formatINR((float)$categoryBreakdown[0]['total']) : '₹0' ?></div>
        </div>
    </div>
</div>

<!-- ─── CUMULATIVE WEALTH ACCUMULATION GRAPH ─── -->
<div class="chart-card" data-animate style="margin-bottom: 1.75rem;">
    <div class="chart-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 style="margin-bottom: 0.25rem;"><i class="fas fa-layer-group text-success"></i> Cumulative Wealth Accumulation (Month-over-Month)</h3>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0;">Progressive running total of savings accumulated each month (e.g. Month 1: ₹18,000 &rarr; Month 2: ₹36,000)</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div style="text-align: right; background: var(--bg-hover); padding: 0.5rem 1rem; border-radius: 10px; border: 1px solid var(--border-color);">
                <span style="font-size: 0.725rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block; font-weight: 600;">Net Accumulated</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: <?= $latestCumulative >= 0 ? 'var(--success-light)' : 'var(--danger-light)' ?>;">
                    <?= formatINR($latestCumulative) ?>
                </span>
            </div>
            <div style="text-align: right; background: var(--bg-hover); padding: 0.5rem 1rem; border-radius: 10px; border: 1px solid var(--border-color);" class="d-none d-sm-block">
                <span style="font-size: 0.725rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block; font-weight: 600;">Avg / Month</span>
                <span style="font-size: 1.25rem; font-weight: 800; color: var(--primary-light);">
                    <?= formatINR($avgMonthlySavings) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="chart-body" style="height: 330px;">
        <canvas id="cumulativeSavingsChart"></canvas>
    </div>
</div>

<!-- ─── 12-MONTH TREND CHART ─── -->
<div class="chart-card" data-animate style="margin-bottom: 1.75rem;">
    <div class="chart-header">
        <h3><i class="fas fa-chart-area"></i> 12-Month Cashflow Trajectory</h3>
    </div>
    <div class="chart-body" style="height: 320px;">
        <canvas id="analyticsTrendChart"></canvas>
    </div>
</div>

<!-- ─── 2-COLUMN BREAKDOWN GRAPHS ─── -->
<div class="charts-grid">
    <!-- Category Donut -->
    <div class="chart-card" data-animate>
        <div class="chart-header">
            <h3><i class="fas fa-chart-pie"></i> Category Distribution</h3>
        </div>
        <div class="chart-body" style="height: 280px;">
            <canvas id="analyticsCategoryChart"></canvas>
        </div>
    </div>

    <!-- Payment Methods Bar -->
    <div class="chart-card" data-animate>
        <div class="chart-header">
            <h3><i class="fas fa-wallet"></i> Payment Methods</h3>
        </div>
        <div class="chart-body" style="height: 280px;">
            <canvas id="paymentMethodsChart"></canvas>
        </div>
    </div>
</div>

<!-- ─── TOP 5 LARGEST EXPENSES ─── -->
<div class="table-card" data-animate>
    <div class="chart-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; border-bottom: 1px solid var(--border-color);">
        <h3><i class="fas fa-fire text-danger"></i> Top 5 Largest Recorded Expenses</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Expense Title</th>
                    <th>Category</th>
                    <th>Method</th>
                    <th class="text-right">Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($largestExpenses)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                        No expenses logged yet.
                    </td>
                </tr>
                <?php else: foreach ($largestExpenses as $exp): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                    <td style="font-weight: 700;"><?= e($exp['title']) ?></td>
                    <td>
                        <span class="badge-custom status-active"><?= e($exp['category']) ?></span>
                    </td>
                    <td style="text-transform: uppercase; font-size: 0.8rem;"><?= e($exp['payment_method']) ?></td>
                    <td class="text-right" style="font-weight: 800; color: var(--danger-light); font-size: 1.05rem;">
                        <?= formatINR((float)$exp['amount']) ?>
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.825rem;"><?= e($exp['notes'] ?: '—') ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const trend = <?= json_encode($trendData) ?>;
    const cumulativeSavings = <?= json_encode($cumulativeSavingsData) ?>;
    const categories = <?= json_encode($categoryBreakdown) ?>;
    const paymentMethods = <?= json_encode($paymentMethods) ?>;

    // 0. Cumulative Savings Chart
    if (document.getElementById('cumulativeSavingsChart') && cumulativeSavings.length) {
        window.FinanceCharts.createCumulativeSavingsChart('cumulativeSavingsChart', cumulativeSavings);
    }

    // 1. Trend Chart
    if (document.getElementById('analyticsTrendChart') && trend.length) {
        window.FinanceCharts.createCashFlowChart('analyticsTrendChart', trend);
    }

    // 2. Category Donut
    if (document.getElementById('analyticsCategoryChart') && categories.length) {
        window.FinanceCharts.createExpenseDonutChart('analyticsCategoryChart', categories);
    }

    // 3. Payment Methods Chart
    if (document.getElementById('paymentMethodsChart') && paymentMethods.length) {
        const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
        const labels = paymentMethods.map(p => p.payment_method.toUpperCase());
        const data = paymentMethods.map(p => parseFloat(p.total));

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Spent (₹)',
                    data: data,
                    backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#06b6d4'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ' ' + window.FinanceCharts.formatINR(ctx.raw); }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        ticks: {
                            callback: function(v) { return '₹' + v; }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
