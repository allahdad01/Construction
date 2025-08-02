<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();
$company_id = getCurrentCompanyId();
$is_super_admin = isSuperAdmin();
$is_company_admin = isCompanyAdmin();

// Get real statistics based on user role
if ($is_super_admin) {
    // Super Admin Dashboard - System-wide statistics
    $stats = [
        'total_companies' => 0,
        'total_users' => 0,
        'total_revenue' => 0,
        'active_subscriptions' => 0
    ];
    
    // Get total companies
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies");
    $stmt->execute();
    $stats['total_companies'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total users
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total revenue
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE status = 'completed'");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get active subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_status = 'active'");
    $stmt->execute();
    $stats['active_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent companies
    $stmt = $conn->prepare("
        SELECT c.*, cp.plan_name 
        FROM companies c 
        LEFT JOIN subscription_plans cp ON c.subscription_plan_id = cp.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get revenue chart data
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, SUM(amount) as revenue 
        FROM company_payments 
        WHERE status = 'completed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute();
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} else {
    // Company Admin Dashboard - Company-specific statistics
    $stats = [
        'total_employees' => 0,
        'total_machines' => 0,
        'active_contracts' => 0,
        'total_earnings' => 0
    ];
    
    // Get total employees
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $stats['total_employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total machines
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $stats['total_machines'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get active contracts
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]);
    $stats['active_contracts'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total earnings
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total FROM contract_payments 
        WHERE company_id = ? AND status = 'completed'
    ");
    $stmt->execute([$company_id]);
    $stats['total_earnings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get recent contracts
    $stmt = $conn->prepare("
        SELECT c.*, p.project_name 
        FROM contracts c 
        LEFT JOIN projects p ON c.project_id = p.id 
        WHERE c.company_id = ? 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$company_id]);
    $recent_contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get earnings chart data
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, SUM(amount) as earnings 
        FROM contract_payments 
        WHERE company_id = ? AND status = 'completed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date
    ");
    $stmt->execute([$company_id]);
    $earnings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent activities
$stmt = $conn->prepare("
    SELECT 'contract' as type, c.contract_name as title, c.created_at as date, 'New contract created' as description
    FROM contracts c 
    WHERE c.company_id = ? 
    UNION ALL
    SELECT 'employee' as type, CONCAT(e.first_name, ' ', e.last_name) as title, e.created_at as date, 'New employee added' as description
    FROM employees e 
    WHERE e.company_id = ? 
    UNION ALL
    SELECT 'machine' as type, m.machine_name as title, m.created_at as date, 'New machine added' as description
    FROM machines m 
    WHERE m.company_id = ? 
    ORDER BY date DESC 
    LIMIT 10
");
$stmt->execute([$company_id, $company_id, $company_id]);
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quick stats for charts
$chart_data = [];
if ($is_super_admin) {
    $chart_data = [
        'labels' => array_column($revenue_data, 'date'),
        'data' => array_column($revenue_data, 'revenue'),
        'label' => 'Revenue',
        'backgroundColor' => 'rgba(78, 115, 223, 0.2)',
        'borderColor' => 'rgba(78, 115, 223, 1)'
    ];
} else {
    $chart_data = [
        'labels' => array_column($earnings_data, 'date'),
        'data' => array_column($earnings_data, 'earnings'),
        'label' => 'Earnings',
        'backgroundColor' => 'rgba(28, 200, 138, 0.2)',
        'borderColor' => 'rgba(28, 200, 138, 1)'
    ];
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </h1>
        <div class="d-flex">
            <a href="../reports/" class="btn btn-primary me-2">
                <i class="fas fa-chart-bar"></i> View Reports
            </a>
            <?php if ($is_company_admin): ?>
            <a href="../employees/add.php" class="btn btn-success me-2">
                <i class="fas fa-user-plus"></i> Add Employee
            </a>
            <a href="../machines/add.php" class="btn btn-info me-2">
                <i class="fas fa-truck"></i> Add Machine
            </a>
            <a href="../contracts/add.php" class="btn btn-warning">
                <i class="fas fa-file-contract"></i> New Contract
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <?php if ($is_super_admin): ?>
        <!-- Super Admin Stats -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Companies
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_companies']; ?></div>
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
                                Total Users
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                Total Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
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
                                Active Subscriptions
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_subscriptions']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- Company Admin Stats -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Employees
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_employees']; ?></div>
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
                                Total Machines
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_machines']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                                Active Contracts
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_contracts']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-contract fa-2x text-gray-300"></i>
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
                                Total Earnings
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($stats['total_earnings'], 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $is_super_admin ? 'Revenue Overview' : 'Earnings Overview'; ?>
                    </h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                            <div class="dropdown-header">Chart Options:</div>
                            <a class="dropdown-item" href="#">This Week</a>
                            <a class="dropdown-item" href="#">This Month</a>
                            <a class="dropdown-item" href="#">This Year</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#">Export Data</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="mainChart" data-chart='<?php echo json_encode($chart_data); ?>'></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $is_super_admin ? 'Recent Companies' : 'Recent Contracts'; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($is_super_admin && !empty($recent_companies)): ?>
                        <?php foreach ($recent_companies as $company): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-building text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?php echo htmlspecialchars($company['company_name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($company['plan_name'] ?? 'No Plan'); ?></small>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-<?php echo $company['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($company['subscription_status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif (!$is_super_admin && !empty($recent_contracts)): ?>
                        <?php foreach ($recent_contracts as $contract): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-file-contract text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?php echo htmlspecialchars($contract['contract_name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($contract['project_name'] ?? 'No Project'); ?></small>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-<?php echo $contract['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($contract['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>No data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_activities)): ?>
                        <div class="timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="timeline-item d-flex align-items-center mb-3">
                                <div class="flex-shrink-0">
                                    <div class="bg-<?php echo $activity['type'] === 'contract' ? 'info' : ($activity['type'] === 'employee' ? 'success' : 'warning'); ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-<?php echo $activity['type'] === 'contract' ? 'file-contract' : ($activity['type'] === 'employee' ? 'user' : 'truck'); ?> text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($activity['description']); ?></small>
                                </div>
                                <div class="flex-shrink-0">
                                    <small class="text-muted"><?php echo date('M j, Y', strtotime($activity['date'])); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clock fa-3x mb-3"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($is_super_admin): ?>
                        <div class="col-md-3 mb-3">
                            <a href="../super-admin/companies/" class="btn btn-outline-primary w-100">
                                <i class="fas fa-building fa-2x mb-2"></i>
                                <br>Manage Companies
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../super-admin/subscription-plans/" class="btn btn-outline-success w-100">
                                <i class="fas fa-credit-card fa-2x mb-2"></i>
                                <br>Subscription Plans
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../reports/" class="btn btn-outline-info w-100">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <br>View Reports
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../super-admin/settings/" class="btn btn-outline-warning w-100">
                                <i class="fas fa-cogs fa-2x mb-2"></i>
                                <br>Platform Settings
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="col-md-3 mb-3">
                            <a href="../employees/" class="btn btn-outline-primary w-100">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <br>Manage Employees
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../machines/" class="btn btn-outline-success w-100">
                                <i class="fas fa-truck fa-2x mb-2"></i>
                                <br>Manage Machines
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../contracts/" class="btn btn-outline-info w-100">
                                <i class="fas fa-file-contract fa-2x mb-2"></i>
                                <br>Manage Contracts
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../reports/" class="btn btn-outline-warning w-100">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <br>View Reports
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize main chart
    const chartCanvas = document.getElementById('mainChart');
    if (chartCanvas) {
        const chartData = JSON.parse(chartCanvas.dataset.chart);
        const ctx = chartCanvas.getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: chartData.label,
                    data: chartData.data,
                    backgroundColor: chartData.backgroundColor,
                    borderColor: chartData.borderColor,
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>