<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');

$file = basename($_GET['file'] ?? '');
$path = __DIR__ . '/../../../public/backups/' . $file;
if ($file && is_file($path)) {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}
header('Location: index.php');
exit;