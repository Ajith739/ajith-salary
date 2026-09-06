<?php
/**
 * CALENDAR VIEW — Working Days, Indian Holidays & Daily Expenses
 */
define('PAGE_TITLE', 'Calendar');
define('PAGE_ID', 'calendar');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/calendar.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();
$settings = getUserSettings($userId);

// Filter period
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

// Get calendar month data
$calData = getCalendarData($year, $month, $settings);
$workingDays = getWorkingDays($year, $month, $settings);
$dailyTravel = (float)$settings['daily_travel_cost'];
$totalTravelCost = $workingDays * $dailyTravel;
$daysInMonth = $calData['daysInMonth'];
$holidaysCount = $daysInMonth - $workingDays;

// Fetch daily expense totals for this month
$stmt = $db->prepare(
    'SELECT DATE(expense_date) as exp_date, SUM(amount) as daily_total, COUNT(*) as tx_count 
     FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     GROUP BY DATE(expense_date)'
);
$stmt->execute([$userId, $year, $month]);
$dailyExpensesRaw = $stmt->fetchAll();

$dailyExpenses = [];
foreach ($dailyExpensesRaw as $r) {
    $dailyExpenses[$r['exp_date']] = [
        'total' => (float)$r['daily_total'],
        'count' => (int)$r['tx_count']
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PERIOD SELECTOR ─── -->
<div class="period-selector">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Working Days & Travel Calendar</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Indian work week calculation: Sundays + 2nd & 4th Saturdays off</p>
    </div>
    
    <div class="period-controls">
        <a href="?year=<?= $month == 1 ? $year-1 : $year ?>&month=<?= $month == 1 ? 12 : $month-1 ?>" 
           class="btn-icon" title="Previous Month">
            <i class="fas fa-chevron-left"></i>
        </a>
        <span class="period-current"><?= getMonthName($month) ?> <?= $year ?></span>
        <a href="?year=<?= $month == 12 ? $year+1 : $year ?>&month=<?= $month == 12 ? 1 : $month+1 ?>" 
           class="btn-icon" title="Next Month">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<!-- ─── SUMMARY CARDS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-briefcase"></i></div>
        <div class="stat-content">
            <div class="stat-label">Working Days</div>
            <div class="stat-value"><?= $workingDays ?> Days</div>
            <div class="stat-meta">Mon-Fri + 1st/3rd/5th Sat</div>
        </div>
    </div>
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-coffee"></i></div>
        <div class="stat-content">
            <div class="stat-label">Holidays & Weekends</div>
            <div class="stat-value"><?= $holidaysCount ?> Days</div>
            <div class="stat-meta">Sundays + 2nd/4th Saturdays</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-bus"></i></div>
        <div class="stat-content">
            <div class="stat-label">Calculated Travel Cost</div>
            <div class="stat-value" data-count="<?= $totalTravelCost ?>"><?= formatINR($totalTravelCost) ?></div>
            <div class="stat-meta"><?= $workingDays ?> days × <?= formatINR($dailyTravel) ?>/day</div>
        </div>
    </div>
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-content">
            <div class="stat-label">Daily Travel Allowance</div>
            <div class="stat-value" data-count="<?= $dailyTravel ?>"><?= formatINR($dailyTravel) ?></div>
            <div class="stat-meta"><a href="settings.php" style="color: var(--primary-light);">Configure in Settings &rarr;</a></div>
        </div>
    </div>
</div>

<!-- ─── CALENDAR CONTAINER ─── -->
<div class="calendar-card" data-animate>
    <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
        <h3><i class="far fa-calendar-alt"></i> <?= getMonthName($month) ?> <?= $year ?> Overview</h3>
        <div style="display: flex; gap: 1rem; font-size: 0.8rem;">
            <span style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 10px; height: 10px; border-radius: 2px; background: var(--primary-light);"></span> Working Day
            </span>
            <span style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 10px; height: 10px; border-radius: 2px; background: var(--danger-light);"></span> Holiday / Weekend
            </span>
            <span style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 10px; height: 10px; border-radius: 2px; background: var(--warning-light);"></span> Has Spending
            </span>
        </div>
    </div>

    <div class="calendar-grid">
        <!-- Day of week headers -->
        <div class="calendar-header-day text-danger">Sun</div>
        <div class="calendar-header-day">Mon</div>
        <div class="calendar-header-day">Tue</div>
        <div class="calendar-header-day">Wed</div>
        <div class="calendar-header-day">Thu</div>
        <div class="calendar-header-day">Fri</div>
        <div class="calendar-header-day text-danger">Sat</div>

        <!-- Empty cells preceding first day of month -->
        <?php for ($i = 0; $i < $calData['firstDayOfWeek']; $i++): ?>
        <div class="calendar-day-cell empty"></div>
        <?php endfor; ?>

        <!-- Days of month -->
        <?php foreach ($calData['days'] as $day): 
            $dateStr = $day['date'];
            $hasExpense = isset($dailyExpenses[$dateStr]);
            $expData = $hasExpense ? $dailyExpenses[$dateStr] : null;
        ?>
        <div class="calendar-day-cell <?= $day['isHoliday'] ? 'is-holiday' : 'is-working' ?> <?= $day['isToday'] ? 'is-today' : '' ?>">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <span class="calendar-date-num" style="color: <?= $day['isToday'] ? 'var(--primary-light)' : 'inherit' ?>;">
                    <?= $day['day'] ?>
                </span>
                <?php if ($day['isToday']): ?>
                <span class="badge-custom status-active" style="font-size: 0.6rem; padding: 0.1rem 0.35rem;">Today</span>
                <?php endif; ?>
            </div>

            <?php if ($day['isHoliday']): ?>
            <div>
                <span class="calendar-holiday-tag"><?= e($day['holidayType']) ?></span>
            </div>
            <?php else: ?>
            <div style="font-size: 0.68rem; color: var(--text-muted); margin-top: 0.2rem;">
                Travel: <?= formatINR($dailyTravel) ?>
            </div>
            <?php endif; ?>

            <?php if ($hasExpense): ?>
            <div class="calendar-expense-tag" title="<?= $expData['count'] ?> transaction(s)">
                <i class="fas fa-shopping-bag"></i> <?= formatINR($expData['total']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
