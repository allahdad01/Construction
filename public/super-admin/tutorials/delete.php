<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    $st = $conn->prepare('SELECT file_path FROM tutorials WHERE id=?');
    $st->execute([$id]);
    $fp = $st->fetchColumn();
    $del = $conn->prepare('DELETE FROM tutorials WHERE id=?');
    $del->execute([$id]);
    if ($fp) {
        $abs = __DIR__ . '/../../../' . $fp;
        if (file_exists($abs) && is_file($abs)) { @unlink($abs); }
    }
}
header('Location: index.php?msg=' . urlencode('Tutorial deleted'));
exit;