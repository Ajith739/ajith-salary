<?php
/**
 * FRIENDS — Friend Loans & Repayments Manager
 */
define('PAGE_TITLE', 'Friends');
define('PAGE_ID', 'friends');

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

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_friend') {
            $name = sanitize($_POST['name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            
            if (empty($name)) {
                $errorMsg = 'Friend name is required.';
            } else {
                $stmt = $db->prepare('INSERT INTO friends (user_id, name, phone, notes) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $name, $phone, $notes]);
                $successMsg = "Friend '{$name}' added successfully!";
            }
        } elseif ($action === 'add_transaction') {
            $friendId = (int)($_POST['friend_id'] ?? 0);
            $type = sanitize($_POST['type'] ?? 'given'); // 'given' or 'repaid'
            $amount = (float)($_POST['amount'] ?? 0);
            $date = sanitize($_POST['transaction_date'] ?? date('Y-m-d'));
            $desc = sanitize($_POST['description'] ?? '');
            
            if ($friendId <= 0 || $amount <= 0 || !isValidDate($date)) {
                $errorMsg = 'Please provide valid transaction details.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO friend_transactions (friend_id, user_id, type, amount, transaction_date, description) 
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$friendId, $userId, $type, $amount, $date, $desc]);
                
                // Refresh monthly financials
                $txMonth = (int)date('n', strtotime($date));
                $txYear = (int)date('Y', strtotime($date));
                generateMonthlyFinancialRecord($userId, $txYear, $txMonth);
                
                $successMsg = ($type === 'given' ? 'Loan of ' : 'Repayment of ') . formatINR($amount) . ' recorded!';
            }
        } elseif ($action === 'settle_up') {
            $friendId = (int)($_POST['friend_id'] ?? 0);
            $remaining = (float)($_POST['remaining'] ?? 0);
            
            if ($friendId > 0 && $remaining > 0) {
                $stmt = $db->prepare(
                    'INSERT INTO friend_transactions (friend_id, user_id, type, amount, transaction_date, description) 
                     VALUES (?, ?, "repaid", ?, CURDATE(), "Full settlement")'
                );
                $stmt->execute([$friendId, $userId, $remaining]);
                
                generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
                $successMsg = 'Friend account settled completely!';
            }
        } elseif ($action === 'delete_friend') {
            $friendId = (int)($_POST['friend_id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM friends WHERE id = ? AND user_id = ?');
            $stmt->execute([$friendId, $userId]);
            
            generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
            $successMsg = 'Friend and history removed.';
        }
    }
}

// Fetch friend summaries
$summary = getFriendMoneySummary($userId);
$friends = $summary['friends'];

// Fetch transaction history for each friend
$transactionsByFriend = [];
$stmt = $db->prepare(
    'SELECT * FROM friend_transactions WHERE user_id = ? ORDER BY transaction_date DESC, created_at DESC'
);
$stmt->execute([$userId]);
$allTransactions = $stmt->fetchAll();

