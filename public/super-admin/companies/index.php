<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$plan_filter = $_GET['plan'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR contact_email LIKE ? OR company_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "subscription_status = ?";
    $params[] = $status_filter;
}

if (!empty($plan_filter)) {
    $where_conditions[] = "subscription_plan = ?";
    $params[] = $plan_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM companies $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get companies
$sql = "SELECT * FROM companies $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get additional data for each company
foreach ($companies as &$company) {
    // Get user count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE company_id = ?");
    $stmt->execute([$company['id']]);
    $company['user_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get employee count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ?");
    $stmt->execute([$company['id']]);
    $company['employee_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get machine count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE company_id = ?");
    $stmt->execute([$company['id']]);
    $company['machine_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total payments
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE company_id = ? AND payment_status = 'completed'");
    $stmt->execute([$company['id']]);
    $company['total_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Companies</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Company
        </a>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search & Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by company name, email, or code" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="trial" <?php echo $status_filter === 'trial' ? 'selected' : ''; ?>>Trial</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="plan">
                        <option value="">All Plans</option>
                        <option value="basic" <?php echo $plan_filter === 'basic' ? 'selected' : ''; ?>>Basic</option>
                        <option value="professional" <?php echo $plan_filter === 'professional' ? 'selected' : ''; ?>>Professional</option>
                        <option value="enterprise" <?php echo $plan_filter === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Companies Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Company List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($companies)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-building fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No companies found.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Company
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Company Code</th>
                                <th>Company Name</th>
                                <th>Contact</th>
                                <th>Subscription</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($company['company_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                            <?php if (!empty($company['domain'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($company['domain']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($company['contact_person'] ?? 'N/A'); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($company['contact_email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($company['subscription_plan']); ?>
                                            </span>
                                            <br><small class="text-muted">
                                                <?php echo formatCurrency($company['total_payments']); ?> paid
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="row">
                                            <div class="col-4">
                                                <small class="text-muted">Users</small><br>
                                                <strong><?php echo $company['user_count']; ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Employees</small><br>
                                                <strong><?php echo $company['employee_count']; ?></strong>
                                            </div>
                                            <div class="col-4">
                                                <small class="text-muted">Machines</small><br>
                                                <strong><?php echo $company['machine_count']; ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $company['subscription_status'] === 'active' ? 'bg-success' : 
                                                ($company['subscription_status'] === 'trial' ? 'bg-warning' : 
                                                ($company['subscription_status'] === 'suspended' ? 'bg-danger' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo ucfirst($company['subscription_status']); ?>
                                        </span>
                                        <?php if ($company['subscription_status'] === 'trial' && $company['trial_ends_at']): ?>
                                            <br><small class="text-muted">
                                                Trial ends: <?php echo formatDate($company['trial_ends_at']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatDate($company['created_at']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/constract360/construction/public/super-admin/companies/view.php?id=<?php echo $company['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="/constract360/construction/public/super-admin/companies/edit.php?id=<?php echo $company['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="/constract360/construction/public/super-admin/companies/users.php?company_id=<?php echo $company['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Users">
                                                <i class="fas fa-users"></i>
                                            </a>
                                            <a href="/constract360/construction/public/super-admin/companies/payments.php?company_id=<?php echo $company['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Payments">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                            <?php if ($company['subscription_status'] === 'active'): ?>
                                                <a href="/constract360/construction/public/super-admin/companies/suspend.php?id=<?php echo $company['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Suspend"
                                                   onclick="return confirmDelete('Are you sure you want to suspend this company?')">
                                                    <i class="fas fa-pause"></i>
                                                </a>
                                            <?php elseif ($company['subscription_status'] === 'suspended'): ?>
                                                <a href="/constract360/construction/public/super-admin/companies/activate.php?id=<?php echo $company['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="Activate"
                                                   onclick="return confirmDelete('Are you sure you want to activate this company?')">
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&plan=<?php echo urlencode($plan_filter); ?>">
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

    <!-- Statistics -->
    <div class="row">
        <?php
        // Get statistics
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'active'");
        $stmt->execute();
        $active_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'trial'");
        $stmt->execute();
        $trial_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'suspended'");
        $stmt->execute();
        $suspended_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE payment_status = 'completed'");
        $stmt->execute();
        $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Companies</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Companies</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Trial Companies</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $trial_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Revenue</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_revenue); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>