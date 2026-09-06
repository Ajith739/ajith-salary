<?php
/**
 * SETTINGS — Financial Configuration, Wallet Balances & User Profile
 */
define('PAGE_TITLE', 'Settings');
define('PAGE_ID', 'settings');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/finance.php';

startSecureSession();
requireAuth();

$userId = getCurrentUserId();
$db = getDB();
$currentUser = getCurrentUser();

$successMsg = '';
$errorMsg = '';

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRF()) {
        $errorMsg = 'Security token expired. Please try again.';
    } else {
        $section = $_POST['section'] ?? '';
        
        if ($section === 'financial_settings') {
            $salary = (float)($_POST['salary'] ?? 18000);
            $salaryDate = max(1, min(31, (int)($_POST['salary_date'] ?? 1)));
            $dailyTravel = (float)($_POST['daily_travel_cost'] ?? 40);
            $rechargeAmount = (float)($_POST['recharge_amount'] ?? 349);
            $rechargeInterval = (int)($_POST['recharge_interval_days'] ?? 90);
            $rechargeProvider = sanitize($_POST['recharge_provider'] ?? 'Jio');
            $nextRechargeDate = !empty($_POST['next_recharge_date']) ? sanitize($_POST['next_recharge_date']) : null;
            
            $sundayHoliday = isset($_POST['sunday_holiday']) ? 1 : 0;
            $secondSatHoliday = isset($_POST['second_saturday_holiday']) ? 1 : 0;
            $fourthSatHoliday = isset($_POST['fourth_saturday_holiday']) ? 1 : 0;
            
            $stmt = $db->prepare(
                'UPDATE financial_settings SET
                    salary = ?, salary_date = ?, daily_travel_cost = ?,
                    recharge_amount = ?, recharge_interval_days = ?, recharge_provider = ?,
                    next_recharge_date = ?, sunday_holiday = ?, second_saturday_holiday = ?,
                    fourth_saturday_holiday = ?, updated_at = NOW()
                 WHERE user_id = ?'
            );
            $stmt->execute([
                $salary, $salaryDate, $dailyTravel, $rechargeAmount,
                $rechargeInterval, $rechargeProvider, $nextRechargeDate,
                $sundayHoliday, $secondSatHoliday, $fourthSatHoliday, $userId
            ]);
            
            // Re-generate current monthly record
            generateMonthlyFinancialRecord($userId, (int)date('Y'), (int)date('n'));
            $successMsg = 'Financial rules & salary settings updated!';
            
        } elseif ($section === 'wallet_balances') {
            $cash = (float)($_POST['current_cash'] ?? 0);
            $bank = (float)($_POST['bank_balance'] ?? 0);
            $upi = (float)($_POST['upi_balance'] ?? 0);
            
            $stmt = $db->prepare(
                'UPDATE financial_settings SET current_cash = ?, bank_balance = ?, upi_balance = ? WHERE user_id = ?'
            );
            $stmt->execute([$cash, $bank, $upi, $userId]);
            
            // Sync wallet_accounts table
            $stmt = $db->prepare("UPDATE wallet_accounts SET balance = ? WHERE user_id = ? AND type = 'cash'");
            $stmt->execute([$cash, $userId]);
            $stmt = $db->prepare("UPDATE wallet_accounts SET balance = ? WHERE user_id = ? AND type = 'bank'");
            $stmt->execute([$bank, $userId]);
            $stmt = $db->prepare("UPDATE wallet_accounts SET balance = ? WHERE user_id = ? AND type = 'upi'");
            $stmt->execute([$upi, $userId]);
            
            $successMsg = 'Current account balances saved!';
            
        } elseif ($section === 'security') {
            $currentPass = $_POST['current_password'] ?? '';
            $newPass = $_POST['new_password'] ?? '';
            $confirmPass = $_POST['confirm_password'] ?? '';
            
            $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $storedHash = $stmt->fetchColumn();
            
            if (!password_verify($currentPass, $storedHash)) {
                $errorMsg = 'Incorrect current password.';
            } elseif (strlen($newPass) < 6) {
                $errorMsg = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirmPass) {
                $errorMsg = 'New passwords do not match.';
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([$newHash, $userId]);
                $successMsg = 'Password updated successfully!';
            }
        }
    }
}

// Reload current settings
$settings = getUserSettings($userId);

include __DIR__ . '/../includes/header.php';
?>

