<?php
/**
 * Financial Calculation Engine
 * 
 * All core financial logic: monthly generation, EMI calculations,
 * savings projections, health scores, etc.
 */

require_once __DIR__ . '/calendar.php';
require_once __DIR__ . '/functions.php';

/**
 * Generate or update monthly financial record
 */
function generateMonthlyFinancialRecord(int $userId, int $year, int $month): array {
    $db = getDB();
    $settings = getUserSettings($userId);
    
    // Calculate working days
    $workingDays = getWorkingDays($year, $month, $settings);
    
    // Travel expense
    $travelExpense = $workingDays * (float)$settings['daily_travel_cost'];
    
    // Salary
    $salary = (float)$settings['salary'];
    
    // Additional income for this month
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM income 
         WHERE user_id = ? AND YEAR(income_date) = ? AND MONTH(income_date) = ? AND income_type != 'salary'"
    );
    $stmt->execute([$userId, $year, $month]);
    $additionalIncome = (float)$stmt->fetchColumn();
    
    $totalIncome = $salary + $additionalIncome;
    
    // Recharge expense for this month
    $rechargeExpense = calculateMonthlyRecharge($userId, $year, $month);
    
    // Recurring expenses
    $recurringExpense = calculateRecurringExpenses($userId, $year, $month);
    
    // Personal expenses
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS total FROM expenses 
         WHERE user_id = ? AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?'
    );
    $stmt->execute([$userId, $year, $month]);
    $personalExpense = (float)$stmt->fetchColumn();
    
    // Money given to friends this month
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM friend_transactions 
         WHERE user_id = ? AND type = 'given' 
         AND YEAR(transaction_date) = ? AND MONTH(transaction_date) = ?"
    );
    $stmt->execute([$userId, $year, $month]);
    $friendMoney = (float)$stmt->fetchColumn();
    
    // Active EMIs this month
    $emiTotal = calculateMonthlyEMI($userId, $year, $month);
    
    // Total expenses
    $totalExpenses = $travelExpense + $rechargeExpense + $recurringExpense + 
                     $personalExpense + $friendMoney + $emiTotal;
    
    // Savings
    $savings = $totalIncome - $totalExpenses;
    $savingsRate = ($totalIncome > 0) ? round(($savings / $totalIncome) * 100, 2) : 0;
    
    // Opening balance (closing of prev month)
    $openingBalance = getPreviousClosingBalance($userId, $year, $month);
    $closingBalance = $openingBalance + $savings;
    
    // Upsert monthly record
    $stmt = $db->prepare(
        'INSERT INTO monthly_financials 
         (user_id, year, month, working_days, salary, additional_income, travel_expense,
          recharge_expense, recurring_expense, personal_expense, friend_money, emi_total,
          total_income, total_expenses, savings, savings_rate, opening_balance, closing_balance, is_generated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
          working_days = VALUES(working_days), salary = VALUES(salary),
          additional_income = VALUES(additional_income), travel_expense = VALUES(travel_expense),
          recharge_expense = VALUES(recharge_expense), recurring_expense = VALUES(recurring_expense),
          personal_expense = VALUES(personal_expense), friend_money = VALUES(friend_money),
          emi_total = VALUES(emi_total), total_income = VALUES(total_income),
          total_expenses = VALUES(total_expenses), savings = VALUES(savings),
          savings_rate = VALUES(savings_rate), opening_balance = VALUES(opening_balance),
          closing_balance = VALUES(closing_balance), is_generated = 1,
          updated_at = NOW()'
    );
    $stmt->execute([
        $userId, $year, $month, $workingDays, $salary, $additionalIncome,
        $travelExpense, $rechargeExpense, $recurringExpense, $personalExpense,
        $friendMoney, $emiTotal, $totalIncome, $totalExpenses, $savings,
        $savingsRate, $openingBalance, $closingBalance
    ]);
    
    return [
        'working_days' => $workingDays,
        'salary' => $salary,
        'additional_income' => $additionalIncome,
        'total_income' => $totalIncome,
        'travel_expense' => $travelExpense,
        'recharge_expense' => $rechargeExpense,
        'recurring_expense' => $recurringExpense,
        'personal_expense' => $personalExpense,
        'friend_money' => $friendMoney,
        'emi_total' => $emiTotal,
        'total_expenses' => $totalExpenses,
        'savings' => $savings,
        'savings_rate' => $savingsRate,
        'opening_balance' => $openingBalance,
        'closing_balance' => $closingBalance
    ];
}

