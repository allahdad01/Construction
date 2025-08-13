<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin', 'driver', 'driver_assistant']);

$db = new Database();
$conn = $db->getConnection();

// Get contract ID and export type
$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;
$export_type = isset($_GET['type']) ? $_GET['type'] : 'pdf'; // pdf or excel

if (!$contract_id) {
    die('Contract ID is required');
}

// Get contract details with project and machine info
$stmt = $conn->prepare("
    SELECT c.*, p.name as project_name, p.client_name, p.client_contact, m.name as machine_name, m.machine_code, m.type as machine_type
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN machines m ON c.machine_id = m.id
    WHERE c.id = ? AND c.company_id = ?
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    die('Contract not found');
}

// Get company details for header
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([getCurrentCompanyId()]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Get contract currency
$contract_currency = $contract['currency'] ?? 'USD';

// Get working hours
$stmt = $conn->prepare("
    SELECT wh.*, e.name as employee_name, e.employee_code
    FROM working_hours wh
    LEFT JOIN employees e ON wh.employee_id = e.id
    WHERE wh.contract_id = ? AND wh.company_id = ?
    ORDER BY wh.date DESC
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$working_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_hours_worked = 0;
$total_amount_earned = 0;
$monthly_data = [];

foreach ($working_hours as $wh) {
    $total_hours_worked += $wh['hours_worked'];
    
    // Calculate amount based on contract type
    if ($contract['contract_type'] === 'hourly') {
        $amount = $wh['hours_worked'] * $contract['rate_amount'];
    } elseif ($contract['contract_type'] === 'daily') {
        $daily_rate = $contract['rate_amount'];
        $amount = $wh['hours_worked'] * ($daily_rate / ($contract['working_hours_per_day'] ?: 8));
    } elseif ($contract['contract_type'] === 'monthly') {
        $monthly_rate = $contract['rate_amount'];
        $amount = $wh['hours_worked'] * ($monthly_rate / ($contract['total_hours_required'] ?: 270));
    } else {
        $amount = 0;
    }
    
    $total_amount_earned += $amount;
    
    // Group by month
    $month = date('Y-m', strtotime($wh['date']));
    if (!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['hours' => 0, 'amount' => 0, 'display_month' => date('M Y', strtotime($wh['date']))];
    }
    $monthly_data[$month]['hours'] += $wh['hours_worked'];
    $monthly_data[$month]['amount'] += $amount;
}

// Get payments
$stmt = $conn->prepare("
    SELECT * FROM contract_payments 
    WHERE contract_id = ? AND company_id = ? AND status = 'completed'
    ORDER BY payment_date DESC
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_amount_paid = 0;
foreach ($payments as $payment) {
    $total_amount_paid += $payment['amount'];
}

$remaining_amount = $total_amount_earned - $total_amount_paid;
$progress_percentage = $contract['total_hours_required'] > 0 ? 
    ($total_hours_worked / $contract['total_hours_required']) * 100 : 0;

if ($export_type === 'excel') {
    // Excel Export
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="timesheet_' . $contract['contract_code'] . '_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "<!DOCTYPE html>";
    echo "<html><head><meta charset='utf-8'></head><body>";
    echo "<table border='1'>";
    
    // Company Header
    echo "<tr><td colspan='7' style='text-align: center; font-size: 18px; font-weight: bold;'>" . htmlspecialchars($company['company_name']) . "</td></tr>";
    echo "<tr><td colspan='7' style='text-align: center;'>Contract Timesheet Report</td></tr>";
    echo "<tr><td colspan='7'>&nbsp;</td></tr>";
    
    // Contract Information
    echo "<tr><td><strong>Contract Code:</strong></td><td>" . htmlspecialchars($contract['contract_code']) . "</td><td></td><td><strong>Project:</strong></td><td>" . htmlspecialchars($contract['project_name'] ?? 'N/A') . "</td><td></td><td></td></tr>";
    echo "<tr><td><strong>Machine:</strong></td><td>" . htmlspecialchars($contract['machine_name'] ?? 'N/A') . "</td><td></td><td><strong>Machine Code:</strong></td><td>" . htmlspecialchars($contract['machine_code'] ?? 'N/A') . "</td><td></td><td></td></tr>";
    echo "<tr><td><strong>Contract Type:</strong></td><td>" . ucfirst($contract['contract_type']) . "</td><td></td><td><strong>Rate:</strong></td><td>" . formatCurrencyAmount($contract['rate_amount'], $contract_currency) . " per " . $contract['contract_type'] . "</td><td></td><td></td></tr>";
    echo "<tr><td><strong>Client:</strong></td><td>" . htmlspecialchars($contract['client_name'] ?? 'N/A') . "</td><td></td><td><strong>Status:</strong></td><td>" . ucfirst($contract['status']) . "</td><td></td><td></td></tr>";
    echo "<tr><td colspan='7'>&nbsp;</td></tr>";
    
    // Summary
    echo "<tr><td><strong>Total Hours:</strong></td><td>" . number_format($total_hours_worked, 1) . "</td><td></td><td><strong>Total Earned:</strong></td><td>" . formatCurrencyAmount($total_amount_earned, $contract_currency) . "</td><td></td><td></td></tr>";
    echo "<tr><td><strong>Total Paid:</strong></td><td>" . formatCurrencyAmount($total_amount_paid, $contract_currency) . "</td><td></td><td><strong>Remaining:</strong></td><td>" . formatCurrencyAmount($remaining_amount, $contract_currency) . "</td><td></td><td></td></tr>";
    echo "<tr><td><strong>Progress:</strong></td><td>" . number_format($progress_percentage, 1) . "%</td><td colspan='5'></td></tr>";
    echo "<tr><td colspan='7'>&nbsp;</td></tr>";
    
    // Working Hours Table
    echo "<tr><td colspan='7' style='font-weight: bold; background-color: #f8f9fa;'>Daily Working Hours</td></tr>";
    echo "<tr style='background-color: #e9ecef;'>";
    echo "<td><strong>Date</strong></td>";
    echo "<td><strong>Employee</strong></td>";
    echo "<td><strong>Employee Code</strong></td>";
    echo "<td><strong>Hours Worked</strong></td>";
    echo "<td><strong>Rate</strong></td>";
    echo "<td><strong>Daily Amount</strong></td>";
    echo "<td><strong>Notes</strong></td>";
    echo "</tr>";
    
    foreach ($working_hours as $wh) {
        // Calculate daily amount
        if ($contract['contract_type'] === 'hourly') {
            $hourly_rate = $contract['rate_amount'];
            $daily_amount = $wh['hours_worked'] * $hourly_rate;
        } elseif ($contract['contract_type'] === 'daily') {
            $hourly_rate = $contract['rate_amount'] / ($contract['working_hours_per_day'] ?: 8);
            $daily_amount = $wh['hours_worked'] * $hourly_rate;
        } elseif ($contract['contract_type'] === 'monthly') {
            $hourly_rate = $contract['rate_amount'] / ($contract['total_hours_required'] ?: 270);
            $daily_amount = $wh['hours_worked'] * $hourly_rate;
        } else {
            $hourly_rate = 0;
            $daily_amount = 0;
        }
        
        echo "<tr>";
        echo "<td>" . date('M j, Y', strtotime($wh['date'])) . "</td>";
        echo "<td>" . htmlspecialchars($wh['employee_name'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($wh['employee_code'] ?? 'N/A') . "</td>";
        echo "<td>" . number_format($wh['hours_worked'], 1) . "</td>";
        echo "<td>" . formatCurrencyAmount($hourly_rate, $contract_currency) . "/hr</td>";
        echo "<td>" . formatCurrencyAmount($daily_amount, $contract_currency) . "</td>";
        echo "<td>" . htmlspecialchars($wh['notes'] ?? '') . "</td>";
        echo "</tr>";
    }
    
    // Monthly Summary
    echo "<tr><td colspan='7'>&nbsp;</td></tr>";
    echo "<tr><td colspan='7' style='font-weight: bold; background-color: #f8f9fa;'>Monthly Summary</td></tr>";
    echo "<tr style='background-color: #e9ecef;'>";
    echo "<td><strong>Month</strong></td>";
    echo "<td><strong>Hours Worked</strong></td>";
    echo "<td><strong>Amount Earned</strong></td>";
    echo "<td colspan='4'></td>";
    echo "</tr>";
    
    foreach ($monthly_data as $month => $data) {
        echo "<tr>";
        echo "<td>" . $data['display_month'] . "</td>";
        echo "<td>" . number_format($data['hours'], 1) . "</td>";
        echo "<td>" . formatCurrencyAmount($data['amount'], $contract_currency) . "</td>";
        echo "<td colspan='4'></td>";
        echo "</tr>";
    }
    
    // Payments Table
    if (!empty($payments)) {
        echo "<tr><td colspan='7'>&nbsp;</td></tr>";
        echo "<tr><td colspan='7' style='font-weight: bold; background-color: #f8f9fa;'>Contract Payments</td></tr>";
        echo "<tr style='background-color: #e9ecef;'>";
        echo "<td><strong>Payment Date</strong></td>";
        echo "<td><strong>Amount</strong></td>";
        echo "<td><strong>Payment Method</strong></td>";
        echo "<td><strong>Reference</strong></td>";
        echo "<td><strong>Status</strong></td>";
        echo "<td colspan='2'></td>";
        echo "</tr>";
        
        foreach ($payments as $payment) {
            echo "<tr>";
            echo "<td>" . date('Y-m-d', strtotime($payment['payment_date'])) . "</td>";
            echo "<td>" . formatCurrencyAmount($payment['amount'], $contract_currency) . "</td>";
            echo "<td>" . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . "</td>";
            echo "<td>" . htmlspecialchars($payment['reference_number'] ?? '') . "</td>";
            echo "<td>" . ucfirst($payment['status']) . "</td>";
            echo "<td colspan='2'></td>";
            echo "</tr>";
        }
    }
    
    echo "</table>";
    echo "</body></html>";
    
} else {
    // PDF Export using HTML
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Timesheet - <?php echo htmlspecialchars($contract['contract_code']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .company-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
            .report-title { font-size: 18px; margin-bottom: 20px; }
            .contract-info { margin-bottom: 30px; }
            .contract-info table { width: 100%; border-collapse: collapse; }
            .contract-info td { padding: 5px; border: 1px solid #ddd; }
            .summary { margin-bottom: 30px; }
            .summary table { width: 100%; border-collapse: collapse; }
            .summary th, .summary td { padding: 8px; border: 1px solid #ddd; text-align: left; }
            .summary th { background-color: #f8f9fa; }
            .timesheet-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            .timesheet-table th, .timesheet-table td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 12px; }
            .timesheet-table th { background-color: #f8f9fa; }
            .section-title { font-size: 16px; font-weight: bold; margin: 20px 0 10px 0; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="margin-bottom: 20px;">
            <button onclick="window.print()">Print / Save as PDF</button>
            <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" style="margin-left: 10px;">Back to Timesheet</a>
        </div>
        
        <div class="header">
            <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
            <div class="report-title">Contract Timesheet Report</div>
            <div>Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></div>
        </div>
        
        <div class="contract-info">
            <h3>Contract Information</h3>
            <table>
                <tr>
                    <td><strong>Contract Code:</strong></td>
                    <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                    <td><strong>Project:</strong></td>
                    <td><?php echo htmlspecialchars($contract['project_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td><strong>Machine:</strong></td>
                    <td><?php echo htmlspecialchars($contract['machine_name'] ?? 'N/A'); ?></td>
                    <td><strong>Machine Code:</strong></td>
                    <td><?php echo htmlspecialchars($contract['machine_code'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td><strong>Machine Type:</strong></td>
                    <td><?php echo htmlspecialchars($contract['machine_type'] ?? 'N/A'); ?></td>
                    <td><strong>Contract Type:</strong></td>
                    <td><?php echo ucfirst($contract['contract_type']); ?></td>
                </tr>
                <tr>
                    <td><strong>Rate:</strong></td>
                    <td><?php echo formatCurrencyAmount($contract['rate_amount'], $contract_currency); ?> per <?php echo $contract['contract_type']; ?></td>
                    <td><strong>Client:</strong></td>
                    <td><?php echo htmlspecialchars($contract['client_name'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td><strong>Client Contact:</strong></td>
                    <td><?php echo htmlspecialchars($contract['client_contact'] ?? 'N/A'); ?></td>
                    <td><strong>Status:</strong></td>
                    <td><?php echo ucfirst($contract['status']); ?></td>
                </tr>
                <tr>
                    <td><strong>Start Date:</strong></td>
                    <td><?php echo date('M j, Y', strtotime($contract['start_date'])); ?></td>
                    <td><strong>End Date:</strong></td>
                    <td><?php echo $contract['end_date'] ? date('M j, Y', strtotime($contract['end_date'])) : 'Ongoing'; ?></td>
                </tr>
            </table>
        </div>
        
        <div class="summary">
            <h3>Contract Summary</h3>
            <table>
                <tr>
                    <th>Total Hours Worked</th>
                    <th>Total Amount Earned</th>
                    <th>Total Amount Paid</th>
                    <th>Remaining Amount</th>
                    <th>Progress</th>
                </tr>
                <tr>
                    <td><?php echo number_format($total_hours_worked, 1); ?> hours</td>
                    <td><?php echo formatCurrencyAmount($total_amount_earned, $contract_currency); ?></td>
                    <td><?php echo formatCurrencyAmount($total_amount_paid, $contract_currency); ?></td>
                    <td><?php echo formatCurrencyAmount($remaining_amount, $contract_currency); ?></td>
                    <td><?php echo number_format($progress_percentage, 1); ?>%</td>
                </tr>
            </table>
        </div>
        
        <div class="section-title">Daily Working Hours</div>
        <table class="timesheet-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Employee</th>
                    <th>Employee Code</th>
                    <th>Hours Worked</th>
                    <th>Rate</th>
                    <th>Daily Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($working_hours as $wh): 
                    // Calculate daily amount
                    if ($contract['contract_type'] === 'hourly') {
                        $hourly_rate = $contract['rate_amount'];
                        $daily_amount = $wh['hours_worked'] * $hourly_rate;
                    } elseif ($contract['contract_type'] === 'daily') {
                        $hourly_rate = $contract['rate_amount'] / ($contract['working_hours_per_day'] ?: 8);
                        $daily_amount = $wh['hours_worked'] * $hourly_rate;
                    } elseif ($contract['contract_type'] === 'monthly') {
                        $hourly_rate = $contract['rate_amount'] / ($contract['total_hours_required'] ?: 270);
                        $daily_amount = $wh['hours_worked'] * $hourly_rate;
                    } else {
                        $hourly_rate = 0;
                        $daily_amount = 0;
                    }
                ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($wh['date'])); ?></td>
                    <td><?php echo date('l', strtotime($wh['date'])); ?></td>
                    <td><?php echo htmlspecialchars($wh['employee_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($wh['employee_code'] ?? 'N/A'); ?></td>
                    <td><?php echo number_format($wh['hours_worked'], 1); ?></td>
                    <td><?php echo formatCurrencyAmount($hourly_rate, $contract_currency); ?>/hr</td>
                    <td><?php echo formatCurrencyAmount($daily_amount, $contract_currency); ?></td>
                    <td><?php echo htmlspecialchars($wh['notes'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td colspan="4">Total</td>
                    <td><?php echo number_format($total_hours_worked, 1); ?> hours</td>
                    <td></td>
                    <td><?php echo formatCurrencyAmount($total_amount_earned, $contract_currency); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        
        <?php if (!empty($monthly_data)): ?>
        <div class="section-title">Monthly Summary</div>
        <table class="timesheet-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Hours Worked</th>
                    <th>Amount Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_data as $month => $data): ?>
                <tr>
                    <td><?php echo $data['display_month']; ?></td>
                    <td><?php echo number_format($data['hours'], 1); ?> hours</td>
                    <td><?php echo formatCurrencyAmount($data['amount'], $contract_currency); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if (!empty($payments)): ?>
        <div class="section-title">Contract Payments</div>
        <table class="timesheet-table">
            <thead>
                <tr>
                    <th>Payment Date</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Reference Number</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                    <td><?php echo formatCurrencyAmount($payment['amount'], $contract_currency); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                    <td><?php echo htmlspecialchars($payment['reference_number'] ?? ''); ?></td>
                    <td><?php echo ucfirst($payment['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f8f9fa; font-weight: bold;">
                    <td>Total Paid</td>
                    <td><?php echo formatCurrencyAmount($total_amount_paid, $contract_currency); ?></td>
                    <td colspan="3"></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
        
        <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
            Report generated by <?php echo htmlspecialchars($company['company_name']); ?> on <?php echo date('F j, Y \a\t g:i A'); ?>
        </div>
    </body>
    </html>
    <?php
}
?>