<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$tab = $_GET['tab'] ?? 'overview';

?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-chart-line"></i> <?php echo __('platform_reports'); ?></h1>
    <form method="get" class="d-flex gap-2 align-items-end">
      <div class="me-2">
        <label class="form-label mb-0 small"><?php echo __('start'); ?></label>
        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
      </div>
      <div class="me-2">
        <label class="form-label mb-0 small"><?php echo __('end'); ?></label>
        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
      </div>
      <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
      <button class="btn btn-primary"><i class="fas fa-sync"></i> <?php echo __('apply'); ?></button>
    </form>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?php echo $tab==='overview'?'active':''; ?>" href="?tab=overview&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo __('overview'); ?></a></li>
    <li class="nav-item"><a class="nav-link <?php echo $tab==='financial'?'active':''; ?>" href="?tab=financial&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>"><?php echo __('financial'); ?></a></li>
  </ul>

  <?php
  switch ($tab) {
    case 'financial':
      include __DIR__ . '/financial_report.php';
      break;
    case 'overview':
    default:
      include __DIR__ . '/overview_report.php';
      break;
  }
  ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>