<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$expense_id = (int)($_GET['id'] ?? 0);

if (!$expense_id) {
    header('Location: index.php');
    exit;
}

// Get expense details - only super admin expenses (company_id = 1)
$stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ? AND company_id = 1");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Delete the expense
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND company_id = 1");
        $stmt->execute([$expense_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Expense deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete expense.";
        }
        
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting expense: " . $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

// If not POST, redirect to index
header('Location: index.php');
exit;
?>