/**
 * Get previous month's closing balance
 */
function getPreviousClosingBalance(int $userId, int $year, int $month): float {
    $db = getDB();
    
    // Calculate previous month
    $prevMonth = $month - 1;
    $prevYear = $year;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }
    
    $stmt = $db->prepare(
        'SELECT closing_balance FROM monthly_financials 
         WHERE user_id = ? AND year = ? AND month = ?'
    );
    $stmt->execute([$userId, $prevYear, $prevMonth]);
    $result = $stmt->fetchColumn();
    
    if ($result !== false) return (float)$result;
    
    // If no previous record, use initial cash
    $settings = getUserSettings($userId);
    return (float)$settings['current_cash'];
}

/**
 * Calculate recharge expense for a specific month
 * Only include if recharge falls in this month
 */
function calculateMonthlyRecharge(int $userId, int $year, int $month): float {
    $db = getDB();
    $settings = getUserSettings($userId);
    
    $rechargeAmount = (float)$settings['recharge_amount'];
    $intervalDays = (int)$settings['recharge_interval_days'];
    $nextRecharge = $settings['next_recharge_date'];
    
    if (!$nextRecharge) return 0;
    
    $rechargeDate = new DateTime($nextRecharge);
    $monthStart = new DateTime("$year-$month-01");
    $monthEnd = new DateTime(date('Y-m-t', mktime(0, 0, 0, $month, 1, $year)));
    
    // Check if any recharge falls in this month
    // We need to check backward and forward from next_recharge_date
    $total = 0;
    
    // Check if next recharge date is in this month
    if ($rechargeDate >= $monthStart && $rechargeDate <= $monthEnd) {
        $total += $rechargeAmount;
    }
    
    // Also check for additional recharge cycles in this month
    // (unlikely for 90-day cycle but handles short intervals)
    $checkDate = clone $rechargeDate;
    while ($checkDate <= $monthEnd) {
        $checkDate->modify("+{$intervalDays} days");
        if ($checkDate >= $monthStart && $checkDate <= $monthEnd) {
            $total += $rechargeAmount;
        }
    }
    
    // Check backward (previous cycles)
    $checkDate = clone $rechargeDate;
    $checkDate->modify("-{$intervalDays} days");
    while ($checkDate >= $monthStart) {
        if ($checkDate >= $monthStart && $checkDate <= $monthEnd) {
            $total += $rechargeAmount;
        }
        $checkDate->modify("-{$intervalDays} days");
    }
    
    return $total;
}

/**
 * Calculate monthly equivalent of recharge (for planning)
 */
function getRechargeMonthlyEquivalent(float $amount, int $intervalDays): float {
    if ($intervalDays <= 0) return 0;
    return round(($amount / $intervalDays) * 30.44, 2); // Average days per month
}

/**
 * Calculate recurring expenses for a month
 */
