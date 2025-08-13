<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$language_id = (int)($_GET['id'] ?? 0);

if (!$language_id) {
    header('Location: /constract360/construction/public/super-admin/languages/');
    exit;
}

// Get language details
$stmt = $conn->prepare("SELECT * FROM languages WHERE id = ?");
$stmt->execute([$language_id]);
$language = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$language) {
    header('Location: /constract360/construction/public/super-admin/languages/');
    exit;
}

// Check if it's the default language
if ($language['is_default']) {
    header('Location: /constract360/construction/public/super-admin/languages/?error=Cannot delete the default language');
    exit;
}

// Handle deletion
try {
    // Start transaction
    $conn->beginTransaction();
    
    // Delete all translations for this language
    $stmt = $conn->prepare("DELETE FROM language_translations WHERE language_id = ?");
    $stmt->execute([$language_id]);
    
    // Delete the language
    $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
    $stmt->execute([$language_id]);
    
    // Commit transaction
    $conn->commit();
    
    // Redirect with success message
    header('Location: /constract360/construction/public/super-admin/languages/?success=Language deleted successfully');
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    
    // Redirect with error message
    header('Location: /constract360/construction/public/super-admin/languages/?error=Error deleting language: ' . $e->getMessage());
    exit;
}
?>