foreach ($allTransactions as $t) {
    $transactionsByFriend[$t['friend_id']][] = $t;
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER & ACTIONS ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Friend Loans & Debt Manager</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Track money lent to friends and repayments</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <button class="btn-secondary-custom" onclick="openAddFriendModal()">
            <i class="fas fa-user-plus"></i> Add Friend
        </button>
        <button class="btn-primary-custom" onclick="openRecordTxModal()">
            <i class="fas fa-exchange-alt"></i> Record Transaction
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

<!-- ─── SUMMARY CARDS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Outstanding</div>
            <div class="stat-value" data-count="<?= $summary['total_outstanding'] ?>"><?= formatINR($summary['total_outstanding']) ?></div>
            <div class="stat-meta">Awaiting return</div>
        </div>
    </div>
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Lent</div>
            <div class="stat-value" data-count="<?= $summary['total_given'] ?>"><?= formatINR($summary['total_given']) ?></div>
            <div class="stat-meta">Lifetime money given</div>
        </div>
    </div>
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Repaid</div>
            <div class="stat-value" data-count="<?= $summary['total_repaid'] ?>"><?= formatINR($summary['total_repaid']) ?></div>
            <div class="stat-meta">Successfully returned</div>
        </div>
    </div>
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-label">Tracked Friends</div>
            <div class="stat-value"><?= count($friends) ?></div>
            <div class="stat-meta"><?= count(array_filter($friends, fn($f) => $f['remaining'] > 0)) ?> with dues</div>
        </div>
    </div>
</div>

<!-- ─── FRIENDS LEDGER GRID ─── -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <?php if (empty($friends)): ?>
    <div class="table-card" style="grid-column: 1/-1; padding: 3rem; text-align: center; color: var(--text-muted);">
        <i class="fas fa-user-friends" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5;"></i>
        <p>No friends added yet. Click "Add Friend" to begin tracking personal loans.</p>
    </div>
    <?php else: foreach ($friends as $f): 
        $txList = $transactionsByFriend[$f['id']] ?? [];
    ?>
    <div class="widget-card" data-animate>
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <div class="friend-avatar" style="width: 44px; height: 44px; font-size: 1.1rem;">
                    <?= strtoupper(substr($f['name'], 0, 1)) ?>
                </div>
                <div>
                    <h4 style="font-weight: 700; margin: 0; font-size: 1.05rem;"><?= e($f['name']) ?></h4>
                    <span style="font-size: 0.775rem; color: var(--text-muted);">
                        <?= e($f['phone'] ?: 'No phone added') ?>
                    </span>
                </div>
            </div>
            <span class="friend-status-badge status-<?= $f['status'] ?>">
                <?= ucfirst($f['status']) ?>
            </span>
        </div>
        
        <div style="background: var(--bg-surface-elevated); padding: 0.85rem 1rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
                <span style="color: var(--text-muted);">Remaining Due:</span>
                <span style="font-weight: 800; font-size: 1.1rem; color: <?= $f['remaining'] > 0 ? 'var(--warning-light)' : 'var(--success)' ?>;">
                    <?= formatINR($f['remaining']) ?>
                </span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted);">
                <span>Lent: <?= formatINR((float)$f['total_given']) ?></span>
                <span>Repaid: <?= formatINR((float)$f['total_repaid']) ?></span>
            </div>
        </div>
        
        <!-- Action buttons -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
            <button class="btn-primary-custom btn-sm-custom" style="flex: 1;" onclick="openQuickRepayModal(<?= $f['id'] ?>, '<?= e($f['name']) ?>', <?= $f['remaining'] ?>)">
                <i class="fas fa-check"></i> Repay
            </button>
            <?php if ($f['remaining'] > 0): ?>
            <form method="POST" action="" style="flex: 1;" onsubmit="return confirm('Settle entire remaining ₹<?= $f['remaining'] ?> for <?= e($f['name']) ?>?');">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="settle_up">
                <input type="hidden" name="friend_id" value="<?= $f['id'] ?>">
                <input type="hidden" name="remaining" value="<?= $f['remaining'] ?>">
                <button type="submit" class="btn-success-custom btn-sm-custom btn-full">
                    <i class="fas fa-handshake"></i> Settle Up
                </button>
            </form>
            <?php endif; ?>
            <form method="POST" action="" onsubmit="return confirm('Delete this friend and all history?');">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="action" value="delete_friend">
                <input type="hidden" name="friend_id" value="<?= $f['id'] ?>">
                <button type="submit" class="btn-table-action delete" title="Delete friend">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
        
        <!-- Mini Transactions History -->
        <div style="border-top: 1px solid var(--border-color); padding-top: 0.75rem;">
            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem;">
                Recent Transactions
            </div>
            <?php if (empty($txList)): ?>
            <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 0.5rem 0;">
                No transaction history.
            </div>
            <?php else: foreach (array_slice($txList, 0, 3) as $tx): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; padding: 0.35rem 0; border-bottom: 1px dashed var(--border-color);">
                <div>
                    <span style="font-weight: 600; color: <?= $tx['type'] === 'given' ? 'var(--danger-light)' : 'var(--success)' ?>;">
                        <?= $tx['type'] === 'given' ? 'Lent' : 'Repaid' ?>
                    </span>
                    <span style="font-size: 0.7rem; color: var(--text-muted); margin-left: 0.4rem;">
                        <?= date('d M Y', strtotime($tx['transaction_date'])) ?>
                    </span>
                </div>
                <div style="font-weight: 700; color: <?= $tx['type'] === 'given' ? 'var(--danger-light)' : 'var(--success)' ?>;">
                    <?= $tx['type'] === 'given' ? '-' : '+' ?><?= formatINR((float)$tx['amount']) ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<script>
function openAddFriendModal() {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_friend">
            
            <div class="form-group-custom">
                <label>Friend Name *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-user"></i>
                    <input type="text" name="name" placeholder="Friend full name" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Phone Number (optional)</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-phone"></i>
                    <input type="text" name="phone" placeholder="+91 98765 43210">
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Notes</label>
                <textarea name="notes" rows="2" placeholder="College buddy, colleague, etc."></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-user-plus"></i> Add Friend
            </button>
        </form>
    `;
    window.openModal('Add New Friend', html);
}

function openRecordTxModal() {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_transaction">
            
            <div class="form-group-custom">
                <label>Friend *</label>
                <select name="friend_id" required>
                    <?php foreach ($friends as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= e($f['name']) ?> (Due: <?= formatINR($f['remaining']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group-custom">
                <label>Transaction Type *</label>
                <select name="type" required>
                    <option value="given">Money Given / Lent (They owe you)</option>
                    <option value="repaid">Money Received / Repaid (Debt reduced)</option>
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
                <label>Date *</label>
                <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group-custom">
                <label>Description (optional)</label>
                <textarea name="description" rows="2" placeholder="Reason or note"></textarea>
            </div>
            
            <button type="submit" class="btn-primary-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-save"></i> Save Transaction
            </button>
        </form>
    `;
    window.openModal('Record Friend Transaction', html);
}

function openQuickRepayModal(friendId, friendName, remaining) {
    const html = `
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="action" value="add_transaction">
            <input type="hidden" name="friend_id" value="${friendId}">
            <input type="hidden" name="type" value="repaid">
            
            <p style="color: var(--text-secondary); margin-bottom: 1rem;">
                Recording repayment from <strong>${friendName}</strong>.<br>
                Current outstanding balance: <strong>${remaining > 0 ? '₹' + remaining : '₹0'}</strong>
            </p>
            
            <div class="form-group-custom">
                <label>Amount Received (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" step="0.01" min="0.01" max="${remaining > 0 ? remaining : ''}" name="amount" value="${remaining > 0 ? remaining : ''}" required>
                </div>
            </div>
            
            <div class="form-group-custom">
                <label>Date Received *</label>
                <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            
            <div class="form-group-custom">
                <label>Note (optional)</label>
                <input type="text" name="description" placeholder="GPay / PhonePe repayment">
            </div>
            
            <button type="submit" class="btn-success-custom btn-full" style="margin-top: 0.5rem;">
                <i class="fas fa-check"></i> Record Repayment
            </button>
        </form>
    `;
    window.openModal('Record Repayment from ' + friendName, html);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
