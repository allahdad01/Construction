<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/header.php';

// Check if user is authenticated
requireAuth();

$user = getCurrentUser();
$company = getCurrentCompany();

$db = new Database();
$conn = $db->getConnection();

// Get data based on user role
$companyStats = [];
$employeeData = null;
$renterData = null;

if (isSuperAdmin()) {
    // Super Admin - Global statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies");
    $stmt->execute();
    $companyStats['total_companies'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies WHERE subscription_status = 'active'");
    $stmt->execute();
    $companyStats['active_companies'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies WHERE subscription_status = 'trial'");
    $stmt->execute();
    $companyStats['trial_companies'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total 
        FROM company_payments 
        WHERE payment_status = 'completed' 
        AND payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $companyStats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} elseif (isCompanyAdmin()) {
    // Company Admin - Company statistics
    $companyId = getCurrentCompanyId();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    $companyStats['active_employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ? AND status = 'available'");
    $stmt->execute([$companyId]);
    $companyStats['available_machines'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    $companyStats['active_contracts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("
        SELECT SUM(total_amount) as total 
        FROM contracts 
        WHERE company_id = ? AND status = 'active'
    ");
    $stmt->execute([$companyId]);
    $companyStats['contract_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} elseif (isEmployee()) {
    // Employee - Personal data
    $employeeData = getUserDashboardData($user['id']);
    
} elseif (isRenter()) {
    // Renter - Rental data
    $renterData = getUserDashboardData($user['id']);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-4"><?php echo __('welcome'); ?>, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h1>
        <div>
            <?php if (isCompanyAdmin()): ?>
                <a href="employees/add.php" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-plus"></i> <?php echo __('add_employee'); ?>
                </a>
                <a href="machines/add.php" class="btn btn-success btn-sm me-2">
                    <i class="fas fa-plus"></i> <?php echo __('add_machine'); ?>
                </a>
                <a href="contracts/add.php" class="btn btn-info btn-sm">
                    <i class="fas fa-plus"></i> <?php echo __('add_contract'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isSuperAdmin()): ?>
        <!-- Super Admin Dashboard -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    <?php echo __('total_companies'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['total_companies']; ?></div>
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
                                    <?php echo __('active_subscriptions'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['active_companies']; ?></div>
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
                                    <?php echo __('trial_companies'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['trial_companies']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                    <?php echo __('monthly_revenue'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($companyStats['monthly_revenue']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="super-admin/companies/" class="btn btn-primary btn-lg btn-block mb-3">
                                    <i class="fas fa-building"></i><br>
                                    <?php echo __('manage_companies'); ?>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="super-admin/subscription-plans/" class="btn btn-success btn-lg btn-block mb-3">
                                    <i class="fas fa-credit-card"></i><br>
                                    <?php echo __('subscription_plans'); ?>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="super-admin/payments/" class="btn btn-info btn-lg btn-block mb-3">
                                    <i class="fas fa-money-bill"></i><br>
                                    <?php echo __('view_payments'); ?>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="super-admin/settings/" class="btn btn-warning btn-lg btn-block mb-3">
                                    <i class="fas fa-cog"></i><br>
                                    <?php echo __('system_settings'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (isCompanyAdmin()): ?>
        <!-- Company Admin Dashboard -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    <?php echo __('active_employees'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['active_employees']; ?></div>
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
                                    <?php echo __('available_machines'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['available_machines']; ?></div>
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
                                    <?php echo __('active_contracts'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['active_contracts']; ?></div>
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
                                    <?php echo __('contract_value'); ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($companyStats['contract_value']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="employees/" class="btn btn-primary btn-lg btn-block mb-3">
                                    <i class="fas fa-users"></i><br>
                                    Manage Employees
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="machines/" class="btn btn-success btn-lg btn-block mb-3">
                                    <i class="fas fa-truck"></i><br>
                                    Manage Machines
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="contracts/" class="btn btn-info btn-lg btn-block mb-3">
                                    <i class="fas fa-file-contract"></i><br>
                                    Manage Contracts
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="expenses/" class="btn btn-warning btn-lg btn-block mb-3">
                                    <i class="fas fa-receipt"></i><br>
                                    Manage Expenses
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (isEmployee() && $employeeData): ?>
        <!-- Employee Dashboard -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Monthly Salary</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($employeeData['monthly_salary']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                    Working Days</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeData['working_days']; ?> days</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
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
                                    Leave Days</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeData['remaining_leave_days']; ?> remaining</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-umbrella-beach fa-2x text-gray-300"></i>
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
                                    Daily Rate</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($employeeData['daily_rate']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="employees/attendance.php?employee_id=<?php echo $employeeData['employee_id']; ?>" class="btn btn-primary btn-lg btn-block mb-3">
                                    <i class="fas fa-clock"></i><br>
                                    View Attendance
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="employees/salary.php?employee_id=<?php echo $employeeData['employee_id']; ?>" class="btn btn-success btn-lg btn-block mb-3">
                                    <i class="fas fa-money-bill"></i><br>
                                    View Salary
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="profile.php" class="btn btn-info btn-lg btn-block mb-3">
                                    <i class="fas fa-user"></i><br>
                                    Update Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif (isRenter() && $renterData): ?>
        <!-- Renter Dashboard -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Active Rentals</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $renterData['active_rentals']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-key fa-2x text-gray-300"></i>
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
                                    Total Paid</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($renterData['total_paid']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                                    Remaining Amount</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($renterData['remaining_amount']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-credit-card fa-2x text-gray-300"></i>
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
                                    Next Payment</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $renterData['next_payment_date'] ? formatDate($renterData['next_payment_date']) : 'N/A'; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="parking/rentals.php?user_id=<?php echo $user['id']; ?>" class="btn btn-primary btn-lg btn-block mb-3">
                                    <i class="fas fa-parking"></i><br>
                                    View Rentals
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="payments.php?user_id=<?php echo $user['id']; ?>" class="btn btn-success btn-lg btn-block mb-3">
                                    <i class="fas fa-money-bill"></i><br>
                                    Make Payment
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="profile.php" class="btn btn-info btn-lg btn-block mb-3">
                                    <i class="fas fa-user"></i><br>
                                    Update Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Default Dashboard -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Welcome to <?php echo APP_NAME; ?></h6>
                    </div>
                    <div class="card-body">
                        <p>Welcome to the Construction Company Management System. Please contact your administrator for access to specific features.</p>
                        <a href="profile.php" class="btn btn-primary">
                            <i class="fas fa-user"></i> Update Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>