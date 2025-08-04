<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

$db = new Database();
$conn = $db->getConnection();

$current_user = getCurrentUser();
$is_super_admin = isSuperAdmin();
$company_id = getCurrentCompanyId();

// Get date range filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['type'] ?? 'overview';

// Initialize statistics arrays
$stats = [];
$chart_data = [];

try {
    if ($is_super_admin) {
        // Super Admin Reports - System-wide statistics
        $stats = getSystemWideStats($conn, $start_date, $end_date);
        $chart_data = getSystemWideChartData($conn, $start_date, $end_date);
    } else {
        // Tenant Admin Reports - Company-specific statistics
        $stats = getCompanyStats($conn, $company_id, $start_date, $end_date);
        $chart_data = getCompanyChartData($conn, $company_id, $start_date, $end_date);
    }
} catch (Exception $e) {
    $error = "Error loading reports: " . $e->getMessage();
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'true') {
    $export_format = $_GET['format'] ?? 'pdf';
    exportReport($conn, $report_type, $start_date, $end_date, $export_format, $is_super_admin, $company_id);
    exit;
}

// Helper functions for statistics
function getSystemWideStats($conn, $start_date, $end_date) {
    $stats = [];
    
    // Total companies
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies WHERE is_active = 1");
    $stmt->execute();
    $stats['total_companies'] = $stmt->fetchColumn();
    
    // Active subscriptions
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies WHERE subscription_status = 'active'");
    $stmt->execute();
    $stats['active_subscriptions'] = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM company_payments 
        WHERE payment_date BETWEEN ? AND ? AND payment_status = 'completed'
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    // Total employees
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE is_active = 1");
    $stmt->execute();
    $stats['total_employees'] = $stmt->fetchColumn();
    
    // Total machines
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE is_active = 1");
    $stmt->execute();
    $stats['total_machines'] = $stmt->fetchColumn();
    
    // Total contracts
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE status = 'active'");
    $stmt->execute();
    $stats['total_contracts'] = $stmt->fetchColumn();
    
    // Total working hours
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(hours_worked), 0) as total 
        FROM working_hours 
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_hours'] = $stmt->fetchColumn();
    
    return $stats;
}

function getCompanyStats($conn, $company_id, $start_date, $end_date) {
    $stats = [];
    
    // Total employees
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employees WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$company_id]);
    $stats['total_employees'] = $stmt->fetchColumn();
    
    // Total machines
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$company_id]);
    $stats['total_machines'] = $stmt->fetchColumn();
    
    // Total contracts
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contracts WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]);
    $stats['total_contracts'] = $stmt->fetchColumn();
    
    // Total working hours
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(hours_worked), 0) as total 
        FROM working_hours 
        WHERE company_id = ? AND date BETWEEN ? AND ?
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $stats['total_hours'] = $stmt->fetchColumn();
    
    // Total earnings
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(wh.hours_worked * c.rate_amount / c.working_hours_per_day), 0) as total
        FROM working_hours wh
        JOIN contracts c ON wh.contract_id = c.id
        WHERE wh.company_id = ? AND wh.date BETWEEN ? AND ?
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $stats['total_earnings'] = $stmt->fetchColumn();
    
    // Total expenses
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE company_id = ? AND expense_date BETWEEN ? AND ?
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $stats['total_expenses'] = $stmt->fetchColumn();
    
    // Total salary payments
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(paid_amount), 0) as total 
        FROM salary_payments 
        WHERE company_id = ? AND payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $stats['total_salary_payments'] = $stmt->fetchColumn();
    
    return $stats;
}

