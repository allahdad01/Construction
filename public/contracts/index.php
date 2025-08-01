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
$type_filter = $_GET['contract_type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.contract_code LIKE ? OR p.name LIKE ? OR m.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "c.contract_type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM contracts c 
              LEFT JOIN projects p ON c.project_id = p.id 
              LEFT JOIN machines m ON c.machine_id = m.id 
              $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get contracts with related data
$sql = "SELECT c.*, p.name as project_name, m.name as machine_name, m.machine_code 
        FROM contracts c 
        LEFT JOIN projects p ON c.project_id = p.id 
        LEFT JOIN machines m ON c.machine_id = m.id 
        $where_clause 
        ORDER BY c.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate working hours for each contract
foreach ($contracts as &$contract) {
    // Get total hours worked for this contract
    $stmt = $conn->prepare("SELECT SUM(hours_worked) as total_hours FROM working_hours WHERE contract_id = ?");
    $stmt->execute([$contract['id']]);
    $contract['total_hours_worked'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_hours'] ?? 0;
    
    // Calculate completion percentage
    if ($contract['contract_type'] === 'hourly') {
        $contract['completion_percentage'] = $contract['total_hours_required'] > 0 ? 
            min(100, ($contract['total_hours_worked'] / $contract['total_hours_required']) * 100) : 0;
    } elseif ($contract['contract_type'] === 'daily') {
        $total_days_worked = ceil($contract['total_hours_worked'] / $contract['working_hours_per_day']);
        $contract['completion_percentage'] = $contract['total_days_required'] > 0 ? 
            min(100, ($total_days_worked / $contract['total_days_required']) * 100) : 0;
    } elseif ($contract['contract_type'] === 'monthly') {
        $total_days_worked = ceil($contract['total_hours_worked'] / $contract['working_hours_per_day']);
        $contract['completion_percentage'] = $contract['total_days_required'] > 0 ? 
            min(100, ($total_days_worked / $contract['total_days_required']) * 100) : 0;
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Contracts</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> New Contract
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
                           placeholder="Search by contract code, project, or machine" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="contract_type">
                        <option value="">All Types</option>
                        <option value="hourly" <?php echo $type_filter === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                        <option value="daily" <?php echo $type_filter === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="monthly" <?php echo $type_filter === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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

    <!-- Contracts Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contract List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($contracts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-file-contract fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No contracts found.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create First Contract
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Contract Code</th>
                                <th>Project</th>
                                <th>Machine</th>
                                <th>Type</th>
                                <th>Rate</th>
                                <th>Working Hours</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $contract): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contract['contract_code']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($contract['project_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($contract['machine_name'] ?? 'N/A'); ?></strong>
                                            <?php if (!empty($contract['machine_code'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($contract['machine_code']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $contract['contract_type'] === 'hourly' ? 'bg-primary' : 
                                                ($contract['contract_type'] === 'daily' ? 'bg-success' : 'bg-info'); 
                                        ?>">
                                            <?php echo ucfirst($contract['contract_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatCurrency($contract['rate_amount']); ?></td>
                                    <td>
                                        <div>
                                            <strong><?php echo number_format($contract['total_hours_worked'], 1); ?> hrs</strong>
                                            <?php if ($contract['contract_type'] === 'hourly'): ?>
                                                <br><small class="text-muted">of <?php echo $contract['total_hours_required']; ?> hrs</small>
                                            <?php elseif ($contract['contract_type'] === 'daily'): ?>
                                                <br><small class="text-muted"><?php echo ceil($contract['total_hours_worked'] / $contract['working_hours_per_day']); ?> days of <?php echo $contract['total_days_required']; ?></small>
                                            <?php elseif ($contract['contract_type'] === 'monthly'): ?>
                                                <br><small class="text-muted"><?php echo ceil($contract['total_hours_worked'] / $contract['working_hours_per_day']); ?> days of <?php echo $contract['total_days_required']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php 
                                                echo $contract['completion_percentage'] >= 100 ? 'bg-success' : 
                                                    ($contract['completion_percentage'] >= 75 ? 'bg-info' : 
                                                    ($contract['completion_percentage'] >= 50 ? 'bg-warning' : 'bg-danger')); 
                                            ?>" 
                                                 style="width: <?php echo $contract['completion_percentage']; ?>%">
                                                <?php echo number_format($contract['completion_percentage'], 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $contract['status'] === 'active' ? 'bg-success' : 
                                                ($contract['status'] === 'completed' ? 'bg-info' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($contract['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $contract['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $contract['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="working-hours.php?contract_id=<?php echo $contract['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Working Hours">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                            <?php if ($contract['status'] === 'active'): ?>
                                                <a href="complete.php?id=<?php echo $contract['id']; ?>" 
                                                   class="btn btn-sm btn-primary" title="Complete"
                                                   onclick="return confirmDelete('Are you sure you want to complete this contract?')">
                                                    <i class="fas fa-check"></i>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&contract_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&contract_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&contract_type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE status = 'active'");
        $stmt->execute();
        $active_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE status = 'completed'");
        $stmt->execute();
        $completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $conn->prepare("SELECT SUM(total_amount) as total_revenue FROM contracts WHERE status = 'active'");
        $stmt->execute();
        $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

        $stmt = $conn->prepare("SELECT SUM(amount_paid) as total_paid FROM contracts");
        $stmt->execute();
        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;
        ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Contracts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_records; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-contract fa-2x text-gray-300"></i>
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
                                Active Contracts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play-circle fa-2x text-gray-300"></i>
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Amount Paid</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_paid); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>