<!-- ─── PAGE HEADER ─── -->
<div class="page-header-actions">
    <div>
        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;">Configuration & Settings</h2>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0;">Manage your base financial figures, travel allowance, and account security</p>
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

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.75rem;">
    <!-- ─── 1. BASE FINANCIAL RULES ─── -->
    <div class="table-card" data-animate style="padding: 1.75rem;">
        <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <h3><i class="fas fa-wallet text-info"></i> Salary & Fixed Allowances</h3>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="section" value="financial_settings">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Monthly Salary (₹) *</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" step="0.01" name="salary" value="<?= (float)$settings['salary'] ?>" required>
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Salary Pay Date (Day)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-calendar-day"></i>
                        <input type="number" min="1" max="31" name="salary_date" value="<?= (int)$settings['salary_date'] ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-group-custom">
                <label>Daily Commute / Travel Cost (₹) *</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-bus"></i>
                    <input type="number" step="0.01" name="daily_travel_cost" value="<?= (float)$settings['daily_travel_cost'] ?>" required>
                </div>
                <small style="color: var(--text-muted); font-size: 0.75rem;">Multiplied automatically by working days each month.</small>
            </div>

            <div class="breakdown-divider" style="margin: 1.25rem 0;"></div>

            <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.85rem; color: var(--text-primary);">
                Mobile Recharge Plan
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Plan Cost (₹)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-rupee-sign"></i>
                        <input type="number" step="0.01" name="recharge_amount" value="<?= (float)$settings['recharge_amount'] ?>">
                    </div>
                </div>
                <div class="form-group-custom">
                    <label>Cycle Days</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-history"></i>
                        <input type="number" name="recharge_interval_days" value="<?= (int)$settings['recharge_interval_days'] ?>">
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group-custom">
                    <label>Provider</label>
                    <input type="text" name="recharge_provider" value="<?= e($settings['recharge_provider']) ?>">
                </div>
                <div class="form-group-custom">
                    <label>Next Due Date</label>
                    <input type="date" name="next_recharge_date" value="<?= e($settings['next_recharge_date']) ?>">
                </div>
            </div>

            <div class="breakdown-divider" style="margin: 1.25rem 0;"></div>

            <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.85rem; color: var(--text-primary);">
                Work Week & Holiday Configuration
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1.5rem; font-size: 0.875rem;">
                <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                    <input type="checkbox" name="sunday_holiday" <?= $settings['sunday_holiday'] ? 'checked' : '' ?> style="accent-color: var(--primary);">
                    <span>All Sundays are holidays</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                    <input type="checkbox" name="second_saturday_holiday" <?= $settings['second_saturday_holiday'] ? 'checked' : '' ?> style="accent-color: var(--primary);">
                    <span>2nd Saturday of each month is a holiday</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer;">
                    <input type="checkbox" name="fourth_saturday_holiday" <?= $settings['fourth_saturday_holiday'] ? 'checked' : '' ?> style="accent-color: var(--primary);">
                    <span>4th Saturday of each month is a holiday</span>
                </label>
            </div>

            <button type="submit" class="btn-primary-custom btn-full">
                <i class="fas fa-save"></i> Save Financial Rules
            </button>
        </form>
    </div>

    <!-- ─── 2. WALLET BALANCES & SECURITY ─── -->
    <div style="display: flex; flex-direction: column; gap: 1.75rem;">
        <!-- Balances Card -->
        <div class="table-card" data-animate style="padding: 1.75rem;">
            <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                <h3><i class="fas fa-coins text-warning"></i> Current Liquid Balances</h3>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="section" value="wallet_balances">

                <div class="form-group-custom">
                    <label>Cash in Hand (₹)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-money-bill-wave text-success"></i>
                        <input type="number" step="0.01" name="current_cash" value="<?= (float)$settings['current_cash'] ?>" required>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>Bank Account Balance (₹)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-university text-info"></i>
                        <input type="number" step="0.01" name="bank_balance" value="<?= (float)$settings['bank_balance'] ?>" required>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>UPI / Digital Wallets (₹)</label>
                    <div class="input-icon-wrap">
                        <i class="fas fa-mobile-alt text-purple"></i>
                        <input type="number" step="0.01" name="upi_balance" value="<?= (float)$settings['upi_balance'] ?>" required>
                    </div>
                </div>

                <button type="submit" class="btn-secondary-custom btn-full">
                    <i class="fas fa-check"></i> Update Balances
                </button>
            </form>
        </div>

        <!-- Security / Password Card -->
        <div class="table-card" data-animate style="padding: 1.75rem;">
            <div class="chart-header" style="margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
                <h3><i class="fas fa-lock text-danger"></i> Password & Security</h3>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="section" value="security">

                <div class="form-group-custom">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group-custom">
                        <label>New Password</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group-custom">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                </div>

                <button type="submit" class="btn-danger-custom btn-full">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
