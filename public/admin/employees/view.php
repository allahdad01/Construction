<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get employee ID from URL
$employee_id = (int)($_GET['id'] ?? 0);

if (!$employee_id) {
    header('Location: index.php');
    exit;
}

// Get employee details
$stmt = $conn->prepare("
    SELECT e.*, u.email as user_email, u.status as user_status
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.id = ? AND e.company_id = ?
");
$stmt->execute([$employee_id, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: index.php');
    exit;
}

// Get employee statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_contracts,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_contracts,
        SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_earnings
    FROM contracts 
    WHERE assigned_employee_id = ?
");
$stmt->execute([$employee_id]);
$contract_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent contracts
$stmt = $conn->prepare("
    SELECT c.*, p.project_name 
    FROM contracts c 
    LEFT JOIN projects p ON c.project_id = p.id 
    WHERE c.assigned_employee_id = ? 
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$stmt->execute([$employee_id]);
$recent_contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_days,
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days
    FROM employee_attendance 
    WHERE employee_id = ?
");
$stmt->execute([$employee_id]);
$attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get salary payments
$stmt = $conn->prepare("
    SELECT * FROM salary_payments 
    WHERE employee_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 10
");
$stmt->execute([$employee_id]);
$salary_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate salary statistics
$total_paid = array_sum(array_column($salary_payments, 'amount'));
$current_month_salary = $employee['monthly_salary'];
$days_worked_this_month = $attendance_stats['present_days'] ?? 0;
$salary_earned_this_month = ($current_month_salary / 30) * $days_worked_this_month;
$salary_remaining = $salary_earned_this_month - $total_paid;
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user"></i> Employee Details
        </h1>
        <div class="d-flex">
            <a href="edit.php?id=<?php echo $employee_id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> Edit Employee
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Employees
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Employee Header -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                <?php 
                                $name_parts = explode(' ', $employee['name']);
                                echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="mb-1"><?php echo htmlspecialchars($employee['name']); ?></h3>
                            <p class="text-muted mb-2">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                                <span class="badge bg-<?php echo $employee['position'] === 'driver' ? 'info' : 'warning'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $employee['position'])); ?>
                                </span>
                                <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($employee['status']); ?>
                                </span>
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-envelope text-muted me-2"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>">
                                            <?php echo htmlspecialchars($employee['email']); ?>
                                        </a>
                                    </p>
                                    <?php if ($employee['phone']): ?>
                                    <p class="mb-1">
                                        <i class="fas fa-phone text-muted me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($employee['phone']); ?>">
                                            <?php echo htmlspecialchars($employee['phone']); ?>
                                        </a>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1">
                                        <i class="fas fa-dollar-sign text-muted me-2"></i>
                                        Monthly Salary: $<?php echo number_format($employee['monthly_salary'], 2); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar text-muted me-2"></i>
                                        Daily Rate: $<?php echo number_format($employee['daily_rate'], 2); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <h5 class="text-success">$<?php echo number_format($salary_earned_this_month, 2); ?></h5>
                                <small class="text-muted">Earned This Month</small>
                                <hr>
                                <h5 class="text-info"><?php echo $days_worked_this_month; ?> days</h5>
                                <small class="text-muted">Days Worked This Month</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                                Total Contracts
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $contract_stats['total_contracts']; ?></div>
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
                                Total Earnings
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($contract_stats['total_earnings'], 2); ?></div>
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
                                Attendance Rate
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $total_days = $attendance_stats['total_days'] ?: 1;
                                $attendance_rate = ($attendance_stats['present_days'] / $total_days) * 100;
                                echo number_format($attendance_rate, 1) . '%';
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Leave Days Used
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $attendance_stats['leave_days']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-times fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Information -->
    <div class="row">
        <!-- Personal Information -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>First Name:</strong><br><?php echo htmlspecialchars($employee['first_name']); ?></p>
                            <p><strong>Last Name:</strong><br><?php echo htmlspecialchars($employee['last_name']); ?></p>
                            <p><strong>Employee Code:</strong><br><?php echo htmlspecialchars($employee['employee_code']); ?></p>
                            <p><strong>Employee Type:</strong><br><?php echo ucfirst(str_replace('_', ' ', $employee['employee_type'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong><br><a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>"><?php echo htmlspecialchars($employee['email']); ?></a></p>
                            <p><strong>Phone:</strong><br><?php echo $employee['phone'] ? '<a href="tel:' . htmlspecialchars($employee['phone']) . '">' . htmlspecialchars($employee['phone']) . '</a>' : 'Not provided'; ?></p>
                            <p><strong>Status:</strong><br><span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($employee['status']); ?></span></p>
                            <p><strong>Created:</strong><br><?php echo date('M j, Y', strtotime($employee['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($employee['address']): ?>
                    <hr>
                    <p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($employee['address'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Salary Information -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Salary Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Monthly Salary:</strong><br>$<?php echo number_format($employee['monthly_salary'], 2); ?></p>
                            <p><strong>Daily Rate:</strong><br>$<?php echo number_format($employee['daily_rate'], 2); ?></p>
                            <p><strong>Earned This Month:</strong><br>$<?php echo number_format($salary_earned_this_month, 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Total Paid:</strong><br>$<?php echo number_format($total_paid, 2); ?></p>
                            <p><strong>Remaining:</strong><br>$<?php echo number_format($salary_remaining, 2); ?></p>
                            <p><strong>Days Worked:</strong><br><?php echo $days_worked_this_month; ?> days</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="progress mb-3">
                        <?php 
                        $payment_percentage = $salary_earned_this_month > 0 ? ($total_paid / $salary_earned_this_month) * 100 : 0;
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, $payment_percentage); ?>%">
                            <?php echo number_format($payment_percentage, 1); ?>%
                        </div>
                    </div>
                    <small class="text-muted">Payment Progress: $<?php echo number_format($total_paid, 2); ?> of $<?php echo number_format($salary_earned_this_month, 2); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <!-- Recent Contracts -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Contracts</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_contracts)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-contract fa-3x mb-3"></i>
                            <p>No contracts assigned yet</p>
                        </div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Salary Payments -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Salary Payments</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($salary_payments)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-money-bill fa-3x mb-3"></i>
                            <p>No salary payments yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($salary_payments as $payment): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-dollar-sign text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">$<?php echo number_format($payment['amount'], 2); ?></h6>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></small>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-success">Paid</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
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
                        <div class="col-md-3 mb-3">
                            <a href="edit.php?id=<?php echo $employee_id; ?>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-edit fa-2x mb-2"></i>
                                <br>Edit Employee
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../attendance/?employee_id=<?php echo $employee_id; ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <br>View Attendance
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../salary-payments/?employee_id=<?php echo $employee_id; ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-money-bill fa-2x mb-2"></i>
                                <br>Salary Payments
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../contracts/?employee_id=<?php echo $employee_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-file-contract fa-2x mb-2"></i>
                                <br>View Contracts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add any interactive functionality here
    console.log('Employee view page loaded successfully!');
});
</script>

<?php require_once '../../../includes/footer.php'; ?>