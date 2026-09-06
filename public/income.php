<?php
/**
 * INCOME — Salary & Additional Income Management
 */
define('PAGE_TITLE', 'Income');
define('PAGE_ID', 'income');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();
$settings = getUserSettings($userId);

$year = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));

$successMsg = '';
$errorMsg = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_income') {
            $type = sanitize($_POST['income_type'] ?? 'freelance');
            $amount = (float)($_POST['amount'] ?? 0);
            $incomeDate = sanitize($_POST['income_date'] ?? date('Y-m-d'));
            $desc = sanitize($_POST['description'] ?? '');
            
            if ($amount <= 0 || !isValidDate($incomeDate)) {
                $errorMsg = 'Please enter a valid amount and date.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO income (user_id, income_type, amount, income_date, description) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $type, $amount, $incomeDate, $desc]);
                
                $incMonth = (int)date('n', strtotime($incomeDate));
                $incYear = (int)date('Y', strtotime($incomeDate));
                generateMonthlyFinancialRecord($userId, $incYear, $incMonth);
                
                $successMsg = 'Income of ' . formatINR($amount) . ' added successfully!';
            }
        } elseif ($action === 'edit_income') {
            $incomeId = (int)($_POST['income_id'] ?? 0);
            $type = sanitize($_POST['income_type'] ?? 'freelance');
            $amount = (float)($_POST['amount'] ?? 0);
            $incomeDate = sanitize($_POST['income_date'] ?? date('Y-m-d'));
            $desc = sanitize($_POST['description'] ?? '');
            
            if ($incomeId <= 0 || $amount <= 0 || !isValidDate($incomeDate)) {
                $errorMsg = 'Please enter a valid amount and date.';
            } else {
                $stmt = $db->prepare('SELECT income_date FROM income WHERE id = ? AND user_id = ?');
                $stmt->execute([$incomeId, $userId]);
                $oldInc = $stmt->fetch();
                
                if ($oldInc) {
                    $stmt = $db->prepare(
                        'UPDATE income SET income_type = ?, amount = ?, income_date = ?, description = ? WHERE id = ? AND user_id = ?'
                    );
                    $stmt->execute([$type, $amount, $incomeDate, $desc, $incomeId, $userId]);
                    
                    $oldM = (int)date('n', strtotime($oldInc['income_date']));
                    $oldY = (int)date('Y', strtotime($oldInc['income_date']));
                    generateMonthlyFinancialRecord($userId, $oldY, $oldM);
                    
                    $newM = (int)date('n', strtotime($incomeDate));
                    $newY = (int)date('Y', strtotime($incomeDate));
                    generateMonthlyFinancialRecord($userId, $newY, $newM);
                    
                    $successMsg = 'Income entry updated successfully!';
                } else {
                    $errorMsg = 'Income record not found.';
                }
            }
        } elseif ($action === 'edit_salary') {
            $newSalary = (float)($_POST['salary'] ?? 0);
            $newSalaryDate = (int)($_POST['salary_date'] ?? 1);
            if ($newSalary <= 0 || $newSalaryDate < 1 || $newSalaryDate > 31) {
                $errorMsg = 'Please enter a valid salary amount and credit date (1-31).';
            } else {
                $stmt = $db->prepare('UPDATE financial_settings SET salary = ?, salary_date = ? WHERE user_id = ?');
                $stmt->execute([$newSalary, $newSalaryDate, $userId]);
                $settings['salary'] = $newSalary;
                $settings['salary_date'] = $newSalaryDate;
                generateMonthlyFinancialRecord($userId, $year, $month);
                $successMsg = 'Base monthly salary updated to ' . formatINR($newSalary) . '!';
            }
        } elseif ($action === 'delete_income') {
            $incomeId = (int)($_POST['income_id'] ?? 0);
            $stmt = $db->prepare('SELECT income_date FROM income WHERE id = ? AND user_id = ?');
            $stmt->execute([$incomeId, $userId]);
            $inc = $stmt->fetch();
            
            if ($inc) {
                $stmt = $db->prepare('DELETE FROM income WHERE id = ? AND user_id = ?');
                $stmt->execute([$incomeId, $userId]);
                
                $incMonth = (int)date('n', strtotime($inc['income_date']));
                $incYear = (int)date('Y', strtotime($inc['income_date']));
                generateMonthlyFinancialRecord($userId, $incYear, $incMonth);
                
                $successMsg = 'Income entry deleted.';
            }
        }
    }
}

