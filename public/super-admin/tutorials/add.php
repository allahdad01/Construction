<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireRole('super_admin');
// Reuse edit form for adding (no id)
$_GET['id'] = 0;
require_once __DIR__ . '/edit.php';