<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/mailer.php';

header('Content-Type: application/json');

try {
    requireAuth();
    requireAnyRole(['company_admin', 'super_admin']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

    $to = trim($_POST['to'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid email']); exit; }

    $db = new Database();
    $conn = $db->getConnection();
    $company_id = getCurrentCompanyId();

    $html = '<p>This is a test email from the SMTP settings.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';
    $err = null;
    $ok = sendCompanyEmail($conn, (int)$company_id, $to, 'SMTP Test Email', $html, null, $err);
    echo json_encode(['success'=>$ok, 'message'=>$ok ? 'Test email sent.' : ('Failed to send email: ' . ($err ?: 'unknown error'))]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
}