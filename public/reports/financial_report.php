<?php
// Financial Report Template
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

// Get financial data
$financial_data = [];
$revenue_data = [];
$expense_data = [];
$profit_data = [];

try {
    if ($is_super_admin) {
        // System-wide financial data
        $stmt = $conn->prepare("
            SELECT 
                DATE(payment_date) as date,
                SUM(amount) as revenue,
                COUNT(*) as transactions
            FROM company_payments 
            WHERE payment_date BETWEEN ? AND ? AND payment_status = 'completed'
            GROUP BY DATE(payment_date)
            ORDER BY date
        ");
        $stmt->execute([$start_date, $end_date]);
        $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get subscription revenue
        $stmt = $conn->prepare("
            SELECT 
                sp.plan_name,
                COUNT(c.id) as subscribers,
                SUM(sp.price) as total_revenue
            FROM companies c
            JOIN subscription_plans sp ON c.subscription_plan_id = sp.id
            WHERE c.subscription_status = 'active'
            GROUP BY sp.id
        ");
        $stmt->execute();
        $subscription_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Company-specific financial data
        // Revenue by currency from actual payments (contracts, area rentals, parking)
        // Contract payments
        $stmt = $conn->prepare("
            SELECT DATE(cp.payment_date) as date, COALESCE(cp.currency, c.currency, 'USD') as currency,
                   SUM(cp.amount) as revenue
            FROM contract_payments cp
            JOIN contracts c ON cp.contract_id = c.id
            WHERE cp.company_id = ? AND cp.status = 'completed' AND cp.payment_date BETWEEN ? AND ?
            GROUP BY DATE(cp.payment_date), COALESCE(cp.currency, c.currency, 'USD')
            ORDER BY date
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $revenue_contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Area rental payments
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, COALESCE(currency, 'USD') as currency,
                   SUM(amount) as revenue
            FROM area_rental_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY DATE(payment_date), COALESCE(currency, 'USD')
            ORDER BY date
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $revenue_area = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Parking payments
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, COALESCE(currency, 'USD') as currency,
                   SUM(amount) as revenue
            FROM parking_payments
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY DATE(payment_date), COALESCE(currency, 'USD')
            ORDER BY date
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $revenue_parking = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge all revenue rows
        $revenue_rows = array_merge($revenue_contracts, $revenue_area, $revenue_parking);
        $revenue_data = $revenue_rows;

        // Expenses by currency
        $stmt = $conn->prepare("
            SELECT DATE(expense_date) as date, COALESCE(currency, 'USD') as currency,
                   SUM(amount) as expenses
            FROM expenses 
            WHERE company_id = ? AND expense_date BETWEEN ? AND ?
            GROUP BY DATE(expense_date), COALESCE(currency, 'USD')
            ORDER BY date
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $expense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Salary payments by currency
        $stmt = $conn->prepare("
            SELECT DATE(payment_date) as date, COALESCE(currency, 'USD') as currency,
                   SUM(amount_paid) as salary_payments
            FROM salary_payments 
            WHERE company_id = ? AND payment_date BETWEEN ? AND ?
            GROUP BY DATE(payment_date), COALESCE(currency, 'USD')
            ORDER BY date
        ");
        $stmt->execute([$company_id, $start_date, $end_date]);
        $salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error loading financial data: " . $e->getMessage();
}
// Note: No closing PHP tag here to prevent accidental output

<div class="financial-report">
    <div class="row">
        <!-- Financial Summary -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Financial Summary</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><strong>Total Revenue</strong></td>
                                    <td class="text-success">
                                        <?php 
                                        if ($is_super_admin) {
                                            $total_revenue = array_sum(array_column($revenue_data, 'revenue'));
                                            echo formatCurrency($total_revenue);
                                        } else {
                                            // Sum by currency
                                            $totals = [];
                                            foreach ($revenue_data as $row) {
                                                $cur = $row['currency'] ?? 'USD';
                                                $totals[$cur] = ($totals[$cur] ?? 0) + ($row['revenue'] ?? 0);
                                            }
                                            if (!empty($totals)) {
                                                $idx = 0;
                                                foreach ($totals as $cur => $amt) {
                                                    echo '<div class="' . ($idx++ > 0 ? 'small' : '') . '">' . formatCurrencyAmount($amt, $cur) . '</div>';
                                                }
                                            } else {
                                                echo '$0.00';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php if (!$is_super_admin): ?>
                                <tr>
                                    <td><strong>Total Expenses</strong></td>
                                    <td class="text-danger">
                                        <?php 
                                        $totals = [];
                                        foreach ($expense_data as $row) {
                                            $cur = $row['currency'] ?? 'USD';
                                            $totals[$cur] = ($totals[$cur] ?? 0) + ($row['expenses'] ?? 0);
                                        }
                                        if (!empty($totals)) {
                                            $idx = 0;
                                            foreach ($totals as $cur => $amt) {
                                                echo '<div class="' . ($idx++ > 0 ? 'small' : '') . '">' . formatCurrencyAmount($amt, $cur) . '</div>';
                                            }
                                        } else {
                                            echo '$0.00';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Salary Payments</strong></td>
                                    <td class="text-warning">
                                        <?php 
                                        $totals = [];
                                        foreach ($salary_data as $row) {
                                            $cur = $row['currency'] ?? 'USD';
                                            $totals[$cur] = ($totals[$cur] ?? 0) + ($row['salary_payments'] ?? 0);
                                        }
                                        if (!empty($totals)) {
                                            $idx = 0;
                                            foreach ($totals as $cur => $amt) {
                                                echo '<div class="' . ($idx++ > 0 ? 'small' : '') . '">' . formatCurrencyAmount($amt, $cur) . '</div>';
                                            }
                                        } else {
                                            echo '$0.00';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr class="table-info">
                                    <td><strong>Net Profit</strong></td>
                                    <td class="text-primary">
                                        <?php 
                                        // Compute net profit per currency (revenue - expenses - salary)
                                        $revTotals = [];
                                        foreach ($revenue_data as $row) {
                                            $cur = $row['currency'] ?? 'USD';
                                            $revTotals[$cur] = ($revTotals[$cur] ?? 0) + ($row['revenue'] ?? 0);
                                        }
                                        $expTotals = [];
                                        foreach ($expense_data as $row) {
                                            $cur = $row['currency'] ?? 'USD';
                                            $expTotals[$cur] = ($expTotals[$cur] ?? 0) + ($row['expenses'] ?? 0);
                                        }
                                        $salTotals = [];
                                        foreach ($salary_data as $row) {
                                            $cur = $row['currency'] ?? 'USD';
                                            $salTotals[$cur] = ($salTotals[$cur] ?? 0) + ($row['salary_payments'] ?? 0);
                                        }
                                        $allCurrencies = array_unique(array_merge(array_keys($revTotals), array_keys($expTotals), array_keys($salTotals)));
                                        if (!empty($allCurrencies)) {
                                            $idx = 0;
                                            foreach ($allCurrencies as $cur) {
                                                $net = ($revTotals[$cur] ?? 0) - ($expTotals[$cur] ?? 0) - ($salTotals[$cur] ?? 0);
                                                echo '<div class="' . ($idx++ > 0 ? 'small' : '') . '">' . formatCurrencyAmount($net, $cur) . '</div>';
                                            }
                                        } else {
                                            echo '$0.00';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Breakdown -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Revenue Breakdown</h6>
                </div>
                <div class="card-body">
                    <?php if ($is_super_admin): ?>
                        <!-- Subscription Revenue -->
                        <h6>Subscription Revenue</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th>Subscribers</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subscription_data as $plan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($plan['plan_name']); ?></td>
                                        <td><?php echo number_format($plan['subscribers']); ?></td>
                                        <td><?php echo formatCurrency($plan['total_revenue']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Contract Revenue -->
                        <h6>Contract Revenue</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Contract Type</th>
                                        <th>Active Contracts</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $contract_types = ['hourly', 'daily', 'monthly'];
                                    foreach ($contract_types as $type):
                                        $stmt = $conn->prepare("
                                            SELECT COUNT(*) as count, SUM(rate_amount) as total
                                            FROM contracts 
                                            WHERE company_id = ? AND contract_type = ? AND status = 'active'
                                        ");
                                        $stmt->execute([$company_id, $type]);
                                        $contract_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <tr>
                                        <td><?php echo ucfirst($type); ?></td>
                                        <td><?php echo number_format($contract_data['count']); ?></td>
                                        <td><?php echo formatCurrency($contract_data['total']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Charts -->
    <div class="row">
        <!-- Revenue Trend Chart -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Revenue Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="financialTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Expense Breakdown -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Expense Breakdown</h6>
                </div>
                <div class="card-body">
                    <?php if (!$is_super_admin): ?>
                        <?php
                        $stmt = $conn->prepare("
                            SELECT category, SUM(amount) as total
                            FROM expenses 
                            WHERE company_id = ? AND expense_date BETWEEN ? AND ?
                            GROUP BY category
                            ORDER BY total DESC
                        ");
                        $stmt->execute([$company_id, $start_date, $end_date]);
                        $expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <canvas id="expenseChart" height="200"></canvas>
                        <div class="mt-3">
                            <?php foreach ($expense_categories as $category): ?>
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo htmlspecialchars($category['category']); ?></span>
                                <span><?php echo formatCurrency($category['total']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">System-wide expense data not available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Transactions -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Transactions</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($is_super_admin) {
                                    $stmt = $conn->prepare("
                                        SELECT payment_date, 'Subscription Payment' as description, 
                                               amount, payment_status as status, 'Revenue' as type, currency
                                        FROM company_payments 
                                        WHERE payment_date BETWEEN ? AND ? AND payment_status = 'completed'
                                        ORDER BY payment_date DESC
                                        LIMIT 20
                                    ");
                                    $stmt->execute([$start_date, $end_date]);
                                } else {
                                    $stmt = $conn->prepare("
                                        (SELECT expense_date as date, description, amount, 
                                                'Expense' as type, 'Completed' as status, currency
                                         FROM expenses 
                                         WHERE company_id = ? AND expense_date BETWEEN ? AND ?)
                                        UNION ALL
                                        (SELECT payment_date as date, CONCAT('Salary - ', e.name) as description,
                                                amount_paid as amount, 'Salary' as type, 'Completed' as status, sp.currency as currency
                                         FROM salary_payments sp
                                         JOIN employees e ON sp.employee_id = e.id
                                         WHERE sp.company_id = ? AND sp.payment_date BETWEEN ? AND ?)
                                        ORDER BY date DESC
                                        LIMIT 20
                                    ");
                                    $stmt->execute([$company_id, $start_date, $end_date, $company_id, $start_date, $end_date]);
                                }
                                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo formatDate($transaction['date'] ?? $transaction['payment_date']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo ($transaction['type'] ?? 'Revenue') === 'Revenue' ? 'success' : 
                                                (($transaction['type'] ?? '') === 'Expense' ? 'danger' : 'warning'); 
                                        ?>">
                                            <?php echo htmlspecialchars($transaction['type'] ?? 'Revenue'); ?>
                                        </span>
                                    </td>
                                    <td class="<?php 
                                        echo ($transaction['type'] ?? 'Revenue') === 'Revenue' ? 'text-success' : 
                                            (($transaction['type'] ?? '') === 'Expense' ? 'text-danger' : 'text-warning'); 
                                    ?>">
                                        <?php echo formatCurrencyAmount($transaction['amount'], $transaction['currency'] ?? 'USD'); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?php echo htmlspecialchars($transaction['status']); ?>
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
</div>

<script>
// Financial Trend Chart
document.addEventListener('DOMContentLoaded', function() {
    const financialCtx = document.getElementById('financialTrendChart').getContext('2d');
    const financialChart = new Chart(financialCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($revenue_data, 'date')); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_column($revenue_data, 'revenue')); ?>,
                borderColor: 'rgb(40, 167, 69)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 2,
                fill: true
            }<?php if (!$is_super_admin): ?>, {
                label: 'Expenses',
                data: <?php echo json_encode(array_column($expense_data, 'expenses')); ?>,
                borderColor: 'rgb(220, 53, 69)',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                borderWidth: 2,
                fill: true
            }<?php endif; ?>]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                yAxes: [{
                    ticks: {
                        callback: function(value) {
                            return '$' + number_format(value);
                        }
                    }
                }]
            },
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, chart) {
                        return chart.datasets[tooltipItem.datasetIndex].label + ': $' + number_format(tooltipItem.yLabel);
                    }
                }
            }
        }
    });

    <?php if (!$is_super_admin): ?>
    // Expense Chart
    const expenseCtx = document.getElementById('expenseChart').getContext('2d');
    const expenseChart = new Chart(expenseCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($expense_categories, 'category')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($expense_categories, 'total')); ?>,
                backgroundColor: [
                    '#e74a3b', '#fd7e14', '#f6c23e', '#1cc88a', '#36b9cc',
                    '#6f42c1', '#e83e8c', '#5a5c69', '#858796', '#6e707e'
                ]
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
});
</script>