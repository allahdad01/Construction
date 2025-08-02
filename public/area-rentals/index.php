<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $rental_id = (int)$_GET['delete'];
    
    try {
        // Check if rental has active contracts
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE area_rental_id = ? AND status = 'active'");
        $stmt->execute([$rental_id]);
        $active_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($active_contracts > 0) {
            throw new Exception("Cannot delete rental. It has $active_contracts active contracts.");
        }
        
        // Delete area rental
        $stmt = $conn->prepare("DELETE FROM area_rentals WHERE id = ? AND company_id = ?");
        $stmt->execute([$rental_id, $company_id]);
        
        $success = 'Area rental deleted successfully!';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(area_name LIKE ? OR area_code LIKE ? OR location LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "area_type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM area_rentals 
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get area rentals with pagination
$stmt = $conn->prepare("
    SELECT ar.*, 
           COUNT(c.id) as contract_count,
           SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active_contracts
    FROM area_rentals ar 
    LEFT JOIN contracts c ON ar.id = c.area_rental_id
    WHERE $where_clause
    GROUP BY ar.id
    ORDER BY ar.created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$area_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_rentals,
        COUNT(CASE WHEN status = 'available' THEN 1 END) as available_rentals,
        COUNT(CASE WHEN status = 'rented' THEN 1 END) as rented_rentals,
        AVG(monthly_rate) as avg_rate,
        SUM(monthly_rate) as total_value
    FROM area_rentals
    WHERE company_id = ?
");
$stats_stmt->execute([$company_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-map-marker-alt"></i> Area Rentals
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Rental Area
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
                                Total Rentals
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_rentals']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
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
                                Available
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['available_rentals']; ?></div>
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
                                Rented
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['rented_rentals']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-key fa-2x text-gray-300"></i>
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
                                Total Value
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_value'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, code, or location">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="rented" <?php echo $status_filter === 'rented' ? 'selected' : ''; ?>>Rented</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Type</label>
                    <select class="form-control" id="type" name="type">
                        <option value="">All Types</option>
                        <option value="warehouse" <?php echo $type_filter === 'warehouse' ? 'selected' : ''; ?>>Warehouse</option>
                        <option value="office" <?php echo $type_filter === 'office' ? 'selected' : ''; ?>>Office</option>
                        <option value="parking" <?php echo $type_filter === 'parking' ? 'selected' : ''; ?>>Parking</option>
                        <option value="land" <?php echo $type_filter === 'land' ? 'selected' : ''; ?>>Land</option>
                        <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
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

    <!-- Area Rentals Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Area Rentals List</h6>
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
            <?php if (empty($area_rentals)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-map-marker-alt fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500">No area rentals found</h5>
                    <p class="text-gray-400">Add your first rental area to get started.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Rental Area
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" id="rentalsTable">
                        <thead>
                            <tr>
                                <th>Area Name</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Rate</th>
                                <th>Status</th>
                                <th>Contracts</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($area_rentals as $rental): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-map-marker-alt text-white"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($rental['area_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($rental['area_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($rental['area_type']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($rental['location']); ?>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="text-success mb-0">$<?php echo number_format($rental['monthly_rate'], 2); ?></h6>
                                        <small class="text-muted">per month</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $rental['status'] === 'available' ? 'success' : ($rental['status'] === 'rented' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($rental['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="mb-0"><?php echo $rental['contract_count']; ?></h6>
                                        <small class="text-muted"><?php echo $rental['active_contracts']; ?> active</small>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($rental['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $rental['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $rental['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $rental['id']; ?>, '<?php echo htmlspecialchars($rental['area_name']); ?>')"
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
                <nav aria-label="Area rentals pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    Previous
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
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
        $('#rentalsTable').DataTable({
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
function confirmDelete(rentalId, rentalName) {
    if (confirm(`Are you sure you want to delete area rental "${rentalName}"? This action cannot be undone.`)) {
        window.location.href = `index.php?delete=${rentalId}`;
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('rentalsTable');
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
    a.download = 'area_rentals.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('PDF export feature coming soon!');
}
</script>

<?php require_once '../../includes/footer.php'; ?>