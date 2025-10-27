<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$employee_id) { http_response_code(400); echo 'Missing employee id'; exit; }

// Employee core
$stmt = $conn->prepare("SELECT e.*, u.first_name, u.last_name, u.email as user_email FROM employees e LEFT JOIN users u ON e.user_id = u.id WHERE e.id = ? AND e.company_id = ?");
$stmt->execute([$employee_id, $company_id]);
$e = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$e) { http_response_code(404); echo 'Employee not found'; exit; }
$salaryCurrency = $e['salary_currency'] ?? 'AFN';
$displayName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? '')) ?: ($e['name'] ?? 'Employee');

// Attendance stats
$att = ['total_days'=>0,'present_days'=>0,'absent_days'=>0,'leave_days'=>0];
try {
  // Get attendance with suspension days excluded
  $s=$conn->prepare("
    SELECT 
      COUNT(*) total_days, 
      COUNT(CASE WHEN ea.status = 'present' THEN 1 END) present_days, 
      COUNT(CASE WHEN ea.status = 'absent' THEN 1 END) absent_days, 
      COUNT(CASE WHEN ea.status = 'leave' THEN 1 END) leave_days 
    FROM employee_attendance ea
    LEFT JOIN employee_work_suspensions ews ON 
        ea.employee_id = ews.employee_id AND 
        ea.date BETWEEN ews.suspension_start_date AND COALESCE(ews.suspension_end_date, CURRENT_DATE)
    WHERE ea.employee_id = ? AND ea.company_id = ? AND 
        (ews.id IS NULL)
  ");
  
  $s->execute([$employee_id, $company_id]);
  $att = $s->fetch(PDO::FETCH_ASSOC) ?: $att;
} catch (Exception $ex) {
  // Log or handle the exception if needed
  error_log("Attendance calculation error: " . $ex->getMessage());
}

// Worked hours & recent contracts
$stats = ['total_contracts'=>0,'active_contracts'=>0,'total_hours_worked'=>0.0];
try {
  $s=$conn->prepare("SELECT COUNT(DISTINCT wh.contract_id) total_contracts, COUNT(DISTINCT CASE WHEN c.status='active' THEN wh.contract_id END) active_contracts, COALESCE(SUM(wh.hours_worked),0) total_hours_worked FROM working_hours wh LEFT JOIN contracts c ON wh.contract_id=c.id WHERE wh.employee_id=? AND wh.company_id=?");
  $s->execute([$employee_id,$company_id]);
  $stats=$s->fetch(PDO::FETCH_ASSOC)?:$stats;
} catch(Exception $ex){}

$contracts=[];
try{
  $s=$conn->prepare("SELECT c.*, p.name as project_name FROM working_hours wh JOIN contracts c ON wh.contract_id=c.id LEFT JOIN projects p ON c.project_id=p.id WHERE wh.employee_id=? AND wh.company_id=? GROUP BY c.id ORDER BY c.created_at DESC LIMIT 8");
  $s->execute([$employee_id,$company_id]);
  $contracts=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
}catch(Exception $ex){}

// Recent salary payments
$payments=[];
try {
  $s=$conn->prepare("SELECT payment_date, amount_paid, currency, status, payment_method, notes FROM salary_payments WHERE employee_id=? AND company_id=? ORDER BY payment_date DESC LIMIT 12");
  $s->execute([$employee_id,$company_id]);
  $payments=$s->fetchAll(PDO::FETCH_ASSOC)?:[];
}catch(Exception $ex){}

// Earned vs paid this month (simple calc)
$totalPaid = 0.0; foreach ($payments as $p) { $totalPaid += (float)($p['amount_paid'] ?? 0); }
$monthlySalary = (float)($e['monthly_salary'] ?? 0);
$dailyRate = $monthlySalary/30.0;

// Calculate days worked excluding suspension days
$daysWorked = (int)($att['present_days'] ?? 0);
$earnedThisMonth = $dailyRate * $daysWorked;
$remaining = max(0.0, $earnedThisMonth - $totalPaid);

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Print Employee - <?php echo htmlspecialchars($displayName); ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color:#222; margin: 20px; }
  h2 { margin: 0 0 6px 0; }
  .muted { color:#666; }
  .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
  .card { border:1px solid #ddd; border-radius:6px; margin-bottom:16px; }
  .card-header { background:#f7f7fb; padding:10px 14px; font-weight:600; border-bottom:1px solid #e5e7eb; }
  .card-body { padding: 12px 14px; }
  table { width:100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border:1px solid #ddd; padding:6px 8px; font-size: 12px; }
  th { background:#fafbfe; text-align:left; }
  .right { text-align:right; }
  .actions { margin-bottom:12px; }
  .btn { display:inline-block; padding:8px 12px; border:1px solid #444; border-radius:4px; text-decoration:none; color:#222; }
  .btn-primary { background:#111; color:#fff; border-color:#111; }
  .small { font-size: 12px; }
  @media print { .actions { display:none; } body { margin: 12mm; } }
</style>
</head>
<body>
<div class="actions">
  <a href="javascript:window.print()" class="btn btn-primary"><?php echo __('print'); ?></a>
  <a href="view.php?id=<?php echo $employee_id; ?>" class="btn"><?php echo __('back'); ?></a>
</div>

<h2><?php echo __('employee_summary'); ?></h2>
<div class="muted"><?php echo __('printed'); ?>: <?php echo date('Y-m-d H:i'); ?></div>

<div class="card">
  <div class="card-header"><?php echo __('personal_information'); ?></div>
  <div class="card-body">
    <table>
      <tr><th style="width:28%"><?php echo __('full_name'); ?></th><td><?php echo htmlspecialchars($displayName); ?></td></tr>
      <tr><th><?php echo __('employee_code'); ?></th><td><?php echo htmlspecialchars($e['employee_code'] ?? 'N/A'); ?></td></tr>
      <tr><th><?php echo __('position'); ?></th><td><?php echo htmlspecialchars($e['position'] ?? 'N/A'); ?></td></tr>
      <tr><th><?php echo __('status'); ?></th><td><?php echo ucfirst($e['status'] ?? 'inactive'); ?></td></tr>
      <tr><th><?php echo __('email'); ?></th><td><?php echo htmlspecialchars($e['email'] ?? $e['user_email'] ?? '-'); ?></td></tr>
      <tr><th><?php echo __('phone'); ?></th><td><?php echo htmlspecialchars($e['phone'] ?? '-'); ?></td></tr>
      <tr><th><?php echo __('hire_date'); ?></th><td><?php echo !empty($e['hire_date']) ? date('M j, Y', strtotime($e['hire_date'])) : '-'; ?></td></tr>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('salary'); ?></div>
  <div class="card-body">
    <table>
      <tr><th style="width:28%"><?php echo __('monthly_salary'); ?></th><td><?php echo formatCurrencyAmount($monthlySalary, $salaryCurrency); ?></td></tr>
      <tr><th><?php echo __('daily_rate'); ?></th><td><?php echo formatCurrencyAmount($dailyRate, $salaryCurrency); ?></td></tr>
      <tr><th><?php echo __('earned'); ?> (<?php echo __('this_month'); ?>)</th><td><?php echo formatCurrencyAmount($earnedThisMonth, $salaryCurrency); ?></td></tr>
      <tr><th><?php echo __('total_paid'); ?> (<?php echo __('recent'); ?>)</th><td><?php echo formatCurrencyAmount($totalPaid, $salaryCurrency); ?></td></tr>
      <tr><th><?php echo __('remaining'); ?></th><td><?php echo formatCurrencyAmount($remaining, $salaryCurrency); ?></td></tr>
    </table>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header"><?php echo __('attendance'); ?></div>
    <div class="card-body">
      <table>
        <tr><th style="width:48%"><?php echo __('total_days'); ?></th><td><?php echo (int)($att['present_days'] ?? 0); ?></td></tr>
        <tr><th><?php echo __('present'); ?></th><td><?php echo (int)($att['present_days'] ?? 0); ?></td></tr>
        <tr><th><?php echo __('leave'); ?></th><td><?php echo (int)($att['leave_days'] ?? 0); ?></td></tr>
        <tr><th><?php echo __('absent'); ?></th><td><?php echo (int)($att['absent_days'] ?? 0); ?></td></tr>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><?php echo __('work_summary'); ?></div>
    <div class="card-body">
      <table>
        <tr><th style="width:48%"><?php echo __('total_contracts'); ?></th><td><?php echo (int)($stats['total_contracts'] ?? 0); ?></td></tr>
        <tr><th><?php echo __('active_contracts'); ?></th><td><?php echo (int)($stats['active_contracts'] ?? 0); ?></td></tr>
        <tr><th><?php echo __('total_hours_worked'); ?></th><td><?php echo number_format((float)($stats['total_hours_worked'] ?? 0), 1); ?> hrs</td></tr>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('recent_contracts'); ?></div>
  <div class="card-body">
    <?php if (empty($contracts)): ?>
      <div class="small muted"><?php echo __('no_contracts_assigned'); ?></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:40%"><?php echo __('contract'); ?></th>
            <th style="width:30%"><?php echo __('project'); ?></th>
            <th style="width:15%"><?php echo __('status'); ?></th>
            <th style="width:15%"><?php echo __('created'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($contracts as $c): ?>
          <tr>
            <td><?php echo htmlspecialchars($c['contract_name'] ?? ($c['contract_code'] ?? ('#'.$c['id']))); ?></td>
            <td><?php echo htmlspecialchars($c['project_name'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($c['status'] ?? '-'); ?></td>
            <td><?php echo !empty($c['created_at']) ? date('Y-m-d', strtotime($c['created_at'])) : '-'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('recent_salary_payments'); ?></div>
  <div class="card-body">
    <?php if (empty($payments)): ?>
      <div class="small muted"><?php echo __('no_salary_payments_found'); ?></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:18%"><?php echo __('date'); ?></th>
            <th style="width:20%"><?php echo __('method'); ?></th>
            <th style="width:42%"><?php echo __('notes'); ?></th>
            <th class="right" style="width:20%"><?php echo __('amount'); ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?php echo !empty($p['payment_date']) ? date('M j, Y', strtotime($p['payment_date'])) : '-'; ?></td>
            <td><?php echo htmlspecialchars($p['payment_method'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></td>
            <td class="right"><?php echo formatCurrencyAmount((float)($p['amount_paid'] ?? 0), $p['currency'] ?? $salaryCurrency); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>