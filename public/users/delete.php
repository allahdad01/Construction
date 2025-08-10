<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$user_id) { header('Location: index.php'); exit; }

// Prevent deleting own account
if ($user_id == getCurrentUser()['id']) {
  header('Location: index.php?error=' . urlencode(__('cannot_delete_own_account')));
  exit;
}

// Load user
$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { header('Location: index.php'); exit; }

// Related checks
$related = [
  'employees' => 0,
  'parking_rentals' => 0,
  'area_rentals' => 0,
];
try {
  $s=$conn->prepare('SELECT COUNT(*) FROM employees WHERE user_id = ? AND company_id = ?');
  $s->execute([$user_id, $company_id]);
  $related['employees'] = (int)$s->fetchColumn();
  $s=$conn->prepare('SELECT COUNT(*) FROM parking_rentals WHERE user_id = ?');
  $s->execute([$user_id]);
  $related['parking_rentals'] = (int)$s->fetchColumn();
  $s=$conn->prepare('SELECT COUNT(*) FROM area_rentals WHERE user_id = ?');
  $s->execute([$user_id]);
  $related['area_rentals'] = (int)$s->fetchColumn();
} catch (Exception $e) {}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
  try {
    if (array_sum($related) > 0) {
      throw new Exception('User has related records. Please remove or reassign them before deleting.');
    }
    $del=$conn->prepare('DELETE FROM users WHERE id = ? AND company_id = ?');
    $del->execute([$user_id, $company_id]);
    header('Location: index.php?success=' . urlencode('User deleted successfully'));
    exit;
  } catch (Exception $e) {
    $error = $e->getMessage();
  }
}

require_once '../../includes/header.php';
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-trash"></i> <?php echo __('delete_user'); ?></h1>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <div class="card shadow mb-4">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-danger"><?php echo __('confirm_deletion'); ?></h6></div>
    <div class="card-body">
      <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('this_action_cannot_be_undone'); ?></div>
      <table class="table table-sm">
        <tr><th><?php echo __('user'); ?></th><td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)</td></tr>
      </table>
      <div class="mb-3">
        <strong><?php echo __('related_records'); ?>:</strong>
        <ul class="mb-2">
          <li><?php echo __('employees'); ?>: <?php echo (int)$related['employees']; ?></li>
          <li><?php echo __('parking_rentals'); ?>: <?php echo (int)$related['parking_rentals']; ?></li>
          <li><?php echo __('area_rentals'); ?>: <?php echo (int)$related['area_rentals']; ?></li>
        </ul>
      </div>
      <form method="POST" class="text-end">
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> <?php echo __('cancel'); ?></a>
        <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash"></i> <?php echo __('confirm_delete'); ?></button>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../includes/footer.php'; ?>