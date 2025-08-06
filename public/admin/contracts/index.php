<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

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

// Get contracts with related data (without working hours SUM to avoid issues)
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

// Debug: Log the SQL query and results
error_log("Contracts Query: " . $sql);
error_log("Contracts found: " . count($contracts));
if (!empty($contracts)) {
    error_log("First contract data: " . print_r($contracts[0], true));
}

// Calculate working hours and payments for each contract separately (like timesheet does)
foreach ($contracts as &$contract) {
    // Get total hours worked for this contract (using exact timesheet method)
    $stmt = $conn->prepare("
        SELECT wh.hours_worked
        FROM working_hours wh
        WHERE wh.contract_id = ? AND wh.company_id = ?
    ");
    $stmt->execute([$contract['id'], getCurrentCompanyId()]);
    $hours_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total like timesheet does
    $total_hours_worked = 0;
    foreach ($hours_records as $wh) {
        $total_hours_worked += $wh['hours_worked'];
    }
    $contract['total_hours_worked'] = $total_hours_worked;
    
    // Debug: Log what we found
    error_log("Contract ID: " . $contract['id'] . " (Code: " . $contract['contract_code'] . ") - Records found: " . count($hours_records) . " - Total hours: " . $total_hours_worked);
    
    // Get total amount paid for this contract
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as amount_paid
        FROM contract_payments 
        WHERE contract_id = ? AND company_id = ? AND status = 'completed'
    ");
    $stmt->execute([$contract['id'], getCurrentCompanyId()]);
    $payment_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $contract['amount_paid'] = $payment_result['amount_paid'];
}

// Calculate progress and contract values for each contract
foreach ($contracts as &$contract) {
    // Ensure total_hours_worked is set
    $contract['total_hours_worked'] = $contract['total_hours_worked'] ?? 0;
    
    // Calculate progress percentage
    if ($contract['contract_type'] === 'hourly') {
        $contract['progress_percentage'] = $contract['total_hours_required'] > 0 ? 
            min(100, ($contract['total_hours_worked'] / $contract['total_hours_required']) * 100) : 0;
    } elseif ($contract['contract_type'] === 'daily') {
        $total_days_worked = $contract['total_hours_worked'] / ($contract['working_hours_per_day'] ?: 8);
        $contract['progress_percentage'] = $contract['total_days_required'] > 0 ? 
            min(100, ($total_days_worked / $contract['total_days_required']) * 100) : 0;
    } elseif ($contract['contract_type'] === 'monthly') {
        $contract['progress_percentage'] = $contract['total_hours_required'] > 0 ? 
            min(100, ($contract['total_hours_worked'] / $contract['total_hours_required']) * 100) : 0;
    } else {
        $contract['progress_percentage'] = 0;
    }
    
    // Calculate contract total value if not set
    if (empty($contract['total_amount']) || $contract['total_amount'] == 0) {
        if ($contract['contract_type'] === 'hourly') {
            $contract['calculated_total'] = $contract['rate_amount'] * ($contract['total_hours_required'] ?: 0);
        } elseif ($contract['contract_type'] === 'daily') {
            $contract['calculated_total'] = $contract['rate_amount'] * ($contract['total_days_required'] ?: 0);
        } elseif ($contract['contract_type'] === 'monthly') {
            // For monthly contracts, calculate based on total hours required
            if ($contract['total_hours_required'] > 0) {
                $total_months = ceil($contract['total_hours_required'] / (($contract['working_hours_per_day'] ?: 8) * 30));
                $contract['calculated_total'] = $contract['rate_amount'] * $total_months;
            } else {
                // If no total hours required, show rate per month
                $contract['calculated_total'] = $contract['rate_amount'];
            }
        } else {
            $contract['calculated_total'] = $contract['total_amount'] ?: 0;
        }
    } else {
        $contract['calculated_total'] = $contract['total_amount'];
    }
}

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ?");
$stmt->execute([getCurrentCompanyId()]);
$total_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'active'");
$stmt->execute([getCurrentCompanyId()]);
$active_contracts_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'completed'");
$stmt->execute([getCurrentCompanyId()]);
$completed_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate contract values grouped by currency from active contracts
$stmt = $conn->prepare("
    SELECT c.*, 
           COALESCE(SUM(cp.amount), 0) as amount_paid
    FROM contracts c
    LEFT JOIN contract_payments cp ON c.id = cp.contract_id AND cp.status = 'completed'
    WHERE c.company_id = ? AND c.status = 'active'
    GROUP BY c.id
");
$stmt->execute([getCurrentCompanyId()]);
$active_contracts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals by currency
$contract_values = [];
if (!empty($active_contracts_data)) {
    foreach ($active_contracts_data as $contract) {
        $currency = $contract['currency'] ?? 'USD';
        
        // Calculate total value if not set
        $calculated_total = 0;
        if (empty($contract['total_amount']) || $contract['total_amount'] == 0) {
            if ($contract['contract_type'] === 'hourly') {
                $calculated_total = $contract['rate_amount'] * ($contract['total_hours_required'] ?: 0);
            } elseif ($contract['contract_type'] === 'daily') {
                $calculated_total = $contract['rate_amount'] * ($contract['total_days_required'] ?: 0);
            } elseif ($contract['contract_type'] === 'monthly') {
                if ($contract['total_hours_required'] > 0) {
                    $total_months = ceil($contract['total_hours_required'] / (($contract['working_hours_per_day'] ?: 8) * 30));
                    $calculated_total = $contract['rate_amount'] * $total_months;
                } else {
                    $calculated_total = $contract['rate_amount'];
                }
            } else {
                $calculated_total = $contract['total_amount'] ?: 0;
            }
        } else {
            $calculated_total = $contract['total_amount'];
        }
        
        if (!isset($contract_values[$currency])) {
            $contract_values[$currency] = ['currency' => $currency, 'total' => 0];
        }
        $contract_values[$currency]['total'] += $calculated_total;
    }
    
    // Convert to indexed array for display
    $contract_values = array_values($contract_values);
}

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
        <h1 class="h3 mb-0 text-gray-800">Contract Management</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Contract
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
                                Total Contracts</div>
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
                                Active Contracts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_contracts_count; ?></div>
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
                                Completed Contracts</div>
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
                                Total Contract Value</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if (empty($contract_values)): ?>
                                    $0.00
                                <?php else: ?>
                                    <?php foreach ($contract_values as $value): ?>
                                        <?php if (isset($value['total']) && isset($value['currency'])): ?>
                                            <div><?php echo formatCurrencyAmount($value['total'], $value['currency']); ?></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
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
                    <h6 class="m-0 font-weight-bold text-primary">Monthly Contract Revenue</h6>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" width="100%" height="40"></canvas>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Contract Types</h6>
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
            <h6 class="m-0 font-weight-bold text-primary">Search & Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by code, project, or machine" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="type">
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
                    <p class="text-gray-500">No contracts found</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Contract
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Contract Code</th>
                                <th>Project / Machine</th>
                                <th>Type & Rate</th>
                                <th>Progress</th>
                                <th>Value</th>
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
                                        <div>
                                            <?php 
                                            // Debug project/machine data
                                            echo "<!-- Debug Project/Machine: Project ID: {$contract['project_id']}, Project Name: '{$contract['project_name']}', Machine ID: {$contract['machine_id']}, Machine Name: '{$contract['machine_name']}' -->";
                                            ?>
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
                                            <br><small class="text-muted">
                                                <?php echo formatCurrencyAmount($contract['rate_amount'], $contract['currency'] ?? 'USD'); ?> 
                                                <?php echo $contract['contract_type'] === 'hourly' ? '/hr' : 
                                                    ($contract['contract_type'] === 'daily' ? '/day' : '/month'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="mb-2">
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar <?php 
                                                    $progress = $contract['progress_percentage'] ?? 0;
                                                    echo $progress >= 100 ? 'bg-success' : 
                                                        ($progress >= 75 ? 'bg-info' : 
                                                        ($progress >= 50 ? 'bg-warning' : 'bg-danger')); 
                                                ?>" 
                                                style="width: <?php echo $progress; ?>%">
                                                    <?php echo round($progress, 1); ?>%
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php 
                                                $hours_worked = $contract['total_hours_worked'] ?? 0;
                                                $hours_required = $contract['total_hours_required'] ?: '∞';
                                                
                                                // Debug: Show contract info for troubleshooting
                                                echo "<!-- Debug: Contract ID: {$contract['id']}, Code: {$contract['contract_code']}, Hours: {$hours_worked} -->";
                                                
                                                // Ensure hours_worked is properly formatted and visible
                                                if ($hours_worked > 0) {
                                                    echo '<strong>' . number_format($hours_worked, 1) . '</strong> / ' . $hours_required . ' hours';
                                                } else {
                                                    echo '0.0 / ' . $hours_required . ' hours';
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo formatCurrencyAmount($contract['calculated_total'] ?? 0, $contract['currency'] ?? 'USD'); ?></strong>
                                            <br><small class="text-muted">
                                                Paid: <?php echo formatCurrencyAmount($contract['amount_paid'] ?? 0, $contract['currency'] ?? 'USD'); ?>
                                            </small>
                                            <?php if (($contract['calculated_total'] ?? 0) > 0): ?>
                                                <br><small class="text-info">
                                                    Remaining: <?php echo formatCurrencyAmount(($contract['calculated_total'] ?? 0) - ($contract['amount_paid'] ?? 0), $contract['currency'] ?? 'USD'); ?>
                                                </small>
                                            <?php endif; ?>
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