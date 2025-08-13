<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/webpush.php';
requireAuth();
header('Content-Type: application/json');

try {
    $db = new Database();
    $conn = $db->getConnection();
    $user = getCurrentUser();
    if (($user['role'] ?? '') !== 'company_admin') { http_response_code(403); echo json_encode(['success'=>false]); exit; }
    $keys = getVapidKeys($conn);
    echo json_encode(['success'=>true, 'publicKey'=>$keys['public']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}