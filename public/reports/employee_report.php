<?php
// Employee Report Template
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

// Get employee data
$employee_data = [];
$performance_data = [];
$attendance_data = [];

try {
    if ($is_super_admin) {
        // System-wide employee data
        $stmt = $conn->prepare("
            SELECT 
                e.id,
                e.first_name,
                e.last_name,
                e.position,
                e.employee_type,
                e.monthly_salary,
                e.daily_rate,
                e.hire_date,
                e.leave_days_used,
                e.leave_days_remaining,
                c.company_name,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COUNT(DISTINCT wh.date) as working_days
            FROM employees e
            JOIN companies c ON e.company_id = c.id
            LEFT JOIN working_hours wh ON e.id = wh.employee_id 
                AND wh.date BETWEEN ? AND ?
            WHERE e.is_active = 1
            GROUP BY e.id
            ORDER BY total_hours DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $employee_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Company-specific employee data
        $stmt = $conn->prepare("
            SELECT 
                e.id,
                e.first_name,
                e.last_name,
                e.position,
                e.employee_type,
                e.monthly_salary,
                e.daily_rate,
                e.hire_date,
                e.leave_days_used,
                e.leave_days_remaining,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COUNT(DISTINCT wh.date) as working_days,
                COALESCE(SUM(wh.hours_worked * c.rate_amount / c.working_hours_per_day), 0) as earnings
            FROM employees e
            LEFT JOIN working_hours wh ON e.id = wh.employee_id 
                AND wh.date BETWEEN ? AND ?
            LEFT JOIN contracts c ON wh.contract_id = c.id
            WHERE e.company_id = ? AND e.is_active = 1
            GROUP BY e.id
            ORDER BY total_hours DESC
        ");
        $stmt->execute([$start_date, $end_date, $company_id]);
        $employee_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attendance data
        $stmt = $conn->prepare("
            SELECT 
                e.id,
                e.first_name,
                e.last_name,
                COUNT(a.id) as present_days,
                COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN a.status = 'leave' THEN 1 END) as leave_days,
                AVG(a.hours_worked) as avg_hours_per_day
            FROM employees e
            LEFT JOIN employee_attendance a ON e.id = a.employee_id 
                AND a.date BETWEEN ? AND ?
            WHERE e.company_id = ? AND e.is_active = 1
            GROUP BY e.id
        ");
        $stmt->execute([$start_date, $end_date, $company_id]);
        $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get performance metrics
    $stmt = $conn->prepare("
        SELECT 
            e.employee_type,
            COUNT(*) as count,
            AVG(e.monthly_salary) as avg_salary,
            AVG(COALESCE(wh.hours_worked, 0)) as avg_hours
        FROM employees e
        LEFT JOIN working_hours wh ON e.id = wh.employee_id 
            AND wh.date BETWEEN ? AND ?
        WHERE e.is_active = 1
        " . (!$is_super_admin ? "AND e.company_id = ?" : "") . "
        GROUP BY e.employee_type
    ");
    
    if ($is_super_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $company_id]);
    }
    $performance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading employee data: " . $e->getMessage();
}

// Calculate summary statistics
$total_employees = count($employee_data);
$total_salary = array_sum(array_column($employee_data, 'monthly_salary'));
$total_hours = array_sum(array_column($employee_data, 'total_hours'));
$avg_salary = $total_employees > 0 ? $total_salary / $total_employees : 0;
$avg_hours = $total_employees > 0 ? $total_hours / $total_employees : 0;
?>

<div class="employee-report">
    <div class="row">
        <!-- Employee Summary -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>Total Employees</strong></td>
                                    <td><?php echo number_format($total_employees); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Salary Budget</strong></td>
                                    <td><?php echo formatCurrency($total_salary); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Average Salary</strong></td>
                                    <td><?php echo formatCurrency($avg_salary); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Working Hours</strong></td>
                                    <td><?php echo number_format($total_hours, 1); ?> hours</td>
                                </tr>
                                <tr>
                                    <td><strong>Average Hours per Employee</strong></td>
                                    <td><?php echo number_format($avg_hours, 1); ?> hours</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance by Type -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance by Type</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Avg Salary</th>
                                    <th>Avg Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performance_data as $type): ?>
                                <tr>
                                    <td><?php echo ucfirst($type['employee_type']); ?></td>
                                    <td><?php echo number_format($type['count']); ?></td>
                                    <td><?php echo formatCurrency($type['avg_salary']); ?></td>
                                    <td><?php echo number_format($type['avg_hours'], 1); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Performance Chart -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Performance</h6>
                </div>
                <div class="card-body">
                    <canvas id="employeePerformanceChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Attendance Overview -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Attendance Overview</h6>
                </div>
                <div class="card-body">
                    <?php if (!$is_super_admin && !empty($attendance_data)): ?>
                        <canvas id="attendanceChart" height="200"></canvas>
                        <div class="mt-3">
                            <?php
                            $total_present = array_sum(array_column($attendance_data, 'present_days'));
                            $total_absent = array_sum(array_column($attendance_data, 'absent_days'));
                            $total_leave = array_sum(array_column($attendance_data, 'leave_days'));
                            ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Present Days</span>
                                <span><?php echo number_format($total_present); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Absent Days</span>
                                <span><?php echo number_format($total_absent); ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Leave Days</span>
                                <span><?php echo number_format($total_leave); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Attendance data not available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Details</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="employeeTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Position</th>
                                    <th>Type</th>
                                    <th>Salary</th>
                                    <th>Working Hours</th>
                                    <th>Working Days</th>
                                    <?php if (!$is_super_admin): ?>
                                    <th>Earnings</th>
                                    <?php endif; ?>
                                    <th>Leave Used</th>
                                    <th>Leave Remaining</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employee_data as $employee): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                        <?php if ($is_super_admin): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($employee['company_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $employee['employee_type'] === 'driver' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($employee['employee_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatCurrency($employee['monthly_salary']); ?></td>
                                    <td><?php echo number_format($employee['total_hours'], 1); ?> hrs</td>
                                    <td><?php echo number_format($employee['working_days']); ?> days</td>
                                    <?php if (!$is_super_admin): ?>
                                    <td class="text-success"><?php echo formatCurrency($employee['earnings']); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo number_format($employee['leave_days_used']); ?> days</td>
                                    <td><?php echo number_format($employee['leave_days_remaining']); ?> days</td>
                                    <td>
                                        <?php
                                        $performance = 0;
                                        if ($employee['total_hours'] > 0) {
                                            $performance = ($employee['total_hours'] / ($employee['working_days'] * 8)) * 100;
                                        }
                                        $performance_color = $performance >= 80 ? 'success' : ($performance >= 60 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-<?php echo $performance_color; ?>">
                                            <?php echo number_format($performance, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performers -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Performers</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        // Get top 3 performers by hours worked
                        $top_performers = array_slice($employee_data, 0, 3);
                        foreach ($top_performers as $index => $employee):
                        ?>
                        <div class="col-lg-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                #<?php echo $index + 1; ?> Performer</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                            </div>
                                            <div class="text-xs text-muted">
                                                <?php echo number_format($employee['total_hours'], 1); ?> hours
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-trophy fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Employee Performance Chart
document.addEventListener('DOMContentLoaded', function() {
    const employeeCtx = document.getElementById('employeePerformanceChart').getContext('2d');
    const employeeChart = new Chart(employeeCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_slice(array_column($employee_data, 'first_name'), 0, 10)); ?>,
            datasets: [{
                label: 'Working Hours',
                data: <?php echo json_encode(array_slice(array_column($employee_data, 'total_hours'), 0, 10)); ?>,
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                borderColor: 'rgb(78, 115, 223)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        beginAtZero: true,
                        callback: function(value) {
                            return value + ' hrs';
                        }
                    }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, chart) {
                        return 'Hours: ' + tooltipItem.yLabel + ' hrs';
                    }
                }
            }
        }
    });

    <?php if (!$is_super_admin && !empty($attendance_data)): ?>
    // Attendance Chart
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceChart = new Chart(attendanceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent', 'Leave'],
            datasets: [{
                data: [
                    <?php echo array_sum(array_column($attendance_data, 'present_days')); ?>,
                    <?php echo array_sum(array_column($attendance_data, 'absent_days')); ?>,
                    <?php echo array_sum(array_column($attendance_data, 'leave_days')); ?>
                ],
                backgroundColor: ['#1cc88a', '#e74a3b', '#f6c23e'],
                hoverBackgroundColor: ['#17a673', '#e53e3e', '#f4b619']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                display: false
            }
        }
    });
    <?php endif; ?>

    // Initialize DataTable for employee table
    $('#employeeTable').DataTable({
        "order": [[4, "desc"]], // Sort by working hours
        "pageLength": 25,
        "responsive": true
    });
});
</script>