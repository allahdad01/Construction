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

$page_title = __('employee_dashboard');

// Resolve employee and pay settings
$emp = null; $cols = [];
try { $rs = $conn->query("SHOW COLUMNS FROM employees"); foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $c) { $cols[] = $c['Field']; } } catch (Exception $e) {}
$empStmt = $conn->prepare('SELECT * FROM employees WHERE company_id = ? AND user_id = ? LIMIT 1');
$empStmt->execute([$company_id, $user['id']]);
$emp = $empStmt->fetch(PDO::FETCH_ASSOC);

$today = new DateTime();
$monthStart = new DateTime(date('Y-m-01'));
// Start from hire date if available and later than month start
if ($emp && in_array('hire_date', $cols, true) && !empty($emp['hire_date'])) {
    try { $hd = new DateTime($emp['hire_date']); if ($hd > $monthStart) { $monthStart = $hd; } } catch (Exception $e) {}
}
$startStr = $monthStart->format('Y-m-d');
$endStr = $today->format('Y-m-d');

$daysInRange = (int)$monthStart->diff($today)->days + 1;
// Leave days in range
$leaveDays = 0;
if ($emp) {
    $st = $conn->prepare("SELECT COUNT(*) FROM employee_attendance WHERE company_id = ? AND employee_id = ? AND status='leave' AND date BETWEEN ? AND ?");
    $st->execute([$company_id, $emp['id'], $startStr, $endStr]);
    $leaveDays = (int)$st->fetchColumn();
}
$workedDays = max(0, $daysInRange - $leaveDays);

// Determine daily rate and currency
$currency = 'USD';
$dailyRate = 0.0;
if ($emp) {
    if (in_array('salary_currency', $cols, true) && !empty($emp['salary_currency'])) { $currency = $emp['salary_currency']; }
    if (in_array('daily_rate', $cols, true) && isset($emp['daily_rate']) && $emp['daily_rate'] > 0) {
        $dailyRate = (float)$emp['daily_rate'];
    } elseif (in_array('monthly_salary', $cols, true) && isset($emp['monthly_salary']) && $emp['monthly_salary'] > 0) {
        $dailyRate = (float)$emp['monthly_salary'] / 30.0;
    } elseif (in_array('salary_amount', $cols, true) && isset($emp['salary_amount']) && $emp['salary_amount'] > 0) {
        $dailyRate = (float)$emp['salary_amount'] / 30.0;
    }
}
$expectedPay = $workedDays * $dailyRate;

// Paid this month in same currency
$paidThisMonth = 0.0;
if ($emp) {
    $payStmt = $conn->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM salary_payments WHERE company_id = ? AND employee_id = ? AND currency = ? AND payment_date BETWEEN ? AND ?");
    $payStmt->execute([$company_id, $emp['id'], $currency, $startStr, $endStr]);
    $paidThisMonth = (float)$payStmt->fetchColumn();
}
$remaining = max(0.0, $expectedPay - $paidThisMonth);
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h1 class="h3 mb-4"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
    </div>
  </div>

  <div class="row g-4 mb-3">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('worked_days'); ?> (<?php echo __('this_month'); ?>)</strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo number_format($workedDays); ?></div>
          <div class="text-muted small"><?php echo number_format($daysInRange); ?> <?php echo __('days_in_period'); ?> • <?php echo number_format($leaveDays); ?> <?php echo __('leave'); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('paid'); ?> (<?php echo __('this_month'); ?>)</strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo formatCurrencyAmount($paidThisMonth, $currency); ?></div>
          <div class="text-muted small"><?php echo __('currency'); ?>: <?php echo htmlspecialchars($currency); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('remaining'); ?> (<?php echo __('this_month'); ?>)</strong></div>
        <div class="card-body">
          <div class="h4 mb-0"><?php echo formatCurrencyAmount($remaining, $currency); ?></div>
          <div class="text-muted small"><?php echo __('expected'); ?>: <?php echo formatCurrencyAmount($expectedPay, $currency); ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong><?php echo __('quick_actions'); ?></strong></div>
        <div class="card-body">
          <a href="<?php echo $base_url; ?>employee/attendance/" class="btn btn-primary w-100 mb-2"><i class="fas fa-clock"></i> <?php echo __('view_attendance'); ?></a>
          <a href="<?php echo $base_url; ?>employee/salary/" class="btn btn-success w-100 mb-2"><i class="fas fa-money-bill-wave"></i> <?php echo __('view_salary'); ?></a>
          <a href="<?php echo $base_url; ?>employee/contracts/" class="btn btn-info w-100"><i class="fas fa-file-contract"></i> <?php echo __('assigned_contracts'); ?></a>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><strong><?php echo __('recent_activity'); ?></strong></div>
        <div class="card-body">
          <p class="text-muted mb-0"><?php echo __('your_recent_work_and_payments_will_appear_here'); ?></p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>