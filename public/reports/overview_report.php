<?php
// Overview Report Template
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

// Get overview data
$overview_data = [
    'total_companies' => 0,
    'total_employees' => 0,
    'total_machines' => 0,
    'total_contracts' => 0,
    'total_hours' => 0,
    'total_revenue' => 0,
    'total_earnings' => 0,
    'total_expenses' => 0,
    'total_salary_payments' => 0
];
$trend_data = [];
$insights = [];

try {
    if ($is_super_admin) {
        // System-wide overview data
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_companies,
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT m.id) as total_machines,
                COUNT(DISTINCT ct.id) as total_contracts,
                SUM(COALESCE(wh.hours_worked, 0)) as total_hours,
                SUM(COALESCE(cp.amount, 0)) as total_revenue
            FROM companies c
            LEFT JOIN employees e ON c.id = e.company_id AND e.is_active = 1
            LEFT JOIN machines m ON c.id = m.company_id AND m.is_active = 1
            LEFT JOIN contracts ct ON c.id = ct.company_id AND ct.status = 'active'
            LEFT JOIN working_hours wh ON e.id = wh.employee_id AND wh.date BETWEEN ? AND ?
            LEFT JOIN company_payments cp ON c.id = cp.company_id AND cp.payment_date BETWEEN ? AND ? AND cp.payment_status = 'completed'
        ");
        $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $overview_data = array_merge($overview_data, $result);
        }
        
        // Get growth trends
        $stmt = $conn->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_companies
            FROM companies 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute([$start_date, $end_date]);
        $trend_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Company-specific overview data
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT e.id) as total_employees,
                COUNT(DISTINCT m.id) as total_machines,
                COUNT(DISTINCT ct.id) as total_contracts,
                SUM(COALESCE(wh.hours_worked, 0)) as total_hours,
                SUM(COALESCE(exp.amount, 0)) as total_expenses,
                SUM(COALESCE(sp.amount_paid, 0)) as total_salary_payments
            FROM employees e
            LEFT JOIN machines m ON e.company_id = m.company_id AND m.is_active = 1
            LEFT JOIN contracts ct ON e.company_id = ct.company_id AND ct.status = 'active'
            LEFT JOIN working_hours wh ON e.id = wh.employee_id AND wh.date BETWEEN ? AND ?
            LEFT JOIN expenses exp ON e.company_id = exp.company_id AND exp.expense_date BETWEEN ? AND ?
            LEFT JOIN salary_payments sp ON e.company_id = sp.company_id AND sp.payment_date BETWEEN ? AND ?
            WHERE e.company_id = ? AND e.is_active = 1
        ");
        $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $company_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $overview_data = array_merge($overview_data, $result);
        }
        // Contracted hours estimate over date range
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(
                COALESCE(total_hours_required,
                    (COALESCE(working_hours_per_day, 8) * 
                     GREATEST(0, DATEDIFF(LEAST(COALESCE(end_date, :end_date), :end_date), GREATEST(COALESCE(start_date, :start_date), :start_date)) + 1)
                    )
                )
            ), 0) as total_contract_hours
            FROM contracts
            WHERE company_id = :company_id AND status = 'active'
              AND COALESCE(end_date, :end_date) >= :start_date
              AND COALESCE(start_date, :start_date) <= :end_date
        ");
        $stmt->execute([':company_id' => $company_id, ':start_date' => $start_date, ':end_date' => $end_date]);
        $overview_data['total_contract_hours'] = $stmt->fetchColumn();
        
        // Compute total earnings from payments (contracts + area rentals + parking) per currency
        // Contracts
        $stmt = $conn->prepare("
            SELECT COALESCE(cp.currency, c.currency, 'USD') as currency, COALESCE(SUM(cp.amount), 0) as total
            FROM contract_payments cp
            JOIN contracts c ON cp.contract_id = c.id
            WHERE cp.company_id = ? AND cp.status = 'completed' AND cp.payment_date BETWEEN ? AND ?
            GROUP BY COALESCE(cp.currency, c.currency, 'USD')
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $earn_contracts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Area rentals
        $stmt = $conn->prepare("
            SELECT COALESCE(currency, 'USD') as currency, COALESCE(SUM(amount), 0) as total
            FROM area_rental_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY COALESCE(currency, 'USD')
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $earn_areas = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Parking
        $stmt = $conn->prepare("
            SELECT COALESCE(currency, 'USD') as currency, COALESCE(SUM(amount), 0) as total
            FROM parking_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY COALESCE(currency, 'USD')
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $earn_parking = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Merge
        $earn_map = [];
        foreach ([$earn_contracts, $earn_areas, $earn_parking] as $src) {
            foreach ($src as $cur => $amt) {
                $earn_map[$cur] = ($earn_map[$cur] ?? 0) + ($amt ?? 0);
            }
        }
        $overview_data['total_earnings_by_currency'] = $earn_map;
        $overview_data['total_earnings'] = array_sum($earn_map);

        // Expenses by currency
        $stmt = $conn->prepare("
            SELECT COALESCE(currency, 'USD') as currency, COALESCE(SUM(amount), 0) as total
            FROM expenses
            WHERE company_id = ? AND expense_date BETWEEN ? AND ?
            GROUP BY COALESCE(currency, 'USD')
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $exp_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $overview_data['total_expenses_by_currency'] = $exp_map;
        $overview_data['total_expenses'] = array_sum($exp_map);

        // Salary payments by currency
        $stmt = $conn->prepare("
            SELECT COALESCE(currency, 'USD') as currency, COALESCE(SUM(amount_paid), 0) as total
            FROM salary_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY COALESCE(currency, 'USD')
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $sal_map = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        $overview_data['total_salary_by_currency'] = $sal_map;
        $overview_data['total_salary_payments'] = array_sum($sal_map);

        // Earnings trend: sum all payment sources by date
        $trend_data = [];
        // Contract payments by date
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, SUM(amount) as revenue
            FROM contract_payments
            WHERE company_id = ? AND status = 'completed' AND payment_date BETWEEN ? AND ?
            GROUP BY DATE(payment_date)
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $trend_data[$row['date']] = ($trend_data[$row['date']] ?? 0) + ($row['revenue'] ?? 0);
        }
        // Area rental payments by date
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, SUM(amount) as revenue
            FROM area_rental_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY DATE(payment_date)
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $trend_data[$row['date']] = ($trend_data[$row['date']] ?? 0) + ($row['revenue'] ?? 0);
        }
        // Parking payments by date
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, SUM(amount) as revenue
            FROM parking_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY DATE(payment_date)
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $trend_data[$row['date']] = ($trend_data[$row['date']] ?? 0) + ($row['revenue'] ?? 0);
        }
        ksort($trend_data);
        // Normalize for chart
        $trend_data = array_map(function($date, $val){ return ['date' => $date, 'earnings' => $val]; }, array_keys($trend_data), $trend_data);
    }
    
    // Generate insights
    $insights = generateInsights($conn, $overview_data, $trend_data, $is_super_admin, $company_id);
    
} catch (Exception $e) {
    $error = "Error loading overview data: " . $e->getMessage();
}

