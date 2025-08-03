<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['company_id'] ?? 0);

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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = ["company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users WHERE $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get users
$sql = "SELECT * FROM users WHERE $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
$stmt->execute([$company_id]);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company_id]);
$active_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ? AND role = 'company_admin'");
$stmt->execute([$company_id]);
$admin_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users"></i> Company Users - <?php echo htmlspecialchars($company['company_name']); ?>
        </h1>
        <div>
            <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Companies
            </a>
            <a href="/constract360/construction/public/super-admin/companies/view.php?id=<?php echo $company['id']; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> View Company
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_users; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Admin Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $admin_users; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search & Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by name, email, or username" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="role">
                        <option value="">All Roles</option>
                        <option value="company_admin" <?php echo $role_filter === 'company_admin' ? 'selected' : ''; ?>>Company Admin</option>
                        <option value="employee" <?php echo $role_filter === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="driver" <?php echo $role_filter === 'driver' ? 'selected' : ''; ?>>Driver</option>
                        <option value="driver_assistant" <?php echo $role_filter === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                        <option value="parking_user" <?php echo $role_filter === 'parking_user' ? 'selected' : ''; ?>>Parking User</option>
                        <option value="area_renter" <?php echo $role_filter === 'area_renter' ? 'selected' : ''; ?>>Area Renter</option>
                        <option value="container_renter" <?php echo $role_filter === 'container_renter' ? 'selected' : ''; ?>>Container Renter</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="?company_id=<?php echo $company_id; ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Users List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($users)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No users found for this company.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                            <?php if (!empty($user['phone'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $user['role'] === 'company_admin' ? 'bg-danger' : 
                                                ($user['role'] === 'employee' ? 'bg-primary' : 'bg-secondary'); 
                                        ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $user['status'] === 'active' ? 'bg-success' : 
                                                ($user['status'] === 'suspended' ? 'bg-danger' : 'bg-secondary'); 
                                        ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?>
                                    </td>
                                    <td><?php echo formatDate($user['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/constract360/construction/public/super-admin/companies/user-view.php?id=<?php echo $user['id']; ?>&company_id=<?php echo $company_id; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/constract360/construction/public/super-admin/companies/user-edit.php?id=<?php echo $user['id']; ?>&company_id=<?php echo $company_id; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <a href="/constract360/construction/public/super-admin/companies/user-suspend.php?id=<?php echo $user['id']; ?>&company_id=<?php echo $company_id; ?>" 
                                                   class="btn btn-sm btn-danger" title="Suspend"
                                                   onclick="return confirmDelete('Are you sure you want to suspend this user?')">
                                                    <i class="fas fa-pause"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="/constract360/construction/public/super-admin/companies/user-activate.php?id=<?php echo $user['id']; ?>&company_id=<?php echo $company_id; ?>" 
                                                   class="btn btn-sm btn-success" title="Activate"
                                                   onclick="return confirmDelete('Are you sure you want to activate this user?')">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?company_id=<?php echo $company_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?company_id=<?php echo $company_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?company_id=<?php echo $company_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(message) {
    return confirm(message);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>