<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is authenticated
requireAuth();

$db = new Database();
$conn = $db->getConnection();

$report_type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

if (!in_array($report_type, ['overview', 'financial', 'employee', 'contract', 'machine'])) {
    header('Location: /constract360/construction/public/reports/');
    exit;
}

// Set headers for download
$filename = "construction_report_{$report_type}_{$start_date}_to_{$end_date}";

if ($format === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    generatePDFReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id);
} elseif ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    generateExcelReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id);
} elseif ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    generateCSVReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id);
}

function generatePDFReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id) {
    // This would require a PDF library like TCPDF or FPDF
    // For now, we'll generate HTML that can be converted to PDF
    echo "<html><body>";
    echo "<h1>Construction Report</h1>";
    echo "<p>Report Type: " . ucfirst($report_type) . "</p>";
    echo "<p>Period: {$start_date} to {$end_date}</p>";
    echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>";
    echo "</body></html>";
}

function generateExcelReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id) {
    // This would require a library like PhpSpreadsheet
    // For now, we'll generate CSV format
    generateCSVReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id);
}

function generateCSVReport($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id) {
    $output = fopen('php://output', 'w');
    
    if ($report_type === 'financial') {
        // Financial report headers
        fputcsv($output, ['Date', 'Revenue', 'Expenses', 'Net Income']);
        
        // Get financial data
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, SUM(amount) as revenue
            FROM company_payments 
            WHERE payment_date BETWEEN ? AND ? AND payment_status = 'completed'
            GROUP BY DATE(payment_date)
            ORDER BY date
        ");
        $stmt->execute([$start_date, $end_date]);
        $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($revenue_data as $row) {
            fputcsv($output, [$row['date'], $row['revenue'], 0, $row['revenue']]);
        }
    } elseif ($report_type === 'employee') {
        // Employee report headers
        fputcsv($output, ['Employee', 'Position', 'Working Hours', 'Salary']);
        
        // Get employee data
        $stmt = $conn->prepare("
            SELECT e.name, e.position, 
                   SUM(wh.hours_worked) as total_hours, e.monthly_salary
            FROM employees e
            LEFT JOIN working_hours wh ON e.id = wh.employee_id 
                AND wh.date BETWEEN ? AND ?
            WHERE e.company_id = ? AND e.is_active = 1
            GROUP BY e.id
        ");
        $stmt->execute([$start_date, $end_date, $company_id]);
        $employee_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($employee_data as $row) {
            fputcsv($output, [
                $row['name'],
                $row['position'],
                $row['total_hours'] ?? 0,
                $row['monthly_salary']
            ]);
        }
    } elseif ($report_type === 'overview') {
        // Overview report headers
        fputcsv($output, ['Metric', 'Value']);
        
        if ($is_super_admin) {
            // System-wide overview
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies");
            $stmt->execute();
            $companies = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees");
            $stmt->execute();
            $employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines");
            $stmt->execute();
            $machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            fputcsv($output, ['Total Companies', $companies]);
            fputcsv($output, ['Total Employees', $employees]);
            fputcsv($output, ['Total Machines', $machines]);
        } else {
            // Company-specific overview
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            fputcsv($output, ['Total Employees', $employees]);
            fputcsv($output, ['Total Machines', $machines]);
        }
    }
    
    fclose($output);
}
?>