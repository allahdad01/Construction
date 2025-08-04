<?php
/**
 * API Endpoint to Refresh Exchange Rates
 */

require_once '../config/config.php';
require_once '../config/exchange_rates.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

header('Content-Type: application/json');

try {
    // Force refresh of exchange rates
    $rates = getRealTimeExchangeRates();
    
    if ($rates && count($rates) > 0) {
        // Cache the new rates
        updateExchangeRatesCache($rates);
        
        $response = [
            'success' => true,
            'message' => 'Exchange rates refreshed successfully',
            'rates' => $rates,
            'last_updated' => date('Y-m-d H:i:s'),
            'supported_currencies' => getSupportedCurrencies()
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Failed to fetch real-time rates, using cached rates',
            'rates' => getCachedExchangeRates(),
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error refreshing exchange rates: ' . $e->getMessage(),
        'rates' => getCachedExchangeRates(),
        'last_updated' => date('Y-m-d H:i:s')
    ];
}

echo json_encode($response);
?>