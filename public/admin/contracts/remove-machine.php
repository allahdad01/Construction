<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/notifications.php';

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
    $st = $conn->prepare('SELECT id FROM contracts WHERE id=? AND company_id=?');
    $st->execute([$contract_id, $company_id]);
    $contract = $st->fetch(PDO::FETCH_ASSOC);
    if (!$contract) { throw new Exception('Contract not found'); }

    // Attempt to find current driver assigned to this machine
    $drvUserId = null;
    try {
        $st2 = $conn->prepare("SELECT e.user_id FROM machine_assignments ma JOIN employees e ON ma.driver_employee_id=e.id WHERE ma.company_id=? AND ma.machine_id=? AND ma.status='active' LIMIT 1");
        $st2->execute([$company_id, $machine_id]);
        $drvUserId = (int)($st2->fetchColumn() ?: 0);
    } catch (Exception $e) {}

    // Remove link
    $del = $conn->prepare('DELETE FROM contract_machines WHERE company_id=? AND contract_id=? AND machine_id=?');
    $del->execute([$company_id, $contract_id, $machine_id]);

    // Notify driver and admins
    try { if ($drvUserId > 0) notifyUser($conn, $drvUserId, 'Machine Unlinked', 'A machine has been removed from your contract assignments.', 'warning'); } catch (Throwable $e) {}
    try { notifyCompanyUsers($conn, $company_id, 'Machine Removed', 'Machine removed from contract #' . $contract_id . '.', 'warning'); } catch (Throwable $e) {}

    echo "<script>window.location.href='" . $target . "&msg=" . urlencode('Machine removed from contract') . "';</script>"; exit;
} catch (Exception $e) {
    echo "<script>window.location.href='" . $target . "&error=" . urlencode($e->getMessage()) . "';</script>"; exit;
}