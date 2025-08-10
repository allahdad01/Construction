<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';
require_once '../../../includes/header.php';

requireAuth();
requireAnyRole(['driver','driver_assistant']);

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

    if (!$date) { $error = 'Please select a date.'; }
    elseif ($hours_worked <= 0) { $error = 'Hours worked must be greater than 0.'; }
    elseif ($hours_worked > 24) { $error = 'Hours worked cannot exceed 24 hours per day.'; }
    else {
        // Ensure unique per day
        $chk = $conn->prepare('SELECT id FROM working_hours WHERE company_id=? AND contract_id=? AND employee_id=? AND date=?');
        $chk->execute([$company_id, $contract_id, $employee['id'], $date]);
        if ($chk->fetch()) { $error = 'You already have an entry for this date.'; }
        else {
            try {
                $ins = $conn->prepare('INSERT INTO working_hours (company_id, contract_id, machine_id, employee_id, date, hours_worked, notes) VALUES (?,?,?,?,?,?,?)');
                $ins->execute([$company_id, $contract_id, $contract['machine_id'], $employee['id'], $date, $hours_worked, $notes]);
                $success = 'Hours added successfully';
                $_POST = [];
            } catch (Exception $e) { $error = 'Failed to add hours: ' . $e->getMessage(); }
        }
    }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Add Work Hours</h1>
    <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Timesheet</a>
  </div>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Contract Information</h6></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong>Contract:</strong></td><td><?php echo htmlspecialchars($contract['contract_code'] ?? ('#'.$contract_id)); ?></td></tr>
            <tr><td><strong>Project:</strong></td><td><?php echo htmlspecialchars($contract['project_name'] ?? ''); ?></td></tr>
            <tr><td><strong>Machine:</strong></td><td><?php echo htmlspecialchars($contract['machine_name'] ?? ''); ?></td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong>Type:</strong></td><td><?php echo ucfirst($contract['contract_type']); ?></td></tr>
            <tr><td><strong>Rate:</strong></td><td><?php echo formatCurrencyAmount((float)$contract['rate_amount'], $contract_currency); ?></td></tr>
            <tr><td><strong>Rate/Hour:</strong></td><td><strong><?php echo formatCurrencyAmount($rate_per_hour, $contract_currency); ?></strong></td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Add Work Hours</h6></div>
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-4">
            <label class="form-label">Date *</label>
            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Hours Worked *</label>
            <input type="number" class="form-control" name="hours_worked" step="0.5" min="0.5" max="24" value="<?php echo htmlspecialchars($_POST['hours_worked'] ?? ''); ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Daily Amount</label>
            <input type="text" class="form-control" id="daily_amount" readonly>
            <small class="text-muted">Calculated automatically</small>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>
        <div class="d-flex justify-content-end gap-2">
          <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Hours</button>
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