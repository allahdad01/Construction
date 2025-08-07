<?php
// Test script to verify contracts amount_paid logic
error_reporting(E_ALL);

// Simulate contracts array
$contracts = [
    [
        'id' => 1,
        'contract_code' => 'TEST001',
        'contract_type' => 'monthly',
        'total_amount' => 0,
        'monthly_rate' => 175000,
        'duration_months' => 3,
        'duration_hours' => 240
    ]
];

echo "Testing contract processing logic...\n\n";

// Simulate the fixed logic
foreach ($contracts as $index => &$contract) {
    echo "Processing contract ID: " . $contract['id'] . "\n";
    
    // Simulate working hours data (instead of DB query)
    $total_hours_worked = 49;
    $contract['total_hours_worked'] = $total_hours_worked;
    
    // Simulate payment data (instead of DB query)
    $payment_result = ['amount_paid' => 50000];
    $contract['amount_paid'] = $payment_result['amount_paid'];
    
    echo "Total hours worked: " . $total_hours_worked . "\n";
    echo "Amount paid: " . $contract['amount_paid'] . "\n";
    
    // Ensure values are set
    $contract['total_hours_worked'] = $contract['total_hours_worked'] ?? 0;
    $contract['amount_paid'] = $contract['amount_paid'] ?? 0;
    
    // Calculate contract value
    $contract_total = 0;
    if (!empty($contract['total_amount']) && $contract['total_amount'] > 0) {
        $contract_total = $contract['total_amount'];
    } else {
        if ($contract['contract_type'] == 'monthly' && !empty($contract['monthly_rate']) && !empty($contract['duration_months'])) {
            $contract_total = $contract['duration_months'] * $contract['monthly_rate'];
        }
    }
    $contract['calculated_total'] = $contract_total;
    
    // Calculate progress
    $progress_percentage = 0;
    if (!empty($contract['duration_hours']) && $contract['duration_hours'] > 0) {
        $progress_percentage = ($total_hours_worked / $contract['duration_hours']) * 100;
        $progress_percentage = min(100, $progress_percentage);
    }
    $contract['progress_percentage'] = $progress_percentage;
    
    echo "Calculated total: " . $contract_total . "\n";
    echo "Progress: " . round($progress_percentage, 1) . "%\n\n";
}

// Unset reference
unset($contract);

// Test accessing the values
echo "Final verification:\n";
foreach ($contracts as $contract) {
    echo "Contract ID: " . $contract['id'] . "\n";
    echo "amount_paid exists: " . (isset($contract['amount_paid']) ? 'YES' : 'NO') . "\n";
    echo "amount_paid value: " . ($contract['amount_paid'] ?? 'NOT_SET') . "\n";
    
    // Test the display logic
    $amount_paid = isset($contract['amount_paid']) ? $contract['amount_paid'] : 0;
    echo "Display value: " . number_format($amount_paid, 2) . "\n";
    
    $calculated_total = $contract['calculated_total'] ?? 0;
    $remaining = $calculated_total - $amount_paid;
    echo "Remaining: " . number_format($remaining, 2) . "\n";
}

echo "\nTest completed successfully!\n";
?>