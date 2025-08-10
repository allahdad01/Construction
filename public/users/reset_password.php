<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    requireAuth();
    requireAnyRole(['company_admin', 'super_admin']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    $db = new Database();
    $conn = $db->getConnection();
    $company_id = getCurrentCompanyId();

    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $newPassword = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
    if (!$userId) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Invalid user']); exit; }

    // Ensure user belongs to this company
    $stmt = $conn->prepare('SELECT id, email FROM users WHERE id = ? AND company_id = ?');
    $stmt->execute([$userId, $company_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'User not found']); exit; }

    // Validate provided password
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters long.']);
        exit;
    }

    // Hash password
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $upd = $conn->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
    $upd->execute([$hash, $userId, $company_id]);

    echo json_encode(['success'=>true,'message'=>'Password reset successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error']);
}