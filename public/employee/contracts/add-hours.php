<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';
require_once '../../../includes/header.php';
requireAuth();
requireAnyRole(['driver']);

require_once '../../../config/notifications.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();
$user = getCurrentUser();

// Resolve current employee
$empStmt = $conn->prepare('SELECT id, name FROM employees WHERE company_id = ? AND user_id = ? LIMIT 1');
$empStmt->execute([$company_id, $user['id']]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);
if (!$employee) { echo '<div class="container-fluid"><div class="alert alert-warning">No employee record linked to your account.</div></div>'; require_once '../../../includes/footer.php'; exit; }

$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;
if (!$contract_id) { header('Location: ../'); exit; }

// Load contract
$stmt = $conn->prepare("SELECT c.*, p.name as project_name, m.name as machine_name FROM contracts c LEFT JOIN projects p ON c.project_id=p.id LEFT JOIN machines m ON c.machine_id=m.id WHERE c.id=? AND c.company_id=?");
$stmt->execute([$contract_id, $company_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$contract) { header('Location: ../'); exit; }

// Fetch contract machines list
$contract_machine_ids = [];
try {
    $st = $conn->prepare('SELECT machine_id FROM contract_machines WHERE company_id = ? AND contract_id = ?');
    $st->execute([$company_id, $contract_id]);
    $contract_machine_ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'machine_id'));
} catch (Exception $e) {}
if (!in_array((int)($contract['machine_id'] ?? 0), $contract_machine_ids, true) && !empty($contract['machine_id'])) {
    $contract_machine_ids[] = (int)$contract['machine_id'];
}

// Verify this driver is actively assigned to any machine of this contract
$assigned_machine_ids = [];
if (!empty($contract_machine_ids)) {
    $ph = implode(',', array_fill(0, count($contract_machine_ids), '?'));
    $params = array_merge([$company_id, $employee['id']], $contract_machine_ids);
    $sql = "SELECT DISTINCT ma.machine_id FROM machine_assignments ma WHERE ma.company_id=? AND ma.driver_employee_id=? AND ma.status='active' AND ma.machine_id IN ($ph)";
    $st = $conn->prepare($sql);
    $st->execute($params);
    $assigned_machine_ids = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'machine_id'));
}
if (empty($assigned_machine_ids)) {
    echo '<div class="container-fluid"><div class="alert alert-danger">You are not assigned as driver to any machine of this contract.</div></div>'; require_once '../../../includes/footer.php'; exit;
}

