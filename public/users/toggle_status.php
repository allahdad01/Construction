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
    $status = isset($_POST['status']) ? trim(strtolower($_POST['status'])) : '';

    if (!$userId || !in_array($status, ['active', 'inactive'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    // Prevent deactivating own account to avoid lockout
    $currentUser = getCurrentUser();
    if ($currentUser && (int)$currentUser['id'] === $userId) {
        echo json_encode(['success' => false, 'message' => 'You cannot change status of your own account.']);
        exit;
    }

    // Ensure user belongs to this company
    $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? AND company_id = ?');
    $stmt->execute([$userId, $company_id]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $isActive = $status === 'active' ? 1 : 0;
    $upd = $conn->prepare("UPDATE users SET status = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
    $upd->execute([$status, $isActive, $userId, $company_id]);

    echo json_encode(['success' => true, 'message' => 'User status updated', 'status' => $status]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}