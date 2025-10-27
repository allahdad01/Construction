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
                m.name,
                m.type,
                m.model,
                m.year_manufactured,
                m.capacity,
                m.fuel_type,
                m.is_active,
                c.company_name,
                COUNT(DISTINCT ct.id) as active_contracts,
                COALESCE(SUM(wh.hours_worked), 0) as total_hours,
                COUNT(DISTINCT wh.date) as working_days
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
        
        // Calculate earnings based on contract rates and working hours
        foreach ($machine_data as &$machine) {
            // Fetch contract details for this machine
            $stmt = $conn->prepare("
                SELECT 
                    ct.id, 
                    ct.rate_amount, 
                    ct.currency,
                    ct.working_hours_per_day,
                    COUNT(DISTINCT wh.date) as contract_working_days
                FROM contracts ct
                LEFT JOIN working_hours wh ON ct.id = wh.contract_id 
                    AND wh.date BETWEEN ? AND ?
                WHERE ct.machine_id = ? AND ct.status = 'active'
                GROUP BY ct.id
            ");
            $stmt->execute([$start_date, $end_date, $machine['id']]);
            $contract_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total earnings for this machine
            $total_earnings = [];
            foreach ($contract_details as $contract) {
                // If no working hours, skip
                if ($contract['contract_working_days'] == 0) continue;
                
                // Calculate daily rate
                $daily_rate = $contract['rate_amount'];
                $currency = $contract['currency'] ?? 'USD';
                
                // Calculate earnings based on working days
                $earnings = $daily_rate * $contract['contract_working_days'];
                
                // Aggregate earnings by currency
                if (!isset($total_earnings[$currency])) {
                    $total_earnings[$currency] = 0;
                }
                $total_earnings[$currency] += $earnings;
            }
            
            // Store earnings as an array of currency => amount
            $machine['earnings'] = $total_earnings;
        }
        unset($machine);
    } else {
        // Company-specific machine data
        $stmt = $conn->prepare("
                    SELECT 
            m.id,
            m.machine_code,
            m.name,
            m.type,
            m.model,
            m.year_manufactured,
            m.capacity,
            m.fuel_type,
            m.is_active,
            COUNT(DISTINCT ct.id) as active_contracts,
            COALESCE(SUM(wh.hours_worked), 0) as total_hours,
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
        
        // Calculate earnings based on contract rates and working hours
        foreach ($machine_data as &$machine) {
            // Fetch contract details for this machine
            $stmt = $conn->prepare("
                SELECT 
                    ct.id, 
                    ct.rate_amount, 
                    ct.currency,
                    ct.working_hours_per_day,
                    COUNT(DISTINCT wh.date) as contract_working_days
                FROM contracts ct
                LEFT JOIN working_hours wh ON ct.id = wh.contract_id 
                    AND wh.date BETWEEN ? AND ?
                WHERE ct.machine_id = ? AND ct.status = 'active'
                GROUP BY ct.id
            ");
            $stmt->execute([$start_date, $end_date, $machine['id']]);
            $contract_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total earnings for this machine
            $total_earnings = [];
            foreach ($contract_details as $contract) {
                // If no working hours, skip
                if ($contract['contract_working_days'] == 0) continue;
                
                // Calculate daily rate
                $daily_rate = $contract['rate_amount'];
                $currency = $contract['currency'] ?? 'USD';
                
                // Calculate earnings based on working days
                $earnings = $daily_rate * $contract['contract_working_days'];
                
                // Aggregate earnings by currency
                if (!isset($total_earnings[$currency])) {
                    $total_earnings[$currency] = 0;
                }
                $total_earnings[$currency] += $earnings;
            }
            
            // Store earnings as an array of currency => amount
            $machine['earnings'] = $total_earnings;
        }
        unset($machine);
    }
    
    // Get machine type statistics
    $stmt = $conn->prepare("
        SELECT 
            type,
            COUNT(*) as count,
            AVG(year_manufactured) as avg_year,
            SUM(COALESCE(wh.hours_worked, 0)) as total_hours
        FROM machines m
        LEFT JOIN contracts ct ON m.id = ct.machine_id AND ct.status = 'active'
        LEFT JOIN working_hours wh ON ct.id = wh.contract_id AND wh.date BETWEEN ? AND ?
        WHERE m.is_active = 1
        " . (!$is_super_admin ? "AND m.company_id = ?" : "") . "
        GROUP BY type
    ");
    
    if ($is_super_admin) {
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt->execute([$start_date, $end_date, $company_id]);
    }
    $machine_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate earnings for each machine type
    foreach ($machine_types as &$type) {
        // Fetch machines of this type
        $stmt = $conn->prepare("
            SELECT id FROM machines 
            WHERE type = ? AND is_active = 1 
            " . (!$is_super_admin ? "AND company_id = ?" : "")
        );
        
        $params = !$is_super_admin 
            ? [$type['type'], $company_id] 
            : [$type['type']];
        
        $stmt->execute($params);
        $machine_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Calculate total earnings for this type
        $total_type_earnings = [];
        foreach ($machine_ids as $machine_id) {
            // Fetch contract details for this machine
            $stmt = $conn->prepare("
                SELECT 
                    ct.id, 
                    ct.rate_amount, 
                    ct.currency,
                    COUNT(DISTINCT wh.date) as contract_working_days
                FROM contracts ct
                LEFT JOIN working_hours wh ON ct.id = wh.contract_id 
                    AND wh.date BETWEEN ? AND ?
                WHERE ct.machine_id = ? AND ct.status = 'active'
                GROUP BY ct.id
            ");
            $stmt->execute([$start_date, $end_date, $machine_id]);
            $contract_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total earnings for this machine
            foreach ($contract_details as $contract) {
                // If no working hours, skip
                if ($contract['contract_working_days'] == 0) continue;
                
                // Calculate daily rate
                $daily_rate = $contract['rate_amount'];
                $currency = $contract['currency'] ?? 'USD';
                
                // Calculate earnings based on working days
                $earnings = $daily_rate * $contract['contract_working_days'];
                
                // Aggregate earnings by currency
                if (!isset($total_type_earnings[$currency])) {
                    $total_type_earnings[$currency] = 0;
                }
                $total_type_earnings[$currency] += $earnings;
            }
        }
        
        $type['total_earnings'] = $total_type_earnings;
    }
    unset($type);
    
    // Calculate summary statistics
    $total_machines = count($machine_data);
    
    // Aggregate earnings across all currencies
    $total_earnings = [];
    foreach ($machine_data as $machine) {
        if (empty($machine['earnings'])) continue;
        
        foreach ($machine['earnings'] as $currency => $amount) {
            if (!isset($total_earnings[$currency])) {
                $total_earnings[$currency] = 0;
            }
            // Ensure numeric value
            $total_earnings[$currency] += is_numeric($amount) ? $amount : 0;
        }
    }
    
    // Safely handle earnings formatting
    $formatted_total_earnings = [];
    foreach ($total_earnings as $currency => $amount) {
        $formatted_total_earnings[$currency] = is_numeric($amount) ? $amount : 0;
    }
    
    $avg_earnings = [];
    foreach ($total_earnings as $currency => $total) {
        $avg_earnings[$currency] = $total_machines > 0 ? 
            (is_numeric($total) ? $total / $total_machines : 0) : 0;
    }
    
    $total_hours = array_sum(array_column($machine_data, 'total_hours'));
    $avg_hours = $total_machines > 0 ? $total_hours / $total_machines : 0;
    
    // Prepare data for chart (use USD or first available currency)
    $chart_earnings = [];
    $chart_labels = [];
    
    foreach ($machine_data as $machine) {
        $chart_labels[] = $machine['machine_code'];
        
        // Prefer USD, otherwise take the first available currency
        $earnings = 0;
        $currency = 'USD';
        if (isset($machine['earnings']['USD'])) {
            $earnings = $machine['earnings']['USD'];
        } elseif (!empty($machine['earnings'])) {
            $first_currency = key($machine['earnings']);
            $earnings = $machine['earnings'][$first_currency];
            $currency = $first_currency;
        }
        
        // Ensure earnings is numeric
        $chart_earnings[] = [
            'amount' => is_numeric($earnings) ? $earnings : 0,
            'currency' => $currency
        ];
    }
    
    // Limit to top 10 machines
    $chart_labels = array_slice($chart_labels, 0, 10);
    $chart_earnings = array_slice($chart_earnings, 0, 10);
    
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong><?php echo __('total_active_machines'); ?></strong></td>
                                    <td><?php echo number_format($total_machines); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('total_earnings'); ?></strong></td>
                                    <td class="text-success">
                                        <?php 
                                        foreach ($formatted_total_earnings as $currency => $amount): 
                                        ?>
                                            <div><?php echo $currency . ': ' . formatCurrency($amount, null, null, $currency); ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('total_working_hours'); ?></strong></td>
                                    <td><?php echo number_format($total_hours, 1); ?> <?php echo __('hours'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('average_earnings_per_machine'); ?></strong></td>
                                    <td>
                                        <?php 
                                        foreach ($avg_earnings as $currency => $amount): 
                                        ?>
                                            <div><?php echo $currency . ': ' . formatCurrency($amount, null, null, $currency); ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('average_hours_per_machine'); ?></strong></td>
                                    <td><?php echo number_format($avg_hours, 1); ?> <?php echo __('hours'); ?></td>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('performance_by_machine_type'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo __('type'); ?></th>
                                    <th><?php echo __('count'); ?></th>
                                    <th><?php echo __('avg_year'); ?></th>
                                    <th><?php echo __('total_hours'); ?></th>
                                    <th><?php echo __('total_earnings'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machine_types as $type): ?>
                                <tr>
                                    <td><?php echo ucfirst($type['type']); ?></td>
                                    <td><?php echo number_format($type['count']); ?></td>
                                    <td><?php echo number_format($type['avg_year'], 0); ?></td>
                                    <td><?php echo number_format($type['total_hours'], 1); ?></td>
                                    <td class="text-success">
                                        <?php 
                                        if (is_array($type['total_earnings'])) {
                                            foreach ($type['total_earnings'] as $currency => $amount) {
                                                // Ensure amount is numeric
                                                $safe_amount = is_numeric($amount) ? $amount : 0;
                                                echo $currency . ': ' . formatCurrency($safe_amount) . '<br>';
                                            }
                                        } else {
                                            // Ensure amount is numeric
                                            $safe_amount = is_numeric($type['total_earnings'] ?? 0) 
                                                ? ($type['total_earnings'] ?? 0) 
                                                : 0;
                                            echo formatCurrency($safe_amount);
                                        }
                                        ?>
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

    <!-- Machine Performance Chart -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_performance'); ?></h6>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_utilization'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="fixed-chart">
                        <canvas id="machineUtilizationChart"></canvas>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo __('active_machines'); ?></span>
                            <span><?php echo number_format($total_machines); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo __('total_earnings'); ?></span>
                            <span>
                                <?php 
                                foreach ($total_earnings as $currency => $amount): 
                                ?>
                                    <div><?php echo $currency . ': ' . formatCurrency($amount, null, null, $currency); ?></div>
                                <?php endforeach; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo __('total_hours'); ?></span>
                            <span><?php echo number_format($total_hours, 1); ?> <?php echo __('hours'); ?></span>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_details'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="machineTable">
                            <thead>
                                <tr>
                                    <th><?php echo __('machine'); ?></th>
                                    <th><?php echo __('type'); ?></th>
                                    <th><?php echo __('model'); ?></th>
                                    <th><?php echo __('year'); ?></th>
                                    <th><?php echo __('capacity'); ?></th>
                                    <th><?php echo __('fuel_type'); ?></th>
                                    <th><?php echo __('active_contracts'); ?></th>
                                    <th><?php echo __('working_hours'); ?></th>
                                    <?php if (!$is_super_admin): ?>
                                    <th><?php echo __('working_days'); ?></th>
                                    <?php endif; ?>
                                    <th><?php echo __('earnings'); ?></th>
                                    <th><?php echo __('utilization'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($machine_data as $machine): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($machine['machine_code']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($machine['name']); ?></small>
                                        <?php if ($is_super_admin): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($machine['company_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $machine['type'] === 'excavator' ? 'primary' : 
                                                ($machine['type'] === 'bulldozer' ? 'success' : 
                                                ($machine['type'] === 'crane' ? 'info' : 'warning')); 
                                        ?>">
                                            <?php echo ucfirst($machine['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($machine['model']); ?></td>
                                    <td><?php echo $machine['year_manufactured']; ?></td>
                                    <td><?php echo htmlspecialchars($machine['capacity']); ?></td>
                                    <td><?php echo ucfirst($machine['fuel_type']); ?></td>
                                    <td><?php echo number_format($machine['active_contracts']); ?></td>
                                    <td><?php echo number_format($machine['total_hours'], 1); ?> <?php echo __('hours'); ?></td>
                                    <?php if (!$is_super_admin): ?>
                                    <td><?php echo number_format($machine['working_days']); ?> <?php echo __('days'); ?></td>
                                    <?php endif; ?>
                                    <td class="text-success">
                                        <?php 
                                        if (is_array($machine['earnings'])) {
                                            foreach ($machine['earnings'] as $currency => $amount) {
                                                // Ensure amount is numeric
                                                $safe_amount = is_numeric($amount) ? $amount : 0;
                                                echo $currency . ': ' . formatCurrency($safe_amount, null, null, $currency) . '<br>';
                                            }
                                        } else {
                                            // Ensure amount is numeric
                                            $safe_amount = is_numeric($machine['earnings'] ?? 0) 
                                                ? ($machine['earnings'] ?? 0) 
                                                : 0;
                                            echo formatCurrency($safe_amount);
                                        }
                                        ?>
                                    </td>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('top_performing_machines'); ?></h6>
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
                                                #<?php echo $index + 1; ?> <?php echo __('machine'); ?></div>
                                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                                <?php echo htmlspecialchars($machine['machine_code']); ?>
                                            </div>
                                            <div class="text-xs text-muted">
                                                <?php echo htmlspecialchars($machine['name'] ?? ($machine['machine_name'] ?? 'N/A')); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_age_analysis'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?php echo __('age_distribution'); ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('age_range'); ?></th>
                                            <th><?php echo __('count'); ?></th>
                                            <th><?php echo __('avg_earnings'); ?></th>
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
                                                $yearManufactured = $m['year_manufactured'] ?? $m['year'] ?? null;
                                                if (!$yearManufactured) { return false; }
                                                $age = (int)$current_year - (int)$yearManufactured;
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
                            <h6><?php echo __('fuel_type_analysis'); ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?php echo __('fuel_type'); ?></th>
                                            <th><?php echo __('count'); ?></th>
                                            <th><?php echo __('avg_hours'); ?></th>
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
                                            <td><?php echo number_format($avg_hours, 1); ?> <?php echo __('hours'); ?></td>
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
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: '<?php echo __('earnings'); ?>',
                data: <?php 
                    $chart_amounts = array_column($chart_earnings, 'amount');
                    $chart_currencies = array_column($chart_earnings, 'currency');
                    echo json_encode($chart_amounts); 
                ?>,
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
                            // Use the first currency from the chart data
                            const currencies = <?php echo json_encode($chart_currencies); ?>;
                            const currency = currencies[0] || 'USD';
                            return currency + ' ' + number_format(value);
                        }
                    }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, chart) {
                        const currencies = <?php echo json_encode($chart_currencies); ?>;
                        const currency = currencies[tooltipItem.index] || 'USD';
                        return '<?php echo __('earnings'); ?>: ' + currency + ' ' + number_format(tooltipItem.yLabel);
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
            labels: ['<?php echo __('active'); ?>', '<?php echo __('idle'); ?>', '<?php echo __('maintenance'); ?>'],
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