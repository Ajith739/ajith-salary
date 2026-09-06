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

$successMsg = '';
$errorMsg = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_travel_cost') {
            $newCost = (float)($_POST['daily_travel_cost'] ?? 40);
            if ($newCost < 0) $newCost = 40;
            $stmt = $db->prepare('UPDATE financial_settings SET daily_travel_cost = ? WHERE user_id = ?');
            $stmt->execute([$newCost, $userId]);
            $settings['daily_travel_cost'] = $newCost;
            generateMonthlyFinancialRecord($userId, $year, $month);
            $successMsg = 'Daily travel allowance updated to ' . formatINR($newCost) . '!';

        } elseif ($action === 'add_date_expense') {
            $title = sanitize($_POST['title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $category = sanitize($_POST['category'] ?? 'Travel');
            $expenseDate = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
            $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');
            $notes = sanitize($_POST['notes'] ?? '');

            if (empty($title) || $amount <= 0 || !isValidDate($expenseDate)) {
                $errorMsg = 'Please provide a valid title, positive amount, and date.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO expenses (user_id, category, title, amount, expense_date, payment_method, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $category, $title, $amount, $expenseDate, $paymentMethod, $notes]);

                $expM = (int)date('n', strtotime($expenseDate));
                $expY = (int)date('Y', strtotime($expenseDate));
                generateMonthlyFinancialRecord($userId, $expY, $expM);

                $successMsg = 'Expense of ' . formatINR($amount) . ' logged for ' . date('d M Y', strtotime($expenseDate)) . '!';
            }

        } elseif ($action === 'edit_date_expense') {
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $category = sanitize($_POST['category'] ?? 'Travel');
            $expenseDate = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
            $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');
            $notes = sanitize($_POST['notes'] ?? '');

            if ($expenseId <= 0 || empty($title) || $amount <= 0 || !isValidDate($expenseDate)) {
                $errorMsg = 'Please provide a valid title, positive amount, and date.';
            } else {
                $stmt = $db->prepare('SELECT expense_date FROM expenses WHERE id = ? AND user_id = ?');
                $stmt->execute([$expenseId, $userId]);
                $oldExp = $stmt->fetch();

                if ($oldExp) {
                    $stmt = $db->prepare(
                        'UPDATE expenses SET category = ?, title = ?, amount = ?, expense_date = ?, payment_method = ?, notes = ? 
                         WHERE id = ? AND user_id = ?'
                    );
                    $stmt->execute([$category, $title, $amount, $expenseDate, $paymentMethod, $notes, $expenseId, $userId]);

                    $oldM = (int)date('n', strtotime($oldExp['expense_date']));
                    $oldY = (int)date('Y', strtotime($oldExp['expense_date']));
                    generateMonthlyFinancialRecord($userId, $oldY, $oldM);

                    $newM = (int)date('n', strtotime($expenseDate));
                    $newY = (int)date('Y', strtotime($expenseDate));
                    generateMonthlyFinancialRecord($userId, $newY, $newM);

                    $successMsg = 'Expense updated successfully!';
                } else {
                    $errorMsg = 'Expense record not found.';
                }
            }

        } elseif ($action === 'delete_date_expense') {
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $stmt = $db->prepare('SELECT expense_date FROM expenses WHERE id = ? AND user_id = ?');
            $stmt->execute([$expenseId, $userId]);
            $exp = $stmt->fetch();

            if ($exp) {
                $stmt = $db->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
                $stmt->execute([$expenseId, $userId]);

                $expM = (int)date('n', strtotime($exp['expense_date']));
                $expY = (int)date('Y', strtotime($exp['expense_date']));
                generateMonthlyFinancialRecord($userId, $expY, $expM);

                $successMsg = 'Expense deleted.';
            }
        }
    }
}

// Get calendar month data
$calData = getCalendarData($year, $month, $settings);
$workingDays = getWorkingDays($year, $month, $settings);
$dailyTravel = (float)($settings['daily_travel_cost'] > 0 ? $settings['daily_travel_cost'] : 40);
$totalTravelCost = $workingDays * $dailyTravel;
$daysInMonth = $calData['daysInMonth'];
$holidaysCount = $daysInMonth - $workingDays;

// Fetch categories
$categories = $db->query('SELECT * FROM expense_categories ORDER BY name ASC')->fetchAll();

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

// Fetch itemized expenses for each date this month
$stmt = $db->prepare(
    'SELECT * FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     ORDER BY expense_date ASC, created_at ASC'
);
$stmt->execute([$userId, $year, $month]);
$monthExpenses = $stmt->fetchAll();

