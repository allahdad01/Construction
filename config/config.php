<?php
/**
 * Main Configuration
 * Construction Company Multi-Tenant SaaS Platform
 */

// Application settings
define('APP_NAME', 'Construction Company SaaS');
define('APP_VERSION', '2.0.0');
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
define('DEFAULT_LEAVE_DAYS_PER_YEAR', 20);

// Salary calculation settings
define('SALARY_CALCULATION_DAYS', 30); // Company standard 30-day month

// Multi-tenant settings
define('TRIAL_PERIOD_DAYS', 14);
define('DEFAULT_SUBSCRIPTION_PLAN', 'basic');

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

function calculateLeaveDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    return $interval->days + 1; // Include both start and end dates
}

// Multi-tenant authentication functions
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCurrentCompany() {
    $user = getCurrentUser();
    if (!$user || !$user['company_id']) {
        return null;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$user['company_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCurrentCompanyId() {
    $user = getCurrentUser();
    return $user ? $user['company_id'] : null;
}

function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

function hasAnyRole($roles) {
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function requireAnyRole($roles) {
    requireAuth();
    if (!hasAnyRole($roles)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function isSuperAdmin() {
    return hasRole('super_admin');
}

function isCompanyAdmin() {
    return hasRole('company_admin');
}

function isEmployee() {
    return hasAnyRole(['driver', 'driver_assistant']);
}

function isRenter() {
    return hasAnyRole(['parking_user', 'area_renter', 'container_renter']);
}

// Company subscription functions
function isCompanyActive() {
    $company = getCurrentCompany();
    if (!$company) {
        return false;
    }
    
    return $company['subscription_status'] === 'active' || 
           ($company['subscription_status'] === 'trial' && 
            strtotime($company['trial_ends_at']) > time());
}

function requireActiveCompany() {
    requireAuth();
    if (!isCompanyActive()) {
        header('Location: subscription-expired.php');
        exit();
    }
}

function getCompanyLimits() {
    $company = getCurrentCompany();
    if (!$company) {
        return null;
    }
    
    return [
        'max_employees' => $company['max_employees'],
        'max_machines' => $company['max_machines'],
        'max_projects' => $company['max_projects']
    ];
}

function checkCompanyLimit($type, $current_count) {
    $limits = getCompanyLimits();
    if (!$limits) {
        return false;
    }
    
    $limit_key = 'max_' . $type;
    return isset($limits[$limit_key]) && $current_count < $limits[$limit_key];
}

// Employee leave management functions
function isEmployeeOnLeave($employeeId) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM employees 
        WHERE id = ? AND company_id = ? AND status = 'on_leave'
        AND leave_start_date <= CURRENT_DATE() 
        AND leave_end_date >= CURRENT_DATE()
    ");
    $stmt->execute([$employeeId, getCurrentCompanyId()]);
    return $stmt->fetch() !== false;
}

function calculateEmployeeWorkingDays($employeeId, $startDate, $endDate) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as working_days 
        FROM employee_attendance 
        WHERE employee_id = ? AND company_id = ? 
        AND date BETWEEN ? AND ? 
        AND status = 'present'
    ");
    $stmt->execute([$employeeId, getCurrentCompanyId(), $startDate, $endDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['working_days'] ?? 0;
}

function calculateEmployeeLeaveDays($employeeId, $startDate, $endDate) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as leave_days 
        FROM employee_attendance 
        WHERE employee_id = ? AND company_id = ? 
        AND date BETWEEN ? AND ? 
        AND status = 'leave'
    ");
    $stmt->execute([$employeeId, getCurrentCompanyId(), $startDate, $endDate]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['leave_days'] ?? 0;
}

// User dashboard functions
function getUserDashboardData($userId) {
    $db = new Database();
    $conn = $db->getConnection();
    $companyId = getCurrentCompanyId();
    
    $user = getCurrentUser();
    if (!$user) {
        return null;
    }
    
    $data = [
        'user' => $user,
        'payments' => [],
        'rentals' => [],
        'salary_info' => null
    ];
    
    // Get user payments
    $stmt = $conn->prepare("
        SELECT * FROM user_payments 
        WHERE user_id = ? AND company_id = ? 
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmt->execute([$userId, $companyId]);
    $data['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user rentals
    if (in_array($user['role'], ['parking_user', 'area_renter', 'container_renter'])) {
        $stmt = $conn->prepare("
            SELECT * FROM parking_rentals 
            WHERE user_id = ? AND company_id = ? 
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->execute([$userId, $companyId]);
        $data['rentals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get salary information for employees
    if (in_array($user['role'], ['driver', 'driver_assistant'])) {
        $stmt = $conn->prepare("
            SELECT e.*, sp.* FROM employees e
            LEFT JOIN salary_payments sp ON e.id = sp.employee_id
            WHERE e.user_id = ? AND e.company_id = ?
            ORDER BY sp.payment_month DESC, sp.payment_year DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $companyId]);
        $data['salary_info'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $data;
}

// Utility functions
function getSubscriptionPlans() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSystemSetting($key, $default = null) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return $default;
    }
    
    switch ($result['setting_type']) {
        case 'integer':
            return (int)$result['setting_value'];
        case 'boolean':
            return (bool)$result['setting_value'];
        case 'json':
            return json_decode($result['setting_value'], true);
        default:
            return $result['setting_value'];
    }
}

function setSystemSetting($key, $value, $type = 'string') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, setting_type) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    return $stmt->execute([$key, $value, $type]);
}

// Currency and Date Format Functions
function getCompanyCurrency($company_id = null) {
    global $conn;
    if (!$company_id) {
        $company_id = getCurrentCompanyId();
    }
    
    $stmt = $conn->prepare("
        SELECT c.* FROM currencies c
        JOIN company_settings cs ON c.id = cs.default_currency_id
        WHERE cs.company_id = ?
    ");
    $stmt->execute([$company_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['currency_code' => 'USD', 'currency_symbol' => '$', 'exchange_rate_to_usd' => 1.0000];
}

function getCompanyDateFormat($company_id = null) {
    global $conn;
    if (!$company_id) {
        $company_id = getCurrentCompanyId();
    }
    
    $stmt = $conn->prepare("
        SELECT df.* FROM date_formats df
        JOIN company_settings cs ON df.id = cs.default_date_format_id
        WHERE cs.company_id = ?
    ");
    $stmt->execute([$company_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['format_code' => 'gregorian', 'format_pattern' => 'Y-m-d'];
}

function formatCurrency($amount, $currency_id = null, $company_id = null) {
    if ($amount === null || $amount === '') {
        return '';
    }
    
    $currency = getCompanyCurrency($company_id);
    $symbol = $currency['currency_symbol'];
    $formatted = number_format($amount, 2);
    
    // Handle different currency symbol positions
    if ($currency['currency_code'] === 'USD' || $currency['currency_code'] === 'CAD' || $currency['currency_code'] === 'AUD') {
        return $symbol . $formatted;
    } elseif ($currency['currency_code'] === 'EUR' || $currency['currency_code'] === 'GBP') {
        return $formatted . ' ' . $symbol;
    } else {
        return $formatted . ' ' . $symbol;
    }
}

function formatDate($date, $company_id = null) {
    if (!$date) {
        return '';
    }
    
    $dateFormat = getCompanyDateFormat($company_id);
    $pattern = $dateFormat['format_pattern'];
    
    // Convert to DateTime object
    $dateObj = new DateTime($date);
    
    // Handle Shamsi date conversion
    if ($dateFormat['format_code'] === 'shamsi') {
        return convertToShamsi($dateObj);
    }
    
    return $dateObj->format($pattern);
}

function convertToShamsi($dateObj) {
    // Simple Shamsi conversion (for demonstration)
    // In production, you might want to use a proper Persian calendar library
    $year = (int)$dateObj->format('Y');
    $month = (int)$dateObj->format('m');
    $day = (int)$dateObj->format('d');
    
    // Basic conversion (this is simplified - for production use a proper library)
    $shamsiYear = $year - 621;
    $shamsiMonth = $month;
    $shamsiDay = $day;
    
    return sprintf('%04d/%02d/%02d', $shamsiYear, $shamsiMonth, $shamsiDay);
}

function convertFromShamsi($shamsiDate) {
    // Convert Shamsi date back to Gregorian
    // This is simplified - for production use a proper Persian calendar library
    $parts = explode('/', $shamsiDate);
    if (count($parts) === 3) {
        $shamsiYear = (int)$parts[0];
        $shamsiMonth = (int)$parts[1];
        $shamsiDay = (int)$parts[2];
        
        $gregorianYear = $shamsiYear + 621;
        $gregorianMonth = $shamsiMonth;
        $gregorianDay = $shamsiDay;
        
        return sprintf('%04d-%02d-%02d', $gregorianYear, $gregorianMonth, $gregorianDay);
    }
    
    return $shamsiDate; // Return as is if conversion fails
}

function getAvailableCurrencies() {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_code");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAvailableDateFormats() {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM date_formats WHERE is_active = 1 ORDER BY format_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateCompanySettings($company_id, $currency_id, $date_format_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO company_settings (company_id, default_currency_id, default_date_format_id) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        default_currency_id = VALUES(default_currency_id),
        default_date_format_id = VALUES(default_date_format_id)
    ");
    
    return $stmt->execute([$company_id, $currency_id, $date_format_id]);
}

function convertCurrency($amount, $from_currency_id, $to_currency_id) {
    global $conn;
    
    if ($from_currency_id === $to_currency_id) {
        return $amount;
    }
    
    // Get exchange rates
    $stmt = $conn->prepare("SELECT exchange_rate_to_usd FROM currencies WHERE id = ?");
    $stmt->execute([$from_currency_id]);
    $from_rate = $stmt->fetch(PDO::FETCH_ASSOC)['exchange_rate_to_usd'];
    
    $stmt->execute([$to_currency_id]);
    $to_rate = $stmt->fetch(PDO::FETCH_ASSOC)['exchange_rate_to_usd'];
    
    // Convert to USD first, then to target currency
    $usd_amount = $amount / $from_rate;
    return $usd_amount * $to_rate;
}
?>