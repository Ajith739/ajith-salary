-- =============================================
-- MyFinance Database Schema
-- Core PHP + MySQL Financial Management System
-- =============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+05:30";

CREATE DATABASE IF NOT EXISTS `myfinance` 
  DEFAULT CHARACTER SET utf8mb4 
  COLLATE utf8mb4_unicode_ci;

USE `myfinance`;

-- ─── USERS ───
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `theme` ENUM('dark','light') DEFAULT 'dark',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB;

-- ─── FINANCIAL SETTINGS ───
CREATE TABLE `financial_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `salary` DECIMAL(12,2) NOT NULL DEFAULT 18000.00,
  `salary_date` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `daily_travel_cost` DECIMAL(8,2) NOT NULL DEFAULT 40.00,
  `recharge_amount` DECIMAL(8,2) NOT NULL DEFAULT 349.00,
  `recharge_interval_days` INT UNSIGNED NOT NULL DEFAULT 90,
  `recharge_provider` VARCHAR(100) DEFAULT 'Jio',
  `next_recharge_date` DATE DEFAULT NULL,
  `current_cash` DECIMAL(12,2) NOT NULL DEFAULT 2000.00,
  `bank_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `upi_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sunday_holiday` TINYINT(1) NOT NULL DEFAULT 1,
  `second_saturday_holiday` TINYINT(1) NOT NULL DEFAULT 1,
  `fourth_saturday_holiday` TINYINT(1) NOT NULL DEFAULT 1,
  `emi_comfortable_pct` DECIMAL(5,2) DEFAULT 20.00,
  `emi_moderate_pct` DECIMAL(5,2) DEFAULT 30.00,
  `emi_high_pct` DECIMAL(5,2) DEFAULT 40.00,
  `emergency_fund_months` TINYINT UNSIGNED DEFAULT 6,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user` (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── INCOME ───
CREATE TABLE `income` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `income_type` ENUM('salary','freelance','bonus','interest','refund','gift','other') NOT NULL DEFAULT 'salary',
  `amount` DECIMAL(12,2) NOT NULL,
  `income_date` DATE NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_income_user_date` (`user_id`, `income_date`),
  CONSTRAINT `fk_income_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── EXPENSE CATEGORIES ───
CREATE TABLE `expense_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(50) NOT NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-receipt',
  `color` VARCHAR(20) DEFAULT '#6366f1',
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ─── EXPENSES ───
CREATE TABLE `expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'other',
  `title` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `payment_method` ENUM('cash','upi','bank','card','wallet','other') DEFAULT 'cash',
  `is_recurring` TINYINT(1) DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expense_user_date` (`user_id`, `expense_date`),
  KEY `idx_expense_category` (`category`),
  CONSTRAINT `fk_expense_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── RECURRING EXPENSES ───
CREATE TABLE `recurring_expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `category` VARCHAR(50) DEFAULT 'bills',
  `frequency` ENUM('daily','weekly','monthly','quarterly','yearly','custom') DEFAULT 'monthly',
  `custom_days` INT UNSIGNED DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `next_due_date` DATE DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_recurring_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── RECHARGE HISTORY ───
CREATE TABLE `recharge_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(100) DEFAULT 'Jio',
  `amount` DECIMAL(8,2) NOT NULL,
  `recharge_date` DATE NOT NULL,
  `next_recharge_date` DATE DEFAULT NULL,
  `interval_days` INT UNSIGNED DEFAULT 90,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_recharge_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── FRIENDS ───
CREATE TABLE `friends` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_friend_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── FRIEND TRANSACTIONS ───
CREATE TABLE `friend_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `friend_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('given','received','repaid') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ft_friend` FOREIGN KEY (`friend_id`) REFERENCES `friends`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ft_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── FINANCIAL GOALS ───
CREATE TABLE `financial_goals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `target_amount` DECIMAL(12,2) NOT NULL,
  `saved_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `monthly_contribution` DECIMAL(10,2) DEFAULT 0.00,
  `target_date` DATE DEFAULT NULL,
  `priority` ENUM('low','medium','high','critical') DEFAULT 'medium',
  `status` ENUM('active','paused','completed','cancelled') DEFAULT 'active',
  `icon` VARCHAR(50) DEFAULT 'fa-bullseye',
  `color` VARCHAR(20) DEFAULT '#22d3ee',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_goal_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── GOAL CONTRIBUTIONS ───
