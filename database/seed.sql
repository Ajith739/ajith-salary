-- =============================================
-- MyFinance Seed Data
-- Demo account with default financial data
-- =============================================
USE `myfinance`;
START TRANSACTION;

-- Demo user (password: demo123)
INSERT INTO `users` (`id`,`name`,`email`,`password_hash`) VALUES 
(1, 'Demo User', 'demo@myfinance.app', 
 '$2y$12$57KqB4MVRRU5UMjqdMbEfux/uX2BePAThVSf/tGb10774lQsq/z2C');

-- Financial settings
INSERT INTO `financial_settings` 
(`user_id`,`salary`,`salary_date`,`daily_travel_cost`,`recharge_amount`,
 `recharge_interval_days`,`recharge_provider`,`next_recharge_date`,
 `current_cash`,`bank_balance`,`upi_balance`) 
VALUES 
(1, 18000.00, 1, 40.00, 349.00, 90, 'Jio', 
 DATE_ADD(CURDATE(), INTERVAL 45 DAY),
 2000.00, 0.00, 0.00);

-- Wallet accounts
INSERT INTO `wallet_accounts` (`user_id`,`name`,`type`,`balance`,`icon`,`color`) VALUES
(1, 'Cash in Hand', 'cash', 2000.00, 'fa-money-bill-wave', '#10b981'),
(1, 'Bank Account', 'bank', 0.00, 'fa-university', '#3b82f6'),
(1, 'UPI', 'upi', 0.00, 'fa-mobile-alt', '#8b5cf6');

-- Friends
INSERT INTO `friends` (`id`,`user_id`,`name`) VALUES
(1, 1, 'Prabin'),
(2, 1, 'Pragel'),
(3, 1, 'Ajith');

-- Friend transactions (money given)
INSERT INTO `friend_transactions` (`friend_id`,`user_id`,`type`,`amount`,`transaction_date`,`description`) VALUES
(1, 1, 'given', 1500.00, CURDATE(), 'Lent money to Prabin'),
(2, 1, 'given', 2000.00, CURDATE(), 'Lent money to Pragel'),
(3, 1, 'given', 1370.00, CURDATE(), 'Lent money to Ajith');

-- Financial goals / Wishlist
INSERT INTO `financial_goals` 
(`user_id`,`name`,`target_amount`,`saved_amount`,`monthly_contribution`,
 `priority`,`status`,`icon`,`color`,`notes`) VALUES
(1, 'iPad', 60000.00, 0.00, 3000.00, 'high', 'active', 'fa-tablet-alt', '#3b82f6', 'Apple iPad for productivity'),
(1, 'Washing Machine', 30000.00, 0.00, 2000.00, 'medium', 'active', 'fa-tshirt', '#10b981', 'Semi-automatic washing machine'),
(1, 'Water Heater', 10000.00, 0.00, 1500.00, 'high', 'active', 'fa-fire', '#f59e0b', 'Water heater for bathing');

-- Existing personal expense: BGMI Game
INSERT INTO `expenses` 
(`user_id`,`category`,`title`,`amount`,`expense_date`,`payment_method`,`notes`) VALUES
(1, 'Gaming', 'BGMI Game', 450.00, CURDATE(), 'upi', 'BGMI UC purchase');

COMMIT;