<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

requireAuth();
requireAnyRole(['company_admin','super_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$project_id) { header('Location: index.php'); exit; }

$stmt = $conn->prepare('SELECT * FROM projects WHERE id=? AND company_id=?');
$stmt->execute([$project_id, $company_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $client_name = trim($_POST['client_name'] ?? '');
        $client_contact = trim($_POST['client_contact'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? null;
        $status = $_POST['status'] ?? 'active';
        $total_budget = $_POST['total_budget'] !== '' ? (float)$_POST['total_budget'] : null;

        if ($name === '' || $description === '' || $start_date === '') { throw new Exception('Please fill all required fields.'); }
        if ($end_date && strtotime($end_date) <= strtotime($start_date)) { throw new Exception('End date must be after start date.'); }

        $upd = $conn->prepare('UPDATE projects SET name=?, description=?, client_name=?, client_contact=?, start_date=?, end_date=?, total_budget=?, status=? WHERE id=? AND company_id=?');
        $upd->execute([$name, $description, $client_name, $client_contact, $start_date, $end_date, $total_budget, $status, $project_id, $company_id]);
        $success = 'Project updated successfully.';
        echo "<script>setTimeout(function(){ window.location.href='view.php?id=".(int)$project_id."'; }, 1200);</script>";
    } catch (Exception $e) { $error = $e->getMessage(); }
}
?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-edit"></i> Edit Project</h1>
    <a href="view.php?id=<?php echo (int)$project_id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Project Information</h6></div>
    <div class="card-body">
      <form method="POST">
        <div class="row">
          <div class="col-md-6">
            <label class="form-label">Project Name *</label>
            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $project['name']); ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Client Name</label>
            <input type="text" class="form-control" name="client_name" value="<?php echo htmlspecialchars($_POST['client_name'] ?? ($project['client_name'] ?? '')); ?>">
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-md-6">
            <label class="form-label">Client Contact</label>
            <input type="text" class="form-control" name="client_contact" value="<?php echo htmlspecialchars($_POST['client_contact'] ?? ($project['client_contact'] ?? '')); ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Start Date *</label>
            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $project['start_date']); ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ($project['end_date'] ?? '')); ?>">
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-md-4">
            <label class="form-label">Budget</label>
            <input type="number" step="0.01" min="0" class="form-control" name="total_budget" value="<?php echo htmlspecialchars($_POST['total_budget'] ?? ($project['total_budget'] ?? '')); ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-control" name="status">
              <?php $stsel = $_POST['status'] ?? $project['status']; ?>
              <option value="active" <?php echo $stsel==='active'?'selected':''; ?>>Active</option>
              <option value="completed" <?php echo $stsel==='completed'?'selected':''; ?>>Completed</option>
              <option value="on_hold" <?php echo $stsel==='on_hold'?'selected':''; ?>>On Hold</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="form-label">Description *</label>
          <textarea class="form-control" name="description" rows="3" required><?php echo htmlspecialchars($_POST['description'] ?? ($project['description'] ?? '')); ?></textarea>
        </div>
        <div class="text-end mt-3">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>