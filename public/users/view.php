<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get user ID from URL
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Get user details with employee information if linked
$stmt = $conn->prepare("
    SELECT u.*, 
           e.employee_code,
           e.position,
           e.employee_type,
           e.monthly_salary,
           e.hire_date,
           e.department,
           e.status as employee_status
    FROM users u 
    LEFT JOIN employees e ON u.id = e.user_id AND u.company_id = e.company_id
    WHERE u.id = ? AND u.company_id = ?
");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

// Get user activity stats
$stats_stmt = $conn->prepare("
    SELECT 
        CASE WHEN last_login IS NOT NULL THEN DATEDIFF(NOW(), last_login) ELSE NULL END as days_since_login,
        DATEDIFF(NOW(), created_at) as days_since_created
    FROM users 
    WHERE id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has any related records
$related_records = [];

// Check parking rentals
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_rentals WHERE user_id = ?");
$stmt->execute([$user_id]);
$related_records['parking_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check area rentals (if they have user_id field)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM area_rentals WHERE user_id = ?");
$stmt->execute([$user_id]);
$related_records['area_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check attendance records (if linked through employee)
if ($user['employee_code']) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE employee_id = (SELECT id FROM employees WHERE user_id = ?)");
    $stmt->execute([$user_id]);
    $related_records['attendance'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user"></i> <?php echo __('user_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_user'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_users'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- User Details -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('basic_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('user_id'); ?>:</strong> #<?php echo htmlspecialchars($user['id']); ?></p>
                            <p><strong><?php echo __('first_name'); ?>:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
                            <p><strong><?php echo __('last_name'); ?>:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
                            <p><strong><?php echo __('email'); ?>:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                            <?php if ($user['username']): ?>
                            <p><strong><?php echo __('username'); ?>:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if ($user['phone']): ?>
                            <p><strong><?php echo __('phone'); ?>:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php echo __('role'); ?>:</strong> 
                                <span class="badge badge-<?php echo $user['role'] === 'company_admin' ? 'danger' : ($user['role'] === 'driver' ? 'primary' : 'info'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </span>
                            </p>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </p>
                            <p><strong><?php echo __('account_active'); ?>:</strong> 
                                <span class="badge badge-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $user['is_active'] ? __('yes') : __('no'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Information (if linked) -->
            <?php if ($user['employee_code']): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('employee_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('employee_code'); ?>:</strong> <?php echo htmlspecialchars($user['employee_code']); ?></p>
                            <p><strong><?php echo __('position'); ?>:</strong> <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo __('employee_type'); ?>:</strong> <?php echo ucfirst(str_replace('_', ' ', $user['employee_type'] ?? 'N/A')); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($user['monthly_salary']): ?>
                            <p><strong><?php echo __('monthly_salary'); ?>:</strong> $<?php echo number_format($user['monthly_salary'], 2); ?></p>
                            <?php endif; ?>
                            <?php if ($user['hire_date']): ?>
                            <p><strong><?php echo __('hire_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($user['hire_date'])); ?></p>
                            <?php endif; ?>
                            <?php if ($user['department']): ?>
                            <p><strong><?php echo __('department'); ?>:</strong> <?php echo htmlspecialchars($user['department']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php echo __('employee_status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $user['employee_status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['employee_status'] ?? 'N/A'); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Activity Log -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('activity_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo $related_records['parking_rentals']; ?></h4>
                                <small class="text-muted"><?php echo __('parking_rentals'); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-success"><?php echo $related_records['area_rentals']; ?></h4>
                                <small class="text-muted"><?php echo __('area_rentals'); ?></small>
                            </div>
                        </div>
                        <?php if (isset($related_records['attendance'])): ?>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-info"><?php echo $related_records['attendance']; ?></h4>
                                <small class="text-muted"><?php echo __('attendance_records'); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Account Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('account_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('last_login'); ?></h6>
                        <?php if ($user['last_login']): ?>
                        <p class="mb-1"><?php echo formatDateTime($user['last_login']); ?></p>
                        <small class="text-muted">
                            <?php echo $stats['days_since_login']; ?> <?php echo __('days_ago'); ?>
                        </small>
                        <?php else: ?>
                        <p class="text-muted"><?php echo __('never_logged_in'); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('account_created'); ?></h6>
                        <p class="mb-1"><?php echo formatDateTime($user['created_at']); ?></p>
                        <small class="text-muted">
                            <?php echo $stats['days_since_created']; ?> <?php echo __('days_ago'); ?>
                        </small>
                    </div>

                    <?php if ($user['updated_at'] && $user['updated_at'] != $user['created_at']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('last_updated'); ?></h6>
                        <p class="mb-1"><?php echo formatDateTime($user['updated_at']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- User Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('user_timeline'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('account_created'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($user['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($user['employee_code'] && $user['hire_date']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('hired_as_employee'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y', strtotime($user['hire_date'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['last_login']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('last_login'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($user['last_login']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($user['updated_at'] && $user['updated_at'] != $user['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('profile_updated'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($user['updated_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $user_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> <?php echo __('edit_profile'); ?>
                        </a>
                        
                        <?php if ($user['status'] === 'active'): ?>
                        <button class="btn btn-warning btn-sm" onclick="toggleUserStatus(<?php echo $user_id; ?>, 'inactive')">
                            <i class="fas fa-pause"></i> <?php echo __('deactivate_user'); ?>
                        </button>
                        <?php else: ?>
                        <button class="btn btn-success btn-sm" onclick="toggleUserStatus(<?php echo $user_id; ?>, 'active')">
                            <i class="fas fa-play"></i> <?php echo __('activate_user'); ?>
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-info btn-sm" onclick="resetPassword(<?php echo $user_id; ?>)">
                            <i class="fas fa-key"></i> <?php echo __('reset_password'); ?>
                        </button>
                        
                        <?php if ($user['id'] != getCurrentUser()['id']): ?>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $user_id; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_user'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.timeline-text {
    margin: 0;
    color: #6c757d;
    font-size: 0.9em;
}
</style>

<script>
function toggleUserStatus(userId, status) {
    if (confirm(`Are you sure you want to ${status === 'active' ? 'activate' : 'deactivate'} this user?`)) {
        // You would implement AJAX call here to update status
        alert('Feature to be implemented: Toggle user status');
    }
}

function resetPassword(userId) {
    if (confirm('Are you sure you want to reset this user\'s password?')) {
        // You would implement AJAX call here to reset password
        alert('Feature to be implemented: Reset password');
    }
}

function confirmDelete(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
        window.location.href = `index.php?delete=${userId}`;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>