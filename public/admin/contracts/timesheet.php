<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin', 'driver', 'driver_assistant']);

$db = new Database();
$conn = $db->getConnection();

// Get contract ID from URL
$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;

if (!$contract_id) {
    header('Location: index.php');
    exit();
}

// Get contract details
$stmt = $conn->prepare("
    SELECT c.*, p.name as project_name, p.project_code, m.name as machine_name, m.machine_code,
           e.name as employee_name, e.employee_code
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN machines m ON c.machine_id = m.id
    LEFT JOIN employees e ON c.employee_id = e.id
    WHERE c.id = ? AND c.company_id = ?
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: index.php');
    exit();
}

// Get working hours for this contract
$stmt = $conn->prepare("
    SELECT wh.*, e.name as employee_name, e.employee_code
    FROM working_hours wh
    LEFT JOIN employees e ON wh.employee_id = e.id
    WHERE wh.contract_id = ? AND wh.company_id = ?
    ORDER BY wh.date ASC
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$working_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_hours_worked = 0;
$total_amount_earned = 0;
$total_amount_paid = 0;

foreach ($working_hours as $wh) {
    $total_hours_worked += $wh['hours_worked'];
    
    // Calculate amount based on contract type
    if ($contract['contract_type'] === 'hourly') {
        $total_amount_earned += $wh['hours_worked'] * $contract['rate_amount'];
    } elseif ($contract['contract_type'] === 'daily') {
        $daily_rate = $contract['rate_amount'];
        $total_amount_earned += $wh['hours_worked'] * ($daily_rate / $contract['working_hours_per_day']);
    } elseif ($contract['contract_type'] === 'monthly') {
        $monthly_rate = $contract['rate_amount'];
        $total_amount_earned += $wh['hours_worked'] * ($monthly_rate / ($contract['total_hours_required'] ?: 270));
    }
}

// Get payments for this contract
$stmt = $conn->prepare("
    SELECT * FROM contract_payments 
    WHERE contract_id = ? AND company_id = ?
    ORDER BY payment_date DESC
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($payments as $payment) {
    $total_amount_paid += $payment['amount'];
}

$remaining_amount = $total_amount_earned - $total_amount_paid;
$progress_percentage = $contract['total_hours_required'] > 0 ? 
    ($total_hours_worked / $contract['total_hours_required']) * 100 : 0;

// Group working hours by month for chart
$monthly_data = [];
foreach ($working_hours as $wh) {
    $month = date('Y-m', strtotime($wh['date']));
    if (!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['hours' => 0, 'amount' => 0];
    }
    $monthly_data[$month]['hours'] += $wh['hours_worked'];
    
    // Calculate amount for this day
    if ($contract['contract_type'] === 'hourly') {
        $monthly_data[$month]['amount'] += $wh['hours_worked'] * $contract['rate_amount'];
    } elseif ($contract['contract_type'] === 'daily') {
        $daily_rate = $contract['rate_amount'];
        $monthly_data[$month]['amount'] += $wh['hours_worked'] * ($daily_rate / $contract['working_hours_per_day']);
    } elseif ($contract['contract_type'] === 'monthly') {
        $monthly_rate = $contract['rate_amount'];
        $monthly_data[$month]['amount'] += $wh['hours_worked'] * ($monthly_rate / ($contract['total_hours_required'] ?: 270));
    }
}

// Get current month working hours
$current_month = date('Y-m');
$current_month_hours = $monthly_data[$current_month]['hours'] ?? 0;
$current_month_amount = $monthly_data[$current_month]['amount'] ?? 0;
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Contract Timesheet</h1>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm me-2">
                <i class="fas fa-arrow-left"></i> Back to Contracts
            </a>
            <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Work Hours
            </a>
        </div>
    </div>

    <!-- Contract Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contract Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract Code:</strong></td>
                            <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Project:</strong></td>
                            <td><?php echo htmlspecialchars($contract['project_name']); ?> (<?php echo htmlspecialchars($contract['project_code']); ?>)</td>
                        </tr>
                        <tr>
                            <td><strong>Machine:</strong></td>
                            <td><?php echo htmlspecialchars($contract['machine_name']); ?> (<?php echo htmlspecialchars($contract['machine_code']); ?>)</td>
                        </tr>
                        <tr>
                            <td><strong>Employee:</strong></td>
                            <td><?php echo htmlspecialchars($contract['employee_name']); ?> (<?php echo htmlspecialchars($contract['employee_code']); ?>)</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract Type:</strong></td>
                            <td>
                                <span class="badge <?php 
                                    echo $contract['contract_type'] === 'hourly' ? 'bg-primary' : 
                                        ($contract['contract_type'] === 'daily' ? 'bg-success' : 'bg-info'); 
                                ?>">
                                    <?php echo ucfirst($contract['contract_type']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Rate:</strong></td>
                                                                        <td><?php echo formatCurrency($contract['rate_amount'], null, getCurrentCompanyId()); ?> per <?php echo $contract['contract_type'] === 'hourly' ? 'hour' : ($contract['contract_type'] === 'daily' ? 'day' : 'month'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Required Hours:</strong></td>
                            <td><?php echo $contract['total_hours_required'] ?: 'N/A'; ?> hours</td>
                        </tr>
                        <tr>
                            <td><strong>Working Hours/Day:</strong></td>
                            <td><?php echo $contract['working_hours_per_day']; ?> hours</td>
                        </tr>
                    </table>
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
                                Total Hours Worked</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_hours_worked, 1); ?> hrs</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                Total Amount Earned</div>
                                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_amount_earned, null, getCurrentCompanyId()); ?></div>
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
                                Amount Paid</div>
                                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_amount_paid, null, getCurrentCompanyId()); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-credit-card fa-2x text-gray-300"></i>
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
                                Remaining Amount</div>
                                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($remaining_amount, null, getCurrentCompanyId()); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contract Progress</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo min($progress_percentage, 100); ?>%"
                             aria-valuenow="<?php echo $progress_percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($progress_percentage, 1); ?>%
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php echo $total_hours_worked; ?> of <?php echo $contract['total_hours_required'] ?: '∞'; ?> hours completed
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <h6>Current Month</h6>
                    <p class="mb-1"><strong><?php echo number_format($current_month_hours, 1); ?> hours</strong></p>
                                                        <p class="mb-0"><strong><?php echo formatCurrency($current_month_amount, null, getCurrentCompanyId()); ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Working Hours Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Daily Timesheet</h6>
            <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Hours
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($working_hours)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clock fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No working hours recorded yet.</p>
                    <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Entry
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="timesheetTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Hours Worked</th>
                                <th>Rate</th>
                                <th>Daily Amount</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $running_total = 0;
                            foreach ($working_hours as $wh): 
                                // Calculate daily amount
                                $daily_amount = 0;
                                if ($contract['contract_type'] === 'hourly') {
                                    $daily_amount = $wh['hours_worked'] * $contract['rate_amount'];
                                } elseif ($contract['contract_type'] === 'daily') {
                                    $daily_rate = $contract['rate_amount'];
                                    $daily_amount = $wh['hours_worked'] * ($daily_rate / $contract['working_hours_per_day']);
                                } elseif ($contract['contract_type'] === 'monthly') {
                                    $monthly_rate = $contract['rate_amount'];
                                    $daily_amount = $wh['hours_worked'] * ($monthly_rate / ($contract['total_hours_required'] ?: 270));
                                }
                                $running_total += $daily_amount;
                            ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo formatDate($wh['date'], getCurrentCompanyId()); ?></strong>
                                            <br><small class="text-muted"><?php echo date('l', strtotime($wh['date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($wh['employee_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($wh['employee_code']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <strong><?php echo number_format($wh['hours_worked'], 1); ?></strong> hours
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php 
                                            if ($contract['contract_type'] === 'hourly') {
                                                echo formatCurrency($contract['rate_amount'], null, getCurrentCompanyId()) . '/hr';
                                            } elseif ($contract['contract_type'] === 'daily') {
                                                echo formatCurrency($contract['rate_amount'] / $contract['working_hours_per_day'], null, getCurrentCompanyId()) . '/hr';
                                            } else {
                                                echo formatCurrency($contract['rate_amount'] / ($contract['total_hours_required'] ?: 270), null, getCurrentCompanyId()) . '/hr';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-success"><?php echo formatCurrency($daily_amount, null, getCurrentCompanyId()); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if (!empty($wh['notes'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($wh['notes']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">No notes</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit-hours.php?id=<?php echo $wh['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete-hours.php?id=<?php echo $wh['id']; ?>" 
                                               class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirmDelete('Are you sure you want to delete this entry?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-info">
                                <td colspan="2"><strong>Total</strong></td>
                                <td class="text-center"><strong><?php echo number_format($total_hours_worked, 1); ?> hours</strong></td>
                                <td></td>
                                <td><strong><?php echo formatCurrency($total_amount_earned, null, getCurrentCompanyId()); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payments Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Contract Payments</h6>
            <a href="add-payment.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Add Payment
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-credit-card fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No payments recorded yet.</p>
                    <a href="add-payment.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add First Payment
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo formatDate($payment['payment_date'], getCurrentCompanyId()); ?></td>
                                    <td><strong><?php echo formatCurrency($payment['amount'], null, getCurrentCompanyId()); ?></strong></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['payment_method'] === 'credit_card' ? 'bg-primary' : 'bg-success'; 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['reference_number']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['status'] === 'completed' ? 'bg-success' : 'bg-warning'; 
                                        ?>">
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit-payment.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete-payment.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirmDelete('Are you sure you want to delete this payment?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <td><strong>Total Paid</strong></td>
                                <td><strong><?php echo formatCurrency($total_amount_paid, null, getCurrentCompanyId()); ?></strong></td>
                                <td colspan="4"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly Chart -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Monthly Work Hours & Revenue</h6>
        </div>
        <div class="card-body">
            <canvas id="monthlyChart" width="400" height="100"></canvas>
        </div>
    </div>
</div>

<script>
function confirmDelete(message) {
    return confirm(message);
}

// Monthly Chart
const monthlyData = <?php echo json_encode($monthly_data); ?>;
const months = Object.keys(monthlyData);
const hoursData = months.map(month => monthlyData[month].hours);
const amountData = months.map(month => monthlyData[month].amount);

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: months.map(month => {
            const [year, monthNum] = month.split('-');
            return new Date(year, monthNum - 1).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short' 
            });
        }),
        datasets: [{
            label: 'Hours Worked',
            data: hoursData,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            yAxisID: 'y'
        }, {
            label: 'Revenue',
            data: amountData,
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1,
            type: 'line',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Month'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Hours'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Revenue ($)'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>