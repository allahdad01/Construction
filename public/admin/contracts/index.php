<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);
require_once '../../../includes/header.php';

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
$where_conditions = ['c.company_id = ?'];
$params = [getCurrentCompanyId()];

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

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM contracts c $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get contracts with related data
$sql = "SELECT c.*, p.name as project_name, m.name as machine_name, m.machine_code,
               COALESCE(SUM(wh.hours_worked), 0) as total_hours_worked
        FROM contracts c
        LEFT JOIN projects p ON c.project_id = p.id
        LEFT JOIN machines m ON c.machine_id = m.id
        LEFT JOIN working_hours wh ON c.id = wh.contract_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.created_at DESC 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate progress for each contract
foreach ($contracts as &$contract) {
    if ($contract['contract_type'] === 'hourly') {
        $contract['progress_percentage'] = $contract['total_hours_required'] > 0 ? 
            min(100, ($contract['total_hours_worked'] / $contract['total_hours_required']) * 100) : 0;
    } elseif ($contract['contract_type'] === 'daily') {
        $total_days_worked = $contract['total_hours_worked'] / $contract['working_hours_per_day'];
        $contract['progress_percentage'] = $contract['total_days_required'] > 0 ? 
            min(100, ($total_days_worked / $contract['total_days_required']) * 100) : 0;
    } elseif ($contract['contract_type'] === 'monthly') {
        $contract['progress_percentage'] = $contract['total_hours_required'] > 0 ? 
            min(100, ($contract['total_hours_worked'] / $contract['total_hours_required']) * 100) : 0;
    }
}

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ?");
$stmt->execute([getCurrentCompanyId()]);
$total_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'active'");
$stmt->execute([getCurrentCompanyId()]);
$active_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'completed'");
$stmt->execute([getCurrentCompanyId()]);
$completed_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM contracts WHERE company_id = ? AND status = 'active'");
$stmt->execute([getCurrentCompanyId()]);
$total_contract_value = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get contract type breakdown
$stmt = $conn->prepare("
    SELECT contract_type, COUNT(*) as count 
    FROM contracts 
    WHERE company_id = ? 
    GROUP BY contract_type
");
$stmt->execute([getCurrentCompanyId()]);
$contract_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly revenue data for chart
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
           SUM(total_amount) as revenue,
           COUNT(*) as contract_count
    FROM contracts 
    WHERE company_id = ? 
    AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$stmt->execute([getCurrentCompanyId()]);
$monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('contract_management'); ?></h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_contract'); ?>
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
                                <?php echo __('total_contracts'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_contracts; ?></div>
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
                                <?php echo __('active_contracts'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_contracts; ?></div>
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
                                <?php echo __('completed'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completed_contracts; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                <?php echo __('total_value'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_contract_value); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('monthly_contract_revenue'); ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_types'); ?></h6>
                </div>
                <div class="card-body">
                    <canvas id="contractTypeChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('search_filter'); ?></h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="<?php echo __('search_by_code_project_machine'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <option value="hourly" <?php echo $type_filter === 'hourly' ? 'selected' : ''; ?>><?php echo __('hourly'); ?></option>
                        <option value="daily" <?php echo $type_filter === 'daily' ? 'selected' : ''; ?>><?php echo __('daily'); ?></option>
                        <option value="monthly" <?php echo $type_filter === 'monthly' ? 'selected' : ''; ?>><?php echo __('monthly'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> <?php echo __('search'); ?>
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i> <?php echo __('clear'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Contracts Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_list'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($contracts)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-file-contract fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_contracts_found'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_contract'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('contract_code'); ?></th>
                                <th><?php echo __('project_machine'); ?></th>
                                <th><?php echo __('type_rate'); ?></th>
                                <th><?php echo __('progress'); ?></th>
                                <th><?php echo __('value'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $contract): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contract['contract_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($contract['project_name'] ?? 'N/A'); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($contract['machine_name'] ?? 'N/A'); ?>
                                                (<?php echo htmlspecialchars($contract['machine_code'] ?? 'N/A'); ?>)
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge <?php 
                                                echo $contract['contract_type'] === 'hourly' ? 'bg-primary' : 
                                                    ($contract['contract_type'] === 'daily' ? 'bg-success' : 'bg-info'); 
                                            ?>">
                                                <?php echo ucfirst($contract['contract_type']); ?>
                                            </span>
                                            <br>                                                <small class="text-muted">
                                                    <?php echo formatCurrency($contract['rate_amount']); ?> 
                                                    <?php echo $contract['contract_type'] === 'hourly' ? '/' . __('hr') : 
                                                        ($contract['contract_type'] === 'daily' ? '/' . __('day') : '/' . __('month')); ?>
                                                </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mb-2">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?php 
                                                    echo $contract['progress_percentage'] >= 100 ? 'bg-success' : 
                                                        ($contract['progress_percentage'] >= 75 ? 'bg-info' : 
                                                        ($contract['progress_percentage'] >= 50 ? 'bg-warning' : 'bg-danger')); 
                                                ?>" 
                                                style="width: <?php echo $contract['progress_percentage']; ?>%">
                                                    <?php echo round($contract['progress_percentage'], 1); ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $contract['total_hours_worked']; ?> / 
                                                <?php echo $contract['total_hours_required'] ?: '∞'; ?> <?php echo __('hours'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo formatCurrency($contract['total_amount']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo __('paid'); ?>: <?php echo formatCurrency($contract['amount_paid']); ?>
                                            </small>
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
                                            <a href="timesheet.php?contract_id=<?php echo $contract['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Timesheet">
                                                <i class="fas fa-clock"></i>
                                            </a>
                                            <?php if ($contract['status'] === 'active'): ?>
                                                <a href="complete.php?id=<?php echo $contract['id']; ?>" 
                                                   class="btn btn-sm btn-primary" title="Complete"
                                                   onclick="return confirmDelete('<?php echo __('confirm_complete_contract'); ?>')">
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo __('previous'); ?>
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
                                        <?php echo __('next'); ?>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Revenue Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'Revenue: $' + context.parsed.y.toLocaleString();
                    }
                }
            }
        }
    }
});

// Contract Type Chart
const typeCtx = document.getElementById('contractTypeChart').getContext('2d');
const typeChart = new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_column($contract_types, 'contract_type')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($contract_types, 'count')); ?>,
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

function confirmDelete(message) {
    return confirm(message);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>