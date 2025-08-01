<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/header.php';

// Check if user is authenticated
requireAuth();

$user = getCurrentUser();
$company = getCurrentCompany();

if (!$user) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get user-specific dashboard data
$dashboardData = getUserDashboardData($user['id']);

// Get company statistics (for company admin)
$companyStats = null;
if (isCompanyAdmin()) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND status = 'active'");
    $stmt->execute([getCurrentCompanyId()]);
    $activeEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ? AND status != 'retired'");
    $stmt->execute([getCurrentCompanyId()]);
    $totalMachines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([getCurrentCompanyId()]);
    $activeContracts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE company_id = ? AND MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())");
    $stmt->execute([getCurrentCompanyId()]);
    $monthlyExpenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $companyStats = [
        'active_employees' => $activeEmployees,
        'total_machines' => $totalMachines,
        'active_contracts' => $activeContracts,
        'monthly_expenses' => $monthlyExpenses
    ];
}

// Get employee-specific data
$employeeData = null;
if (isEmployee()) {
    $stmt = $conn->prepare("
        SELECT e.*, sp.payment_month, sp.payment_year, sp.working_days, sp.leave_days, 
               sp.total_amount, sp.amount_paid, sp.status as payment_status
        FROM employees e
        LEFT JOIN salary_payments sp ON e.id = sp.employee_id 
            AND sp.payment_month = MONTH(CURRENT_DATE()) 
            AND sp.payment_year = YEAR(CURRENT_DATE())
        WHERE e.user_id = ? AND e.company_id = ?
    ");
    $stmt->execute([$user['id'], getCurrentCompanyId()]);
    $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get attendance for current month
    $stmt = $conn->prepare("
        SELECT COUNT(*) as working_days, 
               SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days
        FROM employee_attendance 
        WHERE employee_id = ? AND company_id = ? 
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$employeeData['id'], getCurrentCompanyId()]);
    $attendanceData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employeeData) {
        $employeeData['current_month_working_days'] = $attendanceData['working_days'] ?? 0;
        $employeeData['current_month_leave_days'] = $attendanceData['leave_days'] ?? 0;
    }
}

