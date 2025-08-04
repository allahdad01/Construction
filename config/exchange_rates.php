<?php
/**
 * Real-Time Exchange Rate System
 * Uses external API to get current exchange rates
 */

/**
 * Get real-time exchange rates from API
 * @return array Exchange rates
 */
function getRealTimeExchangeRates() {
    // You can use various free APIs:
    // 1. exchangerate-api.com (free tier: 1000 requests/month)
    // 2. fixer.io (free tier: 100 requests/month)
    // 3. currencyapi.net (free tier: 1000 requests/day)
    
    $api_key = 'YOUR_API_KEY'; // Replace with your API key
    $base_currency = 'USD';
    
    // For demo purposes, we'll use a free API
    $url = "https://api.exchangerate-api.com/v4/latest/{$base_currency}";
    
    try {
        $response = file_get_contents($url);
        if ($response === false) {
            throw new Exception('Failed to fetch exchange rates');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !isset($data['rates'])) {
            throw new Exception('Invalid response from exchange rate API');
        }
        
        return $data['rates'];
        
    } catch (Exception $e) {
        // Fallback to cached rates if API fails
        return getCachedExchangeRates();
    }
}

/**
 * Get cached exchange rates (fallback)
 * @return array Cached exchange rates
 */
function getCachedExchangeRates() {
    global $conn;
    
    // Check if we have cached rates that are less than 1 hour old
    $stmt = $conn->prepare("
        SELECT setting_value, updated_at 
        FROM system_settings 
        WHERE setting_key = 'exchange_rates_cache'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $cache_time = strtotime($result['updated_at']);
        $current_time = time();
        
        // Cache is valid for 1 hour
        if (($current_time - $cache_time) < 3600) {
            return json_decode($result['setting_value'], true);
        }
    }
    
    // Return default rates if no cache or expired
    return getDefaultExchangeRates();
}

/**
 * Get default exchange rates (fallback)
 * @return array Default exchange rates
 */
function getDefaultExchangeRates() {
    return [
        'USD' => 1.0,
        'AFN' => 75.0,  // 1 USD = 75 AFN
        'EUR' => 0.85,  // 1 USD = 0.85 EUR
        'GBP' => 0.73,  // 1 USD = 0.73 GBP
        'PKR' => 280.0, // 1 USD = 280 PKR
        'INR' => 83.0,  // 1 USD = 83 INR
        'AED' => 3.67,  // 1 USD = 3.67 AED
        'SAR' => 3.75,  // 1 USD = 3.75 SAR
    ];
}

/**
 * Update exchange rates cache
 * @param array $rates Exchange rates to cache
 * @return bool Success status
 */
function updateExchangeRatesCache($rates) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES ('exchange_rates_cache', ?, NOW())
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            updated_at = NOW()
        ");
        
        return $stmt->execute([json_encode($rates)]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get current exchange rate between two currencies
 * @param string $from_currency Source currency
 * @param string $to_currency Target currency
 * @return float Exchange rate
 */
function getCurrentExchangeRate($from_currency, $to_currency) {
    if ($from_currency === $to_currency) {
        return 1.0;
    }
    
    // Try to get real-time rates first
    $rates = getRealTimeExchangeRates();
    
    // If we got real-time rates, cache them
    if ($rates && count($rates) > 0) {
        updateExchangeRatesCache($rates);
    } else {
        // Use cached rates
        $rates = getCachedExchangeRates();
    }
    
    // Calculate cross-rate if needed
    if ($from_currency === 'USD') {
        return $rates[$to_currency] ?? 1.0;
    } elseif ($to_currency === 'USD') {
        return 1 / ($rates[$from_currency] ?? 1.0);
    } else {
        // Cross-rate calculation
        $from_to_usd = $rates[$from_currency] ?? 1.0;
        $to_to_usd = $rates[$to_currency] ?? 1.0;
        return $to_to_usd / $from_to_usd;
    }
}

/**
 * Convert amount using real-time exchange rates
 * @param float $amount Amount to convert
 * @param string $from_currency Source currency
 * @param string $to_currency Target currency
 * @return float Converted amount
 */
function convertAmountRealTime($amount, $from_currency, $to_currency) {
    if ($from_currency === $to_currency) {
        return $amount;
    }
    
    $rate = getCurrentExchangeRate($from_currency, $to_currency);
    return $amount * $rate;
}

/**
 * Get exchange rate information for display
 * @return array Exchange rate information
 */
function getExchangeRateInfo() {
    $rates = getRealTimeExchangeRates();
    
    if (!$rates || count($rates) === 0) {
        $rates = getCachedExchangeRates();
    }
    
    $info = [
        'last_updated' => date('Y-m-d H:i:s'),
        'rates' => $rates,
        'source' => 'Real-time API'
    ];
    
    return $info;
}

/**
 * Format exchange rate for display
 * @param float $rate Exchange rate
 * @param string $from_currency Source currency
 * @param string $to_currency Target currency
 * @return string Formatted rate
 */
function formatExchangeRate($rate, $from_currency, $to_currency) {
    if ($rate >= 1) {
        return "1 {$from_currency} = " . number_format($rate, 2) . " {$to_currency}";
    } else {
        return "1 {$to_currency} = " . number_format(1/$rate, 2) . " {$from_currency}";
    }
}

/**
 * Get supported currencies
 * @return array List of supported currencies
 */
function getSupportedCurrencies() {
    return [
        'USD' => 'US Dollar',
        'AFN' => 'Afghan Afghani',
        'EUR' => 'Euro',
        'GBP' => 'British Pound',
        'PKR' => 'Pakistani Rupee',
        'INR' => 'Indian Rupee',
        'AED' => 'UAE Dirham',
        'SAR' => 'Saudi Riyal'
    ];
}

/**
 * Validate currency code
 * @param string $currency Currency code
 * @return bool Whether currency is supported
 */
function isValidCurrency($currency) {
    $supported = getSupportedCurrencies();
    return array_key_exists($currency, $supported);
}

/**
 * Get currency symbol
 * @param string $currency Currency code
 * @return string Currency symbol
 */
function getCurrencySymbol($currency) {
    $symbols = [
        'USD' => '$',
        'AFN' => '؋',
        'EUR' => '€',
        'GBP' => '£',
        'PKR' => '₨',
        'INR' => '₹',
        'AED' => 'د.إ',
        'SAR' => 'ر.س'
    ];
    
    return $symbols[$currency] ?? $currency;
}
?>