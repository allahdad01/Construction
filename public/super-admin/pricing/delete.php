<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$plan_id = (int)($_GET['id'] ?? 0);

if (!$plan_id) {
    header('Location: index.php');
    exit;
}

// Get pricing plan details
$stmt = $conn->prepare("SELECT * FROM pricing_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if any companies are using this plan
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_plan = ?");
        $stmt->execute([$plan['plan_code']]);
        $companies_using = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($companies_using > 0) {
            $_SESSION['error'] = "Cannot delete this plan because $companies_using companies are currently using it.";
            header('Location: index.php');
            exit;
        }
        
        // Delete the pricing plan
        $stmt = $conn->prepare("DELETE FROM pricing_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Pricing plan deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete pricing plan.";
        }
        
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting pricing plan: " . $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

// If not POST, redirect to index
header('Location: index.php');
exit;
?>