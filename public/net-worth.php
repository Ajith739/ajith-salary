<?php
/**
 * NET WORTH — Assets vs Liabilities Command Center
 */
define('PAGE_TITLE', 'Net Worth');
define('PAGE_ID', 'networth');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();
$settings = getUserSettings($userId);

// ── 1. ASSETS CALCULATION ──
$cash = (float)$settings['current_cash'];
$bank = (float)$settings['bank_balance'];
$upi = (float)$settings['upi_balance'];

// Goals accumulated savings
$stmt = $db->prepare('SELECT COALESCE(SUM(saved_amount), 0) FROM financial_goals WHERE user_id = ?');
$stmt->execute([$userId]);
$goalSavings = (float)$stmt->fetchColumn();

// Money lent to friends (Receivables)
$friendSummary = getFriendMoneySummary($userId);
$friendReceivables = max(0, $friendSummary['total_outstanding']);

$totalAssets = $cash + $bank + $upi + $goalSavings + $friendReceivables;

// ── 2. LIABILITIES CALCULATION ──
$stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? AND active = 1');
$stmt->execute([$userId]);
$activeLoans = $stmt->fetchAll();
$totalLiabilities = array_sum(array_column($activeLoans, 'total_payable'));

// ── 3. NET WORTH ──
$netWorth = $totalAssets - $totalLiabilities;
$debtToAssetRatio = $totalAssets > 0 ? round(($totalLiabilities / $totalAssets) * 100, 1) : 0;

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Net Worth Overview</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Holistic calculation of liquid assets, goal reserves, receivables, and debts</p>
    </div>
    <div>
        <a href="settings.php" class="btn-secondary-custom">
            <i class="fas fa-edit"></i> Update Balances
        </a>
    </div>
</div>

<!-- ─── NET WORTH HIGHLIGHT HERO ─── -->
<div class="stat-card" data-animate style="padding: 2rem; margin-bottom: 2rem; background: linear-gradient(135deg, rgba(99, 102, 241, 0.15), rgba(16, 185, 129, 0.15)); border: 1px solid rgba(99, 102, 241, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; width: 100%;">
        <div>
            <span style="font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); font-weight: 600;">
                Calculated Total Net Worth
            </span>
            <div style="font-size: 2.75rem; font-weight: 900; color: <?= $netWorth >= 0 ? 'var(--text-primary)' : 'var(--danger)' ?>; line-height: 1.1; margin-top: 0.35rem;">
                <?= formatINR($netWorth) ?>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.4rem;">
                Total Assets (<?= formatINR($totalAssets) ?>) &minus; Total Liabilities (<?= formatINR($totalLiabilities) ?>)
            </div>
        </div>
        <div style="text-align: right;">
            <span class="badge-custom <?= $debtToAssetRatio > 50 ? 'status-overdue' : 'status-completed' ?>" style="font-size: 0.9rem; padding: 0.45rem 1rem;">
                Debt-to-Asset: <?= $debtToAssetRatio ?>%
            </span>
        </div>
    </div>
</div>

<!-- ─── SUMMARY CARDS ─── -->
<div class="overview-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
    <div class="stat-card stat-income" data-animate>
        <div class="stat-icon"><i class="fas fa-coins"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Assets</div>
            <div class="stat-value" data-count="<?= $totalAssets ?>"><?= formatINR($totalAssets) ?></div>
            <div class="stat-meta">Liquid + Savings + Receivables</div>
        </div>
    </div>
    <div class="stat-card stat-expense" data-animate>
        <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Liabilities</div>
            <div class="stat-value" data-count="<?= $totalLiabilities ?>"><?= formatINR($totalLiabilities) ?></div>
            <div class="stat-meta">Active loan balances</div>
        </div>
    </div>
    <div class="stat-card stat-balance" data-animate>
        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
        <div class="stat-content">
            <div class="stat-label">Liquid Reserves</div>
            <div class="stat-value" data-count="<?= $cash + $bank + $upi ?>"><?= formatINR($cash + $bank + $upi) ?></div>
            <div class="stat-meta">Cash + Bank + UPI</div>
        </div>
    </div>
    <div class="stat-card stat-friends" data-animate>
        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
        <div class="stat-content">
            <div class="stat-label">Friend Receivables</div>
            <div class="stat-value" data-count="<?= $friendReceivables ?>"><?= formatINR($friendReceivables) ?></div>
            <div class="stat-meta">Money owed to you</div>
        </div>
    </div>
</div>

<!-- ─── ASSETS & LIABILITIES BREAKDOWN ROW ─── -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.75rem; margin-bottom: 2rem;">
    <!-- Assets Card -->
    <div class="table-card" data-animate style="padding: 1.75rem;">
        <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <h3><i class="fas fa-plus-circle text-success"></i> Assets Portfolio</h3>
            <strong class="text-success"><?= formatINR($totalAssets) ?></strong>
        </div>
        <div class="breakdown-body">
            <div class="breakdown-row">
                <span class="bk-label"><i class="fas fa-money-bill-wave text-success"></i> Cash in Hand</span>
                <span class="bk-value"><?= formatINR($cash) ?></span>
            </div>
            <div class="breakdown-row">
                <span class="bk-label"><i class="fas fa-university text-info"></i> Bank Balance</span>
                <span class="bk-value"><?= formatINR($bank) ?></span>
            </div>
            <div class="breakdown-row">
                <span class="bk-label"><i class="fas fa-mobile-alt text-purple"></i> UPI Accounts</span>
                <span class="bk-value"><?= formatINR($upi) ?></span>
            </div>
            <div class="breakdown-row">
                <span class="bk-label"><i class="fas fa-bullseye text-primary"></i> Wishlist & Goal Reserves</span>
                <span class="bk-value"><?= formatINR($goalSavings) ?></span>
            </div>
            <div class="breakdown-row">
                <span class="bk-label"><i class="fas fa-user-friends text-warning"></i> Money Lent to Friends</span>
                <span class="bk-value"><?= formatINR($friendReceivables) ?></span>
            </div>
        </div>
    </div>

    <!-- Liabilities Card -->
    <div class="table-card" data-animate style="padding: 1.75rem;">
        <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <h3><i class="fas fa-minus-circle text-danger"></i> Active Liabilities</h3>
            <strong class="text-danger"><?= formatINR($totalLiabilities) ?></strong>
        </div>
        <div class="breakdown-body">
            <?php if (empty($activeLoans)): ?>
            <div style="text-align: center; padding: 2rem 0; color: var(--text-muted);">
                <i class="fas fa-check-circle text-success" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                You have zero active loan liabilities!
            </div>
            <?php else: foreach ($activeLoans as $loan): ?>
            <div class="breakdown-row">
                <span class="bk-label">
                    <i class="fas fa-credit-card text-danger"></i> <?= e($loan['loan_name']) ?> (EMI: <?= formatINR((float)$loan['emi_amount']) ?>)
                </span>
                <span class="bk-value text-danger">-<?= formatINR((float)$loan['total_payable']) ?></span>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
