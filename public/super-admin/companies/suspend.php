<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['id'] ?? 0);

if (!$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Handle suspension
try {
    // Update company status to suspended
    $stmt = $conn->prepare("UPDATE companies SET subscription_status = 'suspended', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$company_id]);
    
    // Log the action (you can add a logs table if needed)
    
    // Redirect with success message
    header('Location: /constract360/construction/public/super-admin/companies/?success=Company suspended successfully');
    exit;
} catch (Exception $e) {
    // Redirect with error message
    header('Location: /constract360/construction/public/super-admin/companies/?error=Error suspending company: ' . $e->getMessage());
    exit;
}
?>