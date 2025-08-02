<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole(['super_admin']);

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $plan_id = (int)$_GET['delete'];
    
    try {
        // Check if plan is being used by any company
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_plan_id = ?");
        $stmt->execute([$plan_id]);
        $usage_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($usage_count > 0) {
            throw new Exception("Cannot delete plan. It is being used by $usage_count companies.");
        }
        
        // Delete subscription plan
        $stmt = $conn->prepare("DELETE FROM subscription_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        
        $success = 'Subscription plan deleted successfully!';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(plan_name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM subscription_plans 
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get subscription plans with pagination
$stmt = $conn->prepare("
    SELECT sp.*, 
           COUNT(c.id) as company_count,
           SUM(CASE WHEN c.subscription_status = 'active' THEN 1 ELSE 0 END) as active_companies
    FROM subscription_plans sp 
    LEFT JOIN companies c ON sp.id = c.subscription_plan_id
    WHERE $where_clause
    GROUP BY sp.id
    ORDER BY sp.created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_plans,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_plans,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_plans,
        AVG(monthly_price) as avg_price,
        MAX(monthly_price) as max_price,
        MIN(monthly_price) as min_price
    FROM subscription_plans
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-credit-card"></i> Subscription Plans
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Plan
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Plans
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_plans']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-credit-card fa-2x text-gray-300"></i>
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
                                Active Plans
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_plans']; ?></div>
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
                                Average Price
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['avg_price'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                Price Range
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['min_price'], 2); ?> - $<?php echo number_format($stats['max_price'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by plan name or description">
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Subscription Plans Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Subscription Plans List</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                    <div class="dropdown-header">Export Options:</div>
                    <a class="dropdown-item" href="#" onclick="exportToCSV()">
                        <i class="fas fa-file-csv me-2"></i>Export to CSV
                    </a>
                    <a class="dropdown-item" href="#" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Export to PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($subscription_plans)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-credit-card fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500">No subscription plans found</h5>
                    <p class="text-gray-400">Add your first subscription plan to get started.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Plan
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" id="plansTable">
                        <thead>
                            <tr>
                                <th>Plan Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Features</th>
                                <th>Companies</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscription_plans as $plan): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-credit-card text-white"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($plan['plan_name']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $plan['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($plan['description']); ?>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h5 class="text-success mb-0">$<?php echo number_format($plan['monthly_price'], 2); ?></h5>
                                        <small class="text-muted">per month</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo $plan['max_employees']; ?> employees<br>
                                                <i class="fas fa-truck"></i> <?php echo $plan['max_machines']; ?> machines<br>
                                                <i class="fas fa-file-contract"></i> <?php echo $plan['max_contracts']; ?> contracts
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-chart-bar"></i> Reports<br>
                                                <i class="fas fa-cog"></i> Settings<br>
                                                <i class="fas fa-download"></i> Export
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="mb-0"><?php echo $plan['company_count']; ?></h6>
                                        <small class="text-muted"><?php echo $plan['active_companies']; ?> active</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $plan['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($plan['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($plan['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $plan['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $plan['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['plan_name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Subscription plans pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
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
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#plansTable').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [
                {
                    targets: -1,
                    orderable: false,
                    searchable: false
                }
            ]
        });
    }
});

// Confirm delete function
function confirmDelete(planId, planName) {
    if (confirm(`Are you sure you want to delete subscription plan "${planName}"? This action cannot be undone.`)) {
        window.location.href = `index.php?delete=${planId}`;
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('plansTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => {
            let text = col.textContent.trim();
            text = text.replace(/"/g, '""');
            return `"${text}"`;
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'subscription_plans.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('PDF export feature coming soon!');
}
</script>

<?php require_once '../../includes/footer.php'; ?>