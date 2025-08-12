<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

requireAuth();
requireAnyRole(['company_admin','super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$contract_id = (int)($_GET['contract_id'] ?? 0);
$machine_id = (int)($_GET['machine_id'] ?? 0);

$target = 'view.php?id=' . $contract_id;

if ($contract_id <= 0 || $machine_id <= 0) {
    echo "<script>window.location.href='" . $target . "&error=" . urlencode('Invalid parameters') . "';</script>"; exit;
}

try {
    // Verify contract belongs to company
    $st = $conn->prepare('SELECT id, machine_id FROM contracts WHERE id=? AND company_id=?');
    $st->execute([$contract_id, $company_id]);
    $contract = $st->fetch(PDO::FETCH_ASSOC);
    if (!$contract) { throw new Exception('Contract not found'); }
    if ((int)$contract['machine_id'] === $machine_id) { throw new Exception('Cannot remove primary machine'); }

    // Remove link
    $del = $conn->prepare('DELETE FROM contract_machines WHERE company_id=? AND contract_id=? AND machine_id=?');
    $del->execute([$company_id, $contract_id, $machine_id]);

    echo "<script>window.location.href='" . $target . "&msg=" . urlencode('Machine removed from contract') . "';</script>"; exit;
} catch (Exception $e) {
    echo "<script>window.location.href='" . $target . "&error=" . urlencode($e->getMessage()) . "';</script>"; exit;
}