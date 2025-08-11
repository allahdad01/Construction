<?php
// Super Admin Overview Report
$is_super_admin = true;
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$overview = [
  'companies' => 0,
  'active_companies' => 0,
  'trial_companies' => 0,
  'users' => 0,
  'monthly_revenue' => []
];
try {
  // Companies
  $stmt = $conn->prepare("SELECT COUNT(*) FROM companies");
  $stmt->execute();
  $overview['companies'] = (int)$stmt->fetchColumn();
  $stmt = $conn->prepare("SELECT COUNT(*) FROM companies WHERE subscription_status='active'");
  $stmt->execute();
  $overview['active_companies'] = (int)$stmt->fetchColumn();
  $stmt = $conn->prepare("SELECT COUNT(*) FROM companies WHERE subscription_status='trial'");
  $stmt->execute();
  $overview['trial_companies'] = (int)$stmt->fetchColumn();
  // Users
  $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role!='super_admin'");
  $stmt->execute();
  $overview['users'] = (int)$stmt->fetchColumn();
  // Monthly platform revenue by currency
  $stmt = $conn->prepare("SELECT COALESCE(currency,'USD') currency, SUM(amount) total FROM company_payments WHERE payment_status='completed' AND payment_date BETWEEN ? AND ? GROUP BY COALESCE(currency,'USD')");
  $stmt->execute([$start_date, $end_date]);
  $overview['monthly_revenue'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}
?>
<div class="card shadow mb-4">
  <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('overview'); ?></h6></div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-3 mb-3"><div class="card border-left-primary h-100"><div class="card-body"><div class="text-xs text-primary text-uppercase mb-1"><?php echo __('companies'); ?></div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $overview['companies']; ?></div></div></div></div>
      <div class="col-md-3 mb-3"><div class="card border-left-success h-100"><div class="card-body"><div class="text-xs text-success text-uppercase mb-1"><?php echo __('active'); ?></div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $overview['active_companies']; ?></div></div></div></div>
      <div class="col-md-3 mb-3"><div class="card border-left-warning h-100"><div class="card-body"><div class="text-xs text-warning text-uppercase mb-1"><?php echo __('trial'); ?></div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $overview['trial_companies']; ?></div></div></div></div>
      <div class="col-md-3 mb-3"><div class="card border-left-info h-100"><div class="card-body"><div class="text-xs text-info text-uppercase mb-1"><?php echo __('users'); ?></div><div class="h5 mb-0 fw-bold text-gray-800"><?php echo $overview['users']; ?></div></div></div></div>
    </div>
    <h6 class="mt-3"><?php echo __('monthly_revenue'); ?></h6>
    <?php if (!empty($overview['monthly_revenue'])): ?>
      <div>
        <?php foreach ($overview['monthly_revenue'] as $i=>$r): ?>
          <span class="me-3 <?php echo $i>0?'small':''; ?>"><?php echo formatCurrencyAmount((float)$r['total'], $r['currency']); ?></span>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-muted small"><?php echo __('no_payments_in_selected_range'); ?></div>
    <?php endif; ?>
  </div>
</div>