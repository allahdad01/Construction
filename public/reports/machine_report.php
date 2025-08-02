<?php
// Machine Report Template
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

// Get machine data
$machine_data = [];
$machine_utilization = [];
$machine_types = [];

try {
    if ($is_super_admin) {
        // System-wide machine data
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.machine_code,
                m.machine_name,
                m.machine_type,
                m.model,
                m.year,
                m.capacity,
                m.fuel_type,
                m.is_active,
                c.company_name,
                COUNT(DISTINCT ct.id) as active_contracts,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COALESCE(SUM(wh.hours_worked * ct.rate_amount / ct.working_hours_per_day), 0) as earnings
            FROM machines m
            JOIN companies c ON m.company_id = c.id
            LEFT JOIN contracts ct ON m.id = ct.machine_id AND ct.status = 'active'
            LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
            WHERE m.is_active = 1
            GROUP BY m.id
            ORDER BY total_hours DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $machine_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Company-specific machine data
        $stmt = $conn->prepare("
            SELECT 
                m.id,
                m.machine_code,
                m.machine_name,
                m.machine_type,
                m.model,
                m.year,
                m.capacity,
                m.fuel_type,
                m.is_active,
                COUNT(DISTINCT ct.id) as active_contracts,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COALESCE(SUM(wh.hours_worked * ct.rate_amount / ct.working_hours_per_day), 0) as earnings,
                COUNT(DISTINCT wh.date) as working_days
            FROM machines m
            LEFT JOIN contracts ct ON m.id = ct.machine_id AND ct.status = 'active'
            LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
            WHERE m.company_id = ? AND m.is_active = 1
            GROUP BY m.id
            ORDER BY total_hours DESC
        ");
        $stmt->execute([$start_date, $end_date, $company_id]);
        $machine_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get machine type statistics
    $stmt = $conn->prepare("
        SELECT 
            machine_type,
            COUNT(*) as count,
            AVG(year) as avg_year,
            SUM(COALESCE(wh.hours_worked, 0)) as total_hours,
            SUM(COALESCE(wh.hours_worked * ct.rate_amount / ct.working_hours_per_day, 0)) as total_earnings
        FROM machines m
        LEFT JOIN contracts ct ON m.id = ct.machine_id AND ct.status = 'active'
        LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
        WHERE m.is_active = 1
        " . (!$is_super_admin ? "AND m.company_id = ?" : "") . "
        GROUP BY machine_type
    ");
    
    if ($is_super_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $company_id]);
    }
    $machine_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary statistics
    $total_machines = count($machine_data);
    $total_earnings = array_sum(array_column($machine_data, 'earnings'));
    $total_hours = array_sum(array_column($machine_data, 'total_hours'));
    $avg_earnings = $total_machines > 0 ? $total_earnings / $total_machines : 0;
    $avg_hours = $total_machines > 0 ? $total_hours / $total_machines : 0;
    
} catch (Exception $e) {
    $error = "Error loading machine data: " . $e->getMessage();
}
?>

<div class="machine-report">
    <div class="row">
        <!-- Machine Summary -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Machine Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>Total Active Machines</strong></td>
                                    <td><?php echo number_format($total_machines); ?></td>
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
                                    <td><strong>Average Earnings per Machine</strong></td>
                                    <td><?php echo formatCurrency($avg_earnings); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Average Hours per Machine</strong></td>
                                    <td><?php echo number_format($avg_hours, 1); ?> hours</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Machine Types -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance by Machine Type</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Count</th>
                                    <th>Avg Year</th>
                                    <th>Total Hours</th>
                                    <th>Total Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machine_types as $type): ?>
                                <tr>
                                    <td><?php echo ucfirst($type['machine_type']); ?></td>
                                    <td><?php echo number_format($type['count']); ?></td>
                                    <td><?php echo number_format($type['avg_year'], 0); ?></td>
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

    <!-- Machine Performance Chart -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Machine Performance</h6>
                </div>
                <div class="card-body">
                    <canvas id="machinePerformanceChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Machine Utilization -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Machine Utilization</h6>
                </div>
                <div class="card-body">
                    <canvas id="machineUtilizationChart" height="200"></canvas>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Active Machines</span>
                            <span><?php echo number_format($total_machines); ?></span>
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

    <!-- Machine List -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Machine Details</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="machineTable">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Type</th>
                                    <th>Model</th>
                                    <th>Year</th>
                                    <th>Capacity</th>
                                    <th>Fuel Type</th>
                                    <th>Active Contracts</th>
                                    <th>Working Hours</th>
                                    <?php if (!$is_super_admin): ?>
                                    <th>Working Days</th>
                                    <?php endif; ?>
                                    <th>Earnings</th>
                                    <th>Utilization</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machine_data as $machine): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($machine['machine_code']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($machine['machine_name']); ?></small>
                                        <?php if ($is_super_admin): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($machine['company_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $machine['machine_type'] === 'excavator' ? 'primary' : 
                                                ($machine['machine_type'] === 'bulldozer' ? 'success' : 
                                                ($machine['machine_type'] === 'crane' ? 'info' : 'warning')); 
                                        ?>">
                                            <?php echo ucfirst($machine['machine_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($machine['model']); ?></td>
                                    <td><?php echo $machine['year']; ?></td>
                                    <td><?php echo htmlspecialchars($machine['capacity']); ?></td>
                                    <td><?php echo ucfirst($machine['fuel_type']); ?></td>
                                    <td><?php echo number_format($machine['active_contracts']); ?></td>
                                    <td><?php echo number_format($machine['total_hours'], 1); ?> hrs</td>
                                    <?php if (!$is_super_admin): ?>
                                    <td><?php echo number_format($machine['working_days']); ?> days</td>
                                    <?php endif; ?>
                                    <td class="text-success"><?php echo formatCurrency($machine['earnings']); ?></td>
                                    <td>
                                        <?php
                                        $utilization = 0;
                                        if ($machine['total_hours'] > 0) {
                                            // Calculate utilization based on working days and 8-hour standard day
                                            $expected_hours = $machine['working_days'] * 8;
                                            $utilization = $expected_hours > 0 ? ($machine['total_hours'] / $expected_hours) * 100 : 0;
                                        }
                                        $utilization_color = $utilization >= 80 ? 'success' : ($utilization >= 60 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge badge-<?php echo $utilization_color; ?>">
                                            <?php echo number_format($utilization, 1); ?>%
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

    <!-- Top Performing Machines -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Performing Machines</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        // Get top 3 machines by earnings
                        $top_machines = array_slice($machine_data, 0, 3);
                        foreach ($top_machines as $index => $machine):
                        ?>
                        <div class="col-lg-4">
                            <div class="card border-left-success shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                #<?php echo $index + 1; ?> Machine</div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                <?php echo htmlspecialchars($machine['machine_code']); ?>
                                            </div>
                                            <div class="text-xs text-muted">
                                                <?php echo htmlspecialchars($machine['machine_name']); ?>
                                            </div>
                                            <div class="text-xs text-success">
                                                <?php echo formatCurrency($machine['earnings']); ?>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-truck fa-2x text-gray-300"></i>
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

    <!-- Machine Age Analysis -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Machine Age Analysis</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Age Distribution</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Age Range</th>
                                            <th>Count</th>
                                            <th>Avg Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $age_ranges = [
                                            '0-5 years' => [0, 5],
                                            '6-10 years' => [6, 10],
                                            '11-15 years' => [11, 15],
                                            '16+ years' => [16, 999]
                                        ];
                                        
                                        foreach ($age_ranges as $range => $years):
                                            $current_year = date('Y');
                                            $filtered_machines = array_filter($machine_data, function($m) use ($years, $current_year) {
                                                $age = $current_year - $m['year'];
                                                return $age >= $years[0] && $age <= $years[1];
                                            });
                                            $count = count($filtered_machines);
                                            $avg_earnings = $count > 0 ? array_sum(array_column($filtered_machines, 'earnings')) / $count : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo $range; ?></td>
                                            <td><?php echo number_format($count); ?></td>
                                            <td><?php echo formatCurrency($avg_earnings); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Fuel Type Analysis</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fuel Type</th>
                                            <th>Count</th>
                                            <th>Avg Hours</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $fuel_types = array_unique(array_column($machine_data, 'fuel_type'));
                                        foreach ($fuel_types as $fuel):
                                            $filtered_machines = array_filter($machine_data, function($m) use ($fuel) {
                                                return $m['fuel_type'] === $fuel;
                                            });
                                            $count = count($filtered_machines);
                                            $avg_hours = $count > 0 ? array_sum(array_column($filtered_machines, 'total_hours')) / $count : 0;
                                        ?>
                                        <tr>
                                            <td><?php echo ucfirst($fuel); ?></td>
                                            <td><?php echo number_format($count); ?></td>
                                            <td><?php echo number_format($avg_hours, 1); ?> hrs</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Machine Performance Chart
document.addEventListener('DOMContentLoaded', function() {
    const machineCtx = document.getElementById('machinePerformanceChart').getContext('2d');
    const machineChart = new Chart(machineCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_slice(array_column($machine_data, 'machine_code'), 0, 10)); ?>,
            datasets: [{
                label: 'Earnings',
                data: <?php echo json_encode(array_slice(array_column($machine_data, 'earnings'), 0, 10)); ?>,
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

    // Machine Utilization Chart
    const utilizationCtx = document.getElementById('machineUtilizationChart').getContext('2d');
    const utilizationChart = new Chart(utilizationCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Idle', 'Maintenance'],
            datasets: [{
                data: [
                    <?php echo $total_machines; ?>,
                    0,
                    0
                ],
                backgroundColor: ['#1cc88a', '#858796', '#f6c23e'],
                hoverBackgroundColor: ['#17a673', '#6e707e', '#f4b619']
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

    // Initialize DataTable for machine table
    $('#machineTable').DataTable({
        "order": [[9, "desc"]], // Sort by earnings
        "pageLength": 25,
        "responsive": true
    });
});
</script>