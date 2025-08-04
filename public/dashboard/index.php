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

// Get multi-currency statistics
$currency_stats = getCurrencyStatistics(getCurrentCompanyId());

// Get monthly expenses with currency breakdown
$stmt = $conn->prepare("
    SELECT currency, SUM(amount) as total_amount, COUNT(*) as count
    FROM expenses 
    WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(expense_date) = YEAR(CURRENT_DATE())
    GROUP BY currency
");
$stmt->execute();
$monthlyExpensesByCurrency = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly revenue (from contracts) with currency breakdown
$stmt = $conn->prepare("
    SELECT currency, SUM(total_amount) as total_amount, COUNT(*) as count
    FROM contracts 
    WHERE status = 'active' 
    AND MONTH(start_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(start_date) = YEAR(CURRENT_DATE())
    GROUP BY currency
");
$stmt->execute();
$monthlyRevenueByCurrency = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals in USD for display
$monthlyExpensesUSD = 0;
$monthlyRevenueUSD = 0;

foreach ($monthlyExpensesByCurrency as $expense) {
    $monthlyExpensesUSD += convertCurrencyByCode($expense['total_amount'], $expense['currency'], 'USD');
}

foreach ($monthlyRevenueByCurrency as $revenue) {
    $monthlyRevenueUSD += convertCurrencyByCode($revenue['total_amount'], $revenue['currency'], 'USD');
}

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

    <!-- Multi-Currency Financial Summary -->
    <div class="row">
        <div class="col-xl-8 col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Multi-Currency Financial Summary (This Month)</h6>
                </div>
                <div class="card-body">
                    <!-- Revenue by Currency -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="text-success">Revenue by Currency:</h6>
                            <?php if (!empty($monthlyRevenueByCurrency)): ?>
                                <?php foreach ($monthlyRevenueByCurrency as $revenue): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span><?php echo $revenue['currency']; ?>:</span>
                                        <strong><?php echo formatCurrencyAmount($revenue['total_amount'], $revenue['currency']); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No revenue data for this month</p>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><strong>Total Revenue (USD):</strong></span>
                                <strong class="text-success"><?php echo formatCurrencyAmount($monthlyRevenueUSD, 'USD'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Expenses by Currency -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="text-danger">Expenses by Currency:</h6>
                            <?php if (!empty($monthlyExpensesByCurrency)): ?>
                                <?php foreach ($monthlyExpensesByCurrency as $expense): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span><?php echo $expense['currency']; ?>:</span>
                                        <strong><?php echo formatCurrencyAmount($expense['total_amount'], $expense['currency']); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No expense data for this month</p>
                            <?php endif; ?>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><strong>Total Expenses (USD):</strong></span>
                                <strong class="text-danger"><?php echo formatCurrencyAmount($monthlyExpensesUSD, 'USD'); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Net Profit/Loss -->
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <div class="text-center">
                                <h5 class="<?php echo ($monthlyRevenueUSD - $monthlyExpensesUSD) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    Net Profit/Loss (USD): <?php echo formatCurrencyAmount($monthlyRevenueUSD - $monthlyExpensesUSD, 'USD'); ?>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-Time Exchange Rates -->
        <div class="col-xl-4 col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Real-Time Exchange Rates</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="refreshExchangeRates()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <?php 
                    require_once '../../config/exchange_rates.php';
                    $rate_info = getExchangeRateInfo();
                    ?>
                    <div class="row">
                        <div class="col-12">
                            <small class="text-muted mb-2 d-block">
                                <i class="fas fa-clock me-1"></i>
                                Last updated: <?php echo $rate_info['last_updated']; ?>
                            </small>
                            <h6>USD Rates:</h6>
                            <?php 
                            $supported_currencies = getSupportedCurrencies();
                            foreach ($supported_currencies as $code => $name):
                                if ($code !== 'USD' && isset($rate_info['rates'][$code])):
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span>1 USD =</span>
                                    <strong><?php echo number_format($rate_info['rates'][$code], 2); ?> <?php echo $code; ?></strong>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <hr>
                            <small class="text-info">
                                <i class="fas fa-info-circle me-1"></i>
                                Rates update automatically every hour
                            </small>
                        </div>
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

<script>
function refreshExchangeRates() {
    const button = event.target;
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
    button.disabled = true;
    
    fetch('../api/refresh-exchange-rates.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showToast('Exchange rates refreshed successfully!', 'success');
                // Reload page to show updated rates
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message, 'warning');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to refresh exchange rates', 'error');
        })
        .finally(() => {
            // Restore button state
            button.innerHTML = originalText;
            button.disabled = false;
        });
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 5000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>