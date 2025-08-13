<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireAnyRole(['company_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$format = $_GET['format'] ?? 'csv';

require_once __DIR__ . '/_export_common.php';
$filename = 'employee_report_' . $start_date . '_to_' . $end_date;
sendDownloadHeaders($format, $filename);

// Detect optional columns on employees
$colsStmt = $conn->query("SHOW COLUMNS FROM employees");
$cols = array_map(function($r){ return $r['Field']; }, $colsStmt->fetchAll(PDO::FETCH_ASSOC));
$hasSalaryCurrency = in_array('salary_currency', $cols, true);
$salaryCurrencyExpr = $hasSalaryCurrency ? "COALESCE(e.salary_currency, 'AFN') as salary_currency" : "'AFN' as salary_currency";

$sql = "SELECT e.employee_code, e.name, e.position, COALESCE(SUM(wh.hours_worked),0) as total_hours, e.monthly_salary, {$salaryCurrencyExpr} FROM employees e LEFT JOIN working_hours wh ON e.id = wh.employee_id AND wh.date BETWEEN ? AND ? WHERE e.company_id = ? AND e.is_active = 1 AND e.position = 'driver' GROUP BY e.id ORDER BY total_hours DESC";
$stmt = $conn->prepare($sql);
$stmt->execute([$start_date, $end_date, $company_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'pdf' || $format === 'excel') {
    $company = getCurrentCompany();
    echo exportHeaderHtml($company, 'Employee Report', $start_date, $end_date);
    echo "<table><thead><tr><th>Code</th><th>Name</th><th>Position</th><th>Total Hours</th><th>Monthly Salary</th></tr></thead><tbody>";
    foreach ($rows as $r) {
        echo "<tr><td>".htmlspecialchars($r['employee_code'])."</td><td>".htmlspecialchars($r['name'])."</td><td>".htmlspecialchars($r['position'])."</td><td>".number_format((float)$r['total_hours'],1)."</td><td>".htmlspecialchars($r['salary_currency'])." ".number_format((float)$r['monthly_salary'],2)."</td></tr>";
    }
    echo "</tbody></table></body></html>";
    exit;
}

// CSV
$out = fopen('php://output', 'w');
fputcsv($out, []);
fputcsv($out, ['Code','Name','Position','Total Hours','Monthly Salary']);
foreach ($rows as $r) {
    fputcsv($out, [$r['employee_code'], $r['name'], $r['position'], number_format((float)$r['total_hours'],1), $r['salary_currency'] . ' ' . number_format((float)$r['monthly_salary'],2)]);
}
fclose($out);