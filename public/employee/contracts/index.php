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
$isAssistant = ($user['role'] === 'driver_assistant');

// Resolve employee for current user
$empStmt = $conn->prepare('SELECT id, name FROM employees WHERE company_id = ? AND user_id = ? LIMIT 1');
$empStmt->execute([$company_id, $user['id']]);
$employee = $empStmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'My Contracts';

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

$items = [];
if ($employee) {
    $stmt = $conn->prepare("SELECT c.id, c.contract_code, c.contract_type, c.status, c.currency, c.rate_amount,
                                   COALESCE(SUM(wh.hours_worked),0) AS hours_worked
                            FROM working_hours wh
                            JOIN contracts c ON wh.contract_id = c.id
                            WHERE wh.company_id = ? AND wh.employee_id = ? AND wh.date BETWEEN ? AND ?
                            GROUP BY c.id, c.contract_code, c.contract_type, c.status, c.currency, c.rate_amount
                            ORDER BY MAX(wh.date) DESC");
    $stmt->execute([$company_id, $employee['id'], $start, $end]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>
<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">My Contracts</h3>
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
      <div class="card-header">Worked Contracts</div>
      <div class="card-body table-responsive">
        <table class="table table-striped">
          <thead><tr><th>Code</th><th>Type</th><th>Status</th><th class="text-end">Hours Worked</th><th class="text-end">Rate</th><?php if (!$isAssistant): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr><td colspan="<?php echo $isAssistant ? 5 : 6; ?>" class="text-muted">No contract work found for the selected period.</td></tr>
            <?php else: foreach ($items as $it): ?>
              <tr>
                <td><?php echo htmlspecialchars($it['contract_code']); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($it['contract_type'])); ?></td>
                <td><?php echo htmlspecialchars(ucfirst($it['status'])); ?></td>
                <td class="text-end"><?php echo number_format((float)$it['hours_worked'], 1); ?></td>
                <td class="text-end"><?php echo formatCurrencyAmount((float)($it['rate_amount'] ?? 0), $it['currency'] ?? 'USD'); ?></td>
                <?php if (!$isAssistant): ?>
                <td class="text-end">
                  <a href="/constract360/construction/public/employee/contracts/add-hours.php?contract_id=<?php echo (int)$it['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Add Hours</a>
                  <a href="/constract360/construction/public/employee/contracts/timesheet.php?contract_id=<?php echo (int)$it['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-list"></i> Timesheet</a>
                </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require_once '../../../includes/footer.php'; ?>