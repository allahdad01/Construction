<?php
// Contract Report Template
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

// Get contract data
$contract_data = [];
$contract_performance = [];
$contract_types = [];

try {
    if ($is_super_admin) {
        // System-wide contract data
        $stmt = $conn->prepare("
            SELECT 
                ct.id,
                ct.contract_code,
                ct.contract_type,
                ct.rate_amount,
                ct.working_hours_per_day,
                ct.start_date,
                ct.end_date,
                ct.status,
                c.company_name,
                p.project_name,
                m.name as machine_name,
                e.name as employee_name,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COALESCE(SUM(wh.hours_worked * ct.rate_amount / ct.working_hours_per_day), 0) as earnings,
                COUNT(DISTINCT wh.date) as working_days
            FROM contracts ct
            JOIN companies c ON ct.company_id = c.id
            JOIN projects p ON ct.project_id = p.id
            JOIN machines m ON ct.machine_id = m.id
            JOIN employees e ON ct.company_id = e.company_id
            LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
            WHERE ct.status = 'active'
            GROUP BY ct.id
            ORDER BY earnings DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $contract_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Company-specific contract data
        $stmt = $conn->prepare("
            SELECT 
                ct.id,
                ct.contract_code,
                ct.contract_type,
                ct.rate_amount,
                ct.working_hours_per_day,
                ct.start_date,
                ct.end_date,
                ct.status,
                p.project_name,
                m.name as machine_name,
                e.name as employee_name,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COALESCE(SUM(wh.hours_worked * ct.rate_amount / ct.working_hours_per_day), 0) as earnings,
                COUNT(DISTINCT wh.date) as working_days,
                COALESCE(SUM(cp.amount), 0) as payments_received
            FROM contracts ct
            JOIN projects p ON ct.project_id = p.id
            JOIN machines m ON ct.machine_id = m.id
            JOIN employees e ON ct.company_id = e.company_id
            LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
            LEFT JOIN contract_payments cp ON ct.id = cp.contract_id AND cp.payment_date BETWEEN ? AND ?
            WHERE ct.company_id = ? AND ct.status = 'active'
            GROUP BY ct.id
            ORDER BY earnings DESC
        ");
        $stmt->execute([$start_date, $end_date, $start_date, $end_date, $company_id]);
        $contract_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get contract type statistics
    $stmt = $conn->prepare("
        SELECT 
            contract_type,
            COUNT(*) as count,
            AVG(rate_amount) as avg_rate,
            SUM(COALESCE(wh.hours_worked, 0)) as total_hours,
            SUM(COALESCE(wh.hours_worked * ct.rate_amount / ct.working_hours_per_day, 0)) as total_earnings
        FROM contracts ct
        LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
        WHERE ct.status = 'active'
        " . (!$is_super_admin ? "AND ct.company_id = ?" : "") . "
        GROUP BY contract_type
    ");
    
    if ($is_super_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $company_id]);
    }
    $contract_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $total_contracts = count($contract_data);
    $total_earnings = array_sum(array_column($contract_data, 'earnings'));
    $total_hours = array_sum(array_column($contract_data, 'total_hours'));
    $avg_earnings = $total_contracts > 0 ? $total_earnings / $total_contracts : 0;
    $avg_hours = $total_contracts > 0 ? $total_hours / $total_contracts : 0;
    
} catch (Exception $e) {
    $error = "Error loading contract data: " . $e->getMessage();
}
?>

<div class="contract-report">
    <div class="row">
        <!-- Contract Summary -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Contract Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>Total Active Contracts</strong></td>
                                    <td><?php echo number_format($total_contracts); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Earnings</strong></td>
                                    <td class="text-success"><?php echo formatCurrency($total_earnings); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Working Hours</strong></td>
                                    <td><?php echo number_format($total_hours, 1); ?> hours</td>
                                </tr>
                                <tr>
                                    <td><strong>Average Earnings per Contract</strong></td>
                                    <td><?php echo formatCurrency($avg_earnings); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Average Hours per Contract</strong></td>
                                    <td><?php echo number_format($avg_hours, 1); ?> hours</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contract Types -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance by Contract Type</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Avg Rate</th>
                                    <th>Total Hours</th>
                                    <th>Total Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contract_types as $type): ?>
                                <tr>
                                    <td><?php echo ucfirst($type['contract_type']); ?></td>
                                    <td><?php echo number_format($type['count']); ?></td>
                                    <td><?php echo formatCurrency($type['avg_rate']); ?></td>
                                    <td><?php echo number_format($type['total_hours'], 1); ?></td>
                                    <td class="text-success"><?php echo formatCurrency($type['total_earnings']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contract Performance Chart -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Contract Performance</h6>
                </div>
                <div class="card-body">
                    <canvas id="contractPerformanceChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Contract Status -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Contract Status</h6>
                </div>
                <div class="card-body">
                    <canvas id="contractStatusChart" height="200"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Active Contracts</span>
                            <span><?php echo number_format($total_contracts); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Total Earnings</span>
                            <span><?php echo formatCurrency($total_earnings); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Total Hours</span>
                            <span><?php echo number_format($total_hours, 1); ?> hrs</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contract List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Contract Details</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="contractTable">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Project</th>
                                    <th>Machine</th>
                                    <th>Employee</th>
                                    <th>Type</th>
                                    <th>Rate</th>
                                    <th>Working Hours</th>
                                    <th>Working Days</th>
                                    <th>Earnings</th>
                                    <?php if (!$is_super_admin): ?>
                                    <th>Payments</th>
                                    <th>Remaining</th>
                                    <?php endif; ?>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contract_data as $contract): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($contract['contract_code']); ?></strong>
                                        <?php if ($is_super_admin): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($contract['company_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($contract['project_name']); ?></td>
                                    <td><?php echo htmlspecialchars($contract['machine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($contract['employee_name']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $contract['contract_type'] === 'hourly' ? 'primary' : 
                                                ($contract['contract_type'] === 'daily' ? 'success' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($contract['contract_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatCurrency($contract['rate_amount']); ?></td>
                                    <td><?php echo number_format($contract['total_hours'], 1); ?> hrs</td>
                                    <td><?php echo number_format($contract['working_days']); ?> days</td>
                                    <td class="text-success"><?php echo formatCurrency($contract['earnings']); ?></td>
                                    <?php if (!$is_super_admin): ?>
                                    <td class="text-info"><?php echo formatCurrency($contract['payments_received']); ?></td>
                                    <td class="text-warning"><?php echo formatCurrency($contract['earnings'] - $contract['payments_received']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php
                                        $progress = 0;
                                        if ($contract['total_hours'] > 0 && $contract['working_hours_per_day'] > 0) {
                                            $expected_hours = $contract['working_days'] * $contract['working_hours_per_day'];
                                            $progress = ($contract['total_hours'] / $expected_hours) * 100;
                                        }
                                        $progress_color = $progress >= 80 ? 'success' : ($progress >= 60 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-<?php echo $progress_color; ?>">
                                            <?php echo number_format($progress, 1); ?>%
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

    <!-- Top Performing Contracts -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Performing Contracts</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        // Get top 3 contracts by earnings
                        $top_contracts = array_slice($contract_data, 0, 3);
                        foreach ($top_contracts as $index => $contract):
                        ?>
                        <div class="col-lg-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                #<?php echo $index + 1; ?> Contract</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                <?php echo htmlspecialchars($contract['contract_code']); ?>
                                            </div>
                                            <div class="text-xs text-muted">
                                                <?php echo htmlspecialchars($contract['project_name']); ?>
                                            </div>
                                            <div class="text-xs text-success">
                                                <?php echo formatCurrency($contract['earnings']); ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-file-contract fa-2x text-gray-300"></i>
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
// Contract Performance Chart
document.addEventListener('DOMContentLoaded', function() {
    const contractCtx = document.getElementById('contractPerformanceChart').getContext('2d');
    const contractChart = new Chart(contractCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_slice(array_column($contract_data, 'contract_code'), 0, 10)); ?>,
            datasets: [{
                label: 'Earnings',
                data: <?php echo json_encode(array_slice(array_column($contract_data, 'earnings'), 0, 10)); ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: 'rgb(40, 167, 69)',
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
                            return '$' + number_format(value);
                        }
                    }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, chart) {
                        return 'Earnings: $' + number_format(tooltipItem.yLabel);
                    }
                }
            }
        }
    });

    // Contract Status Chart
    const statusCtx = document.getElementById('contractStatusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Completed', 'Pending'],
            datasets: [{
                data: [
                    <?php echo $total_contracts; ?>,
                    0,
                    0
                ],
                backgroundColor: ['#1cc88a', '#36b9cc', '#f6c23e'],
                hoverBackgroundColor: ['#17a673', '#2c9faf', '#f4b619']
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

    // Initialize DataTable for contract table
    $('#contractTable').DataTable({
        "order": [[8, "desc"]], // Sort by earnings
        "pageLength": 25,
        "responsive": true
    });
});
</script>