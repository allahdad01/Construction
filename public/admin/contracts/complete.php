<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

requireAuth();
requireAnyRole(['company_admin','super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error = '';
$success = '';

if ($contract_id <= 0) {
    $error = 'Invalid contract.';
} else {
    try {
        // Verify contract belongs to company and is active
        $st = $conn->prepare('SELECT id, status FROM contracts WHERE id=? AND company_id=? LIMIT 1');
        $st->execute([$contract_id, $company_id]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) { throw new Exception('Contract not found.'); }
        if ($c['status'] === 'completed') { throw new Exception('Contract is already completed.'); }

        $upd = $conn->prepare("UPDATE contracts SET status='completed' WHERE id=? AND company_id=?");
        $upd->execute([$contract_id, $company_id]);
        $success = 'Contract marked as completed.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Redirect back to index with a message using query params
$target = 'index.php';
if ($success) { $target .= '?msg=' . urlencode($success); }
if ($error) { $target .= (strpos($target,'?')!==false?'&':'?') . 'error=' . urlencode($error); }
echo "<script>window.location.href='" . $target . "';</script>";
exit;