<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');

$file = basename($_GET['file'] ?? '');
$path = __DIR__ . '/../../../public/backups/' . $file;
if ($file && is_file($path)) { @unlink($path); }
header('Location: index.php?msg=' . urlencode('Backup deleted'));
exit;