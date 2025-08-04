<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$language_id = (int)($_GET['language_id'] ?? 0);
$format = $_GET['format'] ?? 'csv';

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

// Get translations
$stmt = $conn->prepare("SELECT translation_key, translation_value FROM language_translations WHERE language_id = ? ORDER BY translation_key");
$stmt->execute([$language_id]);
$translations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="translations_' . $language['language_code'] . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Add header row
    fputcsv($output, ['translation_key', 'translation_value']);
    
    // Add data rows
    foreach ($translations as $translation) {
        fputcsv($output, [$translation['translation_key'], $translation['translation_value']]);
    }
    
    fclose($output);
    exit;
} elseif ($format === 'json') {
    // Set headers for JSON download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="translations_' . $language['language_code'] . '.json"');
    
    // Create JSON output
    $json_data = [];
    foreach ($translations as $translation) {
        $json_data[$translation['translation_key']] = $translation['translation_value'];
    }
    
    echo json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
} else {
    // Invalid format
    header('Location: /constract360/construction/public/super-admin/languages/');
    exit;
}
?>