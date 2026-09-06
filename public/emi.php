<?php
/**
 * EMI — Interactive EMI Calculator & Active Loan Manager
 */
define('PAGE_TITLE', 'EMI Calculator');
define('PAGE_ID', 'emi');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();
$settings = getUserSettings($userId);
$salary = (float)$settings['salary'];

$successMsg = '';
$errorMsg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_loan') {
            $name = sanitize($_POST['loan_name'] ?? '');
            $principal = (float)($_POST['principal'] ?? 0);
            $rate = (float)($_POST['annual_interest_rate'] ?? 0);
            $tenure = (int)($_POST['tenure_months'] ?? 0);
            $startDate = sanitize($_POST['start_date'] ?? date('Y-m-d'));
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($name) || $principal <= 0 || $tenure <= 0 || !isValidDate($startDate)) {
                $errorMsg = 'Please enter valid loan parameters.';
            } else {
                $emiData = calculateEMI($principal, $rate, $tenure);
                $emiAmount = $emiData['emi'];
                $totalInterest = $emiData['total_interest'];
                $totalPayable = $emiData['total_payable'];
                
                $stmt = $db->prepare(
                    'INSERT INTO loans 
                     (user_id, loan_name, principal, annual_interest_rate, tenure_months, start_date, emi_amount, total_interest, total_payable, active, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
                );
                $stmt->execute([$userId, $name, $principal, $rate, $tenure, $startDate, $emiAmount, $totalInterest, $totalPayable, $notes]);
                
                // Refresh monthly financials
                generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
                $successMsg = "Loan '{$name}' added with monthly EMI of " . formatINR($emiAmount);
            }
        } elseif ($action === 'edit_loan') {
            $loanId = (int)($_POST['loan_id'] ?? 0);
            $name = sanitize($_POST['loan_name'] ?? '');
            $principal = (float)($_POST['principal'] ?? 0);
            $rate = (float)($_POST['annual_interest_rate'] ?? 0);
            $tenure = (int)($_POST['tenure_months'] ?? 0);
            $startDate = sanitize($_POST['start_date'] ?? date('Y-m-d'));
            $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
            $notes = sanitize($_POST['notes'] ?? '');

            if ($loanId <= 0 || empty($name) || $principal <= 0 || $tenure <= 0 || !isValidDate($startDate)) {
                $errorMsg = 'Please enter valid loan parameters.';
            } else {
                $emiData = calculateEMI($principal, $rate, $tenure);
                $stmt = $db->prepare(
                    'UPDATE loans SET 
                     loan_name = ?, principal = ?, annual_interest_rate = ?, tenure_months = ?, 
                     start_date = ?, emi_amount = ?, total_interest = ?, total_payable = ?, active = ?, notes = ? 
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([
                    $name, $principal, $rate, $tenure, $startDate,
                    $emiData['emi'], $emiData['total_interest'], $emiData['total_payable'],
                    $active, $notes, $loanId, $userId
                ]);

                generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
                $successMsg = "Loan '{$name}' updated successfully!";
            }
        } elseif ($action === 'delete_loan') {
            $loanId = (int)($_POST['loan_id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM loans WHERE id = ? AND user_id = ?');
            $stmt->execute([$loanId, $userId]);
            
            generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
            $successMsg = 'Loan deleted.';
        }
    }
}

// Fetch active loans
$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? ORDER BY active DESC, created_at DESC');
$stmt->execute([$userId]);
$loans = $stmt->fetchAll();

$totalActiveEMI = 0;
$totalPrincipalOutstanding = 0;
foreach ($loans as $l) {
    if ($l['active']) {
        $totalActiveEMI += (float)$l['emi_amount'];
        $totalPrincipalOutstanding += (float)$l['principal'];
    }
}

