<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/config.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['language'])) {
        throw new Exception(__('language_parameter_required'));
    }
    
    $language_code = $input['language'];
    
    // Validate language exists in database
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM languages WHERE language_code = ? AND is_active = 1");
    $stmt->execute([$language_code]);
    $language = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$language) {
        throw new Exception(__('invalid_language'));
    }
    
    // Use the enhanced changeLanguage function
    changeLanguage($language['id']);
    
    echo json_encode([
        'success' => true,
        'message' => __('language_changed_successfully'),
        'language' => $language_code,
        'language_name' => $language['language_name_native']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>