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
    // Fetch keys without generating
    $pub = getSystemSettingLocal($conn, 'vapid_public_key', '');
    $priv = getSystemSettingLocal($conn, 'vapid_private_key', '');
    $subj = getSystemSettingLocal($conn, 'vapid_subject', '');
    if (!$pub || !$priv) {
        echo json_encode(['success'=>false, 'code'=>'vapid_not_configured']);
        exit;
    }
    echo json_encode(['success'=>true, 'publicKey'=>$pub]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success'=>false, 'code'=>'vapid_error']);
}