<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../../includes/header.php';

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
    SELECT e.*, u.first_name, u.last_name, u.email as user_email, u.status as user_status
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

// Handle leave days submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_leave') {
    try {
        $employee_id_post = (int)$_POST['employee_id'];
        $leave_type = $_POST['leave_type'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $leave_reason = $_POST['leave_reason'] ?? '';
        $half_day = isset($_POST['half_day']) ? 1 : 0;

        // Validate required fields
        if (empty($leave_type) || empty($start_date) || empty($end_date)) {
            throw new Exception("Please fill in all required fields.");
        }

        // Validate employee belongs to current company
        if ($employee_id_post !== $employee_id) {
            throw new Exception("Invalid employee ID.");
        }

        // Calculate business days
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        if ($start > $end) {
            throw new Exception("End date must be after start date.");
        }

        $business_days = 0;
        $current = clone $start;
        
        while ($current <= $end) {
            $day_of_week = $current->format('w');
            if ($day_of_week != 0 && $day_of_week != 6) { // Not Sunday or Saturday
                $business_days++;
            }
            $current->add(new DateInterval('P1D'));
        }

        // Handle half day
        $leave_days = $half_day && $business_days === 1 ? 0.5 : $business_days;

        if ($leave_days <= 0) {
            throw new Exception("No business days selected for leave.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Check if employee has enough remaining leave days
        $stmt = $conn->prepare("SELECT remaining_leave_days FROM employees WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        $current_remaining = $stmt->fetchColumn();

        if ($current_remaining < $leave_days) {
            throw new Exception("Insufficient leave days remaining. Available: {$current_remaining} days, Requested: {$leave_days} days.");
        }

        // Create leave records for each business day
        $current = clone $start;
        while ($current <= $end) {
            $day_of_week = $current->format('w');
            if ($day_of_week != 0 && $day_of_week != 6) { // Business day
                // Check if attendance record exists
                $stmt = $conn->prepare("SELECT id FROM employee_attendance WHERE employee_id = ? AND date = ? AND company_id = ?");
                $stmt->execute([$employee_id, $current->format('Y-m-d'), $company_id]);
                
                if ($stmt->fetch()) {
                    // Update existing record
                    $stmt = $conn->prepare("
                        UPDATE employee_attendance 
                        SET status = 'on_leave', leave_type = ?, notes = ?, updated_at = NOW()
                        WHERE employee_id = ? AND date = ? AND company_id = ?
                    ");
                    $stmt->execute([$leave_type, $leave_reason, $employee_id, $current->format('Y-m-d'), $company_id]);
                } else {
                    // Create new attendance record
                    $stmt = $conn->prepare("
                        INSERT INTO employee_attendance 
                        (company_id, employee_id, date, status, leave_type, notes, created_at) 
                        VALUES (?, ?, ?, 'on_leave', ?, ?, NOW())
                    ");
                    $stmt->execute([$company_id, $employee_id, $current->format('Y-m-d'), $leave_type, $leave_reason]);
                }
            }
            $current->add(new DateInterval('P1D'));
        }

        // Update employee leave balance
        $stmt = $conn->prepare("
            UPDATE employees 
            SET used_leave_days = used_leave_days + ?, 
                remaining_leave_days = remaining_leave_days - ?,
                updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$leave_days, $leave_days, $employee_id, $company_id]);

        // Commit transaction
        $conn->commit();

        $success = "Leave days added successfully! {$leave_days} business days from {$start_date} to {$end_date}.";
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get employee statistics from working_hours (projects they've worked on)
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT wh.contract_id) as total_contracts,
        COUNT(DISTINCT CASE WHEN c.status = 'active' THEN wh.contract_id END) as active_contracts,
        SUM(wh.hours_worked) as total_hours_worked
    FROM working_hours wh
    LEFT JOIN contracts c ON wh.contract_id = c.id
    WHERE wh.employee_id = ? AND wh.company_id = ?
");
$stmt->execute([$employee_id, $company_id]);
$contract_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent contracts (through working_hours)
$stmt = $conn->prepare("
    SELECT DISTINCT c.*, p.name as project_name 
    FROM working_hours wh
    JOIN contracts c ON wh.contract_id = c.id
    LEFT JOIN projects p ON c.project_id = p.id 
    WHERE wh.employee_id = ? AND wh.company_id = ?
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$stmt->execute([$employee_id, $company_id]);
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
    WHERE employee_id = ? AND company_id = ?
    ORDER BY payment_date DESC 
    LIMIT 10
");
$stmt->execute([$employee_id, $company_id]);
$salary_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate salary statistics
$total_paid = array_sum(array_filter(array_column($salary_payments, 'amount_paid'), 'is_numeric'));
$current_month_salary = $employee['monthly_salary'] ?? 0;
$days_worked_this_month = $attendance_stats['present_days'] ?? 0;
$salary_earned_this_month = $current_month_salary > 0 ? ($current_month_salary / 30) * $days_worked_this_month : 0;
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
            <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#addLeaveModal">
                <i class="fas fa-calendar-times"></i> Add Leave Days
            </button>
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
                                $display_name = '';
                                if (!empty($employee['first_name']) && !empty($employee['last_name'])) {
                                    $display_name = $employee['first_name'] . ' ' . $employee['last_name'];
                                } elseif (!empty($employee['name'])) {
                                    $display_name = $employee['name'];
                                } else {
                                    $display_name = 'Employee';
                                }
                                $name_parts = explode(' ', $display_name);
                                echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="mb-1"><?php echo htmlspecialchars($display_name); ?></h3>
                            <p class="text-muted mb-2">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?></span>
                                <span class="badge bg-<?php echo ($employee['position'] ?? '') === 'driver' ? 'info' : 'warning'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $employee['position'] ?? 'N/A')); ?>
                                </span>
                                <span class="badge bg-<?php echo ($employee['status'] ?? 'inactive') === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($employee['status'] ?? 'inactive'); ?>
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
                                Total Hours Worked
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($contract_stats['total_hours_worked'] ?? 0, 1); ?> hrs</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                            <p><strong>Full Name:</strong><br>
                                <?php 
                                if (!empty($employee['first_name']) && !empty($employee['last_name'])) {
                                    echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']);
                                } elseif (!empty($employee['name'])) {
                                    echo htmlspecialchars($employee['name']);
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </p>
                            <p><strong>Employee Code:</strong><br><?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?></p>
                            <p><strong>Position:</strong><br><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></p>
                            <p><strong>Monthly Salary:</strong><br><?php echo formatCurrency($employee['monthly_salary'] ?? 0); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong><br>
                                <?php if (!empty($employee['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>"><?php echo htmlspecialchars($employee['email']); ?></a>
                                <?php else: ?>
                                    Not provided
                                <?php endif; ?>
                            </p>
                            <p><strong>Phone:</strong><br>
                                <?php if (!empty($employee['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($employee['phone']); ?>"><?php echo htmlspecialchars($employee['phone']); ?></a>
                                <?php else: ?>
                                    Not provided
                                <?php endif; ?>
                            </p>
                            <p><strong>Status:</strong><br><span class="badge bg-<?php echo ($employee['status'] ?? 'inactive') === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($employee['status'] ?? 'inactive'); ?></span></p>
                            <p><strong>Hire Date:</strong><br><?php echo $employee['hire_date'] ? date('M j, Y', strtotime($employee['hire_date'])) : 'N/A'; ?></p>
                        </div>
                    </div>
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

        <!-- Recent Leave History -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">Recent Leave History</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get recent leave records
                    $stmt = $conn->prepare("
                        SELECT date, leave_type, notes, status
                        FROM employee_attendance 
                        WHERE employee_id = ? AND company_id = ? AND status = 'on_leave'
                        ORDER BY date DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([$employee_id, $company_id]);
                    $leave_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($leave_records)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-calendar-times fa-3x mb-3"></i>
                            <p>No leave records yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leave_records as $leave): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-calendar-times text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?php echo ucfirst(str_replace('_', ' ', $leave['leave_type'] ?? 'Leave')); ?></h6>
                                <small class="text-muted"><?php echo date('M j, Y', strtotime($leave['date'])); ?></small>
                                <?php if (!empty($leave['notes'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($leave['notes']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="../attendance/index.php?employee_id=<?php echo $employee_id; ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-list"></i> View All Attendance
                            </a>
                        </div>
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
                                <h6 class="mb-0">
                                    <?php 
                                    $currency = $payment['currency'] ?? 'USD';
                                    $amount = $payment['amount_paid'] ?? 0;
                                    echo $currency . ' ' . number_format($amount, 2); 
                                    ?>
                                </h6>
                                <small class="text-muted"><?php echo $payment['payment_date'] ? date('M j, Y', strtotime($payment['payment_date'])) : 'No date'; ?></small>
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

<!-- Add Leave Days Modal -->
<div class="modal fade" id="addLeaveModal" tabindex="-1" aria-labelledby="addLeaveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="POST" id="addLeaveForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLeaveModalLabel">
                        <i class="fas fa-calendar-times"></i> Add Leave Days for <?php echo htmlspecialchars($display_name); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_leave">
                    <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                    
                    <div class="mb-3">
                        <label for="leave_type" class="form-label">Leave Type *</label>
                        <select class="form-control" id="leave_type" name="leave_type" required>
                            <option value="">Select Leave Type</option>
                            <option value="sick">Sick Leave</option>
                            <option value="vacation">Vacation</option>
                            <option value="personal">Personal Leave</option>
                            <option value="emergency">Emergency Leave</option>
                            <option value="maternity">Maternity Leave</option>
                            <option value="paternity">Paternity Leave</option>
                            <option value="unpaid">Unpaid Leave</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="leave_reason" class="form-label">Reason</label>
                        <textarea class="form-control" id="leave_reason" name="leave_reason" rows="3" placeholder="Optional: Provide reason for leave"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="half_day" name="half_day" value="1">
                            <label class="form-check-label" for="half_day">
                                Half Day Leave (applies to single day only)
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Current Leave Status:</strong><br>
                        Total Leave Days: <?php echo $employee['total_leave_days'] ?? 20; ?> days<br>
                        Used Leave Days: <?php echo $employee['used_leave_days'] ?? 0; ?> days<br>
                        Remaining: <?php echo $employee['remaining_leave_days'] ?? 20; ?> days
                    </div>

                    <div id="leaveDaysCount" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-calculator"></i>
                        <strong>Selected Range:</strong> <span id="daysText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-plus"></i> Add Leave Days
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Leave days calculation
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const halfDayCheckbox = document.getElementById('half_day');
    const leaveDaysCount = document.getElementById('leaveDaysCount');
    const daysText = document.getElementById('daysText');

    function calculateLeaveDays() {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        
        if (startDate && endDate && startDate <= endDate) {
            // Calculate business days (excluding weekends)
            let totalDays = 0;
            let currentDate = new Date(startDate);
            
            while (currentDate <= endDate) {
                // Check if it's a weekday (Monday = 1, Sunday = 0)
                const dayOfWeek = currentDate.getDay();
                if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Not Sunday or Saturday
                    totalDays++;
                }
                currentDate.setDate(currentDate.getDate() + 1);
            }
            
            // Handle half day
            if (halfDayCheckbox.checked && totalDays === 1) {
                daysText.textContent = `0.5 business day (Half day)`;
            } else if (halfDayCheckbox.checked && totalDays > 1) {
                daysText.textContent = `${totalDays} business days (Half day option only applies to single day)`;
                halfDayCheckbox.checked = false; // Uncheck if more than 1 day
            } else {
                daysText.textContent = `${totalDays} business day${totalDays !== 1 ? 's' : ''}`;
            }
            
            leaveDaysCount.style.display = 'block';
        } else {
            leaveDaysCount.style.display = 'none';
        }
    }

    // Event listeners for date calculation
    startDateInput.addEventListener('change', calculateLeaveDays);
    endDateInput.addEventListener('change', calculateLeaveDays);
    halfDayCheckbox.addEventListener('change', calculateLeaveDays);

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    startDateInput.min = today;
    
    // Update end date minimum when start date changes
    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
        calculateLeaveDays();
    });

    console.log('Employee view page loaded successfully!');
});
</script>

<?php require_once '../../../includes/footer.php'; ?>