<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$employee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$employee_id) { header('Location: index.php'); exit; }

// Load employee
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? AND company_id = ?");
$stmt->execute([$employee_id, $company_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$emp) { header('Location: index.php'); exit; }

// Related counts
$related = [
  'salary_payments' => 0,
  'employee_attendance' => 0,
  'working_hours' => 0,
];
try { foreach ($related as $tbl => $_) { $s=$conn->prepare("SELECT COUNT(*) FROM $tbl WHERE employee_id = ?"); $s->execute([$employee_id]); $related[$tbl] = (int)$s->fetchColumn(); } } catch (Exception $e) {}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
  try {
    $conn->beginTransaction();
    // Optionally cascade if requested
    if (!empty($_POST['force_delete'])) {
      foreach (array_keys($related) as $tbl) { $del=$conn->prepare("DELETE FROM $tbl WHERE employee_id = ?"); $del->execute([$employee_id]); }
    } else {
      if (array_sum($related) > 0) {
        throw new Exception('Employee has related records. Enable force delete to remove them.');
      }
    }
    // Delete associated user
    $s=$conn->prepare('SELECT user_id FROM employees WHERE id=? AND company_id=?');
    $s->execute([$employee_id, $company_id]);
    $uid = $s->fetchColumn();
    if ($uid) { $du=$conn->prepare('DELETE FROM users WHERE id=?'); $du->execute([$uid]); }
    // Delete employee
    $del=$conn->prepare('DELETE FROM employees WHERE id=? AND company_id=?');
    $del->execute([$employee_id, $company_id]);
    $conn->commit();
    header('Location: index.php?success=' . urlencode('Employee deleted successfully'));
    exit;
  } catch (Exception $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    $error = $e->getMessage();
  }
}

require_once '../../../includes/header.php';
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-trash"></i> Delete Employee</h1>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger">Confirm Deletion</h6></div>
    <div class="card-body">
      <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</div>
      <table class="table table-sm">
        <tr><th>Employee</th><td><?php echo htmlspecialchars($emp['name'] ?? ''); ?> (<?php echo htmlspecialchars($emp['employee_code'] ?? ''); ?>)</td></tr>
        <tr><th>Position</th><td><?php echo htmlspecialchars($emp['position'] ?? '-'); ?></td></tr>
        <tr><th>Status</th><td><?php echo htmlspecialchars($emp['status'] ?? '-'); ?></td></tr>
      </table>
      <div class="mb-3">
        <strong>Related Records:</strong>
        <ul class="mb-2">
          <li>Salary Payments: <?php echo (int)$related['salary_payments']; ?></li>
          <li>Attendance: <?php echo (int)$related['employee_attendance']; ?></li>
          <li>Working Hours: <?php echo (int)$related['working_hours']; ?></li>
        </ul>
        <small class="text-muted">If there are related records, you can force delete to remove them as well.</small>
      </div>
      <form method="POST" class="text-end">
        <?php if (array_sum($related) > 0): ?>
          <div class="form-check text-start mb-3">
            <input class="form-check-input" type="checkbox" id="force_delete" name="force_delete" value="1">
            <label class="form-check-label" for="force_delete">Force delete (also remove related records)</label>
          </div>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash"></i> Confirm Delete</button>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>