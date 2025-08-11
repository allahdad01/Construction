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

$page_title = 'My Attendance';

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$recordsByDate = [];
if ($employee) {
    $stmt = $conn->prepare("SELECT date, status, leave_type, notes FROM employee_attendance WHERE company_id = ? AND employee_id = ? AND date BETWEEN ? AND ?");
    $stmt->execute([$company_id, $employee['id'], $start, $end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $recordsByDate[$row['date']] = $row; }
}

function generateDateRange($start, $end) {
    $dates = [];
    $cur = new DateTime($start);
    $to = new DateTime($end);
    while ($cur <= $to) { $dates[] = $cur->format('Y-m-d'); $cur->modify('+1 day'); }
    return $dates;
}

?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0"><?php echo __('my_attendance'); ?></h3>
  </div>

  <?php if (!$employee): ?>
    <div class="alert alert-warning"><?php echo __('no_employee_record_linked_to_your_user_account'); ?></div>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-header"><?php echo __('filter'); ?></div>
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-4">
            <label class="form-label"><?php echo __('start'); ?></label>
            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label"><?php echo __('end'); ?></label>
            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end); ?>">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100"><i class="fas fa-filter"></i> <?php echo __('apply'); ?></button>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><?php echo __('attendance'); ?> (<?php echo htmlspecialchars($employee['name']); ?>)</div>
      <div class="card-body table-responsive">
        <table class="table table-striped">
          <thead><tr><th><?php echo __('date'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('details'); ?></th></tr></thead>
          <tbody>
            <?php foreach (generateDateRange($start, $end) as $d): $rec = $recordsByDate[$d] ?? null; $status = $rec['status'] ?? 'present'; ?>
              <tr>
                <td><?php echo htmlspecialchars($d); ?></td>
                <td>
                  <?php if ($status === 'leave'): ?>
                    <span class="badge bg-warning text-dark"><?php echo __('leave'); ?></span>
                  <?php else: ?>
                    <span class="badge bg-success"><?php echo __('present'); ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($status === 'leave'): ?>
                    <?php echo htmlspecialchars($rec['leave_type'] ?? ''); ?> <?php echo $rec['notes'] ? ' - ' . htmlspecialchars($rec['notes']) : ''; ?>
                  <?php else: ?>
                    &mdash;
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>