$expensesByDate = [];
foreach ($monthExpenses as $exp) {
    $dateKey = date('Y-m-d', strtotime($exp['expense_date']));
    $expensesByDate[$dateKey][] = $exp;
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PERIOD SELECTOR ─── -->
<div class="period-selector">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Working Days & Travel Calendar</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Click on any day cell to view, add, edit or remove daily expenses</p>
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

<!-- ─── FEEDBACK ALERTS ─── -->
<?php if ($successMsg): ?>
<div class="alert-item alert-success" style="margin-bottom: 1.5rem;">
    <i class="fas fa-check-circle"></i>
    <span><?= e($successMsg) ?></span>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="alert-item alert-danger" style="margin-bottom: 1.5rem;">
    <i class="fas fa-exclamation-circle"></i>
    <span><?= e($errorMsg) ?></span>
</div>
<?php endif; ?>

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
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div class="stat-label">Daily Travel Allowance</div>
                <button type="button" class="btn-table-action edit" style="width: 24px; height: 24px; font-size: 0.7rem;" title="Edit Daily Travel Cost" onclick="openEditTravelCostModal(<?= $dailyTravel ?>)">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
            <div class="stat-value" data-count="<?= $dailyTravel ?>"><?= formatINR($dailyTravel) ?></div>
            <div class="stat-meta">Default: ₹40 / working day</div>
        </div>
    </div>
</div>

<!-- ─── CALENDAR CONTAINER ─── -->
<div class="calendar-card" data-animate>
    <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
        <h3><i class="far fa-calendar-alt"></i> <?= getMonthName($month) ?> <?= $year ?> Overview</h3>
        <div style="display: flex; gap: 1rem; font-size: 0.8rem; flex-wrap: wrap;">
            <span style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 10px; height: 10px; border-radius: 2px; background: var(--primary-light);"></span> Working Day
            </span>
            <span style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 10px; height: 10px; border-radius: 2px; background: var(--danger-light);"></span> Holiday / Weekend
            </span>
            <span style="display: flex; align-items: center; gap: 0.4rem;">
                <span style="width: 10px; height: 10px; border-radius: 2px; background: var(--warning-light);"></span> Has Expenses
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
        <div class="calendar-day-cell <?= $day['isHoliday'] ? 'is-holiday' : 'is-working' ?> <?= $day['isToday'] ? 'is-today' : '' ?>"
             onclick="openDateExpenseModal('<?= $dateStr ?>', <?= $day['isHoliday'] ? 'true' : 'false' ?>, '<?= addslashes(e($day['holidayType'] ?? '')) ?>')">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <span class="calendar-date-num" style="color: <?= $day['isToday'] ? 'var(--primary-light)' : 'inherit' ?>;">
                    <?= $day['day'] ?>
                </span>
                <div style="display: flex; gap: 0.25rem; align-items: center;">
                    <?php if ($day['isToday']): ?>
                    <span class="badge-custom status-active" style="font-size: 0.6rem; padding: 0.1rem 0.35rem;">Today</span>
                    <?php endif; ?>
                    <button type="button" class="btn-day-edit" title="Manage expenses for <?= $dateStr ?>" onclick="event.stopPropagation(); openDateExpenseModal('<?= $dateStr ?>', <?= $day['isHoliday'] ? 'true' : 'false' ?>, '<?= addslashes(e($day['holidayType'] ?? '')) ?>')">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                </div>
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
                <i class="fas fa-receipt"></i> <?= formatINR($expData['total']) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
window.CALENDAR_EXPENSES = <?= json_encode($expensesByDate) ?>;
window.EXPENSE_CATEGORIES = <?= json_encode(array_column($categories, 'name')) ?>;
window.DAILY_TRAVEL = <?= (float)$dailyTravel ?>;

function openDateExpenseModal(dateStr, isHoliday, holidayName) {
    const list = window.CALENDAR_EXPENSES[dateStr] || [];
    const catOptions = window.EXPENSE_CATEGORIES.map(c => `<option value="${c}" ${c === 'Travel' ? 'selected' : ''}>${c}</option>`).join('');

    let dateBadge = isHoliday 
        ? `<span class="badge-custom status-danger" style="margin-left: 0.5rem;"><i class="fas fa-coffee"></i> ${holidayName || 'Holiday / Weekend'}</span>`
        : `<span class="badge-custom status-active" style="margin-left: 0.5rem;"><i class="fas fa-briefcase"></i> Working Day (Commute ₹${window.DAILY_TRAVEL})</span>`;

    let rowsHtml = '';
    if (list.length === 0) {
        rowsHtml = `
            <div style="text-align: center; padding: 1.5rem; color: var(--text-muted); background: var(--bg-surface-elevated); border-radius: 8px; margin-bottom: 1.25rem;">
                <i class="fas fa-receipt" style="font-size: 1.5rem; opacity: 0.5; margin-bottom: 0.4rem; display: block;"></i>
                No personal expenses logged for this date.
            </div>
        `;
    } else {
        const totalDayAmount = list.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
        rowsHtml = `
            <div style="margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Logged Expenses (${list.length})</span>
                    <strong style="color: var(--danger-light); font-size: 0.95rem;">Total: ₹${totalDayAmount.toLocaleString('en-IN')}</strong>
                </div>
                <div style="max-height: 220px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                    <table class="custom-table" style="font-size: 0.825rem; margin: 0;">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th class="text-right">Amount</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${list.map(item => `
                                <tr>
                                    <td style="font-weight: 600;">${escapeHtml(item.title)}</td>
                                    <td><span class="badge-custom status-active" style="font-size: 0.7rem;">${escapeHtml(item.category)}</span></td>
                                    <td class="text-right" style="font-weight: 700; color: var(--danger-light);">-₹${parseFloat(item.amount).toLocaleString('en-IN')}</td>
                                    <td class="text-right">
                                        <div class="table-actions justify-end">
                                            <button type="button" class="btn-table-action edit" style="width: 26px; height: 26px; font-size: 0.7rem;" title="Edit Expense" onclick='openEditDateExpenseModal(${JSON.stringify(item)})'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this expense?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_date_expense">
                                                <input type="hidden" name="expense_id" value="${item.id}">
                                                <button type="submit" class="btn-table-action delete" style="width: 26px; height: 26px; font-size: 0.7rem;" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    const html = `
        <div style="margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <h4 style="margin: 0; font-size: 1.1rem; font-weight: 700;">
                    <i class="far fa-calendar"></i> ${dateStr}
                </h4>
                ${dateBadge}
            </div>
            ${rowsHtml}
            
            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <h5 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 700;">
                    <i class="fas fa-plus-circle text-primary"></i> Add Expense on this Date
                </h5>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="add_date_expense">
                    <input type="hidden" name="expense_date" value="${dateStr}">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div class="form-group-custom">
                            <label>Title *</label>
                            <input type="text" id="modalExpTitle" name="title" placeholder="e.g. Travel Commute, Lunch" required>
                        </div>
                        <div class="form-group-custom">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label>Amount (₹) *</label>
                                <button type="button" style="background: none; border: none; color: var(--primary-light); font-size: 0.75rem; cursor: pointer; text-decoration: underline;" onclick="document.getElementById('modalExpAmount').value = ${window.DAILY_TRAVEL}; document.getElementById('modalExpTitle').value = 'Daily Commute';">
                                    Use ₹${window.DAILY_TRAVEL} Travel
                                </button>
                            </div>
                            <div class="input-icon-wrap">
                                <i class="fas fa-rupee-sign"></i>
                                <input type="number" id="modalExpAmount" step="0.01" min="0.01" name="amount" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div class="form-group-custom">
                            <label>Category</label>
                            <select name="category">
                                ${catOptions}
                            </select>
                        </div>
                        <div class="form-group-custom">
                            <label>Payment Method</label>
                            <select name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="upi" selected>UPI</option>
                                <option value="card">Card</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group-custom">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" placeholder="Remarks or memo...">
                    </div>
                    
                    <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                        <i class="fas fa-plus"></i> Save Expense for ${dateStr}
                    </button>
                </form>
            </div>
        </div>
    `;

    window.openModal('Date Expenses — ' + dateStr, html);
}

function openEditDateExpenseModal(exp) {
    if (!exp) return;
    const catOptions = window.EXPENSE_CATEGORIES.map(c => `<option value="${c}" ${exp.category === c ? 'selected' : ''}>${c}</option>`).join('');
    const paymentMethods = ['cash', 'upi', 'card', 'bank'];
    const payOptions = paymentMethods.map(p => `<option value="${p}" ${exp.payment_method === p ? 'selected' : ''}>${p.toUpperCase()}</option>`).join('');

    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_date_expense">
            <input type="hidden" name="expense_id" value="${exp.id}">
            
            <div class="form-group-custom">
                <label>Title *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-tag"></i>
                    <input type="text" name="title" value="${escapeHtml(exp.title || '')}" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" value="${exp.amount}" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Category</label>
                    <select name="category">
                        ${catOptions}
                    </select>
                </div>
                <div class="form-group-custom">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        ${payOptions}
                    </select>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Expense Date *</label>
                <input type="date" name="expense_date" value="${exp.expense_date}" required>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2">${escapeHtml(exp.notes || '')}</textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Update Expense
            </button>
        </form>
    `;
    window.openModal('Edit Expense on ' + exp.expense_date, html);
}

function openEditTravelCostModal(currentCost) {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="update_travel_cost">
            
            <p style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 0.9rem;">
                Update your daily work commute travel cost. This rate (default ₹40) will automatically calculate monthly travel expenses across all working days in your calendar.
            </p>
            
            <div class="form-group-custom">
                <label>Daily Travel Allowance (₹ / working day) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-bus"></i>
                    <input type="number" step="0.01" min="0" name="daily_travel_cost" value="${currentCost}" placeholder="40.00" required>
                </div>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Save Travel Allowance
            </button>
        </form>
    `;
    window.openModal('Edit Daily Travel Allowance', html);
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
