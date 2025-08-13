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
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-trash"></i> <?php echo __('delete_employee'); ?></h1>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger"><?php echo __('confirm_deletion'); ?></h6></div>
    <div class="card-body">
      <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('this_action_cannot_be_undone'); ?></div>
      <table class="table table-sm">
        <tr><th><?php echo __('employee'); ?></th><td><?php echo htmlspecialchars($emp['name'] ?? ''); ?> (<?php echo htmlspecialchars($emp['employee_code'] ?? ''); ?>)</td></tr>
        <tr><th><?php echo __('position'); ?></th><td><?php echo htmlspecialchars($emp['position'] ?? '-'); ?></td></tr>
        <tr><th><?php echo __('status'); ?></th><td><?php echo htmlspecialchars($emp['status'] ?? '-'); ?></td></tr>
      </table>
      <div class="mb-3">
        <strong><?php echo __('related_records'); ?>:</strong>
        <ul class="mb-2">
          <li><?php echo __('salary_payments'); ?>: <?php echo (int)$related['salary_payments']; ?></li>
          <li><?php echo __('attendance'); ?>: <?php echo (int)$related['employee_attendance']; ?></li>
          <li><?php echo __('working_hours'); ?>: <?php echo (int)$related['working_hours']; ?></li>
        </ul>
        <small class="text-muted"><?php echo __('if_there_are_related_records_you_can_force_delete_to_remove_them_as_well'); ?></small>
      </div>
      <form method="POST" class="text-end">
        <?php if (array_sum($related) > 0): ?>
          <div class="form-check text-start mb-3">
            <input class="form-check-input" type="checkbox" id="force_delete" name="force_delete" value="1">
            <label class="form-check-label" for="force_delete"><?php echo __('force_delete'); ?> (<?php echo __('also_remove_related_records'); ?>)</label>
          </div>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> <?php echo __('cancel'); ?></a>
        <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash"></i> <?php echo __('confirm_delete'); ?></button>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>