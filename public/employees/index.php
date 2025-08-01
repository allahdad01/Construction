<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = ['company_id = ?'];
$params = [getCurrentCompanyId()];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR employee_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($position_filter)) {
    $where_conditions[] = "position = ?";
    $params[] = $position_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM employees $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get employees
$sql = "SELECT e.*, u.username, u.email as user_email 
        FROM employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        $where_clause 
        ORDER BY e.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ?");
$stmt->execute([getCurrentCompanyId()]);
$total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND status = 'active'");
$stmt->execute([getCurrentCompanyId()]);
$active_employees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND position = 'driver'");
$stmt->execute([getCurrentCompanyId()]);
$total_drivers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND position = 'driver_assistant'");
$stmt->execute([getCurrentCompanyId()]);
$total_assistants = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get monthly salary total
$stmt = $conn->prepare("SELECT SUM(monthly_salary) as total FROM employees WHERE company_id = ? AND status = 'active'");
$stmt->execute([getCurrentCompanyId()]);
$monthly_salary_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Employee Management</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Employee
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_employees; ?></div>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_employees; ?></div>
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
                                Drivers</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_drivers; ?></div>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Monthly Salary Total</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($monthly_salary_total); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by name, email, or employee code" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="position">
                        <option value="">All Positions</option>
                        <option value="driver" <?php echo $position_filter === 'driver' ? 'selected' : ''; ?>>Driver</option>
                        <option value="driver_assistant" <?php echo $position_filter === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="terminated" <?php echo $status_filter === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                        <option value="on_leave" <?php echo $status_filter === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
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

    <!-- Employees Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Employee List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($employees)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No employees found.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Employee
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Contact</th>
                                <th>Salary Info</th>
                                <th>Leave Status</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($employee['employee_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($employee['name']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($employee['user_email'] ?? 'No login access'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $employee['position'] === 'driver' ? 'bg-primary' : 'bg-info'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $employee['position'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($employee['phone']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($employee['email']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo formatCurrency($employee['monthly_salary']); ?></strong>
                                            <br><small class="text-muted">
                                                Daily: <?php echo formatCurrency($employee['daily_rate']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="text-muted">Used: <?php echo $employee['used_leave_days']; ?> days</small><br>
                                            <small class="text-muted">Remaining: <?php echo $employee['remaining_leave_days']; ?> days</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $employee['status'] === 'active' ? 'bg-success' : 
                                                ($employee['status'] === 'on_leave' ? 'bg-warning' : 
                                                ($employee['status'] === 'terminated' ? 'bg-danger' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $employee['status'])); ?>
                                        </span>
                                        <?php if ($employee['status'] === 'on_leave' && $employee['leave_start_date']): ?>
                                            <br><small class="text-muted">
                                                Until: <?php echo formatDate($employee['leave_end_date']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="attendance.php?employee_id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Attendance">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                            <a href="salary.php?employee_id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Salary">
                                                <i class="fas fa-money-bill"></i>
                                            </a>
                                            <?php if ($employee['status'] === 'active'): ?>
                                                <a href="terminate.php?id=<?php echo $employee['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Terminate"
                                                   onclick="return confirmDelete('Are you sure you want to terminate this employee?')">
                                                    <i class="fas fa-user-times"></i>
                                                </a>
                                            <?php elseif ($employee['status'] === 'terminated'): ?>
                                                <a href="reactivate.php?id=<?php echo $employee['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="Reactivate"
                                                   onclick="return confirmDelete('Are you sure you want to reactivate this employee?')">
                                                    <i class="fas fa-user-check"></i>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="add.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus"></i> Add New Employee
                        </a>
                        <a href="attendance/" class="list-group-item list-group-item-action">
                            <i class="fas fa-clock"></i> Manage Attendance
                        </a>
                        <a href="salary-payments/" class="list-group-item list-group-item-action">
                            <i class="fas fa-money-bill"></i> Salary Payments
                        </a>
                        <a href="reports/" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar"></i> Employee Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6>Position Breakdown</h6>
                            <p><strong>Drivers:</strong> <?php echo $total_drivers; ?></p>
                            <p><strong>Assistants:</strong> <?php echo $total_assistants; ?></p>
                        </div>
                        <div class="col-6">
                            <h6>Status Breakdown</h6>
                            <p><strong>Active:</strong> <?php echo $active_employees; ?></p>
                            <p><strong>Inactive:</strong> <?php echo $total_employees - $active_employees; ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6>Monthly Salary Overview</h6>
                        <h4 class="text-success"><?php echo formatCurrency($monthly_salary_total); ?></h4>
                        <small class="text-muted">Total monthly salary for all active employees</small>
                    </div>
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

<?php require_once '../../includes/footer.php'; ?>