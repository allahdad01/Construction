<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$user_id = (int)($_GET['id'] ?? 0);
$company_id = (int)($_GET['company_id'] ?? 0);

if (!$user_id || !$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user activity (recent logins, etc.)
$stmt = $conn->prepare("
    SELECT 'Last Login' as activity, last_login as date, 'User logged in' as description
    FROM users 
    WHERE id = ? AND last_login IS NOT NULL
    UNION ALL
    SELECT 'Created' as activity, created_at as date, 'User account created' as description
    FROM users 
    WHERE id = ?
    ORDER BY date DESC
    LIMIT 10
");
$stmt->execute([$user_id, $user_id]);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user"></i> User Details
        </h1>
        <div>
            <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <a href="user-edit.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit User
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- User Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <p class="form-control-plaintext">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Phone</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Role</label>
                                <p class="form-control-plaintext">
                                    <span class="badge <?php 
                                        echo $user['role'] === 'company_admin' ? 'bg-danger' : 
                                            ($user['role'] === 'employee' ? 'bg-primary' : 'bg-secondary'); 
                                    ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge <?php 
                                        echo $user['status'] === 'active' ? 'bg-success' : 
                                            ($user['status'] === 'suspended' ? 'bg-danger' : 'bg-secondary'); 
                                    ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Company</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($company['company_name']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Last Login</label>
                                <p class="form-control-plaintext">
                                    <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Created At</label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($user['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Updated At</label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($user['updated_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <p class="text-muted">No recent activity found.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($activities as $activity): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['activity']); ?></strong>
                                        <span class="text-muted">- <?php echo htmlspecialchars($activity['description']); ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo formatDateTime($activity['date']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="user-edit.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit User
                        </a>
                        <?php if ($user['status'] === 'active'): ?>
                            <a href="user-suspend.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to suspend this user?')">
                                <i class="fas fa-pause"></i> Suspend User
                            </a>
                        <?php else: ?>
                            <a href="user-activate.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-success"
                               onclick="return confirm('Are you sure you want to activate this user?')">
                                <i class="fas fa-play"></i> Activate User
                            </a>
                        <?php endif; ?>
                        <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Back to Users
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary"><?php echo $user['role'] === 'company_admin' ? 'Admin' : 'User'; ?></h4>
                                <small class="text-muted">Role</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="<?php echo $user['status'] === 'active' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </h4>
                            <small class="text-muted">Status</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>