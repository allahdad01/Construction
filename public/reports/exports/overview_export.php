<?php
require_once __DIR__ . '/_export_common.php';

$conn = getDbConnection();
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$format = strtolower($_GET['format'] ?? 'csv');

$company = getTenantInfo($conn, $company_id);
$filename = "overview_{$start_date}_to_{$end_date}";
sendDownloadHeaders($format, $filename);

if ($format === 'pdf' || $format === 'excel') {
    // HTML/Excel
    echo "<html><head><meta charset='UTF-8'><style>body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;padding:16px}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f6fa}h3{margin:12px 0 6px}</style></head><body>";
    echo exportHeaderHtml($company, 'Overview Report', $start_date, $end_date);

    // Basic company stats
    if ($is_super_admin) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_companies FROM companies");
        $stmt->execute(); $total_companies = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_companies'];
        echo "<h3>System Overview</h3><table><tbody>";
        echo "<tr><td>Total Companies</td><td>" . number_format($total_companies) . "</td></tr>";
        echo "</tbody></table>";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_employees FROM employees WHERE company_id = ? AND is_active = 1");
        $stmt->execute([$company_id]); $total_employees = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total_machines FROM machines WHERE company_id = ? AND is_active = 1");
        $stmt->execute([$company_id]); $total_machines = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_machines'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total_contracts FROM contracts WHERE company_id = ? AND status = 'active'");
        $stmt->execute([$company_id]); $total_contracts = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_contracts'];
        $stmt = $conn->prepare("SELECT COALESCE(SUM(hours_worked),0) as total_hours FROM working_hours WHERE company_id = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$company_id, $start_date, $end_date]); $worked = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_hours'];
        $stmt = $conn->prepare("SELECT COALESCE(SUM( COALESCE(total_hours_required, COALESCE(working_hours_per_day,8) * GREATEST(0, DATEDIFF(LEAST(COALESCE(end_date, ?), ?), GREATEST(COALESCE(start_date, ?), ?)) + 1) )),0) as total_contract_hours FROM contracts WHERE company_id = ? AND status='active' AND COALESCE(end_date, ?) >= ? AND COALESCE(start_date, ?) <= ?");
        $stmt->execute([$end_date, $end_date, $start_date, $start_date, $company_id, $end_date, $start_date, $start_date, $end_date]);
        $contracted = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_contract_hours'];
        $remaining = max(0, $contracted - $worked);

        echo "<h3>Company Overview</h3><table><tbody>";
        echo "<tr><td>Total Employees</td><td>" . number_format($total_employees) . "</td></tr>";
        echo "<tr><td>Total Machines</td><td>" . number_format($total_machines) . "</td></tr>";
        echo "<tr><td>Active Contracts</td><td>" . number_format($total_contracts) . "</td></tr>";
        echo "<tr><td>Total Working Hours</td><td>" . number_format($worked,1) . " hrs (Worked) <div class='small'>" . number_format($contracted,1) . " hrs contracted • " . number_format($remaining,1) . " hrs remaining</div></td></tr>";
        echo "</tbody></table>";
    }
    echo "</body></html>";
    exit;
}

// CSV
$out = fopen('php://output', 'w');
csvReportPreamble($out, 'Overview Report', $company);
fputcsv($out, ['Period', $start_date . ' to ' . $end_date]);
fputcsv($out, []);

if ($is_super_admin) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total_companies FROM companies");
    $stmt->execute(); $total_companies = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_companies'];
    fputcsv($out, ['Metric','Value']);
    fputcsv($out, ['Total Companies', number_format($total_companies)]);
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total_employees FROM employees WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$company_id]); $total_employees = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];
    $stmt = $conn->prepare("SELECT COUNT(*) as total_machines FROM machines WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$company_id]); $total_machines = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_machines'];
    $stmt = $conn->prepare("SELECT COUNT(*) as total_contracts FROM contracts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]); $total_contracts = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total_contracts'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(hours_worked),0) as total_hours FROM working_hours WHERE company_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$company_id, $start_date, $end_date]); $worked = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_hours'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM( COALESCE(total_hours_required, COALESCE(working_hours_per_day,8) * GREATEST(0, DATEDIFF(LEAST(COALESCE(end_date, ?), ?), GREATEST(COALESCE(start_date, ?), ?)) + 1) )),0) as total_contract_hours FROM contracts WHERE company_id = ? AND status='active' AND COALESCE(end_date, ?) >= ? AND COALESCE(start_date, ?) <= ?");
    $stmt->execute([$end_date, $end_date, $start_date, $start_date, $company_id, $end_date, $start_date, $start_date, $end_date]);
    $contracted = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_contract_hours'];
    $remaining = max(0, $contracted - $worked);

    fputcsv($out, ['Metric','Value']);
    fputcsv($out, ['Total Employees', number_format($total_employees)]);
    fputcsv($out, ['Total Machines', number_format($total_machines)]);
    fputcsv($out, ['Active Contracts', number_format($total_contracts)]);
    fputcsv($out, ['Total Working Hours', number_format($worked,1) . ' hrs (Worked); ' . number_format($contracted,1) . ' hrs contracted; ' . number_format($remaining,1) . ' hrs remaining']);
}

fclose($out);