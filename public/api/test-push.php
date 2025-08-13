<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/notifications.php';
requireAuth();
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user = getCurrentUser();
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) { throw new Exception('Unauthorized'); }

    // Check subscription exists
    $conn->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        company_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(200) NOT NULL,
        auth VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_endpoint (endpoint(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $st = $conn->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?');
    $st->execute([$userId]);
    $count = (int)$st->fetchColumn();
    if ($count <= 0) {
        echo json_encode(['success' => false, 'code' => 'no_subscription']);
        exit;
    }

    // Send test push
    pushSendToUser($conn, $userId, 'Test Push', 'This is a test push notification.');
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}