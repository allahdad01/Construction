<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireAuth();
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { throw new Exception('Invalid method'); }
    $db = new Database();
    $conn = $db->getConnection();
    $user = getCurrentUser();
    if (($user['role'] ?? '') !== 'company_admin') { http_response_code(403); echo json_encode(['success'=>false]); exit; }
    $userId = (int)$user['id'];
    $companyId = (int)getCurrentCompanyId();

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

    $body = file_get_contents('php://input');
    $sub = json_decode($body, true);
    if (!$sub || empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
        throw new Exception('Invalid subscription');
    }
    $stmt = $conn->prepare('INSERT INTO push_subscriptions (user_id, company_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), company_id = VALUES(company_id), p256dh = VALUES(p256dh), auth = VALUES(auth)');
    $stmt->execute([$userId, $companyId, $sub['endpoint'], $sub['keys']['p256dh'], $sub['keys']['auth']]);

    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}