// Current period financials
$monthly = generateMonthlyFinancialRecord($userId, $year, $month);
$salary = (float)$settings['salary'];
$workingDays = getWorkingDays($year, $month, $settings);
$dailyRate = $workingDays > 0 ? ($salary / $workingDays) : 0;

// Fetch additional income entries for this month
$stmt = $db->prepare(
    'SELECT * FROM income WHERE user_id = ? AND YEAR(income_date) = ? AND MONTH(income_date) = ? ORDER BY income_date DESC, created_at DESC'
);
$stmt->execute([$userId, $year, $month]);
$incomes = $stmt->fetchAll();

$additionalIncomeTotal = array_sum(array_column($incomes, 'amount'));
$totalIncome = $salary + $additionalIncomeTotal;

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Income Manager</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Salary schedule and extra income streams</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="settings.php" class="btn-secondary-custom">
            <i class="fas fa-cog"></i> Salary Settings
        </a>
        <button class="btn-primary-custom" onclick="openAddIncomeModal()">
            <i class="fas fa-plus"></i> Add Extra Income
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

<!-- ─── OVERVIEW STATS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-content">
            <div class="stat-label">Monthly Salary</div>
            <div class="stat-value" data-count="<?= $salary ?>"><?= formatINR($salary) ?></div>
            <div class="stat-meta">Credited around <?= (int)$settings['salary_date'] ?>th of month</div>
        </div>
    </div>
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-content">
            <div class="stat-label">Extra Income</div>
            <div class="stat-value" data-count="<?= $additionalIncomeTotal ?>"><?= formatINR($additionalIncomeTotal) ?></div>
            <div class="stat-meta"><?= count($incomes) ?> entry(ies) in <?= getMonthName($month) ?></div>
        </div>
    </div>
    <div class="stat-card stat-savings" data-animate>
        <div class="stat-icon"><i class="fas fa-money-check-alt"></i></div>
        <div class="stat-content">
            <div class="stat-label">Gross Income</div>
            <div class="stat-value" data-count="<?= $totalIncome ?>"><?= formatINR($totalIncome) ?></div>
            <div class="stat-meta">Salary + Extra Income</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div class="stat-content">
            <div class="stat-label">Daily Earn Rate</div>
            <div class="stat-value" data-count="<?= $dailyRate ?>"><?= formatINR($dailyRate) ?></div>
            <div class="stat-meta">Across <?= $workingDays ?> working days</div>
        </div>
    </div>
</div>

<!-- ─── FILTER ROW ─── -->
<div class="table-card" style="padding: 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
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
        <button type="submit" class="btn-primary-custom btn-sm-custom"><i class="fas fa-filter"></i> Switch Period</button>
    </form>
    
    <div style="font-size: 0.85rem; color: var(--text-muted);">
        Viewing <?= getMonthName($month) ?> <?= $year ?>
    </div>
</div>

<!-- ─── SALARY DETAILS CARD ─── -->
<div class="breakdown-card" data-animate style="margin-bottom: 1.75rem;">
    <div class="chart-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><i class="fas fa-briefcase"></i> Primary Employment Income Details</h3>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button class="btn-secondary-custom btn-sm-custom" onclick="openEditSalaryModal(<?= (float)$salary ?>, <?= (int)$settings['salary_date'] ?>)">
                <i class="fas fa-edit"></i> Edit Salary
            </button>
            <span class="badge-custom status-completed" style="font-size: 0.8rem; padding: 0.3rem 0.75rem;">Active</span>
        </div>
    </div>
    <div class="breakdown-body">
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-building text-info"></i> Base Monthly Take-Home</span>
            <span class="bk-value text-success"><?= formatINR($salary) ?></span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-calendar-alt text-warning"></i> Expected Credit Day</span>
            <span class="bk-value"><?= (int)$settings['salary_date'] ?>th of every month</span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-business-time text-purple"></i> Working Days this Month</span>
            <span class="bk-value"><?= $workingDays ?> Days (excludes Sundays & 2nd/4th Saturdays)</span>
        </div>
        <div class="breakdown-row">
            <span class="bk-label"><i class="fas fa-calculator text-cyan"></i> Daily Pay Rate</span>
            <span class="bk-value"><?= formatINR($dailyRate) ?> / working day</span>
        </div>
    </div>
