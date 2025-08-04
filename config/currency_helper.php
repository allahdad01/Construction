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
 * Get currency exchange rate (now uses real-time rates)
 * @param string $from_currency Source currency code
 * @param string $to_currency Target currency code
 * @return float Exchange rate
 */
function getExchangeRate($from_currency, $to_currency) {
    // Include the real-time exchange rate system
    require_once __DIR__ . '/exchange_rates.php';
    
    return getCurrentExchangeRate($from_currency, $to_currency);
}

/**
 * Convert amount between currencies using real-time exchange rates
 * @param float $amount Amount to convert
 * @param string $from_currency Source currency code
 * @param string $to_currency Target currency code
 * @return float Converted amount
 */
function convertCurrencyByCode($amount, $from_currency, $to_currency) {
    if ($from_currency === $to_currency) {
        return $amount;
    }
    
    // Include the real-time exchange rate system
    require_once __DIR__ . '/exchange_rates.php';
    
    return convertAmountRealTime($amount, $from_currency, $to_currency);
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
 * Calculate total in multiple currencies
 * @param array $items Array of items with amount and currency
 * @param string $target_currency Target currency for total
 * @return array Totals in different currencies
 */
function calculateMultiCurrencyTotal($items, $target_currency = 'USD') {
    $totals = [
        'USD' => 0,
        'AFN' => 0,
        'EUR' => 0,
        'GBP' => 0
    ];
    
    foreach ($items as $item) {
        $amount = $item['amount'] ?? 0;
        $currency = $item['currency'] ?? 'USD';
        
        // Convert to all currencies
        foreach ($totals as $curr => &$total) {
            $total += convertCurrencyByCode($amount, $currency, $curr);
        }
    }
    
    return [
        'totals' => $totals,
        'target_currency' => $target_currency,
        'target_total' => $totals[$target_currency]
    ];
}

/**
 * Get currency statistics for dashboard
 * @param int $company_id Company ID
 * @return array Currency statistics
 */
function getCurrencyStatistics($company_id = null) {
    if (!$company_id) {
        $company_id = getCurrentCompanyId();
    }
    
    global $conn;
    
    // Get payments in different currencies
    $stmt = $conn->prepare("
        SELECT currency, SUM(amount) as total_amount, COUNT(*) as count
        FROM company_payments 
        WHERE company_id = ? AND payment_status = 'completed'
        GROUP BY currency
    ");
    $stmt->execute([$company_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get expenses in different currencies
    $stmt = $conn->prepare("
        SELECT currency, SUM(amount) as total_amount, COUNT(*) as count
        FROM expenses 
        WHERE company_id = ?
        GROUP BY currency
    ");
    $stmt->execute([$company_id]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals in USD
    $total_payments_usd = 0;
    $total_expenses_usd = 0;
    
    foreach ($payments as $payment) {
        $total_payments_usd += convertCurrencyByCode($payment['total_amount'], $payment['currency'], 'USD');
    }
    
    foreach ($expenses as $expense) {
        $total_expenses_usd += convertCurrencyByCode($expense['total_amount'], $expense['currency'], 'USD');
    }
    
    return [
        'payments' => $payments,
        'expenses' => $expenses,
        'total_payments_usd' => $total_payments_usd,
        'total_expenses_usd' => $total_expenses_usd,
        'net_income_usd' => $total_payments_usd - $total_expenses_usd
    ];
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
 * Get currency conversion rates for display
 * @return array Current exchange rates
 */
function getCurrentExchangeRates() {
    return [
        'USD' => [
            'AFN' => 75.0,
            'EUR' => 0.85,
            'GBP' => 0.73
        ],
        'AFN' => [
            'USD' => 0.013,
            'EUR' => 0.011,
            'GBP' => 0.0097
        ],
        'EUR' => [
            'USD' => 1.18,
            'AFN' => 88.5,
            'GBP' => 0.86
        ],
        'GBP' => [
            'USD' => 1.37,
            'AFN' => 102.75,
            'EUR' => 1.16
        ]
    ];
}

/**
 * Calculate salary in multiple currencies
 * @param float $salary_usd Salary in USD
 * @return array Salary in different currencies
 */
function calculateMultiCurrencySalary($salary_usd) {
    return [
        'USD' => $salary_usd,
        'AFN' => convertCurrencyByCode($salary_usd, 'USD', 'AFN'),
        'EUR' => convertCurrencyByCode($salary_usd, 'USD', 'EUR'),
        'GBP' => convertCurrencyByCode($salary_usd, 'USD', 'GBP')
    ];
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