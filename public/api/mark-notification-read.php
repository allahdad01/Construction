<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireAuth();
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Invalid method'); }

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
    if (!in_array($role, ['company_admin','driver','driver_assistant'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); return; }

    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId <= 0) { throw new Exception('Invalid notification'); }
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
    } elseif ($action === 'mark_all_read') {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
    } else {
        throw new Exception('Unknown action');
    }

    // Return latest unread count
    $cstmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $cstmt->execute([$userId]);
    $unread = (int)$cstmt->fetchColumn();

    echo json_encode(['success' => true, 'unread_count' => $unread]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}