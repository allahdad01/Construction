<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$attendance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$attendance_id) { header('Location: index.php'); exit; }

// Fetch record
$stmt = $conn->prepare("SELECT ea.*, e.name FROM employee_attendance ea LEFT JOIN employees e ON ea.employee_id=e.id WHERE ea.id=? AND ea.company_id=?");
$stmt->execute([$attendance_id, $company_id]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rec) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $del = $conn->prepare('DELETE FROM employee_attendance WHERE id=? AND company_id=?');
        $del->execute([$attendance_id, $company_id]);
        header('Location: index.php?success=' . urlencode(__('attendance_record_deleted_successfully')));
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once '../../../includes/header.php';
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-trash"></i> <?php echo __('delete_attendance'); ?></h1>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger"><?php echo __('confirm_deletion'); ?></h6></div>
    <div class="card-body">
      <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('this_action_cannot_be_undone'); ?></div>
      <table class="table table-sm">
        <tr><th><?php echo __('employee'); ?></th><td><?php echo htmlspecialchars($rec['name'] ?? '-'); ?></td></tr>
        <tr><th><?php echo __('date'); ?></th><td><?php echo !empty($rec['date']) ? date('M j, Y', strtotime($rec['date'])) : '-'; ?></td></tr>
        <tr><th><?php echo __('status'); ?></th><td><?php echo htmlspecialchars($rec['status'] ?? '-'); ?></td></tr>
        <tr><th><?php echo __('notes'); ?></th><td><?php echo htmlspecialchars($rec['notes'] ?? '-'); ?></td></tr>
      </table>
      <form method="POST" class="text-end">
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> <?php echo __('cancel'); ?></a>
        <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash"></i> <?php echo __('confirm_delete'); ?></button>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>