function calculateRecurringExpenses(int $userId, int $year, int $month): float {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM recurring_expenses WHERE user_id = ? AND active = 1'
    );
    $stmt->execute([$userId]);
    $expenses = $stmt->fetchAll();
    
    $total = 0;
    foreach ($expenses as $exp) {
        switch ($exp['frequency']) {
            case 'monthly':
                $total += (float)$exp['amount'];
                break;
            case 'quarterly':
                // Include if this month matches quarterly cycle
                $startMonth = (int)date('n', strtotime($exp['start_date']));
                if (($month - $startMonth) % 3 === 0 || $month === $startMonth) {
                    $total += (float)$exp['amount'];
                }
                break;
            case 'yearly':
                $startMonth = (int)date('n', strtotime($exp['start_date']));
                if ($month === $startMonth) {
                    $total += (float)$exp['amount'];
                }
                break;
            case 'weekly':
                // Approximate: ~4.33 weeks per month
                $total += (float)$exp['amount'] * 4;
                break;
            case 'daily':
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $total += (float)$exp['amount'] * $daysInMonth;
                break;
        }
    }
    
    return $total;
}

/**
 * Calculate monthly EMI total
 */
function calculateMonthlyEMI(int $userId, int $year, int $month): float {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT SUM(emi_amount) AS total FROM loans 
         WHERE user_id = ? AND active = 1 
         AND start_date <= LAST_DAY(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'))"
    );
    $stmt->execute([$userId, $year, $month]);
    return (float)($stmt->fetchColumn() ?: 0);
}

/**
 * Calculate EMI using standard reducing-balance formula
 * EMI = P × r × (1+r)^n / ((1+r)^n - 1)
 */
function calculateEMI(float $principal, float $annualRate, int $tenureMonths): array {
    if ($tenureMonths <= 0 || $principal <= 0) {
        return ['emi' => 0, 'total_interest' => 0, 'total_payable' => 0];
    }
    
    if ($annualRate <= 0) {
        // Zero interest loan
        $emi = $principal / $tenureMonths;
        return [
            'emi' => round($emi, 2),
            'total_interest' => 0,
            'total_payable' => $principal
        ];
    }
    
    $r = ($annualRate / 100) / 12; // Monthly interest rate
    $n = $tenureMonths;
    
    $pow = pow(1 + $r, $n);
    $emi = $principal * $r * $pow / ($pow - 1);
    
    $totalPayable = $emi * $n;
    $totalInterest = $totalPayable - $principal;
    
    return [
        'emi' => round($emi, 2),
        'total_interest' => round($totalInterest, 2),
        'total_payable' => round($totalPayable, 2)
    ];
}

/**
 * Generate complete EMI amortization schedule
 */
function generateEMISchedule(float $principal, float $annualRate, int $tenureMonths, string $startDate): array {
    $emiData = calculateEMI($principal, $annualRate, $tenureMonths);
    $emi = $emiData['emi'];
    $r = ($annualRate / 100) / 12;
    
    $schedule = [];
    $balance = $principal;
    $date = new DateTime($startDate);
    
    for ($i = 1; $i <= $tenureMonths; $i++) {
        $interestComponent = round($balance * $r, 2);
        $principalComponent = round($emi - $interestComponent, 2);
        
        // Adjust last payment
        if ($i === $tenureMonths) {
            $principalComponent = round($balance, 2);
            $emi = $principalComponent + $interestComponent;
        }
        
        $balance -= $principalComponent;
        if ($balance < 0) $balance = 0;
        
        $schedule[] = [
            'month' => $i,
            'date' => $date->format('Y-m-d'),
            'month_name' => $date->format('M Y'),
            'opening_balance' => round($balance + $principalComponent, 2),
            'emi' => round($emi, 2),
            'principal' => $principalComponent,
            'interest' => $interestComponent,
            'closing_balance' => round($balance, 2)
        ];
        
        $date->modify('+1 month');
    }
    
    return [
        'summary' => $emiData,
        'schedule' => $schedule
    ];
}

/**
 * Calculate Financial Health Score (0-100)
 */
