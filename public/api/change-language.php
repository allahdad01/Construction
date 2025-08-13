<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireAuth();
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $lang = preg_replace('/[^a-zA-Z_-]/', '', $input['language'] ?? '') ?: null;
    if (!$lang) { throw new Exception('Invalid language'); }

    $_SESSION['language_code'] = $lang;
    echo json_encode(['success' => true, 'message' => 'Language changed.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}