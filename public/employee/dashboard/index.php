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

$page_title = 'Employee Dashboard';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12">
      <h1 class="h3 mb-4">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-header"><strong>Quick Actions</strong></div>
        <div class="card-body">
          <a href="../../attendance/" class="btn btn-primary w-100 mb-2"><i class="fas fa-clock"></i> View Attendance</a>
          <a href="../../salary-payments/" class="btn btn-success w-100 mb-2"><i class="fas fa-money-bill-wave"></i> View Salary</a>
          <a href="../../contracts/" class="btn btn-info w-100"><i class="fas fa-file-contract"></i> Assigned Contracts</a>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><strong>Recent Activity</strong></div>
        <div class="card-body">
          <p class="text-muted mb-0">Your recent work and payments will appear here.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>