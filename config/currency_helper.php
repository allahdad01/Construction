<?php
/**
 * Currency Helper Functions
 * Centralized currency symbol mapping and formatting
 */

// Prevent multiple inclusions
if (!function_exists('getCurrencySymbol')) {

    /**
     * Get currency symbol for a given currency code
     * @param string $currency Currency code (e.g., 'USD', 'EUR', 'AFN')
     * @return string Currency symbol
     */
    function getCurrencySymbol($currency) {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AFN' => '؋',
            'JPY' => '¥',
            'INR' => '₹',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'CZK' => 'Kč',
            'HUF' => 'Ft',
            'RUB' => '₽',
            'TRY' => '₺',
            'BRL' => 'R$',
            'MXN' => '$',
            'ZAR' => 'R',
            'AED' => 'د.إ',
            'SAR' => 'ر.س',
            'QAR' => 'ر.ق',
            'KWD' => 'د.ك',
            'BHD' => 'د.ب',
            'OMR' => 'ر.ع.',
            'JOD' => 'د.أ',
            'LBP' => 'ل.ل',
            'EGP' => 'ج.م',
            'IQD' => 'ع.د',
            'IRR' => '﷼',
            'PKR' => '₨',
            'BDT' => '৳',
            'LKR' => 'Rs',
            'NPR' => 'Rs',
            'MMK' => 'K',
            'THB' => '฿',
            'VND' => '₫',
            'KRW' => '₩',
            'IDR' => 'Rp',
            'MYR' => 'RM',
            'SGD' => 'S$',
            'PHP' => '₱',
            'HKD' => 'HK$',
            'TWD' => 'NT$',
            'NZD' => 'NZ$'
        ];
        
        return $symbols[$currency] ?? $currency;
    }

    /**
     * Format currency amount with proper symbol
     * @param float $amount Amount to format
     * @param string $currency Currency code
     * @return string Formatted currency string
     */
    function formatCurrencyAmount($amount, $currency) {
        $symbol = getCurrencySymbol($currency);
        return $symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Get currency display with symbol and code
     * @param string $currency Currency code
     * @return string Formatted string with symbol and code
     */
    function getCurrencyDisplay($currency) {
        $symbol = getCurrencySymbol($currency);
        return $symbol . ' (' . $currency . ')';
    }
}
?>