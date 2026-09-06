<?php
/**
 * FORECAST — 12-Month Forward Financial Projections
 */
define('PAGE_TITLE', 'Forecast');
define('PAGE_ID', 'forecast');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$settings = getUserSettings($userId);
$forecast = generateForecast($userId, 12);

// Goals projections
$goals = getGoalProjections($userId);

// Calculate milestones
$finalProjectedBalance = !empty($forecast) ? $forecast[count($forecast) - 1]['balance'] : 0;
$annualProjectedSavings = array_sum(array_column($forecast, 'savings'));

// Emergency fund calculation (Target: 6 months expenses)
$currentMonthData = generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
$monthlyExpenseBurn = max(1, $currentMonthData['total_expenses']);
$currentLiquid = (float)$settings['current_cash'] + (float)$settings['bank_balance'] + (float)$settings['upi_balance'];
$emergencyMonthsCovered = round($currentLiquid / $monthlyExpenseBurn, 1);
$emergencyFundTarget = $monthlyExpenseBurn * (int)($settings['emergency_fund_months'] ?? 6);

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">12-Month Financial Forecast</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Forward cashflow projections based on salary, working day commute, and recurring bills</p>
    </div>
</div>

<!-- ─── SUMMARY CARDS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-savings" data-animate>
        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        <div class="stat-content">
            <div class="stat-label">Projected Net Balance</div>
            <div class="stat-value" data-count="<?= $finalProjectedBalance ?>"><?= formatINR($finalProjectedBalance) ?></div>
            <div class="stat-meta">In 12 months</div>
        </div>
    </div>
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
        <div class="stat-content">
            <div class="stat-label">Annual Net Savings</div>
            <div class="stat-value" data-count="<?= $annualProjectedSavings ?>"><?= formatINR($annualProjectedSavings) ?></div>
            <div class="stat-meta">Over next 12 months</div>
        </div>
    </div>
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="stat-content">
            <div class="stat-label">Emergency Runway</div>
            <div class="stat-value"><?= $emergencyMonthsCovered ?> Months</div>
            <div class="stat-meta">Target: 6 months (<?= formatINR($emergencyFundTarget) ?>)</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-fire text-danger"></i></div>
        <div class="stat-content">
            <div class="stat-label">Monthly Expense Burn</div>
            <div class="stat-value" data-count="<?= $monthlyExpenseBurn ?>"><?= formatINR($monthlyExpenseBurn) ?></div>
            <div class="stat-meta">Average base commitment</div>
        </div>
    </div>
</div>

<!-- ─── FORECAST BALANCE CHART ─── -->
<div class="chart-card" data-animate style="margin-bottom: 2rem;">
    <div class="chart-header">
        <h3><i class="fas fa-chart-area"></i> Projected Balance Trajectory (Next 12 Months)</h3>
    </div>
    <div class="chart-body" style="height: 320px;">
        <canvas id="forecastChart"></canvas>
    </div>
</div>

<!-- ─── GOALS ROADMAP MILESTONES ─── -->
<div class="table-card" data-animate style="padding: 1.5rem; margin-bottom: 2rem;">
    <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
        <h3><i class="fas fa-bullseye text-primary"></i> Projected Goal Milestones</h3>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <?php if (empty($goals)): ?>
        <p style="color: var(--text-muted); padding: 1rem 0;">No active goals found. Add goals in the Goals section.</p>
        <?php else: foreach ($goals as $goal): ?>
        <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div style="width: 36px; height: 36px; border-radius: 8px; background: <?= $goal['color'] ?>20; color: <?= $goal['color'] ?>; display: flex; align-items: center; justify-content: center;">
                    <i class="fas <?= $goal['icon'] ?>"></i>
                </div>
                <div>
                    <h4 style="font-size: 0.95rem; font-weight: 700; margin: 0;"><?= e($goal['name']) ?></h4>
                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= formatINR($goal['target_amount']) ?></span>
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem;">
                <span style="color: var(--text-muted);">Estimated Completion:</span>
                <strong style="color: var(--primary-light);"><?= $goal['estimated_date'] ?></strong>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ─── 12-MONTH PROJECTION SCHEDULE TABLE ─── -->
<div class="table-card" data-animate>
    <div class="chart-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; border-bottom: 1px solid var(--border-color);">
        <h3><i class="fas fa-table"></i> Month-by-Month Projection Breakdown</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-center">Working Days</th>
                    <th class="text-right">Est. Income</th>
                    <th class="text-right">Est. Expenses</th>
                    <th class="text-right">Est. Monthly Savings</th>
                    <th class="text-right">Cumulative Balance</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($forecast as $f): ?>
                <tr>
                    <td style="font-weight: 600;"><?= e($f['month_name']) ?></td>
                    <td class="text-center"><?= $f['working_days'] ?> Days</td>
                    <td class="text-right" style="color: var(--success); font-weight: 600;"><?= formatINR($f['income']) ?></td>
                    <td class="text-right" style="color: var(--danger-light); font-weight: 600;">-<?= formatINR($f['expenses']) ?></td>
                    <td class="text-right" style="font-weight: 700; color: <?= $f['savings'] >= 0 ? 'var(--primary-light)' : 'var(--danger)' ?>;">
                        <?= $f['savings'] >= 0 ? '+' : '' ?><?= formatINR($f['savings']) ?>
                    </td>
                    <td class="text-right" style="font-weight: 800; font-size: 1rem; color: var(--text-primary);">
                        <?= formatINR($f['balance']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const forecast = <?= json_encode($forecast) ?>;
    if (!document.getElementById('forecastChart') || !forecast.length) return;

    const ctx = document.getElementById('forecastChart').getContext('2d');
    const labels = forecast.map(f => f.month_name);
    const balanceData = forecast.map(f => f.balance);
    const savingsData = forecast.map(f => f.savings);

    const grad = ctx.createLinearGradient(0, 0, 0, 300);
    grad.addColorStop(0, 'rgba(99, 102, 241, 0.4)');
    grad.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Cumulative Balance',
                    data: balanceData,
                    borderColor: '#6366f1',
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3,
                    pointBackgroundColor: '#6366f1',
                    pointRadius: 4
                },
                {
                    label: 'Monthly Savings',
                    data: savingsData,
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    borderDash: [4, 4],
                    tension: 0.35,
                    borderWidth: 2,
                    pointBackgroundColor: '#10b981',
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.dataset.label + ': ' + window.FinanceCharts.formatINR(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(v) { return '₹' + v.toLocaleString('en-IN'); }
                    }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
