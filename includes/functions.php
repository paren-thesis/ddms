<?php
/**
 * HTU COMPSSA DDMS - Common Functions
 * Utility functions used throughout the application
 */

require_once '../config/config.php';
require_once '../config/database.php';

/**
 * Sanitize user input to prevent XSS
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a secure password hash
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password against hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate a unique receipt number
 */
function generateReceiptNumber() {
    return 'CSD' . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Redirect to another page
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Display error message
 */
function showError($message) {
    return "<div class='alert alert-danger'>$message</div>";
}

/**
 * Display success message
 */
function showSuccess($message) {
    return "<div class='alert alert-success'>$message</div>";
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'GHC ' . number_format($amount, 2);
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (10-15 digits, optional +)
 */
function isValidPhone($phone) {
    return preg_match('/^\+?[0-9]{10,15}$/', $phone);
}

/**
 * Validate name (Letters, spaces, hyphens, and apostrophes)
 */
function isValidName($name) {
    return !empty($name) && preg_match('/^[a-zA-Z\s\-\']+$/', $name);
}

/**
 * Get current academic year
 */
function getCurrentAcademicYear() {
    static $currentYearResult = null;
    if ($currentYearResult !== null) return $currentYearResult;

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT session_name FROM academic_sessions WHERE is_current = 1 LIMIT 1");
        $session = $stmt->fetch();
        if ($session) {
            $currentYearResult = $session['session_name'];
            return $currentYearResult;
        }
    } catch (Exception $e) {
        // Fallback below
    }

    $currentYear = date('Y');
    $previousYear = $currentYear - 1;
    $currentYearResult = "$previousYear/$currentYear";
    return $currentYearResult;
}

/**
 * Convert number to words (Core logic)
 */
function convertNumberToWords($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'forty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        1000000             => 'million'
    );

    if (!is_numeric($number)) return false;

    $string = $fraction = null;
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }

    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = (int)($number / 100);
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . convertNumberToWords($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convertNumberToWords($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= convertNumberToWords($remainder);
            }
            break;
    }

    if (null !== $fraction && is_numeric($fraction) && $fraction > 0) {
        $string .= ' and ' . convertNumberToWords($fraction) . ' Pesewas';
    }

    return $string;
}

/**
 * Convert number to words for receipt (Wrapper)
 */
function numberToWords($number) {
    if ($number < 0) return 'Negative ' . numberToWords(abs($number));
    
    $words = convertNumberToWords($number);
    if (strpos($words, 'Pesewas') === false) {
        $words .= ' Ghana Cedis';
    }
    
    return ucwords($words);
}

/**
 * Log user activity to the audit_logs table
 */
function logActivity($action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    try {
        $pdo = getDBConnection();
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $action,
            $table_name,
            $record_id,
            $old_values ? json_encode($old_values) : null,
            $new_values ? json_encode($new_values) : null,
            $ip_address,
            $user_agent
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}
?>