function generateInsights($conn, $overview_data, $trend_data, $is_super_admin, $company_id) {
    $insights = [];
    
    if ($is_super_admin) {
        // System-wide insights
        if ($overview_data['total_companies'] > 0) {
            $avg_employees = $overview_data['total_employees'] / $overview_data['total_companies'];
            $insights[] = "Average of " . number_format($avg_employees, 1) . " employees per company";
        }
        
        if ($overview_data['total_hours'] > 0) {
            $avg_hours_per_employee = $overview_data['total_hours'] / $overview_data['total_employees'];
            $insights[] = "Average of " . number_format($avg_hours_per_employee, 1) . " hours per employee";
        }
        
        // Growth trend
        if (count($trend_data) > 1) {
            $first_day = $trend_data[0]['new_companies'];
            $last_day = end($trend_data)['new_companies'];
            if ($first_day > 0) {
                $growth_rate = (($last_day - $first_day) / $first_day) * 100;
                $insights[] = "Company growth rate: " . number_format($growth_rate, 1) . "%";
            }
        }
        
    } else {
        // Company-specific insights
        if ($overview_data['total_employees'] > 0) {
            $avg_hours_per_employee = $overview_data['total_hours'] / $overview_data['total_employees'];
            $insights[] = "Average of " . number_format($avg_hours_per_employee, 1) . " hours per employee";
        }
        
        if ($overview_data['total_earnings'] > 0 && $overview_data['total_expenses'] > 0) {
            $profit_margin = (($overview_data['total_earnings'] - $overview_data['total_expenses']) / $overview_data['total_earnings']) * 100;
            $insights[] = "Profit margin: " . number_format($profit_margin, 1) . "%";
        }
        
        if ($overview_data['total_earnings'] > 0) {
            $earnings_per_hour = $overview_data['total_earnings'] / $overview_data['total_hours'];
            $insights[] = "Earnings per hour: " . formatCurrency($earnings_per_hour);
        }
    }
    
    return $insights;
}
?>