$emiBurdenPct = $salary > 0 ? round(($totalActiveEMI / $salary) * 100, 1) : 0;

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER & ACTIONS ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">EMI & Loan Command Center</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Real-time loan calculation, schedule breakdown, and active EMI tracking</p>
    </div>
    <div>
        <button class="btn-primary-custom" onclick="openAddLoanModal()">
            <i class="fas fa-plus"></i> Add Active Loan
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
    <div class="stat-card stat-emi" data-animate>
        <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
        <div class="stat-content">
            <div class="stat-label">Monthly EMI Commitment</div>
            <div class="stat-value" data-count="<?= $totalActiveEMI ?>"><?= formatINR($totalActiveEMI) ?></div>
            <div class="stat-meta"><?= count($loans) ?> active loan(s)</div>
        </div>
    </div>
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-percentage"></i></div>
        <div class="stat-content">
            <div class="stat-label">EMI Burden of Salary</div>
            <div class="stat-value"><?= $emiBurdenPct ?>%</div>
            <div class="stat-meta">Recommended: &lt; 30%</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-landmark"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Principal</div>
            <div class="stat-value" data-count="<?= $totalPrincipalOutstanding ?>"><?= formatINR($totalPrincipalOutstanding) ?></div>
            <div class="stat-meta">Initial total borrowings</div>
        </div>
    </div>
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="stat-content">
            <div class="stat-label">Salary Remaining</div>
            <div class="stat-value" data-count="<?= max(0, $salary - $totalActiveEMI) ?>"><?= formatINR(max(0, $salary - $totalActiveEMI)) ?></div>
            <div class="stat-meta">After EMI deduction</div>
        </div>
    </div>
</div>

<!-- ─── INTERACTIVE EMI CALCULATOR ─── -->
<div class="table-card" data-animate style="padding: 1.75rem; margin-bottom: 2rem;">
    <div class="chart-header" style="margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.85rem;">
        <h3><i class="fas fa-calculator"></i> Interactive Loan & EMI Calculator</h3>
        <span class="badge-custom status-active">Reducing Balance Formula</span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; align-items: center;">
        <!-- Left: Sliders & Controls -->
        <div>
            <!-- Principal Slider -->
            <div class="form-group-custom" style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <label style="margin: 0; font-weight: 600;">Loan Amount (Principal)</label>
                    <span id="principalDisplay" style="font-weight: 700; color: var(--primary-light);">₹1,00,000</span>
                </div>
                <input type="range" id="principalSlider" min="5000" max="2000000" step="5000" value="100000" style="width: 100%; accent-color: var(--primary);">
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    <span>₹5k</span>
                    <span>₹20 Lakhs</span>
                </div>
            </div>

            <!-- Rate Slider -->
            <div class="form-group-custom" style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <label style="margin: 0; font-weight: 600;">Annual Interest Rate (%)</label>
                    <span id="rateDisplay" style="font-weight: 700; color: var(--warning-light);">12.5%</span>
                </div>
                <input type="range" id="rateSlider" min="0" max="30" step="0.25" value="12.5" style="width: 100%; accent-color: var(--warning);">
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    <span>0% (No Cost)</span>
                    <span>30%</span>
                </div>
            </div>

            <!-- Tenure Slider -->
            <div class="form-group-custom" style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <label style="margin: 0; font-weight: 600;">Tenure (Months)</label>
                    <span id="tenureDisplay" style="font-weight: 700; color: var(--info-light);">12 Months (1 Year)</span>
                </div>
                <input type="range" id="tenureSlider" min="3" max="84" step="1" value="12" style="width: 100%; accent-color: var(--info);">
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                    <span>3 Months</span>
                    <span>84 Months (7 Yrs)</span>
                </div>
            </div>

            <button class="btn-secondary-custom btn-full" onclick="showLiveAmortizationSchedule()">
                <i class="fas fa-table"></i> View Full Amortization Schedule
            </button>
        </div>

        <!-- Right: Calculation Summary Box -->
        <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); padding: 1.75rem; text-align: center;">
            <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 0.5rem;">
                Estimated Monthly EMI
            </div>
            <div id="emiResult" style="font-size: 2.5rem; font-weight: 900; color: var(--primary-light); line-height: 1.1; margin-bottom: 1.5rem;">
                ₹8,908
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem; text-align: left;">
                <div>
                    <span style="font-size: 0.775rem; color: var(--text-muted); display: block;">Total Interest:</span>
                    <strong id="interestResult" style="color: var(--warning-light); font-size: 1.1rem;">₹6,900</strong>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.775rem; color: var(--text-muted); display: block;">Total Payable:</span>
                    <strong id="payableResult" style="color: var(--text-primary); font-size: 1.1rem;">₹1,06,900</strong>
                </div>
            </div>

            <!-- Share bar -->
            <div style="margin-top: 1.25rem;">
                <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 0.4rem;">
                    <span style="color: var(--primary-light);">Principal (<span id="principalSharePct">93%</span>)</span>
                    <span style="color: var(--warning-light);">Interest (<span id="interestSharePct">7%</span>)</span>
                </div>
                <div style="height: 8px; border-radius: 4px; display: flex; overflow: hidden; background: rgba(255, 255, 255, 0.08);">
                    <div id="principalShareBar" style="width: 93%; background: var(--primary);"></div>
                    <div id="interestShareBar" style="width: 7%; background: var(--warning);"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ─── ACTIVE LOANS LIST ─── -->
