<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireAuth();
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Ensure table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $currentUser = getCurrentUser();
    $userId = (int)($currentUser['id'] ?? 0);
    $role = $currentUser['role'] ?? '';
    if ($userId <= 0) { throw new Exception('Unauthorized'); }
    if ($role !== 'company_admin') { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); return; }

    // Fetch latest notifications
    $limit = 20;
    $stmt = $conn->prepare('SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int)$limit);
    $stmt->execute([$userId]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Unread count
    $cstmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $cstmt->execute([$userId]);
    $unread = (int)$cstmt->fetchColumn();

    echo json_encode(['success' => true, 'notifications' => $list, 'unread_count' => $unread]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}