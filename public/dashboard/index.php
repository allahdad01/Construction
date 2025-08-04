<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

// Get dashboard statistics
$db = new Database();
$conn = $db->getConnection();

// Get employee count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE status = 'active'");
$stmt->execute();
$employeeCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get machine count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE status != 'retired'");
$stmt->execute();
$machineCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get active contracts
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE status = 'active'");
$stmt->execute();
$contractCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get active projects
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE status = 'active'");
$stmt->execute();
$projectCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get monthly expenses
$stmt = $conn->prepare("
    SELECT SUM(amount) as total_amount
    FROM expenses 
    WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(expense_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$monthlyExpensesUSD = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Get monthly revenue (from contracts)
$stmt = $conn->prepare("
    SELECT SUM(total_amount) as total_amount
    FROM contracts 
    WHERE status = 'active' 
    AND MONTH(start_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(start_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$monthlyRevenueUSD = $stmt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// Get recent activities
$stmt = $conn->prepare("
    SELECT 'contract' as type, contract_code as code, 'New contract created' as description, created_at 
    FROM contracts 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'employee' as type, employee_code as code, CONCAT('New ', position, ' hired') as description, created_at 
    FROM employees 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    UNION ALL
    SELECT 'machine' as type, machine_code as code, 'New machine added' as description, created_at 
    FROM machines 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Dashboard</h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Active Employees</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeCount; ?></div>
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
                                Available Machines</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $machineCount; ?></div>
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
                                Active Contracts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $contractCount; ?></div>
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
                                Active Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $projectCount; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="row">
        <div class="col-xl-8 col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Financial Summary (This Month)</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6 class="text-success">Total Revenue</h6>
                                <h4 class="text-success"><?php echo formatCurrencyAmount($monthlyRevenueUSD, 'USD'); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6 class="text-danger">Total Expenses</h6>
                                <h4 class="text-danger"><?php echo formatCurrencyAmount($monthlyExpensesUSD, 'USD'); ?></h4>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h5 class="<?php echo ($monthlyRevenueUSD - $monthlyExpensesUSD) >= 0 ? 'text-success' : 'text-danger'; ?>">
                            Net Profit/Loss: <?php echo formatCurrencyAmount($monthlyRevenueUSD - $monthlyExpensesUSD, 'USD'); ?>
                        </h5>
                    </div>
                </div>
            </div>
        </div>


    </div>

        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <a href="../employees/add.php" class="btn btn-primary btn-block">
                                <i class="fas fa-user-plus"></i> Add Employee
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="../machines/add.php" class="btn btn-success btn-block">
                                <i class="fas fa-truck"></i> Add Machine
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="../contracts/add.php" class="btn btn-info btn-block">
                                <i class="fas fa-file-contract"></i> New Contract
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="../expenses/add.php" class="btn btn-warning btn-block">
                                <i class="fas fa-dollar-sign"></i> Add Expense
                            </a>
                        </div>
                    </div>
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
                    <?php if (empty($recentActivities)): ?>
                        <p class="text-muted">No recent activities.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['code']); ?></strong>
                                        <span class="text-muted">- <?php echo htmlspecialchars($activity['description']); ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo formatDateTime($activity['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>



<?php require_once '../../includes/footer.php'; ?>