<?php
/**
 * Main Configuration
 * Construction Company SaaS Platform
 */

// Application settings
define('APP_NAME', 'Construction Company SaaS');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost');

// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'construction_saas');
define('DB_USER', 'root');
define('DB_PASS', '');

// Session settings
define('SESSION_NAME', 'construction_saas_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// File upload settings
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Pagination settings
define('ITEMS_PER_PAGE', 20);

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Currency settings
define('CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');

// Company settings
define('COMPANY_NAME', 'Construction Company Ltd.');
define('COMPANY_ADDRESS', '123 Construction St, City, State 12345');
define('COMPANY_PHONE', '+1 (555) 123-4567');
define('COMPANY_EMAIL', 'info@constructioncompany.com');

// Working hours settings
define('DEFAULT_WORKING_HOURS_PER_DAY', 9);
define('DEFAULT_WORKING_DAYS_PER_MONTH', 30);

// Salary calculation settings
define('SALARY_CALCULATION_DAYS', 30); // Company standard 30-day month

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_name(SESSION_NAME);
session_start();

// Set timezone
date_default_timezone_set('UTC');

// Helper functions
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function formatDate($date) {
    return date(DATE_FORMAT, strtotime($date));
}

function formatDateTime($datetime) {
    return date(DATETIME_FORMAT, strtotime($datetime));
}

function generateCode($prefix, $length = 8) {
    return $prefix . strtoupper(substr(md5(uniqid()), 0, $length));
}

function calculateDailyRate($monthlyAmount) {
    return $monthlyAmount / SALARY_CALCULATION_DAYS;
}

function calculateWorkingDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    return $interval->days + 1; // Include both start and end dates
}

function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        header('Location: unauthorized.php');
        exit();
    }
}
?>