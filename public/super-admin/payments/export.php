<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

// Get export format
$format = $_GET['format'] ?? 'csv';

// Get all payments with company information
$sql = "
    SELECT cp.*, c.company_name, c.company_code
    FROM company_payments cp 
    LEFT JOIN companies c ON cp.company_id = c.id 
    ORDER BY cp.payment_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers based on format
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'Payment Code',
        'Company',
        'Company Code',
        'Amount',
        'Currency',
        'Payment Method',
        'Payment Status',
        'Payment Date',
        'Billing Period Start',
        'Billing Period End',
        'Subscription Plan',
        'Transaction ID',
        'Notes',
        'Created At'
    ]);
    
    // Add data
    foreach ($payments as $payment) {
        fputcsv($output, [
            $payment['payment_code'],
            $payment['company_name'],
            $payment['company_code'],
            $payment['amount'],
            $payment['currency'],
            $payment['payment_method'],
            $payment['payment_status'],
            $payment['payment_date'],
            $payment['billing_period_start'],
            $payment['billing_period_end'],
            $payment['subscription_plan'],
            $payment['transaction_id'],
            $payment['notes'],
            $payment['created_at']
        ]);
    }
    
    fclose($output);
    
} elseif ($format === 'excel') {
    // For Excel, we'll create a CSV that Excel can open
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr>';
    echo '<th>Payment Code</th>';
    echo '<th>Company</th>';
    echo '<th>Company Code</th>';
    echo '<th>Amount</th>';
    echo '<th>Currency</th>';
    echo '<th>Payment Method</th>';
    echo '<th>Payment Status</th>';
    echo '<th>Payment Date</th>';
    echo '<th>Billing Period Start</th>';
    echo '<th>Billing Period End</th>';
    echo '<th>Subscription Plan</th>';
    echo '<th>Transaction ID</th>';
    echo '<th>Notes</th>';
    echo '<th>Created At</th>';
    echo '</tr>';
    
    foreach ($payments as $payment) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($payment['payment_code']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['company_name']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['company_code']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['amount']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['currency']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['payment_method']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['payment_status']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['payment_date']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['billing_period_start']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['billing_period_end']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['subscription_plan']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['transaction_id']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['notes']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['created_at']) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
} else {
    // Default to CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payments_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'Payment Code',
        'Company',
        'Company Code',
        'Amount',
        'Currency',
        'Payment Method',
        'Payment Status',
        'Payment Date',
        'Billing Period Start',
        'Billing Period End',
        'Subscription Plan',
        'Transaction ID',
        'Notes',
        'Created At'
    ]);
    
    foreach ($payments as $payment) {
        fputcsv($output, [
            $payment['payment_code'],
            $payment['company_name'],
            $payment['company_code'],
            $payment['amount'],
            $payment['currency'],
            $payment['payment_method'],
            $payment['payment_status'],
            $payment['payment_date'],
            $payment['billing_period_start'],
            $payment['billing_period_end'],
            $payment['subscription_plan'],
            $payment['transaction_id'],
            $payment['notes'],
            $payment['created_at']
        ]);
    }
    
    fclose($output);
}

exit;
?>