CREATE TABLE `goal_contributions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `goal_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `contribution_date` DATE NOT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_gc_goal` FOREIGN KEY (`goal_id`) REFERENCES `financial_goals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── LOANS ───
CREATE TABLE `loans` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `loan_name` VARCHAR(150) NOT NULL,
  `principal` DECIMAL(14,2) NOT NULL,
  `annual_interest_rate` DECIMAL(6,3) NOT NULL,
  `tenure_months` INT UNSIGNED NOT NULL,
  `processing_fee` DECIMAL(10,2) DEFAULT 0.00,
  `down_payment` DECIMAL(12,2) DEFAULT 0.00,
  `start_date` DATE NOT NULL,
  `emi_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_interest` DECIMAL(14,2) DEFAULT 0.00,
  `total_payable` DECIMAL(14,2) DEFAULT 0.00,
  `active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_loan_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── LOAN PAYMENTS ───
CREATE TABLE `loan_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `loan_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `month_number` INT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `principal_component` DECIMAL(10,2) NOT NULL,
  `interest_component` DECIMAL(10,2) NOT NULL,
  `remaining_balance` DECIMAL(14,2) NOT NULL,
  `status` ENUM('pending','paid','overdue') DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_lp_loan` FOREIGN KEY (`loan_id`) REFERENCES `loans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── MONTHLY FINANCIALS ───
CREATE TABLE `monthly_financials` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL,
  `working_days` TINYINT UNSIGNED DEFAULT 0,
  `salary` DECIMAL(12,2) DEFAULT 0.00,
  `additional_income` DECIMAL(12,2) DEFAULT 0.00,
  `travel_expense` DECIMAL(10,2) DEFAULT 0.00,
  `recharge_expense` DECIMAL(8,2) DEFAULT 0.00,
  `recurring_expense` DECIMAL(10,2) DEFAULT 0.00,
  `personal_expense` DECIMAL(12,2) DEFAULT 0.00,
  `friend_money` DECIMAL(12,2) DEFAULT 0.00,
  `emi_total` DECIMAL(12,2) DEFAULT 0.00,
  `other_expense` DECIMAL(12,2) DEFAULT 0.00,
  `total_income` DECIMAL(12,2) DEFAULT 0.00,
  `total_expenses` DECIMAL(12,2) DEFAULT 0.00,
  `savings` DECIMAL(12,2) DEFAULT 0.00,
  `savings_rate` DECIMAL(5,2) DEFAULT 0.00,
  `opening_balance` DECIMAL(14,2) DEFAULT 0.00,
  `closing_balance` DECIMAL(14,2) DEFAULT 0.00,
  `is_generated` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_year_month` (`user_id`, `year`, `month`),
  CONSTRAINT `fk_mf_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── TRANSACTIONS LEDGER ───
CREATE TABLE `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` ENUM('income','expense','transfer','lend','repayment','emi','savings','recharge') NOT NULL,
  `category` VARCHAR(50) DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `transaction_date` DATE NOT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `payment_method` VARCHAR(30) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tx_user_date` (`user_id`, `transaction_date`),
  KEY `idx_tx_type` (`type`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── WALLET ACCOUNTS ───
CREATE TABLE `wallet_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('cash','bank','upi','wallet','other') NOT NULL,
  `balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `icon` VARCHAR(50) DEFAULT 'fa-wallet',
  `color` VARCHAR(20) DEFAULT '#22d3ee',
  `active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── NOTIFICATIONS ───
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','warning','success','danger') DEFAULT 'info',
  `icon` VARCHAR(50) DEFAULT 'fa-bell',
  `is_read` TINYINT(1) DEFAULT 0,
  `action_url` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─── DEFAULT EXPENSE CATEGORIES ───
INSERT INTO `expense_categories` (`name`, `icon`, `color`, `is_default`) VALUES
('Food', 'fa-utensils', '#f59e0b', 1),
('Travel', 'fa-bus', '#3b82f6', 1),
('Shopping', 'fa-shopping-bag', '#ec4899', 1),
('Entertainment', 'fa-gamepad', '#8b5cf6', 1),
('Gaming', 'fa-dice', '#ef4444', 1),
('Bills', 'fa-file-invoice', '#14b8a6', 1),
('Health', 'fa-heartbeat', '#f43f5e', 1),
('Education', 'fa-graduation-cap', '#6366f1', 1),
('Family', 'fa-users', '#0ea5e9', 1),
('Recharge', 'fa-mobile-alt', '#10b981', 1),
('Subscriptions', 'fa-tv', '#a855f7', 1),
('Insurance', 'fa-shield-alt', '#64748b', 1),
('Rent', 'fa-home', '#f97316', 1),
('Other', 'fa-receipt', '#94a3b8', 1);

COMMIT;