// Get renter-specific data
$renterData = null;
if (isRenter()) {
    $stmt = $conn->prepare("
        SELECT * FROM parking_rentals 
        WHERE user_id = ? AND company_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id'], getCurrentCompanyId()]);
    $parkingRentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("
        SELECT * FROM area_rentals 
        WHERE user_id = ? AND company_id = ? AND status = 'active'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['id'], getCurrentCompanyId()]);
    $areaRentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $renterData = [
        'parking_rentals' => $parkingRentals,
        'area_rentals' => $areaRentals
    ];
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h1>
        </div>
    </div>

    <!-- User Role Specific Dashboard -->
    <?php if (isSuperAdmin()): ?>
        <!-- Super Admin Dashboard -->
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Super Admin Dashboard:</strong> You have access to manage all companies and system settings.
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="../super-admin/" class="list-group-item list-group-item-action">
                                <i class="fas fa-tachometer-alt"></i> Super Admin Dashboard
                            </a>
                            <a href="../super-admin/companies/" class="list-group-item list-group-item-action">
                                <i class="fas fa-building"></i> Manage Companies
                            </a>
                            <a href="../super-admin/subscription-plans/" class="list-group-item list-group-item-action">
                                <i class="fas fa-list"></i> Subscription Plans
                            </a>
                            <a href="../super-admin/settings/" class="list-group-item list-group-item-action">
                                <i class="fas fa-cogs"></i> System Settings
                            </a>
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
                                    Active Employees</div>
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
                                    Total Machines</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $companyStats['total_machines']; ?></div>
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
                                    Monthly Expenses</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($companyStats['monthly_expenses']); ?></div>
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
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="../employees/" class="list-group-item list-group-item-action">
                                <i class="fas fa-users"></i> Manage Employees
                            </a>
                            <a href="../machines/" class="list-group-item list-group-item-action">
                                <i class="fas fa-truck"></i> Manage Machines
                            </a>
                            <a href="../contracts/" class="list-group-item list-group-item-action">
                                <i class="fas fa-file-contract"></i> Manage Contracts
                            </a>
                            <a href="../parking/" class="list-group-item list-group-item-action">
                                <i class="fas fa-parking"></i> Parking Management
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Company Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Company:</strong> <?php echo htmlspecialchars($company['company_name']); ?></p>
                        <p><strong>Subscription:</strong> 
                            <span class="badge bg-info"><?php echo ucfirst($company['subscription_plan']); ?></span>
                        </p>
                        <p><strong>Status:</strong> 
                            <span class="badge <?php echo $company['subscription_status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo ucfirst($company['subscription_status']); ?>
                            </span>
                        </p>
                        <?php if ($company['subscription_status'] === 'trial' && $company['trial_ends_at']): ?>
                            <p><strong>Trial Ends:</strong> <?php echo formatDate($company['trial_ends_at']); ?></p>
                        <?php endif; ?>
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
                                    Working Days (This Month)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeData['current_month_working_days']; ?></div>
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
                                    Leave Days (This Month)</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeData['current_month_leave_days']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
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
                                    Remaining Leave Days</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeData['remaining_leave_days']; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Salary Information</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Position:</strong> <?php echo ucfirst(str_replace('_', ' ', $employeeData['position'])); ?></p>
                        <p><strong>Daily Rate:</strong> <?php echo formatCurrency($employeeData['daily_rate']); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge <?php 
                                echo $employeeData['status'] === 'active' ? 'bg-success' : 
                                    ($employeeData['status'] === 'on_leave' ? 'bg-warning' : 'bg-danger'); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $employeeData['status'])); ?>
                            </span>
                        </p>
                        <?php if ($employeeData['payment_status']): ?>
                            <p><strong>Current Month Payment:</strong> 
                                <span class="badge <?php echo $employeeData['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($employeeData['payment_status']); ?>
                                </span>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="../attendance/" class="list-group-item list-group-item-action">
                                <i class="fas fa-clock"></i> View Attendance
                            </a>
                            <a href="../salary/" class="list-group-item list-group-item-action">
                                <i class="fas fa-money-bill"></i> Salary History
                            </a>
                            <a href="../leave/" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-times"></i> Leave Management
                            </a>
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
                                    Active Parking Rentals</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($renterData['parking_rentals']); ?></div>
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
                                    Active Area Rentals</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($renterData['area_rentals']); ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-map-marked-alt fa-2x text-gray-300"></i>
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
                                    Total Payments</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $totalPayments = 0;
                                    foreach ($dashboardData['payments'] as $payment) {
                                        if ($payment['payment_status'] === 'paid') {
                                            $totalPayments += $payment['amount'];
                                        }
                                    }
                                    echo formatCurrency($totalPayments);
                                    ?>
                                </div>
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
                                    Pending Payments</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $pendingPayments = 0;
                                    foreach ($dashboardData['payments'] as $payment) {
                                        if ($payment['payment_status'] === 'pending') {
                                            $pendingPayments += $payment['amount'];
                                        }
                                    }
                                    echo formatCurrency($pendingPayments);
                                    ?>
                                </div>
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
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Active Rentals</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($renterData['parking_rentals']) && empty($renterData['area_rentals'])): ?>
                            <p class="text-muted">No active rentals found.</p>
                        <?php else: ?>
                            <?php foreach ($renterData['parking_rentals'] as $rental): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong>Parking Rental:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?><br>
                                    <small class="text-muted">
                                        Amount: <?php echo formatCurrency($rental['total_amount']); ?> | 
                                        Status: <span class="badge bg-success">Active</span>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php foreach ($renterData['area_rentals'] as $rental): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong>Area Rental:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?><br>
                                    <small class="text-muted">
                                        Amount: <?php echo formatCurrency($rental['total_amount']); ?> | 
                                        Status: <span class="badge bg-success">Active</span>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="../payments/" class="list-group-item list-group-item-action">
                                <i class="fas fa-money-bill"></i> View Payments
                            </a>
                            <a href="../rentals/" class="list-group-item list-group-item-action">
                                <i class="fas fa-list"></i> Rental History
                            </a>
                            <a href="../profile/" class="list-group-item list-group-item-action">
                                <i class="fas fa-user"></i> Update Profile
                            </a>
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
                        <h6 class="m-0 font-weight-bold text-primary">Welcome</h6>
                    </div>
                    <div class="card-body">
                        <p>Welcome to the Construction Company SaaS Platform!</p>
                        <p>Your role: <strong><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></strong></p>
                        <p>Company: <strong><?php echo htmlspecialchars($company['company_name'] ?? 'N/A'); ?></strong></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>