<div class="table-card" data-animate>
    <div class="chart-header" style="padding: 1.25rem 1.5rem; margin-bottom: 0; border-bottom: 1px solid var(--border-color);">
        <h3><i class="fas fa-list-alt"></i> Your Active Loans & EMIs</h3>
    </div>
    <div style="overflow-x: auto;">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Loan Name</th>
                    <th class="text-right">Principal</th>
                    <th class="text-right">Rate</th>
                    <th class="text-center">Tenure</th>
                    <th class="text-right">Monthly EMI</th>
                    <th class="text-center">Start Date</th>
                    <th class="text-right">Total Payable</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($loans)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                        No active loans recorded. Use the calculator above to plan or click "Add Active Loan".
                    </td>
                </tr>
                <?php else: foreach ($loans as $loan): ?>
                <tr>
                    <td style="font-weight: 700;"><?= e($loan['loan_name']) ?></td>
                    <td class="text-right"><?= formatINR((float)$loan['principal']) ?></td>
                    <td class="text-right"><?= (float)$loan['annual_interest_rate'] ?>%</td>
                    <td class="text-center"><?= (int)$loan['tenure_months'] ?> mo</td>
                    <td class="text-right" style="font-weight: 700; color: var(--danger-light);"><?= formatINR((float)$loan['emi_amount']) ?></td>
                    <td class="text-center"><?= date('d M Y', strtotime($loan['start_date'])) ?></td>
                    <td class="text-right"><?= formatINR((float)$loan['total_payable']) ?></td>
                    <td class="text-right">
                        <div class="table-actions justify-end">
                            <button type="button" class="btn-table-action edit" title="Edit Loan" onclick='openEditLoanModal(<?= htmlspecialchars(json_encode($loan), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-table-action view" title="View Schedule" onclick='viewLoanSchedule(<?= htmlspecialchars(json_encode($loan), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="fas fa-calendar-check"></i>
                            </button>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this loan?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="action" value="delete_loan">
                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
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
// Format Indian Rupee
function formatINRClient(val) {
    val = Math.round(val);
    const s = val.toString();
    const last3 = s.substring(s.length - 3);
    const other = s.substring(0, s.length - 3);
    const formatted = other !== '' ? other.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3;
    return '₹' + formatted;
}

// EMI Math
function calcEMI(p, rAnnual, n) {
    if (p <= 0 || n <= 0) return { emi: 0, interest: 0, total: 0 };
    if (rAnnual <= 0) return { emi: p / n, interest: 0, total: p };

    const r = (rAnnual / 100) / 12;
    const pow = Math.pow(1 + r, n);
    const emi = p * r * pow / (pow - 1);
    const total = emi * n;
    const interest = total - p;
    return { emi: emi, interest: interest, total: total };
}

// Sliders listener
const pSlider = document.getElementById('principalSlider');
const rSlider = document.getElementById('rateSlider');
const tSlider = document.getElementById('tenureSlider');

function updateCalculator() {
    const p = parseFloat(pSlider.value);
    const r = parseFloat(rSlider.value);
    const t = parseInt(tSlider.value);

    document.getElementById('principalDisplay').textContent = formatINRClient(p);
    document.getElementById('rateDisplay').textContent = r + '%';
    const yrs = (t / 12).toFixed(1);
    document.getElementById('tenureDisplay').textContent = `${t} Months (${yrs} Yrs)`;

    const res = calcEMI(p, r, t);
    document.getElementById('emiResult').textContent = formatINRClient(res.emi);
    document.getElementById('interestResult').textContent = formatINRClient(res.interest);
    document.getElementById('payableResult').textContent = formatINRClient(res.total);

    const pPct = Math.round((p / res.total) * 100);
    const iPct = 100 - pPct;

    document.getElementById('principalSharePct').textContent = pPct + '%';
    document.getElementById('interestSharePct').textContent = iPct + '%';
    document.getElementById('principalShareBar').style.width = pPct + '%';
    document.getElementById('interestShareBar').style.width = iPct + '%';
}

[pSlider, rSlider, tSlider].forEach(slider => {
    slider.addEventListener('input', updateCalculator);
});

updateCalculator();

function showLiveAmortizationSchedule() {
    const p = parseFloat(pSlider.value);
    const r = parseFloat(rSlider.value);
    const t = parseInt(tSlider.value);
    renderScheduleModal(p, r, t, 'Calculated Loan Schedule');
}

function viewLoanSchedule(loan) {
    renderScheduleModal(parseFloat(loan.principal), parseFloat(loan.annual_interest_rate), parseInt(loan.tenure_months), loan.loan_name + ' Amortization Schedule');
}

function renderScheduleModal(principal, annualRate, tenureMonths, title) {
    const r = (annualRate / 100) / 12;
    const res = calcEMI(principal, annualRate, tenureMonths);
    const emi = res.emi;
    let balance = principal;

    let rowsHtml = '';
    for (let i = 1; i <= tenureMonths; i++) {
        const interestComp = balance * r;
        let principalComp = emi - interestComp;
        if (i === tenureMonths) {
            principalComp = balance;
        }
        balance -= principalComp;
        if (balance < 0) balance = 0;

        rowsHtml += `
            <tr>
                <td class="text-center">${i}</td>
                <td class="text-right">${formatINRClient(emi)}</td>
                <td class="text-right">${formatINRClient(principalComp)}</td>
                <td class="text-right">${formatINRClient(interestComp)}</td>
                <td class="text-right">${formatINRClient(balance)}</td>
            </tr>
        `;
    }

    const html = `
        <div style="max-height: 60vh; overflow-y: auto;">
            <table class="custom-table" style="font-size: 0.825rem;">
                <thead>
                    <tr>
                        <th class="text-center">Mo #</th>
                        <th class="text-right">EMI</th>
                        <th class="text-right">Principal</th>
                        <th class="text-right">Interest</th>
                        <th class="text-right">Remaining</th>
                    </tr>
                </thead>
                <tbody>${rowsHtml}</tbody>
            </table>
        </div>
    `;
    window.openModal(title, html);
}

function openAddLoanModal() {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_loan">
            
            <div class="form-group-custom">
                <label>Loan / EMI Name *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-tag"></i>
                    <input type="text" name="loan_name" placeholder="e.g. Phone EMI, Two Wheeler Loan" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Principal Amount (₹) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" step="0.01" min="1" name="principal" placeholder="50000" required>
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Interest Rate (% p.a.) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-percentage"></i>
                        <input type="number" step="0.01" min="0" name="annual_interest_rate" placeholder="12.0" required>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Tenure (Months) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="number" min="1" max="360" name="tenure_months" placeholder="12" required>
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" placeholder="Lender bank, account/loan ID..."></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-plus"></i> Save Loan
            </button>
        </form>
    `;
    window.openModal('Add Active Loan / EMI', html);
}

function openEditLoanModal(loan) {
    if (!loan) return;
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="edit_loan">
            <input type="hidden" name="loan_id" value="${loan.id}">
            
            <div class="form-group-custom">
                <label>Loan / EMI Title *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-tag"></i>
                    <input type="text" name="loan_name" value="${loan.loan_name || ''}" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Principal Amount (₹) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" step="0.01" min="1" name="principal" value="${loan.principal}" required>
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Interest Rate (% p.a.) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-percentage"></i>
                        <input type="number" step="0.01" min="0" name="annual_interest_rate" value="${loan.annual_interest_rate}" required>
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Tenure (Months) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="number" min="1" max="360" name="tenure_months" value="${loan.tenure_months}" required>
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" value="${loan.start_date}" required>
                </div>
            </div>

            <div class="form-group-custom">
                <label>Status *</label>
                <select name="active">
                    <option value="1" ${loan.active == 1 ? 'selected' : ''}>Active (Ongoing EMI)</option>
                    <option value="0" ${loan.active == 0 ? 'selected' : ''}>Closed / Completed</option>
                </select>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2">${loan.notes || ''}</textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Update Loan
            </button>
        </form>
    `;
    window.openModal('Edit Loan / EMI', html);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