function calculateFinancialHealth(int $userId): array {
    $db = getDB();
    $settings = getUserSettings($userId);
    $salary = (float)$settings['salary'];
    
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('n');
    
    // Get current month's data
    $monthly = generateMonthlyFinancialRecord($userId, $currentYear, $currentMonth);
    
    $scores = [];
    
    // 1. Savings Rate (30 points) — target: >40% is excellent
    $savingsRate = $monthly['savings_rate'];
    $savingsScore = min(30, ($savingsRate / 40) * 30);
    $scores['savings_rate'] = [
        'score' => round($savingsScore, 1),
        'max' => 30,
        'value' => $savingsRate . '%',
        'label' => 'Savings Rate'
    ];
    
    // 2. Expense Ratio (20 points) — lower is better
    $expenseRatio = ($salary > 0) ? ($monthly['total_expenses'] / $salary) * 100 : 100;
    $expenseScore = max(0, 20 - ($expenseRatio / 100 * 20));
    $scores['expense_ratio'] = [
        'score' => round($expenseScore, 1),
        'max' => 20,
        'value' => round($expenseRatio, 1) . '%',
        'label' => 'Expense Control'
    ];
    
    // 3. EMI Burden (20 points) — lower EMI/salary ratio is better
    $emiBurden = ($salary > 0) ? ($monthly['emi_total'] / $salary) * 100 : 0;
    $emiScore = max(0, 20 - ($emiBurden / 50 * 20));
    $scores['emi_burden'] = [
        'score' => round($emiScore, 1),
        'max' => 20,
        'value' => round($emiBurden, 1) . '%',
        'label' => 'EMI Burden'
    ];
    
    // 4. Cash Reserve (15 points) — compare to 3 months expenses
    $avgExpenses = $monthly['total_expenses'];
    $targetReserve = $avgExpenses * 3;
    $totalCash = (float)$settings['current_cash'] + (float)$settings['bank_balance'] + (float)$settings['upi_balance'];
    $cashScore = ($targetReserve > 0) ? min(15, ($totalCash / $targetReserve) * 15) : 15;
    $scores['cash_reserve'] = [
        'score' => round($cashScore, 1),
        'max' => 15,
        'value' => formatINR($totalCash),
        'label' => 'Cash Reserve'
    ];
    
    // 5. Goal Progress (10 points)
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(saved_amount), 0) as saved, COALESCE(SUM(target_amount), 0) as target
         FROM financial_goals WHERE user_id = ? AND status = 'active'"
    );
    $stmt->execute([$userId]);
    $goals = $stmt->fetch();
    $goalProgress = ($goals['target'] > 0) ? ($goals['saved'] / $goals['target']) * 100 : 0;
    $goalScore = min(10, ($goalProgress / 100) * 10);
    $scores['goal_progress'] = [
        'score' => round($goalScore, 1),
        'max' => 10,
        'value' => round($goalProgress, 1) . '%',
        'label' => 'Goal Progress'
    ];
    
    // 6. Outstanding Money (5 points) — less outstanding is better
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN type='given' THEN amount ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN type IN('repaid','received') THEN amount ELSE 0 END), 0) AS outstanding
         FROM friend_transactions WHERE user_id = ?"
    );
    $stmt->execute([$userId]);
    $outstanding = max(0, (float)$stmt->fetchColumn());
    $outstandingRatio = ($salary > 0) ? ($outstanding / $salary) * 100 : 0;
    $outstandingScore = max(0, 5 - ($outstandingRatio / 30 * 5));
    $scores['outstanding_money'] = [
        'score' => round($outstandingScore, 1),
        'max' => 5,
        'value' => formatINR($outstanding),
        'label' => 'Outstanding Loans to Friends'
    ];
    
    // Total
    $totalScore = array_sum(array_column($scores, 'score'));
    $totalScore = min(100, max(0, round($totalScore)));
    
    // Status
    $status = 'Critical';
    $statusColor = '#ef4444';
    if ($totalScore >= 80) { $status = 'Excellent'; $statusColor = '#10b981'; }
    elseif ($totalScore >= 60) { $status = 'Good'; $statusColor = '#22d3ee'; }
    elseif ($totalScore >= 40) { $status = 'Fair'; $statusColor = '#f59e0b'; }
    elseif ($totalScore >= 20) { $status = 'Poor'; $statusColor = '#f97316'; }
    
    return [
        'score' => $totalScore,
        'status' => $status,
        'status_color' => $statusColor,
        'factors' => $scores,
        'monthly' => $monthly
    ];
}

