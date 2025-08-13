<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$payment_id = (int)($_GET['id'] ?? 0);
$company_id = (int)($_GET['company_id'] ?? 0);

if (!$payment_id || !$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get payment details
$stmt = $conn->prepare("SELECT * FROM company_payments WHERE id = ? AND company_id = ?");
$stmt->execute([$payment_id, $company_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Handle approval
try {
    $stmt = $conn->prepare("UPDATE company_payments SET payment_status = 'completed', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$payment_id]);
    
    $_SESSION['success_message'] = 'Payment approved successfully';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error approving payment: ' . $e->getMessage();
}

header("Location: payment-view.php?id=$payment_id&company_id=$company_id");
exit;
?>