<div class="overview-report">
    <!-- Key Metrics -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Key Metrics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($is_super_admin): ?>
                            <!-- Super Admin Metrics -->
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-primary"><?php echo number_format($overview_data['total_companies']); ?></h3>
                                    <p class="text-muted mb-0">Total Companies</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-success"><?php echo number_format($overview_data['total_employees']); ?></h3>
                                    <p class="text-muted mb-0">Total Employees</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-info"><?php echo number_format($overview_data['total_machines']); ?></h3>
                                    <p class="text-muted mb-0">Total Machines</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-warning"><?php echo number_format($overview_data['total_hours'], 1); ?></h3>
                                    <p class="text-muted mb-0">Total Hours</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Tenant Admin Metrics -->
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-primary"><?php echo number_format($overview_data['total_employees']); ?></h3>
                                    <p class="text-muted mb-0">Total Employees</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <?php if (!empty($overview_data['total_earnings_by_currency'])): ?>
                                        <?php $idx = 0; foreach ($overview_data['total_earnings_by_currency'] as $cur => $amt): ?>
                                            <h4 class="text-success <?php echo $idx++ > 0 ? 'small' : ''; ?>"><?php echo formatCurrencyAmount($amt, $cur); ?></h4>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <h3 class="text-success">$0.00</h3>
                                    <?php endif; ?>
                                    <p class="text-muted mb-0">Total Earnings</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <h4 class="text-info mb-1"><?php echo number_format($overview_data['total_hours'], 1); ?> hrs</h4>
                                    <div class="small text-muted"><?php echo number_format($overview_data['total_contract_hours'] ?? 0, 1); ?> hrs contracted</div>
                                    <p class="text-muted mb-0">Total Hours</p>
                                </div>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="border rounded p-3">
                                    <?php if (!empty($overview_data['total_expenses_by_currency'])): ?>
                                        <?php $idx = 0; foreach ($overview_data['total_expenses_by_currency'] as $cur => $amt): ?>
                                            <h4 class="text-warning <?php echo $idx++ > 0 ? 'small' : ''; ?>"><?php echo formatCurrencyAmount($amt, $cur); ?></h4>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <h3 class="text-warning">$0.00</h3>
                                    <?php endif; ?>
                                    <p class="text-muted mb-0">Total Expenses</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights and Trends -->
    <div class="row">
        <!-- Insights -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Key Insights</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($insights)): ?>
                        <ul class="list-unstyled">
                            <?php foreach ($insights as $insight): ?>
                            <li class="mb-2">
                                <i class="fas fa-lightbulb text-warning mr-2"></i>
                                <?php echo htmlspecialchars($insight); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No insights available for this period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $is_super_admin ? 'Company Growth Trend' : 'Earnings Trend'; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Summary -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if ($is_super_admin): ?>
                            <!-- System Performance -->
                            <div class="col-md-6">
                                <h6>System Performance</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td>Active Companies</td>
                                                <td><?php echo number_format($overview_data['total_companies']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Revenue</td>
                                                <td><?php echo formatCurrency($overview_data['total_revenue']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Active Contracts</td>
                                                <td><?php echo number_format($overview_data['total_contracts']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Working Hours</td>
                                                <td><?php echo number_format($overview_data['total_hours'], 1); ?> hrs</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Efficiency Metrics</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td>Avg Employees per Company</td>
                                                <td><?php echo number_format($overview_data['total_employees'] / max($overview_data['total_companies'], 1), 1); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Avg Machines per Company</td>
                                                <td><?php echo number_format($overview_data['total_machines'] / max($overview_data['total_companies'], 1), 1); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Avg Hours per Employee</td>
                                                <td><?php echo number_format($overview_data['total_hours'] / max($overview_data['total_employees'], 1), 1); ?> hrs</td>
                                            </tr>
                                            <tr>
                                                <td>Revenue per Company</td>
                                                <td><?php echo formatCurrency($overview_data['total_revenue'] / max($overview_data['total_companies'], 1)); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Company Performance -->
                            <div class="col-md-6">
                                <h6>Financial Performance</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td>Total Earnings</td>
                                                <td class="text-success">
                                                    <?php if (!empty($overview_data['total_earnings_by_currency'])): ?>
                                                        <?php $idx = 0; foreach ($overview_data['total_earnings_by_currency'] as $cur => $amt): ?>
                                                            <div class="<?php echo $idx++ > 0 ? 'small' : ''; ?>"><?php echo formatCurrencyAmount($amt, $cur); ?></div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        $0.00
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Total Expenses</td>
                                                <td class="text-danger">
                                                    <?php if (!empty($overview_data['total_expenses_by_currency'])): ?>
                                                        <?php $idx = 0; foreach ($overview_data['total_expenses_by_currency'] as $cur => $amt): ?>
                                                            <div class="<?php echo $idx++ > 0 ? 'small' : ''; ?>"><?php echo formatCurrencyAmount($amt, $cur); ?></div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        $0.00
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>Salary Payments</td>
                                                <td class="text-warning">
                                                    <?php if (!empty($overview_data['total_salary_by_currency'])): ?>
                                                        <?php $idx = 0; foreach ($overview_data['total_salary_by_currency'] as $cur => $amt): ?>
                                                            <div class="<?php echo $idx++ > 0 ? 'small' : ''; ?>"><?php echo formatCurrencyAmount($amt, $cur); ?></div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        $0.00
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr class="table-info">
                                                <td><strong>Net Profit</strong></td>
                                                <td class="text-primary">
                                                    <?php 
                                                    $allCurrencies = array_unique(array_merge(
                                                        array_keys($overview_data['total_earnings_by_currency'] ?? []),
                                                        array_keys($overview_data['total_expenses_by_currency'] ?? []),
                                                        array_keys($overview_data['total_salary_by_currency'] ?? [])
                                                    ));
                                                    if (!empty($allCurrencies)) {
                                                        $idx = 0;
                                                        foreach ($allCurrencies as $cur) {
                                                            $net = ($overview_data['total_earnings_by_currency'][$cur] ?? 0)
                                                                 - ($overview_data['total_expenses_by_currency'][$cur] ?? 0)
                                                                 - ($overview_data['total_salary_by_currency'][$cur] ?? 0);
                                                            echo '<div class="' . ($idx++ > 0 ? 'small' : '') . '\">' . formatCurrencyAmount($net, $cur) . '</div>';
                                                        }
                                                    } else {
                                                        echo '$0.00';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Operational Performance</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td>Total Employees</td>
                                                <td><?php echo number_format($overview_data['total_employees']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Machines</td>
                                                <td><?php echo number_format($overview_data['total_machines']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Active Contracts</td>
                                                <td><?php echo number_format($overview_data['total_contracts']); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Working Hours</td>
                                                <td><?php echo number_format($overview_data['total_hours'], 1); ?> hrs</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
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
                        <div class="col-md-3 text-center mb-3">
                            <a href="../employees/" class="btn btn-primary btn-block">
                                <i class="fas fa-users"></i><br>
                                Manage Employees
                            </a>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <a href="../contracts/" class="btn btn-success btn-block">
                                <i class="fas fa-file-contract"></i><br>
                                View Contracts
                            </a>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <a href="../machines/" class="btn btn-info btn-block">
                                <i class="fas fa-truck"></i><br>
                                Manage Machines
                            </a>
                        </div>
                        <div class="col-md-3 text-center mb-3">
                            <a href="../expenses/" class="btn btn-warning btn-block">
                                <i class="fas fa-receipt"></i><br>
                                Track Expenses
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Trend Chart
document.addEventListener('DOMContentLoaded', function() {
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($trend_data, 'date')); ?>,
            datasets: [{
                label: '<?php echo $is_super_admin ? 'New Companies' : 'Earnings'; ?>',
                data: <?php echo json_encode(array_column($trend_data, $is_super_admin ? 'new_companies' : 'earnings')); ?>,
                borderColor: 'rgb(78, 115, 223)',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderWidth: 2,
                fill: true
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
                            <?php if ($is_super_admin): ?>
                            return value;
                            <?php else: ?>
                            return '$' + number_format(value);
                            <?php endif; ?>
                        }
                    }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, chart) {
                        <?php if ($is_super_admin): ?>
                        return 'New Companies: ' + tooltipItem.yLabel;
                        <?php else: ?>
                        return 'Earnings: $' + number_format(tooltipItem.yLabel);
                        <?php endif; ?>
                    }
                }
            }
        }
    });
});
</script>