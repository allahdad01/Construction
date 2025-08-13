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

// Handle deletion
try {
    $stmt = $conn->prepare("DELETE FROM company_payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    
    $_SESSION['success_message'] = 'Payment deleted successfully';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error deleting payment: ' . $e->getMessage();
}

header("Location: payments.php?company_id=$company_id");
exit;
?>