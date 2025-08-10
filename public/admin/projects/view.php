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

$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? AND company_id = ?");
$stmt->execute([$project_id, $company_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) { header('Location: index.php'); exit; }

?>
<div class="container-fluid">
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-project-diagram"></i> Project Details</h1>
    <div>
      <a href="edit.php?id=<?php echo (int)$project_id; ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
      <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <div class="card shadow">
    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Information</h6></div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong>Project Code:</strong></td><td><?php echo htmlspecialchars($project['project_code']); ?></td></tr>
            <tr><td><strong>Name:</strong></td><td><?php echo htmlspecialchars($project['name']); ?></td></tr>
            <tr><td><strong>Client:</strong></td><td><?php echo htmlspecialchars($project['client_name'] ?? ''); ?></td></tr>
            <tr><td><strong>Contact:</strong></td><td><?php echo htmlspecialchars($project['client_contact'] ?? ''); ?></td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-borderless">
            <tr><td><strong>Status:</strong></td><td><span class="badge bg-<?php echo $project['status']==='active'?'success':($project['status']==='completed'?'primary':'secondary'); ?>"><?php echo ucfirst($project['status']); ?></span></td></tr>
            <tr><td><strong>Start Date:</strong></td><td><?php echo $project['start_date'] ? date('M j, Y', strtotime($project['start_date'])) : 'Not set'; ?></td></tr>
            <tr><td><strong>End Date:</strong></td><td><?php echo $project['end_date'] ? date('M j, Y', strtotime($project['end_date'])) : 'Not set'; ?></td></tr>
            <tr><td><strong>Budget:</strong></td><td><?php echo $project['total_budget'] ? number_format($project['total_budget'],2) : '<span class="text-muted">Not specified</span>'; ?></td></tr>
          </table>
        </div>
      </div>
      <div class="mt-3">
        <h6>Description</h6>
        <div class="border rounded p-3 bg-light"><?php echo nl2br(htmlspecialchars($project['description'] ?? '')); ?></div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>