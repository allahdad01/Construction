<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Auth
requireAuth();

function getDbConnection() {
    static $conn = null;
    if ($conn === null) {
        $db = new Database();
        $conn = $db->getConnection();
    }
    return $conn;
}

function getTenantInfo(PDO $conn, $company_id) {
    if (!$company_id) { return null; }
    // Detect available columns to avoid unknown column errors
    $stmtCols = $conn->query("SHOW COLUMNS FROM companies");
    $existingCols = array_map(function($r){ return $r['Field']; }, $stmtCols->fetchAll(PDO::FETCH_ASSOC));

    $wanted = [
        'company_name' => 'company_name',
        'company_code' => 'company_code',
        'address' => 'address',
        'phone' => 'phone',
        'email' => 'email',
    ];
    $selectParts = [];
    foreach ($wanted as $alias => $col) {
        if (in_array($col, $existingCols, true)) {
            $selectParts[] = "$col as $alias";
        } else {
            $selectParts[] = "NULL as $alias";
        }
    }
    // Fallback if company_name does not exist
    if (!in_array('company_name', $existingCols, true)) {
        // Try generic name column
        if (in_array('name', $existingCols, true)) {
            // Replace the NULL as company_name with name as company_name
            foreach ($selectParts as $i => $part) {
                if ($part === 'NULL as company_name') { $selectParts[$i] = 'name as company_name'; break; }
            }
        }
    }

    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM companies WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute([$company_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Ensure keys exist
    $defaults = [
        'company_name' => null,
        'company_code' => null,
        'address' => null,
        'phone' => null,
        'email' => null,
    ];
    return array_merge($defaults, $row);
}

function exportHeaderHtml($company, $report_title, $start_date, $end_date) {
    $tenant = $company && !empty($company['company_name']) ? htmlspecialchars($company['company_name']) : 'All Tenants';
    $code = $company && !empty($company['company_code']) ? ' | ' . htmlspecialchars($company['company_code']) : '';
    $addr = $company && !empty($company['address']) ? '<div><small>' . htmlspecialchars($company['address']) . '</small></div>' : '';
    $phone = $company && !empty($company['phone']) ? '<small>Phone: ' . htmlspecialchars($company['phone']) . '</small>' : '';
    $email = $company && !empty($company['email']) ? '<small> | Email: ' . htmlspecialchars($company['email']) . '</small>' : '';

    return "
        <div style=\"border-bottom:1px solid #ddd; margin-bottom:12px; padding-bottom:8px;\">
            <h2 style=\"margin:0;\">{$report_title}</h2>
            <div style=\"margin-top:4px;\"><strong>Tenant:</strong> {$tenant}{$code}</div>
            {$addr}
            <div style=\"margin-top:6px;\"><strong>Period:</strong> {$start_date} to {$end_date}</div>
            <div style=\"margin-top:2px; color:#666;\"><small>Generated: " . date('Y-m-d H:i:s') . "</small> {$phone}{$email}</div>
        </div>
    ";
}

function sendDownloadHeaders($format, $filename_base) {
    $format = strtolower($format);
    if ($format === 'pdf') {
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $filename_base . '.pdf"');
    } elseif ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.xls"');
    } else { // csv
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename_base . '.csv"');
    }
}

function csvReportPreamble($out, $report_title, $company) {
    fputcsv($out, [$report_title]);
    if ($company) {
        $label = ($company['company_name'] ?? '') . (empty($company['company_code']) ? '' : (' (' . $company['company_code'] . ')'));
        fputcsv($out, ['Tenant', trim($label) !== '' ? $label : 'N/A']);
        if (!empty($company['address'])) fputcsv($out, ['Address', $company['address']]);
        $contact = trim(($company['phone'] ?? '') . (empty($company['email']) ? '' : (' | ' . $company['email'])));
        if ($contact !== '') fputcsv($out, ['Contact', $contact]);
    } else {
        fputcsv($out, ['Tenant', 'All Tenants']);
    }
}