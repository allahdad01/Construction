<?php
/**
 * Comprehensive Multi-Currency Helper System
 */

/**
 * Get all available currencies with exchange rates
 * @return array Currencies with rates
 */
function getAvailableCurrenciesWithRates() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}





/**
 * Format currency amount with symbol
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @param bool $show_symbol Whether to show currency symbol
 * @return string Formatted amount
 */
function formatCurrencyAmount($amount, $currency, $show_symbol = true) {
    $symbols = [
        'USD' => '$',
        'AFN' => '؋',
        'EUR' => '€',
        'GBP' => '£'
    ];
    
    $symbol = $show_symbol ? ($symbols[$currency] ?? $currency) : '';
    $formatted = number_format($amount, 2);
    
    return $symbol . $formatted;
}

/**
 * Get company currency with fallback
 * @param int $company_id Company ID
 * @return string Currency code
 */
function getCompanyCurrencyCode($company_id = null) {
    if (!$company_id) {
        $company_id = getCurrentCompanyId();
    }
    
    if (!$company_id) {
        return 'USD'; // Default for public pages
    }
    
    global $conn;
    $stmt = $conn->prepare("
        SELECT cs.setting_value 
        FROM company_settings cs 
        WHERE cs.company_id = ? AND cs.setting_key = 'default_currency_id'
    ");
    $stmt->execute([$company_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Get currency code from currency_id
        $stmt = $conn->prepare("SELECT currency_code FROM currencies WHERE id = ?");
        $stmt->execute([$result['setting_value']]);
        $currency = $stmt->fetch(PDO::FETCH_ASSOC);
        return $currency ? $currency['currency_code'] : 'USD';
    }
    
    return 'USD';
}





/**
 * Format amount in company currency
 * @param float $amount Amount to format
 * @param int $company_id Company ID
 * @return string Formatted amount
 */
function formatCompanyCurrency($amount, $company_id = null) {
    $currency = getCompanyCurrencyCode($company_id);
    return formatCurrencyAmount($amount, $currency);
}





/**
 * Get currency display options for forms
 * @return array Currency options for select dropdowns
 */
function getCurrencyOptions() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY currency_name");
    $stmt->execute();
    $currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $options = [];
    foreach ($currencies as $currency) {
        $options[$currency['currency_code']] = $currency['currency_name'] . ' (' . $currency['currency_code'] . ')';
    }
    
    return $options;
}

/**
 * Validate currency code
 * @param string $currency_code Currency code to validate
 * @return bool Whether currency is valid
 */
function isValidCurrency($currency_code) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM currencies WHERE currency_code = ? AND is_active = 1");
    $stmt->execute([$currency_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Get currency information
 * @param string $currency_code Currency code
 * @return array Currency information
 */
function getCurrencyInfo($currency_code) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM currencies WHERE currency_code = ? AND is_active = 1");
    $stmt->execute([$currency_code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>