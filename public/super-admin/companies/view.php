<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['id'] ?? 0);

if (!$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
$stmt->execute([$company_id]);
$user_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ?");
$stmt->execute([$company_id]);
$employee_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE company_id = ?");
$stmt->execute([$company_id]);
$machine_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE company_id = ?");
$stmt->execute([$company_id]);
$project_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE company_id = ? AND payment_status = 'completed'");
$stmt->execute([$company_id]);
$total_payments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get recent users
$stmt = $conn->prepare("SELECT * FROM users WHERE company_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$company_id]);
$recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments
$stmt = $conn->prepare("SELECT * FROM company_payments WHERE company_id = ? ORDER BY payment_date DESC LIMIT 5");
$stmt->execute([$company_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-building"></i> Company Details
        </h1>
        <div>
            <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Companies
            </a>
            <a href="/constract360/construction/public/super-admin/companies/edit.php?id=<?php echo $company['id']; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit Company
            </a>
        </div>
    </div>

    <!-- Company Information -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Company Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Company Code:</strong></td>
                                    <td><?php echo htmlspecialchars($company['company_code']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Company Name:</strong></td>
                                    <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Contact Person:</strong></td>
                                    <td><?php echo htmlspecialchars($company['contact_person'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Contact Email:</strong></td>
                                    <td><?php echo htmlspecialchars($company['contact_email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Contact Phone:</strong></td>
                                    <td><?php echo htmlspecialchars($company['contact_phone'] ?? 'N/A'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Address:</strong></td>
                                    <td><?php echo htmlspecialchars($company['address'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>City:</strong></td>
                                    <td><?php echo htmlspecialchars($company['city'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>State:</strong></td>
                                    <td><?php echo htmlspecialchars($company['state'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Country:</strong></td>
                                    <td><?php echo htmlspecialchars($company['country'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td><?php echo formatDate($company['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Subscription Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Plan:</strong>
                        <span class="badge bg-info"><?php echo ucfirst($company['subscription_plan']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong>
                        <span class="badge <?php 
                            echo $company['subscription_status'] === 'active' ? 'bg-success' : 
                                ($company['subscription_status'] === 'trial' ? 'bg-warning' : 
                                ($company['subscription_status'] === 'suspended' ? 'bg-danger' : 'bg-secondary')); 
                        ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
                        </span>
                    </div>
                    <?php if ($company['subscription_status'] === 'trial' && $company['trial_ends_at']): ?>
                        <div class="mb-3">
                            <strong>Trial Ends:</strong>
                            <span class="text-warning"><?php echo formatDate($company['trial_ends_at']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <strong>Total Payments:</strong>
                        <span class="text-success"><?php echo formatCurrency($total_payments); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Limits:</strong>
                        <ul class="list-unstyled">
                            <li>Max Employees: <?php echo $company['max_employees']; ?></li>
                            <li>Max Machines: <?php echo $company['max_machines']; ?></li>
                            <li>Max Projects: <?php echo $company['max_projects']; ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $user_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employee_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-tie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Machines</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $machine_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $project_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_users)): ?>
                        <p class="text-muted">No users found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($user['role']); ?></span></td>
                                            <td><span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Payments</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_payments)): ?>
                        <p class="text-muted">No payments found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Payment Code</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($payment['payment_code']); ?></td>
                                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                                            <td><span class="badge <?php echo $payment['payment_status'] === 'completed' ? 'bg-success' : 'bg-warning'; ?>"><?php echo ucfirst($payment['payment_status']); ?></span></td>
                                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(message) {
    return confirm(message);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>