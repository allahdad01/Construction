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

// Resolve employee for current user
$empStmt = $conn->prepare('SELECT id, name FROM employees WHERE company_id = ? AND user_id = ? LIMIT 1');
$empStmt->execute([$company_id, $user['id']]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'My Leave Days';

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$leaves = [];
if ($employee) {
    $stmt = $conn->prepare("SELECT date, leave_type, notes FROM employee_attendance WHERE company_id = ? AND employee_id = ? AND status = 'leave' AND date BETWEEN ? AND ? ORDER BY date DESC");
    $stmt->execute([$company_id, $employee['id'], $start, $end]);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">My Leave Days</h3>
  </div>

  <?php if (!$employee): ?>
    <div class="alert alert-warning">No employee record linked to your user account.</div>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-header">Filter</div>
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Start</label>
            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">End</label>
            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end); ?>">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> Apply</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Leave Records</div>
      <div class="card-body table-responsive">
        <table class="table table-striped">
          <thead><tr><th>Date</th><th>Type</th><th>Notes</th></tr></thead>
          <tbody>
            <?php if (empty($leaves)): ?>
              <tr><td colspan="3" class="text-muted">No leave days for the selected period.</td></tr>
            <?php else: foreach ($leaves as $lv): ?>
              <tr>
                <td><?php echo htmlspecialchars($lv['date']); ?></td>
                <td><?php echo htmlspecialchars($lv['leave_type'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($lv['notes'] ?? ''); ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>