<?php
/**
 * EXPENSES — Full Expense Tracker & Recurring Bills
 */
define('PAGE_TITLE', 'Expenses');
define('PAGE_ID', 'expenses');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();
$settings = getUserSettings($userId);

// Filter period
$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
$categoryFilter = sanitize($_GET['category'] ?? '');

// Handle Form Submissions
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_expense') {
            $title = sanitize($_POST['title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $category = sanitize($_POST['category'] ?? 'Other');
            $expenseDate = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
            $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');
            $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($title) || $amount <= 0 || !isValidDate($expenseDate)) {
                $errorMsg = 'Please provide a valid title, positive amount, and date.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO expenses (user_id, category, title, amount, expense_date, payment_method, is_recurring, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $category, $title, $amount, $expenseDate, $paymentMethod, $isRecurring, $notes]);
                
                // Refresh monthly financials
                $expMonth = (int)date('n', strtotime($expenseDate));
                $expYear = (int)date('Y', strtotime($expenseDate));
                generateMonthlyFinancialRecord($userId, $expYear, $expMonth);
                
                $successMsg = 'Expense of ' . formatINR($amount) . ' added successfully!';
            }
        } elseif ($action === 'edit_expense') {
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $category = sanitize($_POST['category'] ?? 'Other');
            $expenseDate = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
            $paymentMethod = sanitize($_POST['payment_method'] ?? 'cash');
            $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
            $notes = sanitize($_POST['notes'] ?? '');

            if ($expenseId <= 0 || empty($title) || $amount <= 0 || !isValidDate($expenseDate)) {
                $errorMsg = 'Please provide a valid title, positive amount, and date.';
            } else {
                $stmt = $db->prepare('SELECT expense_date FROM expenses WHERE id = ? AND user_id = ?');
                $stmt->execute([$expenseId, $userId]);
                $oldExp = $stmt->fetch();

                if ($oldExp) {
                    $stmt = $db->prepare(
                        'UPDATE expenses SET category = ?, title = ?, amount = ?, expense_date = ?, payment_method = ?, is_recurring = ?, notes = ? 
                         WHERE id = ? AND user_id = ?'
                    );
                    $stmt->execute([$category, $title, $amount, $expenseDate, $paymentMethod, $isRecurring, $notes, $expenseId, $userId]);

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
        } elseif ($action === 'delete_expense') {
            $expenseId = (int)($_POST['expense_id'] ?? 0);
            $stmt = $db->prepare('SELECT expense_date FROM expenses WHERE id = ? AND user_id = ?');
            $stmt->execute([$expenseId, $userId]);
            $exp = $stmt->fetch();
            
            if ($exp) {
                $stmt = $db->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?');
                $stmt->execute([$expenseId, $userId]);
                
                $expMonth = (int)date('n', strtotime($exp['expense_date']));
                $expYear = (int)date('Y', strtotime($exp['expense_date']));
                generateMonthlyFinancialRecord($userId, $expYear, $expMonth);
                
                $successMsg = 'Expense deleted successfully!';
            }
        } elseif ($action === 'add_recurring') {
            $name = sanitize($_POST['name'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $category = sanitize($_POST['category'] ?? 'Bills');
            $frequency = sanitize($_POST['frequency'] ?? 'monthly');
            $startDate = sanitize($_POST['start_date'] ?? date('Y-m-d'));
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($name) || $amount <= 0 || !isValidDate($startDate)) {
                $errorMsg = 'Please provide valid recurring bill details.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO recurring_expenses (user_id, name, amount, category, frequency, start_date, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $name, $amount, $category, $frequency, $startDate, $notes]);
                generateMonthlyFinancialRecord($userId, $year, $month);
                $successMsg = 'Recurring expense added successfully!';
            }
        } elseif ($action === 'edit_recurring') {
            $recId = (int)($_POST['recurring_id'] ?? 0);
            $name = sanitize($_POST['name'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $category = sanitize($_POST['category'] ?? 'Bills');
            $frequency = sanitize($_POST['frequency'] ?? 'monthly');
            $startDate = sanitize($_POST['start_date'] ?? date('Y-m-d'));
            $notes = sanitize($_POST['notes'] ?? '');

            if ($recId <= 0 || empty($name) || $amount <= 0 || !isValidDate($startDate)) {
                $errorMsg = 'Please provide valid recurring bill details.';
            } else {
                $stmt = $db->prepare(
                    'UPDATE recurring_expenses SET name = ?, amount = ?, category = ?, frequency = ?, start_date = ?, notes = ? 
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([$name, $amount, $category, $frequency, $startDate, $notes, $recId, $userId]);
                generateMonthlyFinancialRecord($userId, $year, $month);
                $successMsg = 'Recurring expense updated successfully!';
            }
        } elseif ($action === 'delete_recurring') {
            $recId = (int)($_POST['recurring_id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM recurring_expenses WHERE id = ? AND user_id = ?');
            $stmt->execute([$recId, $userId]);
            generateMonthlyFinancialRecord($userId, $year, $month);
            $successMsg = 'Recurring expense removed.';
        }
    }
}

// Fetch categories
$categories = $db->query('SELECT * FROM expense_categories ORDER BY name ASC')->fetchAll();

// Fetch filtered expenses
$sql = 'SELECT * FROM expenses WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?';
$params = [$userId, $year, $month];

if (!empty($categoryFilter)) {
    $sql .= ' AND category = ?';
    $params[] = $categoryFilter;
}

$sql .= ' ORDER BY expense_date DESC, created_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Calculate stats for current filter
$stmt = $db->prepare(
    'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?'
);
$stmt->execute([$userId, $year, $month]);
$totalMonthExpenses = (float)$stmt->fetchColumn();

// Daily Average
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$currentDay = ($year == date('Y') && $month == date('n')) ? (int)date('j') : $daysInMonth;
$dailyAverage = $currentDay > 0 ? ($totalMonthExpenses / $currentDay) : 0;

// Highest category
$stmt = $db->prepare(
    'SELECT category, SUM(amount) as cat_sum FROM expenses 
     WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ? 
     GROUP BY category ORDER BY cat_sum DESC LIMIT 1'
);
$stmt->execute([$userId, $year, $month]);
$topCatRow = $stmt->fetch();
$topCatName = $topCatRow['category'] ?? 'None';
$topCatAmount = (float)($topCatRow['cat_sum'] ?? 0);

// Active recurring expenses
$stmt = $db->prepare('SELECT * FROM recurring_expenses WHERE user_id = ? AND active = 1 ORDER BY amount DESC');
$stmt->execute([$userId]);
$recurringExpenses = $stmt->fetchAll();
$totalRecurringMonthly = array_sum(array_column($recurringExpenses, 'amount'));

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER & ACTIONS ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Expense Tracker</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Manage your daily spending and recurring commitments</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button class="btn-secondary-custom" onclick="openAddRecurringModal()">
            <i class="fas fa-redo"></i> Add Recurring
        </button>
        <button class="btn-primary-custom" onclick="openAddExpenseModal()">
            <i class="fas fa-plus"></i> Add Expense
        </button>
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

<!-- ─── STATS CARDS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-content">
            <div class="stat-label"><?= getMonthName($month) ?> Spending</div>
            <div class="stat-value" data-count="<?= $totalMonthExpenses ?>"><?= formatINR($totalMonthExpenses) ?></div>
            <div class="stat-meta"><?= count($expenses) ?> transaction(s)</div>
        </div>
    </div>
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
        <div class="stat-content">
            <div class="stat-label">Daily Average</div>
            <div class="stat-value" data-count="<?= $dailyAverage ?>"><?= formatINR($dailyAverage) ?></div>
            <div class="stat-meta">Over <?= $currentDay ?> days</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
        <div class="stat-content">
            <div class="stat-label">Top Category</div>
            <div class="stat-value" style="font-size: 1.35rem;"><?= e($topCatName) ?></div>
            <div class="stat-meta"><?= formatINR($topCatAmount) ?> spent</div>
        </div>
    </div>
    <div class="stat-card stat-emi" data-animate>
        <div class="stat-icon"><i class="fas fa-redo"></i></div>
        <div class="stat-content">
            <div class="stat-label">Recurring Bills</div>
            <div class="stat-value" data-count="<?= $totalRecurringMonthly ?>"><?= formatINR($totalRecurringMonthly) ?></div>
            <div class="stat-meta"><?= count($recurringExpenses) ?> active bill(s)</div>
        </div>
    </div>
</div>

<!-- ─── FILTER ROW ─── -->
<div class="table-card" style="padding: 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <form method="GET" action="" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
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
        <select name="category" class="form-group-custom" style="width: auto; margin-bottom: 0; padding: 0.5rem 1rem;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat['name']) ?>" <?= $categoryFilter === $cat['name'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary-custom btn-sm-custom"><i class="fas fa-filter"></i> Filter</button>
    </form>
    
    <div style="font-size: 0.85rem; color: var(--text-muted);">
        Showing <strong><?= count($expenses) ?></strong> records for <?= getMonthName($month) ?> <?= $year ?>
    </div>
</div>

<!-- ─── EXPENSES TABLE ─── -->
<div class="table-card" data-animate>
    <div class="chart-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; border-bottom: 1px solid var(--border-color);">
        <h3><i class="fas fa-list"></i> <?= getMonthName($month) ?> <?= $year ?> Expenses</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Payment</th>
                    <th class="text-right">Amount</th>
                    <th>Notes</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                        <i class="fas fa-receipt" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.5;"></i>
                        No expenses recorded for this period. Click "Add Expense" to start tracking.
                    </td>
                </tr>
                <?php else: foreach ($expenses as $exp): ?>
                <tr>
                    <td style="white-space: nowrap; font-weight: 500;">
                        <?= date('d M Y', strtotime($exp['expense_date'])) ?>
                    </td>
                    <td style="font-weight: 600;">
                        <?= e($exp['title']) ?>
                        <?php if ($exp['is_recurring']): ?>
                        <span class="badge-custom status-active" style="font-size: 0.65rem; margin-left: 0.4rem;">Recurring</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.1); color: var(--primary-light); font-size: 0.8rem; font-weight: 600;">
                            <i class="fas fa-tag" style="font-size: 0.75rem;"></i> <?= e($exp['category']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge-custom" style="background: rgba(255, 255, 255, 0.06); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase;">
                            <?= e($exp['payment_method']) ?>
                        </span>
                    </td>
                    <td class="text-right" style="font-weight: 700; color: var(--danger-light); white-space: nowrap;">
                        -<?= formatINR((float)$exp['amount']) ?>
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.825rem; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?= e($exp['notes'] ?: '—') ?>
                    </td>
                    <td class="text-right">
                        <div class="table-actions justify-end">
                            <button type="button" class="btn-table-action edit" title="Edit Expense" onclick='openEditExpenseModal(<?= htmlspecialchars(json_encode($exp), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this expense?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_expense">
                                <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                <button type="submit" class="btn-table-action delete" title="Delete Expense">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─── RECURRING EXPENSES SECTION ─── -->
<div class="table-card" data-animate style="margin-top: 2rem;">
    <div class="chart-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; border-bottom: 1px solid var(--border-color);">
        <h3><i class="fas fa-redo"></i> Active Subscriptions & Recurring Bills</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Bill / Service</th>
                    <th>Category</th>
                    <th>Frequency</th>
                    <th class="text-right">Amount</th>
                    <th>Start Date</th>
                    <th>Notes</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recurringExpenses)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                        No recurring bills configured yet.
                    </td>
                </tr>
                <?php else: foreach ($recurringExpenses as $rec): ?>
                <tr>
                    <td style="font-weight: 600;"><?= e($rec['name']) ?></td>
                    <td><span class="badge-custom status-active"><?= e($rec['category']) ?></span></td>
                    <td style="text-transform: capitalize; font-size: 0.85rem;"><?= e($rec['frequency']) ?></td>
                    <td class="text-right" style="font-weight: 700; color: var(--danger-light);"><?= formatINR((float)$rec['amount']) ?></td>
                    <td><?= date('d M Y', strtotime($rec['start_date'])) ?></td>
                    <td style="color: var(--text-muted); font-size: 0.825rem;"><?= e($rec['notes'] ?: '—') ?></td>
                    <td class="text-right">
                        <div class="table-actions justify-end">
                            <button type="button" class="btn-table-action edit" title="Edit Recurring Bill" onclick='openEditRecurringModal(<?= htmlspecialchars(json_encode($rec), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Remove this recurring bill?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_recurring">
                                <input type="hidden" name="recurring_id" value="<?= $rec['id'] ?>">
                                <button type="submit" class="btn-table-action delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const EXPENSE_CATEGORIES = <?= json_encode(array_column($categories, 'name')) ?>;

function openAddExpenseModal() {
    const categoryOptions = EXPENSE_CATEGORIES.map(c => `<option value="${c}">${c}</option>`).join('');
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_expense">
            
            <div class="form-group-custom">
                <label>Title *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-tag"></i>
                    <input type="text" name="title" placeholder="e.g. Grocery Shopping, Dinner" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Category</label>
                    <select name="category">
                        ${categoryOptions}
                    </select>
                </div>
                <div class="form-group-custom">
                    <label>Payment Method</label>
                    <select name="payment_method">
                        <option value="upi">UPI</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="card">Debit/Credit Card</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Expense Date *</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" placeholder="Add extra details..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-plus"></i> Save Expense
            </button>
        </form>
    `;
    window.openModal('Add New Expense', html);
}

function openEditExpenseModal(exp) {
    if (!exp) return;
    const catOptions = EXPENSE_CATEGORIES.map(c => `<option value="${c}" ${exp.category === c ? 'selected' : ''}>${c}</option>`).join('');
    const paymentMethods = ['upi', 'cash', 'bank', 'card'];
    const payOptions = paymentMethods.map(p => `<option value="${p}" ${exp.payment_method === p ? 'selected' : ''}>${p.toUpperCase()}</option>`).join('');

    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_expense">
            <input type="hidden" name="expense_id" value="${exp.id}">
            
            <div class="form-group-custom">
                <label>Title *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-tag"></i>
                    <input type="text" name="title" value="${exp.title || ''}" required>
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
                <textarea name="notes" rows="2">${exp.notes || ''}</textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Update Expense
            </button>
        </form>
    `;
    window.openModal('Edit Expense', html);
}

function openAddRecurringModal() {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_recurring">
            
            <div class="form-group-custom">
                <label>Bill Name *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-redo"></i>
                    <input type="text" name="name" placeholder="e.g. WiFi, Netflix, Gym" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Category</label>
                    <select name="category">
                        <option value="Bills">Bills & Utilities</option>
                        <option value="Subscriptions">Subscriptions</option>
                        <option value="Rent">Rent</option>
                        <option value="Health">Health / Gym</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group-custom">
                    <label>Frequency</label>
                    <select name="frequency">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Start Date *</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" placeholder="Account numbers, due date remarks..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-check"></i> Save Recurring Bill
            </button>
        </form>
    `;
    window.openModal('Add Recurring Bill', html);
}

function openEditRecurringModal(rec) {
    if (!rec) return;
    const cats = ['Bills', 'Subscriptions', 'Rent', 'Health', 'Other'];
    const catOptions = cats.map(c => `<option value="${c}" ${rec.category === c ? 'selected' : ''}>${c}</option>`).join('');
    const freqs = ['monthly', 'quarterly', 'yearly', 'weekly'];
    const freqOptions = freqs.map(f => `<option value="${f}" ${rec.frequency === f ? 'selected' : ''}>${f.charAt(0).toUpperCase() + f.slice(1)}</option>`).join('');

    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_recurring">
            <input type="hidden" name="recurring_id" value="${rec.id}">
            
            <div class="form-group-custom">
                <label>Bill Name *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-redo"></i>
                    <input type="text" name="name" value="${rec.name || ''}" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" value="${rec.amount}" required>
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
                    <label>Frequency</label>
                    <select name="frequency">
                        ${freqOptions}
                    </select>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Start Date *</label>
                <input type="date" name="start_date" value="${rec.start_date}" required>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2">${rec.notes || ''}</textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Update Recurring Bill
            </button>
        </form>
    `;
    window.openModal('Edit Recurring Bill', html);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
