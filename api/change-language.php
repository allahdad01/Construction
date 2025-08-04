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
        throw new Exception('Language parameter is required');
    }
    
    $language_code = $input['language'];
    
    // Validate language exists in database
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM languages WHERE language_code = ? AND is_active = 1");
    $stmt->execute([$language_code]);
    $language = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$language) {
        throw new Exception('Invalid language code');
    }
    
    // Store language preference in session
    $_SESSION['current_language'] = $language_code;
    
    // If user is logged in, update their company settings
    if (isset($_SESSION['user_id']) && isset($_SESSION['company_id'])) {
        $company_id = $_SESSION['company_id'];
        
        // Update company language setting
        $stmt = $conn->prepare("
            INSERT INTO company_settings (company_id, setting_key, setting_value) 
            VALUES (?, 'default_language_id', ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$company_id, $language['id'], $language['id']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Language changed successfully',
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