/**
 * Get friend money summary
 */
function getFriendMoneySummary(int $userId): array {
    $db = getDB();
    
    $stmt = $db->prepare(
        "SELECT f.id, f.name,
                COALESCE(SUM(CASE WHEN ft.type = 'given' THEN ft.amount ELSE 0 END), 0) AS total_given,
                COALESCE(SUM(CASE WHEN ft.type IN ('repaid','received') THEN ft.amount ELSE 0 END), 0) AS total_repaid
         FROM friends f
         LEFT JOIN friend_transactions ft ON f.id = ft.friend_id
         WHERE f.user_id = ?
         GROUP BY f.id, f.name
         ORDER BY f.name"
    );
    $stmt->execute([$userId]);
    $friends = $stmt->fetchAll();
    
    $totalGiven = 0;
    $totalRepaid = 0;
    
    foreach ($friends as &$friend) {
        $friend['remaining'] = (float)$friend['total_given'] - (float)$friend['total_repaid'];
        $friend['status'] = 'pending';
        if ($friend['remaining'] <= 0) $friend['status'] = 'repaid';
        elseif ((float)$friend['total_repaid'] > 0) $friend['status'] = 'partial';
        
        $totalGiven += (float)$friend['total_given'];
        $totalRepaid += (float)$friend['total_repaid'];
    }
    
    return [
        'friends' => $friends,
        'total_given' => $totalGiven,
        'total_repaid' => $totalRepaid,
        'total_outstanding' => $totalGiven - $totalRepaid
    ];
}

/**
 * Get goal projections
 */
function getGoalProjections(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM financial_goals WHERE user_id = ? AND status = 'active' ORDER BY priority DESC, target_amount ASC"
    );
    $stmt->execute([$userId]);
    $goals = $stmt->fetchAll();
    
    $projections = [];
    foreach ($goals as $goal) {
        $remaining = (float)$goal['target_amount'] - (float)$goal['saved_amount'];
        $monthlyContribution = (float)$goal['monthly_contribution'];
        $percentage = calcPercentage((float)$goal['saved_amount'], (float)$goal['target_amount']);
        
        $monthsRequired = ($monthlyContribution > 0) ? ceil($remaining / $monthlyContribution) : 0;
        $estimatedDate = ($monthsRequired > 0) 
            ? date('F Y', strtotime("+{$monthsRequired} months")) 
            : 'Not set';
        
        // Required to finish by target date
        $requiredMonthly = 0;
        if ($goal['target_date']) {
            $now = new DateTime();
            $target = new DateTime($goal['target_date']);
            $monthsLeft = max(1, (int)$target->diff($now)->m + ($target->diff($now)->y * 12));
            if ($target > $now) {
                $requiredMonthly = ceil($remaining / $monthsLeft);
            }
        }
        
        $projections[] = [
            'id' => $goal['id'],
            'name' => $goal['name'],
            'target_amount' => (float)$goal['target_amount'],
            'saved_amount' => (float)$goal['saved_amount'],
            'remaining' => $remaining,
            'percentage' => $percentage,
            'monthly_contribution' => $monthlyContribution,
            'months_required' => $monthsRequired,
            'estimated_date' => $estimatedDate,
            'required_monthly' => $requiredMonthly,
            'weekly_savings' => round($monthlyContribution / 4.33, 2),
            'daily_savings' => round($monthlyContribution / 30.44, 2),
            'priority' => $goal['priority'],
            'status' => $goal['status'],
            'icon' => $goal['icon'],
            'color' => $goal['color'],
            'target_date' => $goal['target_date']
        ];
    }
    
    return $projections;
}

/**
 * Generate financial forecast
 */
