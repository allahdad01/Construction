<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

requireAuth();
requireAnyRole(['driver','driver_assistant']);

$db = new Database();
$conn = $db->getConnection();
$user = getCurrentUser();
$company_id = getCurrentCompanyId();

// Resolve employee for current user with dynamic columns
$cols = [];
try {
    $rs = $conn->query("SHOW COLUMNS FROM employees");
    foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $c) { $cols[] = $c['Field']; }
} catch (Exception $e) {}
$selFields = ['id','name','position'];
if (in_array('salary_amount', $cols, true)) { $selFields[] = 'salary_amount'; }
if (in_array('salary_currency', $cols, true)) { $selFields[] = 'salary_currency'; }
$empSql = 'SELECT ' . implode(',', $selFields) . ' FROM employees WHERE company_id = ? AND user_id = ? LIMIT 1';
$empStmt = $conn->prepare($empSql);
$empStmt->execute([$company_id, $user['id']]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

$page_title = __('my_salary_payments');

$payments = [];
if ($employee) {
    $stmt = $conn->prepare("SELECT id, payment_code, payment_month, payment_year, amount_paid, currency, payment_date FROM salary_payments WHERE company_id=? AND employee_id=? ORDER BY payment_year DESC, payment_month DESC, id DESC");
    $stmt->execute([$company_id, $employee['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0"><?php echo __('my_salary'); ?></h3>
  </div>

  <?php if (!$employee): ?>
    <div class="alert alert-warning"><?php echo __('no_employee_record_linked_to_your_user_account'); ?></div>
  <?php else: ?>
    <div class="row g-4 mb-3">
      <div class="col-md-4">
        <div class="card"><div class="card-body">
          <div class="text-muted"><?php echo __('position'); ?></div>
          <div><strong><?php echo htmlspecialchars($employee['position'] ?? ''); ?></strong></div>
        </div></div>
      </div>
      <?php if (isset($employee['salary_amount'])): ?>
      <div class="col-md-4">
        <div class="card"><div class="card-body">
          <div class="text-muted"><?php echo __('base_salary'); ?></div>
          <div><strong><?php echo formatCurrencyAmount((float)($employee['salary_amount'] ?? 0), $employee['salary_currency'] ?? 'USD'); ?></strong></div>
        </div></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="card-header"><?php echo __('payments'); ?></div>
      <div class="card-body table-responsive">
        <table class="table table-striped">
          <thead><tr><th><?php echo __('code'); ?></th><th><?php echo __('period'); ?></th><th class="text-end"><?php echo __('amount'); ?></th><th><?php echo __('date'); ?></th></tr></thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="4" class="text-muted"><?php echo __('no_salary_payments_found'); ?></td></tr>
            <?php else: foreach ($payments as $p): ?>
              <tr>
                <td><?php echo htmlspecialchars($p['payment_code'] ?? ('#'.$p['id'])); ?></td>
                <td><?php echo sprintf('%02d/%d', (int)($p['payment_month'] ?? 0), (int)($p['payment_year'] ?? 0)); ?></td>
                <td class="text-end"><?php echo formatCurrencyAmount((float)$p['amount_paid'], $p['currency'] ?? 'USD'); ?></td>
                <td><?php echo htmlspecialchars($p['payment_date'] ?? ''); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>