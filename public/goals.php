<?php
/**
 * GOALS — Financial Goals & Wishlist Tracker
 */
define('PAGE_TITLE', 'Goals');
define('PAGE_ID', 'goals');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();

$successMsg = '';
$errorMsg = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_goal') {
            $name = sanitize($_POST['name'] ?? '');
            $targetAmount = (float)($_POST['target_amount'] ?? 0);
            $savedAmount = (float)($_POST['saved_amount'] ?? 0);
            $monthlyContrib = (float)($_POST['monthly_contribution'] ?? 0);
            $targetDate = !empty($_POST['target_date']) ? sanitize($_POST['target_date']) : null;
            $priority = sanitize($_POST['priority'] ?? 'medium');
            $icon = sanitize($_POST['icon'] ?? 'fa-bullseye');
            $color = sanitize($_POST['color'] ?? '#3b82f6');
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($name) || $targetAmount <= 0) {
                $errorMsg = 'Goal name and target amount are required.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO financial_goals 
                     (user_id, name, target_amount, saved_amount, monthly_contribution, target_date, priority, icon, color, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$userId, $name, $targetAmount, $savedAmount, $monthlyContrib, $targetDate, $priority, $icon, $color, $notes]);
                $successMsg = "Goal '{$name}' created successfully!";
            }
        } elseif ($action === 'add_contribution') {
            $goalId = (int)($_POST['goal_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $date = sanitize($_POST['contribution_date'] ?? date('Y-m-d'));
            $notes = sanitize($_POST['notes'] ?? '');
            
            if ($goalId <= 0 || $amount <= 0) {
                $errorMsg = 'Please enter a valid contribution amount.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO goal_contributions (goal_id, user_id, amount, contribution_date, notes) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$goalId, $userId, $amount, $date, $notes]);
                
                // Update goal saved_amount
                $stmt = $db->prepare('UPDATE financial_goals SET saved_amount = saved_amount + ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$amount, $goalId, $userId]);
                
                $successMsg = 'Contribution of ' . formatINR($amount) . ' saved to goal!';
            }
        } elseif ($action === 'delete_goal') {
            $goalId = (int)($_POST['goal_id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM financial_goals WHERE id = ? AND user_id = ?');
            $stmt->execute([$goalId, $userId]);
            $successMsg = 'Goal removed.';
        }
    }
}

// Fetch all goals and projections
$goals = getGoalProjections($userId);

$totalTarget = array_sum(array_column($goals, 'target_amount'));
$totalSaved = array_sum(array_column($goals, 'saved_amount'));
$overallProgress = $totalTarget > 0 ? calcPercentage($totalSaved, $totalTarget) : 0;
$totalMonthlyAllocated = array_sum(array_column($goals, 'monthly_contribution'));

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER & ACTIONS ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Financial Goals & Wishlist</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Target purchases, emergency savings, and progress milestones</p>
    </div>
    <div>
        <button class="btn-primary-custom" onclick="openAddGoalModal()">
            <i class="fas fa-plus"></i> Create New Goal
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
    <div class="stat-card stat-savings" data-animate>
        <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Target</div>
            <div class="stat-value" data-count="<?= $totalTarget ?>"><?= formatINR($totalTarget) ?></div>
            <div class="stat-meta"><?= count($goals) ?> active goal(s)</div>
        </div>
    </div>
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
        <div class="stat-content">
            <div class="stat-label">Saved So Far</div>
            <div class="stat-value" data-count="<?= $totalSaved ?>"><?= formatINR($totalSaved) ?></div>
            <div class="stat-meta"><?= $overallProgress ?>% of total target</div>
        </div>
    </div>
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-content">
            <div class="stat-label">Remaining Needed</div>
            <div class="stat-value" data-count="<?= max(0, $totalTarget - $totalSaved) ?>"><?= formatINR(max(0, $totalTarget - $totalSaved)) ?></div>
            <div class="stat-meta">To fulfill all wishlist goals</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="stat-content">
            <div class="stat-label">Monthly Allocation</div>
            <div class="stat-value" data-count="<?= $totalMonthlyAllocated ?>"><?= formatINR($totalMonthlyAllocated) ?></div>
            <div class="stat-meta">Planned monthly savings</div>
        </div>
    </div>
</div>

<!-- ─── GOALS GRID ─── -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <?php if (empty($goals)): ?>
    <div class="table-card" style="grid-column: 1/-1; padding: 3rem; text-align: center; color: var(--text-muted);">
        <i class="fas fa-bullseye" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5;"></i>
        <p>No financial goals set yet. Click "Create New Goal" to start planning for gadgets, appliances, or savings.</p>
    </div>
    <?php else: foreach ($goals as $g): ?>
    <div class="widget-card" data-animate>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <div style="width: 46px; height: 46px; border-radius: 12px; background: <?= $g['color'] ?>20; color: <?= $g['color'] ?>; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                    <i class="fas <?= $g['icon'] ?>"></i>
                </div>
                <div>
                    <h3 style="font-size: 1.15rem; font-weight: 700; margin: 0;"><?= e($g['name']) ?></h3>
                    <span class="badge-custom" style="font-size: 0.7rem; text-transform: uppercase; background: rgba(255, 255, 255, 0.08); padding: 0.2rem 0.5rem;">
                        <?= ucfirst($g['priority']) ?> Priority
                    </span>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.25rem; font-weight: 800; color: <?= $g['color'] ?>;">
                    <?= $g['percentage'] ?>%
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar-custom" style="height: 10px; margin-bottom: 0.85rem;">
            <div class="progress-fill" style="width: <?= min(100, $g['percentage']) ?>%; background: <?= $g['color'] ?>;"></div>
        </div>

        <!-- Amounts summary -->
        <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 1rem;">
            <div>
                <span style="color: var(--text-muted); font-size: 0.775rem; display: block;">Saved</span>
                <strong style="color: var(--text-primary); font-size: 1.05rem;"><?= formatINR($g['saved_amount']) ?></strong>
            </div>
            <div style="text-align: right;">
                <span style="color: var(--text-muted); font-size: 0.775rem; display: block;">Target</span>
                <strong style="color: var(--text-secondary); font-size: 1.05rem;"><?= formatINR($g['target_amount']) ?></strong>
            </div>
        </div>

        <!-- Projections Info Box -->
        <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 0.85rem 1rem; margin-bottom: 1rem; font-size: 0.825rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="color: var(--text-muted);"><i class="fas fa-calendar-alt"></i> Estimated Completion:</span>
                <strong><?= $g['estimated_date'] ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="color: var(--text-muted);"><i class="fas fa-wallet"></i> Monthly Contribution:</span>
                <strong><?= formatINR($g['monthly_contribution']) ?>/mo</strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);"><i class="fas fa-coins"></i> Daily Savings Required:</span>
                <strong><?= formatINR($g['daily_savings']) ?>/day</strong>
            </div>
        </div>

        <!-- Actions -->
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn-primary-custom btn-sm-custom" style="flex: 1;" onclick="openAddContributionModal(<?= $g['id'] ?>, '<?= e($g['name']) ?>')">
                <i class="fas fa-plus"></i> Add Funds
            </button>
            <form method="POST" action="" onsubmit="return confirm('Delete this goal?');">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_goal">
                <input type="hidden" name="goal_id" value="<?= $g['id'] ?>">
                <button type="submit" class="btn-table-action delete" title="Delete Goal">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<script>
function openAddGoalModal() {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_goal">
            
            <div class="form-group-custom">
                <label>Goal / Item Name *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-bullseye"></i>
                    <input type="text" name="name" placeholder="e.g. iPad Pro, Emergency Fund, Vacation" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Target Amount (₹) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" step="0.01" min="1" name="target_amount" placeholder="50000" required>
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Initial Saved (₹)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" step="0.01" min="0" name="saved_amount" value="0">
                    </div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Monthly Contribution (₹)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-piggy-bank"></i>
                        <input type="number" step="0.01" min="0" name="monthly_contribution" placeholder="2500">
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Target Date</label>
                    <input type="date" name="target_date">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="high">High</option>
                        <option value="medium" selected>Medium</option>
                        <option value="low">Low</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="form-group-custom">
                    <label>Color Accent</label>
                    <select name="color">
                        <option value="#3b82f6" selected>Blue</option>
                        <option value="#10b981">Emerald Green</option>
                        <option value="#f59e0b">Amber Gold</option>
                        <option value="#8b5cf6">Purple</option>
                        <option value="#ec4899">Pink</option>
                        <option value="#06b6d4">Cyan</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Icon</label>
                <select name="icon">
                    <option value="fa-bullseye">Target / General (fa-bullseye)</option>
                    <option value="fa-tablet-alt">Tablet / Tech (fa-tablet-alt)</option>
                    <option value="fa-laptop">Laptop (fa-laptop)</option>
                    <option value="fa-tshirt">Appliance (fa-tshirt)</option>
                    <option value="fa-fire">Home Appliance (fa-fire)</option>
                    <option value="fa-car">Vehicle / Bike (fa-car)</option>
                    <option value="fa-plane">Travel / Vacation (fa-plane)</option>
                    <option value="fa-shield-alt">Emergency Fund (fa-shield-alt)</option>
                </select>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-check"></i> Create Goal
            </button>
        </form>
    `;
    window.openModal('Create Financial Goal', html);
}

function openAddContributionModal(goalId, goalName) {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_contribution">
            <input type="hidden" name="goal_id" value="${goalId}">
            
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Adding funds towards <strong>${goalName}</strong>
            </p>
            
            <div class="form-group-custom">
                <label>Deposit Amount (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Contribution Date *</label>
                <input type="date" name="contribution_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group-custom">
                <label>Notes (optional)</label>
                <input type="text" name="notes" placeholder="Salary savings, bonus deposit...">
            </div>
            
            <button type="submit" class="btn-success-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-plus"></i> Save Contribution
            </button>
        </form>
    `;
    window.openModal('Add Savings to ' + goalName, html);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
