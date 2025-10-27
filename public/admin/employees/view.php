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

        // Calculate business days (drivers and driver assistants: all days are business days)
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        if ($start > $end) {
            throw new Exception("End date must be after start date.");
        }

        $isDriverRole = in_array(strtolower($employee['position'] ?? ''), ['driver','driver_assistant']);

        $business_days = 0;
        $current = clone $start;
        
        while ($current <= $end) {
            $day_of_week = (int)$current->format('w');
            $isBusinessDay = $isDriverRole ? true : ($day_of_week !== 0 && $day_of_week !== 6);
            if ($isBusinessDay) {
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
            $day_of_week = (int)$current->format('w');
            $isDriverRole = in_array(strtolower($employee['position'] ?? ''), ['driver','driver_assistant']);
            $isBusinessDay = $isDriverRole ? true : ($day_of_week !== 0 && $day_of_week !== 6);
            if ($isBusinessDay) { // Business day
                // Check if attendance record exists
                $stmt = $conn->prepare("SELECT id FROM employee_attendance WHERE employee_id = ? AND date = ? AND company_id = ?");
                $stmt->execute([$employee_id, $current->format('Y-m-d'), $company_id]);
                
                if ($stmt->fetch()) {
                    // Update existing record
                    $stmt = $conn->prepare("
                        UPDATE employee_attendance 
                        SET status = 'leave', leave_type = ?, notes = ?, updated_at = NOW()
                        WHERE employee_id = ? AND date = ? AND company_id = ?
                    ");
                    $stmt->execute([$leave_type, $leave_reason, $employee_id, $current->format('Y-m-d'), $company_id]);
                } else {
                    // Create new attendance record
                    $stmt = $conn->prepare("
                        INSERT INTO employee_attendance 
                        (company_id, employee_id, date, status, leave_type, notes, created_at) 
                        VALUES (?, ?, ?, 'leave', ?, ?, NOW())
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

// After processing leave, ensure attendance is filled as present for every day from hire date to today (except leave days)
try {
    $hireDateRaw = $employee['hire_date'] ?? null;
    $startDate = $hireDateRaw ? new DateTime($hireDateRaw) : null;
    $todayDate = new DateTime();
    if ($startDate && $startDate <= $todayDate) {
        // Cap range to avoid extreme loops (e.g., 10 years)
        $maxDays = 3650; // ~10 years
        $rangeDays = $startDate->diff($todayDate)->days;
        if ($rangeDays > $maxDays) {
            $startDate = (new DateTime())->sub(new DateInterval('P' . $maxDays . 'D'));
        }

        // Fetch work suspensions for this employee
        $suspension_stmt = $conn->prepare("
            SELECT suspension_start_date, suspension_end_date 
            FROM employee_work_suspensions 
            WHERE employee_id = ? AND company_id = ? 
            
            AND (suspension_end_date IS NULL OR suspension_end_date >= ?)
        ");
        $suspension_stmt->execute([$employee_id, $company_id, $startDate->format('Y-m-d')]);
        $suspensions = $suspension_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Load existing attendance in range
        $stmt = $conn->prepare("SELECT date, status FROM employee_attendance WHERE employee_id = ? AND company_id = ? AND date BETWEEN ? AND ?");
        $stmt->execute([$employee_id, $company_id, $startDate->format('Y-m-d'), $todayDate->format('Y-m-d')]);
        $existingByDate = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingByDate[$row['date']] = $row['status'];
        }

        // Prepare insert
        $ins = $conn->prepare("INSERT INTO employee_attendance (company_id, employee_id, date, status, created_at) VALUES (?, ?, ?, 'present', NOW())");

        $cursor = clone $startDate;
        while ($cursor <= $todayDate) {
            $d = $cursor->format('Y-m-d');
            
            // Check if the current date is within any suspension period
            $is_suspended = false;
            foreach ($suspensions as $suspension) {
                $suspension_start = new DateTime($suspension['suspension_start_date']);
                $suspension_end = $suspension['suspension_end_date'] ? new DateTime($suspension['suspension_end_date']) : $todayDate;
                
                if ($cursor >= $suspension_start && $cursor <= $suspension_end) {
                    $is_suspended = true;
                    break;
                }
            }

            // Skip suspended days
            if (!$is_suspended && !isset($existingByDate[$d])) {
                $ins->execute([$company_id, $employee_id, $d]);
            }
            
            $cursor->add(new DateInterval('P1D'));
        }
    }
} catch (Exception $e) {
    // Do not block the page if autofill fails
}

// Fetch work suspensions
$stmt = $conn->prepare("
    SELECT * FROM employee_work_suspensions 
    WHERE employee_id = ? AND company_id = ?
    ORDER BY suspension_start_date DESC
");
$stmt->execute([$employee_id, $company_id]);
$work_suspensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        COUNT(CASE WHEN ea.status = 'present' THEN 1 END) as present_days,
        COUNT(CASE WHEN ea.status = 'absent' THEN 1 END) as absent_days,
        COUNT(CASE WHEN ea.status = 'leave' THEN 1 END) as leave_days
    FROM employee_attendance ea
    LEFT JOIN employee_work_suspensions ews ON 
        ea.employee_id = ews.employee_id AND 
        ea.date BETWEEN ews.suspension_start_date AND COALESCE(ews.suspension_end_date, CURRENT_DATE)
    WHERE ea.employee_id = ? AND 
        (ews.id IS NULL)
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

// Calculate days worked, excluding suspension days
$stmt = $conn->prepare("
    SELECT COUNT(*) as days_worked
    FROM employee_attendance ea
    LEFT JOIN employee_work_suspensions ews ON 
        ea.employee_id = ews.employee_id AND 
        ea.date BETWEEN ews.suspension_start_date AND COALESCE(ews.suspension_end_date, CURRENT_DATE)
    WHERE ea.employee_id = ? 
        AND ea.status = 'present'
        AND (ews.id IS NULL)
");
$stmt->execute([$employee_id]);
$days_worked_this_month = $stmt->fetchColumn();

$salary_earned_this_month = $current_month_salary > 0 ? ($current_month_salary / 30) * $days_worked_this_month : 0;
$salary_remaining = $salary_earned_this_month - $total_paid;
$salary_currency = $employee['salary_currency'] ?? 'AFN';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user"></i> <?php echo __('employee_details'); ?>
        </h1>
        <div class="d-flex">
            <a href="edit.php?id=<?php echo $employee_id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> <?php echo __('edit_employee'); ?>
            </a>
            <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#addLeaveModal">
                <i class="fas fa-calendar-times"></i> <?php echo __('add_leave_days'); ?>
            </button>
            <a href="print.php?id=<?php echo $employee_id; ?>" target="_blank" class="btn btn-outline-dark me-2">
                <i class="fas fa-print"></i> <?php echo __('print'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_employees'); ?>
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
                                        Monthly Salary: <?php echo formatCurrencyAmount($employee['monthly_salary'] ?? 0, $salary_currency); ?>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-calendar text-muted me-2"></i>
                                        Daily Rate: <?php echo formatCurrencyAmount(($employee['monthly_salary'] ?? 0) / 30, $salary_currency); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-end">
                                <h5 class="text-success"><?php echo formatCurrencyAmount($salary_earned_this_month, $salary_currency); ?></h5>
                                <small class="text-muted">Earned This Month</small>
                                <hr>
                                <h5 class="text-info"><?php echo $days_worked_this_month; ?> days</h5>
                                <small class="text-muted">Days Worked This Month</small>
                                <br>
                                <small class="text-muted">Leave Days This Month: <?php echo $attendance_stats['leave_days'] ?? 0; ?></small>
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
                                <?php echo __('total_contracts'); ?>
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
                                <?php echo __('total_hours_worked'); ?>
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
                                <?php echo __('attendance_rate'); ?>
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
                                <?php echo __('leave_days_used'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('personal_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('full_name'); ?>:</strong><br>
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
                            <p><strong><?php echo __('employee_code'); ?>:</strong><br><?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo __('position'); ?>:</strong><br><?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo __('monthly_salary'); ?>:</strong><br><?php echo formatCurrencyAmount($employee['monthly_salary'] ?? 0, $salary_currency); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('email'); ?>:</strong><br>
                                <?php if (!empty($employee['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>"><?php echo htmlspecialchars($employee['email']); ?></a>
                                <?php else: ?>
                                    <?php echo __('not_provided'); ?>
                                <?php endif; ?>
                            </p>
                            <p><strong><?php echo __('phone'); ?>:</strong><br>
                                <?php if (!empty($employee['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($employee['phone']); ?>"><?php echo htmlspecialchars($employee['phone']); ?></a>
                                <?php else: ?>
                                    <?php echo __('not_provided'); ?>
                                <?php endif; ?>
                            </p>
                            <p><strong><?php echo __('status'); ?>:</strong><br><span class="badge bg-<?php echo ($employee['status'] ?? 'inactive') === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($employee['status'] ?? 'inactive'); ?></span></p>
                            <p><strong><?php echo __('hire_date'); ?>:</strong><br><?php echo $employee['hire_date'] ? date('M j, Y', strtotime($employee['hire_date'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Information -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('salary_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('monthly_salary'); ?>:</strong><br><?php echo formatCurrencyAmount($employee['monthly_salary'], $salary_currency); ?></p>
                            <p><strong><?php echo __('daily_rate'); ?>:</strong><br><?php echo formatCurrencyAmount(($employee['monthly_salary'] ?? 0) / 30, $salary_currency); ?></p>
                            <p><strong><?php echo __('earned_this_month'); ?>:</strong><br><?php echo formatCurrencyAmount($salary_earned_this_month, $salary_currency); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('total_paid'); ?>:</strong><br><?php echo formatCurrencyAmount($total_paid, $salary_currency); ?></p>
                            <p><strong><?php echo __('remaining'); ?>:</strong><br><?php echo formatCurrencyAmount($salary_remaining, $salary_currency); ?></p>
                            <p><strong><?php echo __('days_worked'); ?>:</strong><br><?php echo $days_worked_this_month; ?> days</p>
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
                    <small class="text-muted"><?php echo __('payment_progress'); ?>: <?php echo formatCurrencyAmount($total_paid, $salary_currency); ?> <?php echo __('of'); ?> <?php echo formatCurrencyAmount($salary_earned_this_month, $salary_currency); ?></small>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('recent_contracts'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_contracts)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-file-contract fa-3x mb-3"></i>
                            <p><?php echo __('no_contracts_assigned_yet'); ?></p>
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
                                <h6 class="mb-0"><?php echo htmlspecialchars($contract['contract_name'] ?? ($contract['contract_code'] ?? ('Contract #' . ($contract['id'] ?? 'N/A')))); ?></h6>
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
                    <h6 class="m-0 font-weight-bold text-warning"><?php echo __('recent_leave_history'); ?></h6>
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
                            <p><?php echo __('no_leave_records_yet'); ?></p>
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
                                <i class="fas fa-list"></i> <?php echo __('view_all_attendance'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('recent_salary_payments'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($salary_payments)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-money-bill fa-3x mb-3"></i>
                            <p><?php echo __('no_salary_payments_yet'); ?></p>
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
        <!-- Work Suspensions -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning"><?php echo __('work_suspensions'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($work_suspensions)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-pause-circle fa-3x mb-3"></i>
                            <p><?php echo __('no_work_suspensions_yet'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($work_suspensions as $suspension): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="fas fa-pause-circle text-white"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0"><?php echo __('work_suspension'); ?></h6>
                                <small class="text-muted">
                                    <?php echo date('M j, Y', strtotime($suspension['suspension_start_date'])); ?> 
                                    <?php if ($suspension['suspension_end_date']): ?>
                                        - <?php echo date('M j, Y', strtotime($suspension['suspension_end_date'])); ?>
                                    <?php else: ?>
                                        (<?php echo __('ongoing'); ?>)
                                    <?php endif; ?>
                                </small>
                                <?php if (!empty($suspension['reason'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($suspension['reason']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="badge bg-<?php echo $suspension['status'] === 'active' ? 'warning' : 'secondary'; ?>">
                                    <?php echo ucfirst($suspension['status']); ?>
                                </span>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="edit.php?id=<?php echo $employee_id; ?>" class="btn btn-outline-warning w-100">
                                <i class="fas fa-edit fa-2x mb-2"></i>
                                <br><?php echo __('edit_employee'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../attendance/?employee_id=<?php echo $employee_id; ?>" class="btn btn-outline-info w-100">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <br><?php echo __('view_attendance'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../salary-payments/?employee_id=<?php echo $employee_id; ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-money-bill fa-2x mb-2"></i>
                                <br><?php echo __('salary_payments'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="../contracts/?employee_id=<?php echo $employee_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-file-contract fa-2x mb-2"></i>
                                <br><?php echo __('view_contracts'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="#" class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#workSuspensionModal">
                                <i class="fas fa-pause-circle fa-2x mb-2"></i>
                                <br><?php echo __('manage_suspensions'); ?>
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
                        <i class="fas fa-calendar-times"></i> <?php echo __('add_leave_days_for'); ?> <?php echo htmlspecialchars($display_name); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_leave">
                    <input type="hidden" name="employee_id" value="<?php echo $employee_id; ?>">
                    
                    <div class="mb-3">
                        <label for="leave_type" class="form-label"><?php echo __('leave_type'); ?> *</label>
                        <select class="form-control" id="leave_type" name="leave_type" required>
                            <option value=""><?php echo __('select_leave_type'); ?></option>
                            <option value="sick"><?php echo __('sick_leave'); ?></option>
                            <option value="vacation"><?php echo __('vacation'); ?></option>
                            <option value="personal"><?php echo __('personal_leave'); ?></option>
                            <option value="emergency"><?php echo __('emergency_leave'); ?></option>
                            <option value="maternity"><?php echo __('maternity_leave'); ?></option>
                            <option value="paternity"><?php echo __('paternity_leave'); ?></option>
                            <option value="unpaid"><?php echo __('unpaid_leave'); ?></option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label"><?php echo __('end_date'); ?> *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="leave_reason" class="form-label"><?php echo __('reason'); ?></label>
                        <textarea class="form-control" id="leave_reason" name="leave_reason" rows="3" placeholder="<?php echo __('optional_provide_reason_for_leave'); ?>"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="half_day" name="half_day" value="1">
                            <label class="form-check-label" for="half_day">
                                <?php echo __('half_day_leave_applies_to_single_day_only'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong><?php echo __('current_leave_status'); ?>:</strong><br>
                        <?php echo __('total_leave_days'); ?>: <?php echo $employee['total_leave_days'] ?? 20; ?> <?php echo __('days'); ?><br>
                        <?php echo __('used_leave_days'); ?>: <?php echo $employee['used_leave_days'] ?? 0; ?> <?php echo __('days'); ?><br>
                        <?php echo __('remaining'); ?>: <?php echo $employee['remaining_leave_days'] ?? 20; ?> <?php echo __('days'); ?>
                    </div>

                    <div id="leaveDaysCount" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-calculator"></i>
                        <strong><?php echo __('selected_range'); ?>:</strong> <span id="daysText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-plus"></i> <?php echo __('add_leave_days'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Work Suspension Modal -->
<div class="modal fade" id="workSuspensionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('work_suspensions'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><?php echo __('start_date'); ?></th>
                                <th><?php echo __('end_date'); ?></th>
                                <th><?php echo __('reason'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($work_suspensions as $suspension): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($suspension['suspension_start_date'])); ?></td>
                                <td>
                                    <?php 
                                    echo $suspension['suspension_end_date'] 
                                        ? date('M j, Y', strtotime($suspension['suspension_end_date'])) 
                                        : '<span class="badge bg-warning">' . __('ongoing') . '</span>'; 
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($suspension['reason'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $suspension['status'] === 'active' ? 'warning' : 'secondary'; 
                                    ?>">
                                        <?php echo ucfirst($suspension['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?php echo $employee_id; ?>&suspension_id=<?php echo $suspension['id']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <a href="edit.php?id=<?php echo $employee_id; ?>#work-suspensions" class="btn btn-success">
                        <i class="fas fa-plus"></i> <?php echo __('add_suspension'); ?>
                    </a>
                </div>
            </div>
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