function generateForecast(int $userId, int $months = 12): array {
    $settings = getUserSettings($userId);
    $salary = (float)$settings['salary'];
    
    $currentYear = (int)date('Y');
    $currentMonth = (int)date('n');
    
    // Get current month data
    $current = generateMonthlyFinancialRecord($userId, $currentYear, $currentMonth);
    
    $forecast = [];
    $balance = $current['closing_balance'];
    $monthlySavings = $current['savings'];
    
    for ($i = 1; $i <= $months; $i++) {
        $futureMonth = $currentMonth + $i;
        $futureYear = $currentYear;
        while ($futureMonth > 12) {
            $futureMonth -= 12;
            $futureYear++;
        }
        
        $workingDays = getWorkingDays($futureYear, $futureMonth, $settings);
        $travelExpense = $workingDays * (float)$settings['daily_travel_cost'];
        
        // Estimate expenses similar to current month pattern
        $estimatedExpenses = $travelExpense + $current['recurring_expense'] + 
                            $current['personal_expense'] + $current['emi_total'];
        
        $estimatedSavings = $salary - $estimatedExpenses;
        $balance += $estimatedSavings;
        
        $forecast[] = [
            'month' => $futureMonth,
            'year' => $futureYear,
            'month_name' => getMonthName($futureMonth) . ' ' . $futureYear,
            'working_days' => $workingDays,
            'income' => $salary,
            'expenses' => round($estimatedExpenses, 2),
            'savings' => round($estimatedSavings, 2),
            'balance' => round($balance, 2)
        ];
    }
    
    return $forecast;
}

/**
 * Get dashboard notifications/alerts
 */
function generateAlerts(int $userId): array {
    $db = getDB();
    $settings = getUserSettings($userId);
    $alerts = [];
    
    // Recharge alert
    if ($settings['next_recharge_date']) {
        $nextRecharge = new DateTime($settings['next_recharge_date']);
        $today = new DateTime();
        $daysRemaining = (int)$today->diff($nextRecharge)->format('%r%a');
        
        if ($daysRemaining <= 7 && $daysRemaining >= 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'fa-mobile-alt',
                'title' => 'Recharge Due Soon',
                'message' => "Your mobile recharge (₹{$settings['recharge_amount']}) is due in {$daysRemaining} days.",
                'action' => 'settings.php'
            ];
        } elseif ($daysRemaining < 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'fa-exclamation-triangle',
                'title' => 'Recharge Overdue',
                'message' => "Your mobile recharge was due " . abs($daysRemaining) . " days ago.",
                'action' => 'settings.php'
            ];
        }
    }
    
    // EMI alerts
    $stmt = $db->prepare('SELECT * FROM loans WHERE user_id = ? AND active = 1');
    $stmt->execute([$userId]);
    $loans = $stmt->fetchAll();
    foreach ($loans as $loan) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'fa-credit-card',
            'title' => 'EMI Due: ' . $loan['loan_name'],
            'message' => "Monthly EMI of " . formatINR((float)$loan['emi_amount']) . " is active.",
            'action' => 'emi.php'
        ];
    }
    
    // Goal progress alerts
    $goals = getGoalProjections($userId);
    foreach ($goals as $goal) {
        if ($goal['percentage'] >= 75 && $goal['percentage'] < 100) {
            $alerts[] = [
                'type' => 'success',
                'icon' => 'fa-trophy',
                'title' => $goal['name'] . ' Almost Complete!',
                'message' => "{$goal['percentage']}% saved. Only " . formatINR($goal['remaining']) . " remaining!",
                'action' => 'goals.php'
            ];
        }
    }
    
    // Friend money alert
    $friendSummary = getFriendMoneySummary($userId);
    if ($friendSummary['total_outstanding'] > 0) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'fa-users',
            'title' => 'Outstanding Friend Loans',
            'message' => formatINR($friendSummary['total_outstanding']) . " is still outstanding from friends.",
            'action' => 'friends.php'
        ];
    }
    
    return $alerts;
}