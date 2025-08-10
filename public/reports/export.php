<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Auth first
requireAuth();

$db = new Database();
$conn = $db->getConnection();

$report_type = $_GET['type'] ?? '';
$format = strtolower($_GET['format'] ?? 'csv');
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

if (!in_array($report_type, ['overview', 'financial', 'employee', 'contract', 'machine'])) {
    header('Location: /constract360/construction/public/reports/');
    exit;
}

// Company/Tenant info for header
$company = null;
if ($company_id) {
    $stmt = $conn->prepare("SELECT company_name, company_code, address, phone, email FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$filename = "construction_report_{$report_type}_{$start_date}_to_{$end_date}";

if ($format === 'pdf') {
    // Return styled HTML; user can print/save as PDF. Proper PDF generation requires a library.
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
    echo exportHtml($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id, $company);
    exit;
}

if ($format === 'excel') {
    // HTML table with Excel MIME so it opens in Excel
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    echo exportExcelHtml($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id, $company);
    exit;
}

// Default CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
exportCSV($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id, $company);
exit;

function renderHeaderBlock($company, $report_type, $start_date, $end_date) {
    $tenant = $company ? htmlspecialchars($company['company_name']) : 'All Tenants';
    $code = $company && !empty($company['company_code']) ? ' | ' . htmlspecialchars($company['company_code']) : '';
    $addr = $company && !empty($company['address']) ? '<div><small>' . htmlspecialchars($company['address']) . '</small></div>' : '';
    $phone = $company && !empty($company['phone']) ? '<small>Phone: ' . htmlspecialchars($company['phone']) . '</small>' : '';
    $email = $company && !empty($company['email']) ? '<small> | Email: ' . htmlspecialchars($company['email']) . '</small>' : '';

    return "
        <div style=\"border-bottom:1px solid #ddd; margin-bottom:12px; padding-bottom:8px;\">
            <h2 style=\"margin:0;\">Construction Report</h2>
            <div style=\"margin-top:4px;\"><strong>Tenant:</strong> {$tenant}{$code}</div>
            {$addr}
            <div style=\"margin-top:6px;\"><strong>Type:</strong> " . ucfirst($report_type) . " | <strong>Period:</strong> {$start_date} to {$end_date}</div>
            <div style=\"margin-top:2px; color:#666;\"><small>Generated: " . date('Y-m-d H:i:s') . "</small> {$phone}{$email}</div>
        </div>
    ";
}

function exportHtml($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id, $company) {
    ob_start();
    echo "<html><head><meta charset='UTF-8'><style>
        body{font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#333;padding:16px}
        table{width:100%;border-collapse:collapse;margin:12px 0}
        th,td{border:1px solid #ddd;padding:8px;text-align:left}
        th{background:#f5f6fa}
        h3{margin:12px 0 6px}
    </style></head><body>";

    echo renderHeaderBlock($company, $report_type, $start_date, $end_date);

    // Render content by report type
    switch ($report_type) {
        case 'financial':
            renderFinancialTables($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'employee':
            renderEmployeeTable($conn, $start_date, $end_date, $company_id);
            break;
        case 'overview':
            renderOverviewTable($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'contract':
            renderContractTable($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'machine':
            renderMachineTable($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
    }

    echo "</body></html>";
    return ob_get_clean();
}

function exportExcelHtml($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id, $company) {
    ob_start();
    echo renderHeaderBlock($company, $report_type, $start_date, $end_date);
    echo "<table>";

    switch ($report_type) {
        case 'financial':
            renderFinancialRows($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'employee':
            renderEmployeeRows($conn, $start_date, $end_date, $company_id);
            break;
        case 'overview':
            renderOverviewRows($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'contract':
            renderContractRows($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'machine':
            renderMachineRows($conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
    }

    echo "</table>";
    return ob_get_clean();
}

function exportCSV($conn, $report_type, $start_date, $end_date, $is_super_admin, $company_id, $company) {
    $out = fopen('php://output', 'w');
    // Header rows
    fputcsv($out, ['Construction Report']);
    fputcsv($out, ['Type', ucfirst($report_type)]);
    fputcsv($out, ['Period', $start_date . ' to ' . $end_date]);
    if ($company) {
        fputcsv($out, ['Tenant', $company['company_name'] . (empty($company['company_code']) ? '' : (' (' . $company['company_code'] . ')'))]);
    } else {
        fputcsv($out, ['Tenant', 'All Tenants']);
    }
    fputcsv($out, []);

    switch ($report_type) {
        case 'financial':
            writeFinancialCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'employee':
            writeEmployeeCSV($out, $conn, $start_date, $end_date, $company_id);
            break;
        case 'overview':
            writeOverviewCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'contract':
            writeContractCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
        case 'machine':
            writeMachineCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id);
            break;
    }

    fclose($out);
}

// Financial helpers
function renderFinancialTables($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<h3>Financial Summary</h3>";
    echo "<table><thead><tr><th>Date</th><th>Currency</th><th>Revenue</th><th>Expenses</th><th>Salary</th><th>Net</th></tr></thead><tbody>";

    if ($is_super_admin) {
        $stmt = $conn->prepare("SELECT DATE(payment_date) as date, currency, SUM(amount) as revenue FROM company_payments WHERE payment_date BETWEEN ? AND ? AND payment_status='completed' GROUP BY DATE(payment_date), currency ORDER BY date");
        $stmt->execute([$start_date, $end_date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            echo "<tr><td>{$r['date']}</td><td>" . htmlspecialchars($r['currency'] ?? 'USD') . "</td><td>" . number_format($r['revenue'],2) . "</td><td>0.00</td><td>0.00</td><td>" . number_format($r['revenue'],2) . "</td></tr>";
        }
    } else {
        // revenue
        $stmt = $conn->prepare("SELECT DATE(cp.payment_date) as date, COALESCE(cp.currency, c.currency, 'USD') as currency, SUM(cp.amount) as revenue FROM contract_payments cp JOIN contracts c ON cp.contract_id = c.id WHERE cp.company_id = ? AND cp.status='completed' AND cp.payment_date BETWEEN ? AND ? GROUP BY DATE(cp.payment_date), COALESCE(cp.currency, c.currency, 'USD') ORDER BY date");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // expenses
        $stmt = $conn->prepare("SELECT DATE(expense_date) as date, COALESCE(currency,'USD') as currency, SUM(amount) as expenses FROM expenses WHERE company_id = ? AND expense_date BETWEEN ? AND ? GROUP BY DATE(expense_date), COALESCE(currency,'USD') ORDER BY date");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // salary
        $stmt = $conn->prepare("SELECT DATE(payment_date) as date, COALESCE(currency,'USD') as currency, SUM(amount_paid) as salary FROM salary_payments WHERE company_id = ? AND payment_date BETWEEN ? AND ? GROUP BY DATE(payment_date), COALESCE(currency,'USD') ORDER BY date");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge by date+currency
        $map = [];
        foreach ($revenues as $r) { $k = $r['date'].'|'.$r['currency']; $map[$k]['rev'] = ($map[$k]['rev'] ?? 0) + $r['revenue']; $map[$k]['cur'] = $r['currency']; $map[$k]['date'] = $r['date']; }
        foreach ($expenses as $e) { $k = $e['date'].'|'.$e['currency']; $map[$k]['exp'] = ($map[$k]['exp'] ?? 0) + $e['expenses']; $map[$k]['cur'] = $e['currency']; $map[$k]['date'] = $e['date']; }
        foreach ($salaries as $s) { $k = $s['date'].'|'.$s['currency']; $map[$k]['sal'] = ($map[$k]['sal'] ?? 0) + $s['salary']; $map[$k]['cur'] = $s['currency']; $map[$k]['date'] = $s['date']; }

        ksort($map);
        foreach ($map as $row) {
            $rev = $row['rev'] ?? 0; $exp = $row['exp'] ?? 0; $sal = $row['sal'] ?? 0; $net = $rev - $exp - $sal;
            echo "<tr><td>{$row['date']}</td><td>" . htmlspecialchars($row['cur']) . "</td><td>" . number_format($rev,2) . "</td><td>" . number_format($exp,2) . "</td><td>" . number_format($sal,2) . "</td><td>" . number_format($net,2) . "</td></tr>";
        }
    }

    echo "</tbody></table>";
}

function renderEmployeeTable($conn, $start_date, $end_date, $company_id) {
    echo "<h3>Employees</h3>";
    echo "<table><thead><tr><th>Name</th><th>Position</th><th>Total Hours</th><th>Monthly Salary</th></tr></thead><tbody>";
    $stmt = $conn->prepare("SELECT e.name, e.position, SUM(wh.hours_worked) as total_hours, e.monthly_salary FROM employees e LEFT JOIN working_hours wh ON e.id = wh.employee_id AND wh.date BETWEEN ? AND ? WHERE e.company_id = ? AND e.is_active = 1 GROUP BY e.id ORDER BY total_hours DESC");
    $stmt->execute([$start_date, $end_date, $company_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "<tr><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['position']) . "</td><td>" . number_format($row['total_hours'] ?? 0, 1) . "</td><td>" . number_format($row['monthly_salary'] ?? 0, 2) . "</td></tr>";
    }
    echo "</tbody></table>";
}

function renderOverviewTable($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<h3>Overview</h3>";
    echo "<table><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>";
    if ($is_super_admin) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies");
        $stmt->execute(); $companies = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees");
        $stmt->execute(); $employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines");
        $stmt->execute(); $machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<tr><td>Total Companies</td><td>" . number_format($companies) . "</td></tr>";
        echo "<tr><td>Total Employees</td><td>" . number_format($employees) . "</td></tr>";
        echo "<tr><td>Total Machines</td><td>" . number_format($machines) . "</td></tr>";
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ?");
        $stmt->execute([$company_id]); $employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ?");
        $stmt->execute([$company_id]); $machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        echo "<tr><td>Total Employees</td><td>" . number_format($employees) . "</td></tr>";
        echo "<tr><td>Total Machines</td><td>" . number_format($machines) . "</td></tr>";
    }
    echo "</tbody></table>";
}

function renderContractTable($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<h3>Contracts</h3>";
    echo "<table><thead><tr><th>Code</th><th>Type</th><th>Project</th><th>Hours</th><th>Earnings</th></tr></thead><tbody>";
    $sql = "SELECT ct.contract_code, ct.contract_type, COALESCE(p.name, p.project_name) as project_name, SUM(wh.hours_worked) as total_hours, SUM(wh.hours_worked * ct.rate_amount / NULLIF(COALESCE(ct.working_hours_per_day,8),0)) as earnings FROM contracts ct LEFT JOIN projects p ON ct.project_id = p.id LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if (!$is_super_admin) { $sql .= " WHERE ct.company_id = ?"; $params[] = $company_id; }
    $sql .= " GROUP BY ct.id ORDER BY earnings DESC";
    $stmt = $conn->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "<tr><td>" . htmlspecialchars($row['contract_code']) . "</td><td>" . htmlspecialchars($row['contract_type']) . "</td><td>" . htmlspecialchars($row['project_name'] ?? 'N/A') . "</td><td>" . number_format($row['total_hours'] ?? 0, 1) . "</td><td>" . number_format($row['earnings'] ?? 0, 2) . "</td></tr>";
    }
    echo "</tbody></table>";
}

function renderMachineTable($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<h3>Machines</h3>";
    echo "<table><thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Total Hours</th><th>Earnings</th></tr></thead><tbody>";
    $sql = "SELECT m.machine_code, m.name, m.type, SUM(wh.hours_worked) as total_hours, SUM(wh.hours_worked * ct.rate_amount / NULLIF(COALESCE(ct.working_hours_per_day,8),0)) as earnings FROM machines m LEFT JOIN contracts ct ON m.id = ct.machine_id LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if (!$is_super_admin) { $sql .= " WHERE m.company_id = ?"; $params[] = $company_id; }
    $sql .= " GROUP BY m.id ORDER BY total_hours DESC";
    $stmt = $conn->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "<tr><td>" . htmlspecialchars($row['machine_code']) . "</td><td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td><td>" . htmlspecialchars($row['type']) . "</td><td>" . number_format($row['total_hours'] ?? 0, 1) . "</td><td>" . number_format($row['earnings'] ?? 0, 2) . "</td></tr>";
    }
    echo "</tbody></table>";
}

function renderFinancialRows($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<tr><th>Date</th><th>Currency</th><th>Revenue</th><th>Expenses</th><th>Salary</th><th>Net</th></tr>";
    ob_start(); renderFinancialTables($conn, $start_date, $end_date, $is_super_admin, $company_id); $html = ob_get_clean();
    // Extract rows from table
    if (preg_match('/<tbody>(.*)<\/tbody>/s', $html, $m)) {
        echo $m[1];
    }
}

function renderEmployeeRows($conn, $start_date, $end_date, $company_id) {
    echo "<tr><th>Name</th><th>Position</th><th>Total Hours</th><th>Monthly Salary</th></tr>";
    $stmt = $conn->prepare("SELECT e.name, e.position, SUM(wh.hours_worked) as total_hours, e.monthly_salary FROM employees e LEFT JOIN working_hours wh ON e.id = wh.employee_id AND wh.date BETWEEN ? AND ? WHERE e.company_id = ? AND e.is_active = 1 GROUP BY e.id ORDER BY total_hours DESC");
    $stmt->execute([$start_date, $end_date, $company_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "<tr><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['position']) . "</td><td>" . number_format($row['total_hours'] ?? 0, 1) . "</td><td>" . number_format($row['monthly_salary'] ?? 0, 2) . "</td></tr>";
    }
}

function renderOverviewRows($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<tr><th>Metric</th><th>Value</th></tr>";
    ob_start(); renderOverviewTable($conn, $start_date, $end_date, $is_super_admin, $company_id); $html = ob_get_clean();
    if (preg_match('/<tbody>(.*)<\/tbody>/s', $html, $m)) { echo $m[1]; }
}

function renderContractRows($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<tr><th>Code</th><th>Type</th><th>Project</th><th>Hours</th><th>Earnings</th></tr>";
    ob_start(); renderContractTable($conn, $start_date, $end_date, $is_super_admin, $company_id); $html = ob_get_clean();
    if (preg_match('/<tbody>(.*)<\/tbody>/s', $html, $m)) { echo $m[1]; }
}

function renderMachineRows($conn, $start_date, $end_date, $is_super_admin, $company_id) {
    echo "<tr><th>Code</th><th>Name</th><th>Type</th><th>Total Hours</th><th>Earnings</th></tr>";
    ob_start(); renderMachineTable($conn, $start_date, $end_date, $is_super_admin, $company_id); $html = ob_get_clean();
    if (preg_match('/<tbody>(.*)<\/tbody>/s', $html, $m)) { echo $m[1]; }
}

function writeFinancialCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id) {
    fputcsv($out, ['Date','Currency','Revenue','Expenses','Salary','Net']);
    if ($is_super_admin) {
        $stmt = $conn->prepare("SELECT DATE(payment_date) as date, currency, SUM(amount) as revenue FROM company_payments WHERE payment_date BETWEEN ? AND ? AND payment_status='completed' GROUP BY DATE(payment_date), currency ORDER BY date");
        $stmt->execute([$start_date, $end_date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            fputcsv($out, [$r['date'], $r['currency'] ?? 'USD', number_format($r['revenue'],2), '0.00', '0.00', number_format($r['revenue'],2)]);
        }
    } else {
        $stmt = $conn->prepare("SELECT DATE(cp.payment_date) as date, COALESCE(cp.currency, c.currency, 'USD') as currency, SUM(cp.amount) as revenue FROM contract_payments cp JOIN contracts c ON cp.contract_id = c.id WHERE cp.company_id = ? AND cp.status='completed' AND cp.payment_date BETWEEN ? AND ? GROUP BY DATE(cp.payment_date), COALESCE(cp.currency, c.currency, 'USD') ORDER BY date");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $conn->prepare("SELECT DATE(expense_date) as date, COALESCE(currency,'USD') as currency, SUM(amount) as expenses FROM expenses WHERE company_id = ? AND expense_date BETWEEN ? AND ? GROUP BY DATE(expense_date), COALESCE(currency,'USD') ORDER BY date");
        $stmt->execute([$company_id, $start_date, $end_date]); $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $conn->prepare("SELECT DATE(payment_date) as date, COALESCE(currency,'USD') as currency, SUM(amount_paid) as salary FROM salary_payments WHERE company_id = ? AND payment_date BETWEEN ? AND ? GROUP BY DATE(payment_date), COALESCE(currency,'USD') ORDER BY date");
        $stmt->execute([$company_id, $start_date, $end_date]); $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($revenues as $r) { $k = $r['date'].'|'.$r['currency']; $map[$k]['rev'] = ($map[$k]['rev'] ?? 0) + $r['revenue']; $map[$k]['cur'] = $r['currency']; $map[$k]['date'] = $r['date']; }
        foreach ($expenses as $e) { $k = $e['date'].'|'.$e['currency']; $map[$k]['exp'] = ($map[$k]['exp'] ?? 0) + $e['expenses']; $map[$k]['cur'] = $e['currency']; $map[$k]['date'] = $e['date']; }
        foreach ($salaries as $s) { $k = $s['date'].'|'.$s['currency']; $map[$k]['sal'] = ($map[$k]['sal'] ?? 0) + $s['salary']; $map[$k]['cur'] = $s['currency']; $map[$k]['date'] = $s['date']; }
        ksort($map);
        foreach ($map as $row) {
            $rev = $row['rev'] ?? 0; $exp = $row['exp'] ?? 0; $sal = $row['sal'] ?? 0; $net = $rev - $exp - $sal;
            fputcsv($out, [$row['date'], $row['cur'], number_format($rev,2), number_format($exp,2), number_format($sal,2), number_format($net,2)]);
        }
    }
}

function writeEmployeeCSV($out, $conn, $start_date, $end_date, $company_id) {
    fputcsv($out, ['Name','Position','Total Hours','Monthly Salary']);
    $stmt = $conn->prepare("SELECT e.name, e.position, SUM(wh.hours_worked) as total_hours, e.monthly_salary FROM employees e LEFT JOIN working_hours wh ON e.id = wh.employee_id AND wh.date BETWEEN ? AND ? WHERE e.company_id = ? AND e.is_active = 1 GROUP BY e.id ORDER BY total_hours DESC");
    $stmt->execute([$start_date, $end_date, $company_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['name'], $row['position'], number_format($row['total_hours'] ?? 0, 1), number_format($row['monthly_salary'] ?? 0, 2)]);
    }
}

function writeOverviewCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id) {
    fputcsv($out, ['Metric','Value']);
    if ($is_super_admin) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies"); $stmt->execute(); $companies = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees"); $stmt->execute(); $employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines"); $stmt->execute(); $machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        fputcsv($out, ['Total Companies', number_format($companies)]);
        fputcsv($out, ['Total Employees', number_format($employees)]);
        fputcsv($out, ['Total Machines', number_format($machines)]);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ?"); $stmt->execute([$company_id]); $employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ?"); $stmt->execute([$company_id]); $machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        fputcsv($out, ['Total Employees', number_format($employees)]);
        fputcsv($out, ['Total Machines', number_format($machines)]);
    }
}

function writeContractCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id) {
    fputcsv($out, ['Code','Type','Project','Hours','Earnings']);
    $sql = "SELECT ct.contract_code, ct.contract_type, COALESCE(p.name, p.project_name) as project_name, SUM(wh.hours_worked) as total_hours, SUM(wh.hours_worked * ct.rate_amount / NULLIF(COALESCE(ct.working_hours_per_day,8),0)) as earnings FROM contracts ct LEFT JOIN projects p ON ct.project_id = p.id LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if (!$is_super_admin) { $sql .= " WHERE ct.company_id = ?"; $params[] = $company_id; }
    $sql .= " GROUP BY ct.id ORDER BY earnings DESC";
    $stmt = $conn->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['contract_code'], $row['contract_type'], $row['project_name'] ?? 'N/A', number_format($row['total_hours'] ?? 0, 1), number_format($row['earnings'] ?? 0, 2)]);
    }
}

function writeMachineCSV($out, $conn, $start_date, $end_date, $is_super_admin, $company_id) {
    fputcsv($out, ['Code','Name','Type','Total Hours','Earnings']);
    $sql = "SELECT m.machine_code, m.name, m.type, SUM(wh.hours_worked) as total_hours, SUM(wh.hours_worked * ct.rate_amount / NULLIF(COALESCE(ct.working_hours_per_day,8),0)) as earnings FROM machines m LEFT JOIN contracts ct ON m.id = ct.machine_id LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
    if (!$is_super_admin) { $sql .= " WHERE m.company_id = ?"; $params[] = $company_id; }
    $sql .= " GROUP BY m.id ORDER BY total_hours DESC";
    $stmt = $conn->prepare($sql); $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [$row['machine_code'], $row['name'] ?? 'N/A', $row['type'], number_format($row['total_hours'] ?? 0, 1), number_format($row['earnings'] ?? 0, 2)]);
    }
}
?>