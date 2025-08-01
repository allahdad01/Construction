<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

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
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR employee_code LIKE ? OR email LIKE ?)";
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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM employees $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get employees
$sql = "SELECT * FROM employees $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Employees</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Employee
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
                           placeholder="Search by name, code, or email" 
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
                                <th>Code</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Monthly Salary</th>
                                <th>Daily Rate</th>
                                <th>Status</th>
                                <th>Hire Date</th>
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
                                            <?php if (!empty($employee['email'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($employee['email']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $employee['position'] === 'driver' ? 'bg-primary' : 'bg-info'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $employee['position'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatCurrency($employee['monthly_salary']); ?></td>
                                    <td><?php echo formatCurrency($employee['daily_rate']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $employee['status'] === 'active' ? 'bg-success' : 
                                                ($employee['status'] === 'inactive' ? 'bg-warning' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($employee['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($employee['hire_date']); ?></td>
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
                                            <?php if ($employee['status'] === 'active'): ?>
                                                <a href="terminate.php?id=<?php echo $employee['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Terminate"
                                                   onclick="return confirmDelete('Are you sure you want to terminate this employee?')">
                                                    <i class="fas fa-user-times"></i>
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

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Get statistics
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
        $stmt->execute();
        $active_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE position = 'driver'");
        $stmt->execute();
        $driver_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE position = 'driver_assistant'");
        $stmt->execute();
        $assistant_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $driver_count; ?></div>
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
                                Driver Assistants</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $assistant_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-friends fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>