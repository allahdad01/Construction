<?php
require_once __DIR__ . '/_export_common.php';

$conn = getDbConnection();
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$format = strtolower($_GET['format'] ?? 'csv');

$company = getTenantInfo($conn, $company_id);
$filename = "machine_{$start_date}_to_{$end_date}";
sendDownloadHeaders($format, $filename);

$sql = "SELECT m.machine_code, COALESCE(m.name, m.machine_name) as name, m.type, SUM(wh.hours_worked) as total_hours, SUM(wh.hours_worked * ct.rate_amount / NULLIF(COALESCE(ct.working_hours_per_day,8),0)) as earnings, COALESCE(ct.currency,'USD') as currency FROM machines m LEFT JOIN contracts ct ON m.id = ct.machine_id LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?";
$params = [$start_date, $end_date];
if (!$is_super_admin) { $sql .= " WHERE m.company_id = ?"; $params[] = $company_id; }
$sql .= " GROUP BY m.id ORDER BY total_hours DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'pdf' || $format === 'excel') {
    echo "<html><head><meta charset='UTF-8'><style>body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;padding:16px}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f6fa}h3{margin:12px 0 6px}</style></head><body>";
    echo exportHeaderHtml($company, 'Machine Report', $start_date, $end_date);
    echo "<table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Total Hours</th><th>Earnings</th></tr></thead><tbody>";
    foreach ($rows as $r) {
        echo "<tr><td>".htmlspecialchars($r['machine_code'])."</td><td>".htmlspecialchars($r['name'] ?? 'N/A')."</td><td>".htmlspecialchars($r['type'])."</td><td>".number_format((float)($r['total_hours'] ?? 0),1)."</td><td>".htmlspecialchars($r['currency'])." ".number_format((float)($r['earnings'] ?? 0),2)."</td></tr>";
    }
    echo "</tbody></table></body></html>";
    exit;
}

$out = fopen('php://output', 'w');
csvReportPreamble($out, 'Machine Report', $company);
fputcsv($out, ['Period', $start_date . ' to ' . $end_date]);
fputcsv($out, []);
fputcsv($out, ['Code','Name','Type','Total Hours','Earnings']);
foreach ($rows as $r) {
    fputcsv($out, [$r['machine_code'], $r['name'] ?? 'N/A', $r['type'], number_format((float)($r['total_hours'] ?? 0),1), ($r['currency'] ?? 'USD') . ' ' . number_format((float)($r['earnings'] ?? 0),2)]);
}
fclose($out);