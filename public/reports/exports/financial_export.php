<?php
require_once __DIR__ . '/_export_common.php';

$conn = getDbConnection();
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$format = strtolower($_GET['format'] ?? 'csv');

$company = getTenantInfo($conn, $company_id);
$filename = "financial_{$start_date}_to_{$end_date}";
sendDownloadHeaders($format, $filename);

// Prepare datasets
if ($is_super_admin) {
    $revenues = [];
    $stmt = $conn->prepare("SELECT DATE(payment_date) as date, currency, SUM(amount) as revenue FROM company_payments WHERE payment_date BETWEEN ? AND ? AND payment_status='completed' GROUP BY DATE(payment_date), currency ORDER BY date");
    $stmt->execute([$start_date, $end_date]);
    $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT DATE(cp.payment_date) as date, COALESCE(cp.currency,c.currency,'USD') as currency, SUM(cp.amount) as revenue FROM contract_payments cp JOIN contracts c ON cp.contract_id = c.id WHERE cp.company_id = ? AND cp.status='completed' AND cp.payment_date BETWEEN ? AND ? GROUP BY DATE(cp.payment_date), COALESCE(cp.currency,c.currency,'USD') ORDER BY date");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $conn->prepare("SELECT DATE(expense_date) as date, COALESCE(currency,'USD') as currency, SUM(amount) as expenses FROM expenses WHERE company_id = ? AND expense_date BETWEEN ? AND ? GROUP BY DATE(expense_date), COALESCE(currency,'USD') ORDER BY date");
    $stmt->execute([$company_id, $start_date, $end_date]); $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $conn->prepare("SELECT DATE(payment_date) as date, COALESCE(currency,'USD') as currency, SUM(amount_paid) as salary FROM salary_payments WHERE company_id = ? AND payment_date BETWEEN ? AND ? GROUP BY DATE(payment_date), COALESCE(currency,'USD') ORDER BY date");
    $stmt->execute([$company_id, $start_date, $end_date]); $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Merge
    $map = [];
    foreach ($revenues as $r){ $k=$r['date'].'|'.$r['currency']; $map[$k]['rev']=($map[$k]['rev']??0)+$r['revenue']; $map[$k]['date']=$r['date']; $map[$k]['cur']=$r['currency']; }
    foreach ($expenses as $e){ $k=$e['date'].'|'.$e['currency']; $map[$k]['exp']=($map[$k]['exp']??0)+$e['expenses']; $map[$k]['date']=$e['date']; $map[$k]['cur']=$e['currency']; }
    foreach ($salaries as $s){ $k=$s['date'].'|'.$s['currency']; $map[$k]['sal']=($map[$k]['sal']??0)+$s['salary']; $map[$k]['date']=$s['date']; $map[$k]['cur']=$s['currency']; }
}

if ($format === 'pdf' || $format === 'excel') {
    echo "<html><head><meta charset='UTF-8'><style>body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;padding:16px}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f6fa}h3{margin:12px 0 6px}</style></head><body>";
    echo exportHeaderHtml($company, 'Financial Report', $start_date, $end_date);
    echo "<h3>Summary by Day</h3><table><thead><tr><th>Date</th><th>Currency</th><th>Revenue</th><th>Expenses</th><th>Salary</th><th>Net</th></tr></thead><tbody>";
    if ($is_super_admin) {
        foreach ($revenues as $r) {
            $rev=(float)($r['revenue']??0); echo "<tr><td>{$r['date']}</td><td>".htmlspecialchars($r['currency']??'USD')."</td><td>".number_format($rev,2)."</td><td>0.00</td><td>0.00</td><td>".number_format($rev,2)."</td></tr>";
        }
    } else {
        ksort($map);
        foreach ($map as $row) {
            $rev=$row['rev']??0; $exp=$row['exp']??0; $sal=$row['sal']??0; $net=$rev-$exp-$sal;
            echo "<tr><td>{$row['date']}</td><td>".htmlspecialchars($row['cur'])."</td><td>".number_format($rev,2)."</td><td>".number_format($exp,2)."</td><td>".number_format($sal,2)."</td><td>".number_format($net,2)."</td></tr>";
        }
    }
    echo "</tbody></table></body></html>";
    exit;
}

$out=fopen('php://output','w');
csvReportPreamble($out, 'Financial Report', $company);
fputcsv($out, ['Period', $start_date . ' to ' . $end_date]);
fputcsv($out, []);
fputcsv($out, ['Date','Currency','Revenue','Expenses','Salary','Net']);
if ($is_super_admin) {
    foreach ($revenues as $r) {
        $rev=(float)($r['revenue']??0); fputcsv($out, [$r['date'],$r['currency']??'USD',number_format($rev,2),'0.00','0.00',number_format($rev,2)]);
    }
} else {
    ksort($map);
    foreach ($map as $row) {
        $rev=$row['rev']??0; $exp=$row['exp']??0; $sal=$row['sal']??0; $net=$rev-$exp-$sal;
        fputcsv($out, [$row['date'],$row['cur'],number_format($rev,2),number_format($exp,2),number_format($sal,2),number_format($net,2)]);
    }
}
fclose($out);