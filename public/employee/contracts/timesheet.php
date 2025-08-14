<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';
require_once '../../../includes/header.php';

requireAuth();
requireAnyRole(['driver']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();
$user = getCurrentUser();
$isAssistant = ($user['role'] === 'driver_assistant');

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
$contract_currency = $contract['currency'] ?? 'USD';

// Load working hours for this employee and contract
$stmt = $conn->prepare("SELECT id, date, hours_worked, notes FROM working_hours WHERE company_id=? AND contract_id=? AND employee_id=? ORDER BY date ASC");
$stmt->execute([$company_id, $contract_id, $employee['id']]);
$working_hours = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Totals
$total_hours = 0.0; $total_amount = 0.0;
$rate_per_hour = 0.0;
if ($contract['contract_type'] === 'hourly') { $rate_per_hour = (float)$contract['rate_amount']; }
elseif ($contract['contract_type'] === 'daily') { $rate_per_hour = (float)$contract['rate_amount'] / max(1,(int)$contract['working_hours_per_day']); }
else { $rate_per_hour = (float)$contract['rate_amount'] / max(1,(int)($contract['total_hours_required'] ?: 270)); }
foreach ($working_hours as $wh) { $total_hours += (float)$wh['hours_worked']; $total_amount += (float)$wh['hours_worked'] * $rate_per_hour; }
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><?php echo __('my_timesheet'); ?></h1>
    <div>
      <?php if (!$isAssistant): ?>
      <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> <?php echo __('add_hours'); ?></a>
      <?php endif; ?>
      <a href="<?php echo $base_url; ?>employee/contracts/" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> <?php echo __('back_to_contracts'); ?></a>
    </div>
  </div>

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_information'); ?></h6></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong><?php echo __('contract'); ?>:</strong></td><td><?php echo htmlspecialchars($contract['contract_code'] ?? ('#'.$contract_id)); ?></td></tr>
            <tr><td><strong><?php echo __('project'); ?>:</strong></td><td><?php echo htmlspecialchars($contract['project_name'] ?? ''); ?></td></tr>
            <tr><td><strong><?php echo __('machine'); ?>:</strong></td><td><?php echo htmlspecialchars($contract['machine_name'] ?? ''); ?></td></tr>
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

  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('daily_timesheet'); ?></h6></div>
    <div class="card-body">
      <?php if (empty($working_hours)): ?>
        <div class="text-center py-4">
          <i class="fas fa-clock fa-3x text-gray-300 mb-3"></i>
          <p class="text-gray-500"><?php echo __('no_working_hours_recorded_yet'); ?></p>
          <?php if (!$isAssistant): ?>
          <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary"><i class="fas fa-plus"></i> <?php echo __('add_first_entry'); ?></a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead><tr><th><?php echo __('date'); ?></th><th><?php echo __('hours_worked'); ?></th><th><?php echo __('rate'); ?></th><th><?php echo __('daily_amount'); ?></th><th><?php echo __('notes'); ?></th></tr></thead>
            <tbody>
              <?php foreach ($working_hours as $wh): $daily_amount = (float)$wh['hours_worked'] * $rate_per_hour; ?>
                <tr>
                  <td><?php echo date('M j, Y', strtotime($wh['date'])); ?><br><small class="text-muted"><?php echo date('l', strtotime($wh['date'])); ?></small></td>
                  <td class="text-center"><strong><?php echo number_format((float)$wh['hours_worked'],1); ?></strong> <?php echo __('hours'); ?></td>
                  <td><?php echo formatCurrencyAmount($rate_per_hour, $contract_currency); ?>/hr</td>
                  <td><strong class="text-success"><?php echo formatCurrencyAmount($daily_amount, $contract_currency); ?></strong></td>
                  <td><small class="text-muted"><?php echo htmlspecialchars($wh['notes'] ?? ''); ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="table-info"><td><strong><?php echo __('total'); ?></strong></td><td class="text-center"><strong><?php echo number_format($total_hours,1); ?> <?php echo __('hours'); ?></strong></td><td></td><td><strong><?php echo formatCurrencyAmount($total_amount, $contract_currency); ?></strong></td><td></td></tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>