function getSystemWideChartData($conn, $start_date, $end_date) {
    $chart_data = [];
    
    // Revenue trend
    $stmt = $conn->prepare("
        SELECT DATE(payment_date) as date, SUM(amount) as revenue
        FROM company_payments 
        WHERE payment_date BETWEEN ? AND ? AND payment_status = 'completed'
        GROUP BY DATE(payment_date)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $chart_data['revenue_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Company growth
    $stmt = $conn->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as new_companies
        FROM companies 
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $chart_data['company_growth'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Working hours trend
    $stmt = $conn->prepare("
        SELECT DATE(date) as date, SUM(hours_worked) as hours
        FROM working_hours 
        WHERE date BETWEEN ? AND ?
        GROUP BY DATE(date)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $chart_data['hours_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $chart_data;
}

function getCompanyChartData($conn, $company_id, $start_date, $end_date) {
    $chart_data = [];
    
    // Earnings trend
    $stmt = $conn->prepare("
        SELECT DATE(wh.date) as date, 
               SUM(wh.hours_worked * c.rate_amount / c.working_hours_per_day) as earnings
        FROM working_hours wh
        JOIN contracts c ON wh.contract_id = c.id
        WHERE wh.company_id = ? AND wh.date BETWEEN ? AND ?
        GROUP BY DATE(wh.date)
        ORDER BY date
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $chart_data['earnings_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Working hours trend
    $stmt = $conn->prepare("
        SELECT DATE(date) as date, SUM(hours_worked) as hours
        FROM working_hours 
        WHERE company_id = ? AND date BETWEEN ? AND ?
        GROUP BY DATE(date)
        ORDER BY date
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $chart_data['hours_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Expenses trend
    $stmt = $conn->prepare("
        SELECT DATE(expense_date) as date, SUM(amount) as expenses
        FROM expenses 
        WHERE company_id = ? AND expense_date BETWEEN ? AND ?
        GROUP BY DATE(expense_date)
        ORDER BY date
    ");
    $stmt->execute([$company_id, $start_date, $end_date]);
    $chart_data['expenses_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $chart_data;
}

?>

<script>
function exportReport(report_type, format) {
    const start_date = document.getElementById('start_date').value || '<?php echo date('Y-m-01'); ?>';
    const end_date = document.getElementById('end_date').value || '<?php echo date('Y-m-d'); ?>';
    
    const url = `/constract360/construction/public/reports/export.php?type=${report_type}&format=${format}&start_date=${start_date}&end_date=${end_date}`;
    window.open(url, '_blank');
}
</script>
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-chart-bar"></i> Reports & Analytics
        </h1>
        <div class="d-flex">
            <button class="btn btn-success mr-2" onclick="exportReport('overview', 'pdf')">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button class="btn btn-info mr-2" onclick="exportReport('overview', 'excel')">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="btn btn-secondary" onclick="exportReport('overview', 'csv')">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3">
                    <label for="start_date">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="type">Report Type</label>
                    <select class="form-control" id="type" name="type">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                        <option value="financial" <?php echo $report_type === 'financial' ? 'selected' : ''; ?>>Financial</option>
                        <option value="employee" <?php echo $report_type === 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="contract" <?php echo $report_type === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="machine" <?php echo $report_type === 'machine' ? 'selected' : ''; ?>>Machine</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row">
        <?php if ($is_super_admin): ?>
            <!-- Super Admin Statistics -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Companies</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_companies'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-building fa-2x text-gray-300"></i>
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
                                    Active Subscriptions</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['active_subscriptions'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                    Total Revenue</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($stats['total_revenue'] ?? 0); ?>
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
                                    Total Hours</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_hours'] ?? 0, 1); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Tenant Admin Statistics -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Employees</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_employees'] ?? 0); ?>
                                </div>
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
                                    Total Earnings</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($stats['total_earnings'] ?? 0); ?>
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
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Total Hours</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_hours'] ?? 0, 1); ?>
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
                                    Total Expenses</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatCurrency($stats['total_expenses'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-receipt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Revenue/Earnings Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php echo $is_super_admin ? 'Revenue Trend' : 'Earnings Trend'; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Working Hours Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Working Hours</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="hoursChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Detailed Report</h6>
                </div>
                <div class="card-body">
                    <?php if ($report_type === 'financial'): ?>
                        <?php include 'financial_report.php'; ?>
                    <?php elseif ($report_type === 'employee'): ?>
                        <?php include 'employee_report.php'; ?>
                    <?php elseif ($report_type === 'contract'): ?>
                        <?php include 'contract_report.php'; ?>
                    <?php elseif ($report_type === 'machine'): ?>
                        <?php include 'machine_report.php'; ?>
                    <?php else: ?>
                        <?php include 'overview_report.php'; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart.js configuration
document.addEventListener('DOMContentLoaded', function() {
    // Revenue/Earnings Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($chart_data['revenue_trend'] ?? $chart_data['earnings_trend'] ?? [], 'date')); ?>,
            datasets: [{
                label: '<?php echo $is_super_admin ? 'Revenue' : 'Earnings'; ?>',
                data: <?php echo json_encode(array_column($chart_data['revenue_trend'] ?? $chart_data['earnings_trend'] ?? [], $is_super_admin ? 'revenue' : 'earnings')); ?>,
                borderColor: 'rgb(78, 115, 223)',
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    time: {
                        parser: 'YYYY-MM-DD',
                        tooltipFormat: 'll'
                    },
                    gridLines: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                }],
                yAxes: [{
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return '$' + number_format(value, 0, '.', ',');
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }],
            },
            legend: {
                display: false
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': $' + number_format(tooltipItem.yLabel);
                    }
                }
            }
        }
    });

    // Working Hours Chart
    const hoursCtx = document.getElementById('hoursChart').getContext('2d');
    const hoursChart = new Chart(hoursCtx, {
        type: 'doughnut',
        data: {
            labels: ['Worked', 'Remaining'],
            datasets: [{
                data: [<?php echo $stats['total_hours'] ?? 0; ?>, 100],
                backgroundColor: ['#4e73df', '#858796'],
                hoverBackgroundColor: ['#2e59d9', '#858796'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 80,
        },
    });
});

// Export functions
function exportReport(format) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', 'true');
    urlParams.set('format', format);
    window.location.href = window.location.pathname + '?' + urlParams.toString();
}

// Number formatting helper
function number_format(number, decimals, dec_point, thousands_sep) {
    number = (number + '').replace(',', '').replace(' ', '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if ((sep.length > 0)) {
        var i = s[0].length;
        if (i % 3 !== 0) {
            s[0] = s[0].padStart(s[0].length + (3 - i % 3), ' ');
        }
        s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, sep);
    }
    if ((prec > 0) && (s[1].length < prec)) {
        s[1] = s[1].padEnd(prec, '0');
    }
    return s.join(dec);
}
</script>

<?php require_once '../../includes/footer.php'; ?>