// Compute rate per hour
$rate_per_hour = 0.0;
if ($contract['contract_type'] === 'hourly') { $rate_per_hour = (float)$contract['rate_amount']; }
elseif ($contract['contract_type'] === 'daily') { $rate_per_hour = (float)$contract['rate_amount'] / max(1,(int)$contract['working_hours_per_day']); }
else { $rate_per_hour = (float)$contract['rate_amount'] / max(1,(int)($contract['total_hours_required'] ?: 270)); }
$contract_currency = $contract['currency'] ?? 'USD';

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $hours_worked = (float)($_POST['hours_worked'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $selected_machine_id = isset($_POST['machine_id']) ? (int)$_POST['machine_id'] : 0;

    if (!$date) { $error = __('please_select_a_date'); }
    elseif ($hours_worked <= 0) { $error = __('hours_worked_must_be_greater_than_0'); }
    elseif ($hours_worked > 24) { $error = __('hours_worked_cannot_exceed_24_hours_per_day'); }
    elseif ($selected_machine_id > 0 && !in_array($selected_machine_id, $assigned_machine_ids, true)) { $error = __('invalid_machine_selection'); }
    else {
        // Ensure unique per day
        $chk = $conn->prepare('SELECT id FROM working_hours WHERE company_id=? AND contract_id=? AND employee_id=? AND date=?');
        $chk->execute([$company_id, $contract_id, $employee['id'], $date]);
        if ($chk->fetch()) { $error = 'You already have an entry for this date.'; }
        else {
            try {
                $ins = $conn->prepare('INSERT INTO working_hours (company_id, contract_id, machine_id, employee_id, date, hours_worked, notes) VALUES (?,?,?,?,?,?,?)');
                $ins->execute([$company_id, $contract_id, ($selected_machine_id ?: $assigned_machine_ids[0]), $employee['id'], $date, $hours_worked, $notes]);
                $success = 'Hours added successfully';
                $_POST = [];

                // Notify tenant admins that hours were added
                try {
                    $title = 'Hours Added';
                    $msg = 'Driver ' . ($employee['name'] ?? ('#'.$employee['id'])) . ' added ' . number_format($hours_worked, 2) . ' hours on ' . $date . ' for contract ' . ($contract['contract_code'] ?? ('#'.$contract_id)) . '.';
                    notifyCompanyUsers($conn, (int)$company_id, $title, $msg, 'info');
                } catch (Throwable $nt) {}
            } catch (Exception $e) { $error = 'Failed to add hours: ' . $e->getMessage(); }
        }
    }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo __('add_work_hours'); ?></h1>
    <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> <?php echo __('back_to_timesheet'); ?></a>
  </div>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_information'); ?></h6></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong><?php echo __('contract'); ?>:</strong></td><td><?php echo htmlspecialchars($contract['contract_code'] ?? ('#'.$contract_id)); ?></td></tr>
            <tr><td><strong><?php echo __('project'); ?>:</strong></td><td><?php echo htmlspecialchars($contract['project_name'] ?? ''); ?></td></tr>
            <tr><td><strong><?php echo __('machine'); ?>(s):</strong></td><td>
              <?php
                // Display assigned machines for this driver on this contract
                $ph = implode(',', array_fill(0, count($assigned_machine_ids), '?'));
                $st2 = $conn->prepare("SELECT machine_code, name FROM machines WHERE id IN ($ph)");
                $st2->execute($assigned_machine_ids);
                $names = [];
                foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $row) { $names[] = $row['machine_code'].' - '.$row['name']; }
                echo htmlspecialchars(implode(', ', $names));
              ?>
            </td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong><?php echo __('type'); ?>:</strong></td><td><?php echo ucfirst($contract['contract_type']); ?></td></tr>
            <tr><td><strong><?php echo __('rate'); ?>:</strong></td><td><?php echo formatCurrencyAmount((float)$contract['rate_amount'], $contract_currency); ?></td></tr>
            <tr><td><strong><?php echo __('rate_per_hour'); ?>:</strong></td><td><strong><?php echo formatCurrencyAmount($rate_per_hour, $contract_currency); ?></strong></td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('add_work_hours'); ?></h6></div>
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-4">
            <label class="form-label"><?php echo __('date'); ?> *</label>
            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label"><?php echo __('hours_worked'); ?> *</label>
            <input type="number" class="form-control" name="hours_worked" step="0.5" min="0.5" max="24" value="<?php echo htmlspecialchars($_POST['hours_worked'] ?? ''); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label"><?php echo __('daily_amount'); ?></label>
            <input type="text" class="form-control" id="daily_amount" readonly>
            <small class="text-muted"><?php echo __('calculated_automatically'); ?></small>
          </div>
        </div>
        <?php if (count($assigned_machine_ids) > 1): ?>
        <div class="row mt-3">
          <div class="col-md-6">
            <label class="form-label"><?php echo __('machine'); ?> *</label>
            <select class="form-control" name="machine_id" required>
              <?php
                $st3 = $conn->prepare("SELECT id, machine_code, name FROM machines WHERE id IN (".implode(',', array_fill(0, count($assigned_machine_ids), '?')).")");
                $st3->execute($assigned_machine_ids);
                foreach ($st3->fetchAll(PDO::FETCH_ASSOC) as $row):
              ?>
                <option value="<?php echo $row['id']; ?>" <?php echo (!empty($_POST['machine_id']) && $_POST['machine_id']==$row['id'])?'selected':''; ?>><?php echo htmlspecialchars($row['machine_code'].' - '.$row['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <?php endif; ?>
        <div class="mb-3 mt-3">
          <label class="form-label"><?php echo __('notes'); ?></label>
          <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2">
          <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> <?php echo __('cancel'); ?></a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('add_hours'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  const hoursInput = document.querySelector('input[name="hours_worked"]');
  const amountEl = document.getElementById('daily_amount');
  const ratePerHour = <?php echo json_encode($rate_per_hour); ?>;
  function recalc(){
    const h = parseFloat(hoursInput.value||'0');
    const amt = (h*ratePerHour)||0;
    amountEl.value = amt.toFixed(2);
  }
  hoursInput && hoursInput.addEventListener('input', recalc);
  recalc();
})();
</script>
<?php require_once '../../../includes/footer.php'; ?>