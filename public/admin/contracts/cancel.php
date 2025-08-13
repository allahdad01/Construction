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
        $st = $conn->prepare('SELECT id, status, machine_id FROM contracts WHERE id=? AND company_id=? LIMIT 1');
        $st->execute([$contract_id, $company_id]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) { throw new Exception('Contract not found.'); }
        if ($c['status'] === 'cancelled') { throw new Exception('Contract is already cancelled.'); }

        // Collect all machines linked to this contract (primary + contract_machines)
        $machineIds = [];
        if (!empty($c['machine_id'])) { $machineIds[] = (int)$c['machine_id']; }
        try {
            $st2 = $conn->prepare('SELECT machine_id FROM contract_machines WHERE company_id=? AND contract_id=?');
            $st2->execute([$company_id, $contract_id]);
            foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) { $mid = (int)$row['machine_id']; if ($mid) { $machineIds[] = $mid; } }
        } catch (Exception $e) {}
        $machineIds = array_values(array_unique($machineIds));

        // Transaction: update contract status, end assignments, free machines
        $conn->beginTransaction();

        // Mark contract cancelled and set end_date to today
        $conn->prepare("UPDATE contracts SET status='cancelled', end_date = CURRENT_DATE WHERE id=? AND company_id=?")
             ->execute([$contract_id, $company_id]);

        if (!empty($machineIds)) {
            $ph = implode(',', array_fill(0, count($machineIds), '?'));
            $params = $machineIds;
            // End active assignments for these machines
            $sqlEndAssign = "UPDATE machine_assignments SET status='ended', end_date = CURRENT_DATE 
                             WHERE company_id=? AND status='active' AND machine_id IN ($ph)";
            $conn->prepare($sqlEndAssign)->execute(array_merge([$company_id], $params));
            // Free machines (set available)
            $sqlFree = "UPDATE machines SET status='available' WHERE id IN ($ph)";
            $conn->prepare($sqlFree)->execute($params);
        }

        $conn->commit();
        $success = 'Contract cancelled and machines freed.';
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $error = $e->getMessage();
    }
}

$target = 'index.php';
if ($success) { $target .= '?msg=' . urlencode($success); }
if ($error) { $target .= (strpos($target,'?')!==false?'&':'?') . 'error=' . urlencode($error); }
echo "<script>window.location.href='" . $target . "';</script>";
exit;