</div>

<!-- ─── EXTRA INCOME LOG ─── -->
<div class="table-card" data-animate>
    <div class="chart-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; border-bottom: 1px solid var(--border-color);">
        <h3><i class="fas fa-coins"></i> Additional Income Received in <?= getMonthName($month) ?> <?= $year ?></h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source / Type</th>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($incomes)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                        No additional income logged for this month. Click "Add Extra Income" to add freelance, bonuses, or gifts.
                    </td>
                </tr>
                <?php else: foreach ($incomes as $inc): ?>
                <tr>
                    <td style="font-weight: 500;"><?= date('d M Y', strtotime($inc['income_date'])) ?></td>
                    <td>
                        <span class="badge-custom status-active" style="text-transform: capitalize;">
                            <?= e($inc['income_type']) ?>
                        </span>
                    </td>
                    <td style="color: var(--text-secondary);"><?= e($inc['description'] ?: '—') ?></td>
                    <td class="text-right" style="font-weight: 700; color: var(--success); font-size: 1rem;">
                        +<?= formatINR((float)$inc['amount']) ?>
                    </td>
                    <td class="text-right">
                        <div class="table-actions justify-end">
                            <button type="button" class="btn-table-action edit" title="Edit Income" onclick='openEditIncomeModal(<?= htmlspecialchars(json_encode($inc), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this income entry?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_income">
                                <input type="hidden" name="income_id" value="<?= $inc['id'] ?>">
                                <button type="submit" class="btn-table-action delete" title="Delete Income">
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
function openAddIncomeModal() {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_income">
            
            <div class="form-group-custom">
                <label>Income Source / Type *</label>
                <select name="income_type" required>
                    <option value="freelance">Freelance Work</option>
                    <option value="bonus">Bonus / Incentive</option>
                    <option value="interest">Interest / Dividends</option>
                    <option value="refund">Refund / Cashback</option>
                    <option value="gift">Gift</option>
                    <option value="other">Other Income</option>
                </select>
            </div>
            
            <div class="form-group-custom">
                <label>Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Date Received *</label>
                <input type="date" name="income_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group-custom">
                <label>Description / Client Notes</label>
                <textarea name="description" rows="2" placeholder="e.g. Website development for client, festival bonus"></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-plus"></i> Save Income
            </button>
        </form>
    `;
    window.openModal('Add Additional Income', html);
}

function openEditIncomeModal(inc) {
    if (!inc) return;
    const types = ['freelance', 'bonus', 'interest', 'refund', 'gift', 'other'];
    const typeOptions = types.map(t => `<option value="${t}" ${inc.income_type === t ? 'selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('');

    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_income">
            <input type="hidden" name="income_id" value="${inc.id}">
            
            <div class="form-group-custom">
                <label>Income Source / Type *</label>
                <select name="income_type" required>
                    ${typeOptions}
                </select>
            </div>
            
            <div class="form-group-custom">
                <label>Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" value="${inc.amount}" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Date Received *</label>
                <input type="date" name="income_date" value="${inc.income_date}" required>
            </div>
            
            <div class="form-group-custom">
                <label>Description / Notes</label>
                <textarea name="description" rows="2">${inc.description || ''}</textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Update Income
            </button>
        </form>
    `;
    window.openModal('Edit Income Entry', html);
}

function openEditSalaryModal(currentSalary, currentSalaryDate) {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_salary">
            
            <div class="form-group-custom">
                <label>Base Monthly Take-Home Salary (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="1" name="salary" value="${currentSalary}" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Expected Credit Day (1 - 31) *</label>
                <input type="number" min="1" max="31" name="salary_date" value="${currentSalaryDate}" required>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Save Salary Settings
            </button>
        </form>
    `;
    window.openModal('Edit Primary Salary', html);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
