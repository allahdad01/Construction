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
    $stmt = $conn->prepare("SELECT company_name, company_code, address, phone, email FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function exportHeaderHtml($company, $report_title, $start_date, $end_date) {
    $tenant = $company ? htmlspecialchars($company['company_name']) : 'All Tenants';
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
        fputcsv($out, ['Tenant', $company['company_name'] . (empty($company['company_code']) ? '' : (' (' . $company['company_code'] . ')'))]);
        if (!empty($company['address'])) fputcsv($out, ['Address', $company['address']]);
        $contact = trim(($company['phone'] ?? '') . (empty($company['email']) ? '' : (' | ' . $company['email'])));
        if ($contact !== '') fputcsv($out, ['Contact', $contact]);
    } else {
        fputcsv($out, ['Tenant', 'All Tenants']);
    }
}