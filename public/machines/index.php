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
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR machine_code LIKE ? OR model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM machines $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get machines
$sql = "SELECT * FROM machines $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Machines</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Machine
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
                           placeholder="Search by name, code, or model" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="type">
                        <option value="">All Types</option>
                        <option value="excavator" <?php echo $type_filter === 'excavator' ? 'selected' : ''; ?>>Excavator</option>
                        <option value="bulldozer" <?php echo $type_filter === 'bulldozer' ? 'selected' : ''; ?>>Bulldozer</option>
                        <option value="crane" <?php echo $type_filter === 'crane' ? 'selected' : ''; ?>>Crane</option>
                        <option value="loader" <?php echo $type_filter === 'loader' ? 'selected' : ''; ?>>Loader</option>
                        <option value="truck" <?php echo $type_filter === 'truck' ? 'selected' : ''; ?>>Truck</option>
                        <option value="compactor" <?php echo $type_filter === 'compactor' ? 'selected' : ''; ?>>Compactor</option>
                        <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="in_use" <?php echo $status_filter === 'in_use' ? 'selected' : ''; ?>>In Use</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="retired" <?php echo $status_filter === 'retired' ? 'selected' : ''; ?>>Retired</option>
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

    <!-- Machines Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Machine List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($machines)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-truck fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No machines found.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Machine
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Purchase Cost</th>
                                <th>Current Value</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $machine): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($machine['machine_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($machine['name']); ?></strong>
                                            <?php if (!empty($machine['year_manufactured'])): ?>
                                                <br><small class="text-muted">Year: <?php echo htmlspecialchars($machine['year_manufactured']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(htmlspecialchars($machine['type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($machine['model'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $machine['status'] === 'available' ? 'bg-success' : 
                                                ($machine['status'] === 'in_use' ? 'bg-primary' : 
                                                ($machine['status'] === 'maintenance' ? 'bg-warning' : 'bg-danger')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $machine['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($machine['purchase_cost']) ? formatCurrency($machine['purchase_cost']) : 'N/A'; ?></td>
                                    <td><?php echo !empty($machine['current_value']) ? formatCurrency($machine['current_value']) : 'N/A'; ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $machine['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $machine['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($machine['status'] !== 'retired'): ?>
                                                <a href="retire.php?id=<?php echo $machine['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Retire"
                                                   onclick="return confirmDelete('Are you sure you want to retire this machine?')">
                                                    <i class="fas fa-times"></i>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
                                Total Machines</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Get statistics
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE status = 'available'");
        $stmt->execute();
        $available_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE status = 'in_use'");
        $stmt->execute();
        $in_use_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE status = 'maintenance'");
        $stmt->execute();
        $maintenance_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT SUM(current_value) as total_value FROM machines WHERE status != 'retired'");
        $stmt->execute();
        $total_value = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
        ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Available</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $available_count; ?></div>
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
                                In Use</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $in_use_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cog fa-2x text-gray-300"></i>
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
                                Total Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_value); ?></div>
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

<?php require_once '../../includes/footer.php'; ?>