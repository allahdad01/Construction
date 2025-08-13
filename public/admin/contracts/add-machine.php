<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

requireAuth();
requireAnyRole(['company_admin','super_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;
if (!$contract_id) { header('Location: index.php'); exit; }

// Ensure link table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS contract_machines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        contract_id INT NOT NULL,
        machine_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_contract_machine (company_id, contract_id, machine_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Load contract
$stmt = $conn->prepare("SELECT id, contract_code, project_id, machine_id FROM contracts WHERE id = ? AND company_id = ?");
$stmt->execute([$contract_id, $company_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contract) { header('Location: index.php'); exit; }

// Machines already linked
$linked_ids = [];
try {
    $st = $conn->prepare('SELECT machine_id FROM contract_machines WHERE company_id=? AND contract_id=?');
    $st->execute([$company_id, $contract_id]);
    $linked_ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'machine_id'));
} catch (Exception $e) {}
if (!in_array((int)$contract['machine_id'], $linked_ids, true)) {
    $linked_ids[] = (int)$contract['machine_id'];
}

// Load available machines (exclude already linked)
$placeholders = implode(',', array_fill(0, count($linked_ids) ?: 1, '?'));
$params = array_merge([$company_id], $linked_ids ?: [0]);
$sql = "SELECT id, machine_code, name, type FROM machines WHERE company_id = ? AND status = 'available'";
if (!empty($linked_ids)) { $sql .= " AND id NOT IN ($placeholders)"; }
$sql .= " ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$available_machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $machine_ids = $_POST['machine_ids'] ?? [];
    if (empty($machine_ids) || !is_array($machine_ids)) {
        $error = 'Please select at least one machine to add.';
    } else {
        try {
            $conn->beginTransaction();
            $ins = $conn->prepare('INSERT IGNORE INTO contract_machines (company_id, contract_id, machine_id) VALUES (?,?,?)');
            $upd = $conn->prepare("UPDATE machines SET status = 'in_use' WHERE id = ?");
            foreach ($machine_ids as $mid) {
                $mid = (int)$mid; if ($mid <= 0) { continue; }
                $ins->execute([$company_id, $contract_id, $mid]);
                $upd->execute([$mid]);
            }
            $conn->commit();
            $success = 'Machines added to contract.';
            echo "<script>setTimeout(function(){ window.location.href='view.php?id=" . (int)$contract_id . "'; }, 1500);</script>";
        } catch (Exception $e) {
            if ($conn->inTransaction()) { $conn->rollBack(); }
            $error = 'Failed to add machines: ' . $e->getMessage();
        }
    }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-plus"></i> <?php echo __('add_machine_to_contract'); ?></h1>
    <a href="view.php?id=<?php echo (int)$contract_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <div class="card">
    <div class="card-header"><?php echo __('contract'); ?>: <?php echo htmlspecialchars($contract['contract_code']); ?></div>
    <div class="card-body">
      <form method="POST">
        <div class="mb-3">
          <label class="form-label"><?php echo __('select_machines'); ?> (<?php echo __('available'); ?>)</label>
          <select class="form-control" name="machine_ids[]" multiple required>
            <?php foreach ($available_machines as $m): ?>
              <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['machine_code'] . ' - ' . $m['name'] . ' (' . $m['type'] . ')'); ?></option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted"><?php echo __('hold_ctrl_cmd_to_select_multiple'); ?></small>
        </div>
        <div class="text-end">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('add'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>