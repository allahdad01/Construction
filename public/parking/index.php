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
$type_filter = $_GET['space_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(space_code LIKE ? OR space_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "space_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM parking_spaces $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get parking spaces
$sql = "SELECT * FROM parking_spaces $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$parking_spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active rentals for each parking space
foreach ($parking_spaces as &$space) {
    $stmt = $conn->prepare("SELECT COUNT(*) as active_rentals FROM parking_rentals WHERE parking_space_id = ? AND status = 'active'");
    $stmt->execute([$space['id']]);
    $space['active_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_rentals'];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Parking Spaces</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Parking Space
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
                           placeholder="Search by space code or name" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="space_type">
                        <option value="">All Types</option>
                        <option value="machine" <?php echo $type_filter === 'machine' ? 'selected' : ''; ?>>Machine</option>
                        <option value="container" <?php echo $type_filter === 'container' ? 'selected' : ''; ?>>Container</option>
                        <option value="equipment" <?php echo $type_filter === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="occupied" <?php echo $status_filter === 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
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

    <!-- Parking Spaces Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Parking Space List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($parking_spaces)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-parking fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No parking spaces found.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Parking Space
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Space Code</th>
                                <th>Space Name</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Monthly Rate</th>
                                <th>Daily Rate</th>
                                <th>Status</th>
                                <th>Active Rentals</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parking_spaces as $space): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($space['space_code']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($space['space_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(htmlspecialchars($space['space_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($space['size'] ?? 'N/A'); ?></td>
                                    <td><?php echo formatCurrency($space['monthly_rate']); ?></td>
                                    <td><?php echo formatCurrency($space['daily_rate']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $space['status'] === 'available' ? 'bg-success' : 
                                                ($space['status'] === 'occupied' ? 'bg-warning' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($space['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $space['active_rentals'] > 0 ? 'bg-primary' : 'bg-secondary'; ?>">
                                            <?php echo $space['active_rentals']; ?> rental(s)
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $space['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $space['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="rentals.php?space_id=<?php echo $space['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Rentals">
                                                <i class="fas fa-list"></i>
                                            </a>
                                            <a href="add-rental.php?space_id=<?php echo $space['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Add Rental">
                                                <i class="fas fa-plus"></i>
                                            </a>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&space_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&space_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&space_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'available'");
        $stmt->execute();
        $available_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'occupied'");
        $stmt->execute();
        $occupied_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_rentals WHERE status = 'active'");
        $stmt->execute();
        $active_rentals = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT SUM(monthly_rate) as total_revenue FROM parking_spaces WHERE status = 'occupied'");
        $stmt->execute();
        $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
        ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Spaces</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-parking fa-2x text-gray-300"></i>
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
                                Active Rentals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_rentals; ?></div>
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
                                Monthly Revenue</div>
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

<?php require_once '../../includes/footer.php'; ?>