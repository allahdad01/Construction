<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$user_id = (int)($_GET['id'] ?? 0);
$company_id = (int)($_GET['company_id'] ?? 0);

if (!$user_id || !$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle activation
try {
    $stmt = $conn->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
    
    $_SESSION['success_message'] = 'User activated successfully';
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error activating user: ' . $e->getMessage();
}

header("Location: user-view.php?id=$user_id&company_id=$company_id");
exit;
?>