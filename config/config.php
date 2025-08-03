<?php
/**
 * Main Configuration
 * Construction Company Multi-Tenant SaaS Platform
 */

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

// Application Configuration
define('APP_NAME', 'Construction SaaS Platform');
define('APP_VERSION', '1.0.0');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');
define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? 'true');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Database Configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'construction_saas');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? '');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');

// Security Configuration
define('SESSION_SECRET', $_ENV['SESSION_SECRET'] ?? 'your-secret-key-change-this');
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'your-encryption-key-change-this');

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

// Multi-tenant Configuration
define('TRIAL_PERIOD_DAYS', 30);
define('DEFAULT_SUBSCRIPTION_PLAN', 1);

// Error Reporting
if (APP_DEBUG === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', APP_ENV === 'production' ? 1 : 0);
ini_set('session.use_strict_mode', 1);
session_name(SESSION_NAME);
session_start();

// Timezone
date_default_timezone_set('UTC');

// Basic Helper functions (legacy - use the enhanced versions below)
function formatCurrencyBasic($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function formatDateBasic($date) {
    return date(DATE_FORMAT, strtotime($date));
}

function formatDateTimeBasic($datetime) {
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

// Language Functions
function getCompanyLanguage($company_id = null) {
    global $conn;
    if (!$company_id) {
        $company_id = getCurrentCompanyId();
    }
    
    $stmt = $conn->prepare("
        SELECT l.* FROM languages l
        JOIN company_settings cs ON l.id = cs.default_language_id
        WHERE cs.company_id = ?
    ");
    $stmt->execute([$company_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['language_code' => 'en', 'direction' => 'ltr'];
}

function getTranslation($key, $company_id = null) {
    global $conn;
    if (!$company_id) {
        $company_id = getCurrentCompanyId();
    }
    
    $language = getCompanyLanguage($company_id);
    $language_id = $language['id'] ?? 1;
    
    $stmt = $conn->prepare("
        SELECT translation_value FROM language_translations 
        WHERE language_id = ? AND translation_key = ?
    ");
    $stmt->execute([$language_id, $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['translation_value'] : $key;
}

function __($key, $company_id = null) {
    return getTranslation($key, $company_id);
}

function getAvailableLanguages() {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM languages WHERE is_active = 1 ORDER BY language_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updateCompanyLanguage($company_id, $language_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE company_settings 
        SET default_language_id = ? 
        WHERE company_id = ?
    ");
    
    return $stmt->execute([$language_id, $company_id]);
}

function getLanguageDirection($company_id = null) {
    $language = getCompanyLanguage($company_id);
    return $language['direction'] ?? 'ltr';
}

function isRTL($company_id = null) {
    return getLanguageDirection($company_id) === 'rtl';
}

function getLanguageName($language_code) {
    global $conn;
    $stmt = $conn->prepare("SELECT language_name, language_name_native FROM languages WHERE language_code = ?");
    $stmt->execute([$language_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['language_name_native'] . ' (' . $result['language_name'] . ')' : $language_code;
}

function addLanguage($language_code, $language_name, $language_name_native, $direction = 'ltr') {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO languages (language_code, language_name, language_name_native, direction) 
        VALUES (?, ?, ?, ?)
    ");
    
    return $stmt->execute([$language_code, $language_name, $language_name_native, $direction]);
}

function addTranslation($language_id, $translation_key, $translation_value) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO language_translations (language_id, translation_key, translation_value) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE translation_value = VALUES(translation_value)
    ");
    
    return $stmt->execute([$language_id, $translation_key, $translation_value]);
}

function getTranslationsForLanguage($language_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT translation_key, translation_value 
        FROM language_translations 
        WHERE language_id = ? 
        ORDER BY translation_key
    ");
    $stmt->execute([$language_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMissingTranslations($language_id) {
    global $conn;
    
    // Get all translation keys from default language (English)
    $stmt = $conn->prepare("
        SELECT DISTINCT translation_key 
        FROM language_translations 
        WHERE language_id = 1
    ");
    $stmt->execute();
    $all_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get existing translations for the target language
    $stmt = $conn->prepare("
        SELECT translation_key 
        FROM language_translations 
        WHERE language_id = ?
    ");
    $stmt->execute([$language_id]);
    $existing_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Return missing keys
    return array_diff($all_keys, $existing_keys);
}
?>