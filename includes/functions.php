<?php
/**
 * General Utility Functions
 */

/**
 * Format amount in Indian Rupee notation: ₹1,23,456.00
 */
function formatINR(float $amount, bool $showPaisa = false): string {
    $negative = $amount < 0;
    $amount = abs($amount);
    
    $decimal = '';
    if ($showPaisa) {
        $decimal = '.' . str_pad((int)(($amount - floor($amount)) * 100), 2, '0', STR_PAD_LEFT);
    }
    
    $amount = (int)floor($amount);
    $last3 = substr((string)$amount, -3);
    $rest = substr((string)$amount, 0, -3);
    
    if ($rest !== '' && $rest !== false) {
        $rest = preg_replace('/(\d)(?=(\d{2})+$)/', '$1,', $rest);
        $formatted = $rest . ',' . $last3;
    } else {
        $formatted = $last3;
    }
    
    return ($negative ? '-' : '') . '₹' . $formatted . $decimal;
}

/**
 * Format number in Indian notation without currency symbol
 */
function formatIndianNumber(float $number): string {
    $negative = $number < 0;
    $number = abs((int)floor($number));
    
    $last3 = substr((string)$number, -3);
    $rest = substr((string)$number, 0, -3);
    
    if ($rest !== '' && $rest !== false) {
        $rest = preg_replace('/(\d)(?=(\d{2})+$)/', '$1,', $rest);
        return ($negative ? '-' : '') . $rest . ',' . $last3;
    }
    return ($negative ? '-' : '') . $last3;
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output for HTML
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate positive decimal
 */
function isValidAmount($amount): bool {
    return is_numeric($amount) && (float)$amount >= 0;
}

/**
 * Validate date string (Y-m-d)
 */
function isValidDate(string $date): bool {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Get month name from number
 */
function getMonthName(int $month): string {
    return date('F', mktime(0, 0, 0, $month, 1));
}

/**
 * Get short month name
 */
function getShortMonthName(int $month): string {
    return date('M', mktime(0, 0, 0, $month, 1));
}

/**
 * Get user's financial settings
 */
function getUserSettings(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM financial_settings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Create default settings
        $stmt = $db->prepare(
            'INSERT INTO financial_settings (user_id) VALUES (?)'
        );
        $stmt->execute([$userId]);
        $stmt = $db->prepare('SELECT * FROM financial_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();
    }
    
    return $settings;
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get relative time string
 */
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

/**
 * Calculate percentage safely
 */
function calcPercentage(float $part, float $total): float {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 2);
}

/**
 * Get current financial year
 */
function getCurrentFinancialYear(): string {
    $month = (int)date('n');
    $year = (int)date('Y');
    if ($month >= 4) {
        return $year . '-' . ($year + 1);
    }
    return ($year - 1) . '-' . $year;
}