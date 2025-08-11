<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin', 'driver', 'driver_assistant']);
require_once '../../../includes/header.php';

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
    SELECT c.*, p.name as project_name, p.project_code, m.name as machine_name, m.machine_code
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN machines m ON c.machine_id = m.id
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

// Get unique employees who worked on this contract
$stmt = $conn->prepare("
    SELECT DISTINCT e.id, e.name as employee_name, e.employee_code
    FROM working_hours wh
    JOIN employees e ON wh.employee_id = e.id
    WHERE wh.contract_id = ? AND wh.company_id = ?
    ORDER BY e.name
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$contract_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Add currency column to contract_payments if it doesn't exist
try {
    $conn->exec("ALTER TABLE contract_payments ADD COLUMN currency VARCHAR(3) DEFAULT NULL AFTER amount");
} catch (Exception $e) {
    // Column might already exist, ignore error
}

// Include centralized currency helper
require_once '../../../config/currency_helper.php';

// Get contract currency for all calculations
$contract_currency = $contract['currency'] ?? 'USD';

// Get payments for this contract
$stmt = $conn->prepare("
    SELECT cp.*, COALESCE(cp.currency, ?) as payment_currency
    FROM contract_payments cp
    WHERE cp.contract_id = ? AND cp.company_id = ?
    ORDER BY cp.payment_date DESC
");
$stmt->execute([$contract_currency, $contract_id, getCurrentCompanyId()]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total amount paid in contract currency
foreach ($payments as $payment) {
    // For now, assume all payments are in contract currency
    // Future enhancement: add currency conversion if payment currency differs
    $total_amount_paid += $payment['amount'];
}

$remaining_amount = $total_amount_earned - $total_amount_paid;
$progress_percentage = $contract['total_hours_required'] > 0 ? 
    ($total_hours_worked / $contract['total_hours_required']) * 100 : 0;

// Group working hours by month for chart (all in contract currency)
$monthly_data = [];
foreach ($working_hours as $wh) {
    $month = date('Y-m', strtotime($wh['date']));
    if (!isset($monthly_data[$month])) {
        $monthly_data[$month] = [
            'hours' => 0, 
            'amount' => 0, 
            'currency' => $contract_currency,
            'display_month' => date('M Y', strtotime($wh['date']))
        ];
    }
    $monthly_data[$month]['hours'] += $wh['hours_worked'];
    
    // Calculate amount for this day in contract currency
    if ($contract['contract_type'] === 'hourly') {
        $monthly_data[$month]['amount'] += $wh['hours_worked'] * $contract['rate_amount'];
    } elseif ($contract['contract_type'] === 'daily') {
        $daily_rate = $contract['rate_amount'];
        $monthly_data[$month]['amount'] += $wh['hours_worked'] * ($daily_rate / ($contract['working_hours_per_day'] ?: 8));
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
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('contract_timesheet'); ?></h1>
        <div>
            <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm me-2">
                <i class="fas fa-plus"></i> <?php echo __('add_work_hours'); ?>
            </a>
            <a href="add-payment.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-success btn-sm me-2">
                <i class="fas fa-dollar-sign"></i> <?php echo __('add_payment'); ?>
            </a>
            <a href="export-timesheet.php?contract_id=<?php echo $contract_id; ?>&type=pdf" target="_blank" class="btn btn-danger btn-sm me-1">
                <i class="fas fa-file-pdf"></i> <?php echo __('pdf'); ?>
            </a>
            <a href="export-timesheet.php?contract_id=<?php echo $contract_id; ?>&type=excel" class="btn btn-success btn-sm me-2">
                <i class="fas fa-file-excel"></i> <?php echo __('excel'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_contracts'); ?>
            </a>
        </div>
    </div>

    <!-- Success Messages -->
    <?php if (isset($_GET['payment_deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo __('payment_has_been_successfully_deleted'); ?>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['hours_deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo __('working_hours_entry_has_been_successfully_deleted'); ?>.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Contract Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_information'); ?></h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong><?php echo __('contract_code'); ?>:</strong></td>
                            <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('project'); ?>:</strong></td>
                            <td><?php echo htmlspecialchars($contract['project_name']); ?> (<?php echo htmlspecialchars($contract['project_code']); ?>)</td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('machine'); ?>:</strong></td>
                            <td><?php echo htmlspecialchars($contract['machine_name']); ?> (<?php echo htmlspecialchars($contract['machine_code']); ?>)</td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('employees'); ?>:</strong></td>
                            <td>
                                <?php if (!empty($contract_employees)): ?>
                                    <?php foreach ($contract_employees as $index => $employee): ?>
                                        <?php echo htmlspecialchars($employee['employee_name'] ?? 'N/A'); ?> 
                                        (<?php echo htmlspecialchars($employee['employee_code'] ?? 'N/A'); ?>)<?php echo $index < count($contract_employees) - 1 ? ', ' : ''; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted"><?php echo __('no_employees_assigned'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong><?php echo __('contract_type'); ?>:</strong></td>
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
                            <td><strong><?php echo __('currency'); ?>:</strong></td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-money-bill-wave"></i> <?php echo getCurrencySymbol($contract_currency) . ' (' . $contract_currency . ')'; ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('rate'); ?>:</strong></td>
                            <td>
                                <strong class="text-success">
                                    <?php echo formatCurrencyAmount($contract['rate_amount'], $contract_currency); ?>
                                </strong>
                                per <?php echo $contract['contract_type'] === 'hourly' ? 'hour' : ($contract['contract_type'] === 'daily' ? 'day' : 'month'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('total_contract_value'); ?>:</strong></td>
                            <td>
                                <strong class="text-primary">
                                    <?php echo formatCurrencyAmount($contract['total_amount'] ?? 0, $contract_currency); ?>
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('required_hours'); ?>:</strong></td>
                            <td><?php echo $contract['total_hours_required'] ?: 'N/A'; ?> hours</td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('working_hours_per_day'); ?>:</strong></td>
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
                                <?php echo __('total_hours_worked'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_hours_worked, 1); ?> hrs
                            </div>
                            <small class="text-muted">
                                <?php echo __('of'); ?> <?php echo $contract['total_hours_required'] ?: __('unlimited'); ?> <?php echo __('required'); ?>
                            </small>
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
                                <?php echo __('total_amount_earned'); ?> (<?php echo $contract_currency; ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrencyAmount($total_amount_earned, $contract_currency); ?>
                            </div>
                            <small class="text-muted">
                                <?php echo __('based_on'); ?> <?php echo ucfirst($contract['contract_type']); ?> <?php echo __('rate'); ?>
                            </small>
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
                                <?php echo __('amount_paid'); ?> (<?php echo $contract_currency; ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrencyAmount($total_amount_paid, $contract_currency); ?>
                            </div>
                            <small class="text-muted">
                                <?php echo count($payments); ?> <?php echo __('payment_s_received'); ?>
                            </small>
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
                                <?php echo __('remaining_balance'); ?> (<?php echo $contract_currency; ?>)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <span class="<?php echo $remaining_amount < 0 ? 'text-danger' : 'text-warning'; ?>">
                                    <?php echo formatCurrencyAmount($remaining_amount, $contract_currency); ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo $remaining_amount < 0 ? __('overpaid') : ($remaining_amount > 0 ? __('outstanding') : __('fully_paid')); ?>
                            </small>
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_progress'); ?></h6>
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
                        <?php echo $total_hours_worked; ?> <?php echo __('of'); ?> <?php echo $contract['total_hours_required'] ?: '∞'; ?> <?php echo __('hours_completed'); ?>
                    </small>
                </div>
                <div class="col-md-4 text-end">
                    <h6><?php echo __('current_month'); ?></h6>
                    <p class="mb-1"><strong><?php echo number_format($current_month_hours, 1); ?> <?php echo __('hours'); ?></strong></p>
                                                        <p class="mb-0"><strong><?php echo formatCurrencyAmount($current_month_amount, $contract_currency); ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Working Hours Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('daily_timesheet'); ?></h6>
            <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('add_hours'); ?>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($working_hours)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clock fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_working_hours_recorded_yet'); ?></p>
                    <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_entry'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="timesheetTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('employee'); ?></th>
                                <th><?php echo __('hours_worked'); ?></th>
                                <th><?php echo __('rate'); ?></th>
                                <th><?php echo __('daily_amount'); ?></th>
                                <th><?php echo __('notes'); ?></th>
                                <th><?php echo __('actions'); ?></th>
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
                                            <strong><?php echo date('M j, Y', strtotime($wh['date'])); ?></strong>
                                            <br><small class="text-muted"><?php echo date('l', strtotime($wh['date'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($wh['employee_name'] ?? 'N/A'); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($wh['employee_code'] ?? 'N/A'); ?></small>
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
                                                echo formatCurrencyAmount($contract['rate_amount'], $contract_currency) . '/hr';
                                            } elseif ($contract['contract_type'] === 'daily') {
                                                $hourly_rate = $contract['rate_amount'] / ($contract['working_hours_per_day'] ?: 8);
                                                echo formatCurrencyAmount($hourly_rate, $contract_currency) . '/hr';
                                            } else {
                                                $hourly_rate = $contract['rate_amount'] / ($contract['total_hours_required'] ?: 270);
                                                echo formatCurrencyAmount($hourly_rate, $contract_currency) . '/hr';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong class="text-success"><?php echo formatCurrencyAmount($daily_amount, $contract_currency); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if (!empty($wh['notes'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($wh['notes']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo __('no_notes'); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/constract360/construction/public/admin/contracts/edit-hours.php?id=<?php echo $wh['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="/constract360/construction/public/admin/contracts/delete-hours.php?id=<?php echo $wh['id']; ?>" 
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
                                <td colspan="2"><strong><?php echo __('total'); ?></strong></td>
                                <td class="text-center"><strong><?php echo number_format($total_hours_worked, 1); ?> <?php echo __('hours'); ?></strong></td>
                                <td></td>
                                <td><strong><?php echo formatCurrencyAmount($total_amount_earned, $contract_currency); ?></strong></td>
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_payments'); ?></h6>
            <a href="add-payment.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('add_payment'); ?>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-credit-card fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_payments_recorded_yet'); ?></p>
                    <a href="add-payment.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_payment'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th><?php echo __('payment_date'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('payment_method'); ?></th>
                                <th><?php echo __('reference'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                                    <td><strong><?php echo formatCurrencyAmount($payment['amount'], $contract_currency); ?></strong></td>
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
                                            <a href="/constract360/construction/public/admin/contracts/edit-payment.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="/constract360/construction/public/admin/contracts/delete-payment.php?id=<?php echo $payment['id']; ?>" 
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
                                <td><strong><?php echo __('total_paid'); ?></strong></td>
                                <td><strong><?php echo formatCurrencyAmount($total_amount_paid, $contract_currency); ?></strong></td>
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('monthly_work_hours_revenue'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($monthly_data)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-chart-bar fa-3x mb-3"></i>
                    <p><?php echo __('no_working_hours_data_available_for_chart_display'); ?></p>
                </div>
            <?php else: ?>
                <canvas id="monthlyChart" width="400" height="100"></canvas>
                <div class="mt-3">
                    <small class="text-muted">
                        <?php echo __('showing_data_for'); ?> <?php echo count($monthly_data); ?> <?php echo __('month_s'); ?>. 
                        <?php echo __('total_hours'); ?>: <?php echo array_sum(array_column($monthly_data, 'hours')); ?> | 
                        <?php echo __('total_amount'); ?>: <?php echo formatCurrencyAmount(array_sum(array_column($monthly_data, 'amount')), $contract_currency); ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
function confirmDelete(message) {
    return confirm(message);
}

// Monthly Chart
const monthlyData = <?php echo json_encode($monthly_data); ?>;
console.log('Monthly Data:', monthlyData);

if (Object.keys(monthlyData).length > 0) {
    const months = Object.keys(monthlyData);
    const hoursData = months.map(month => monthlyData[month].hours || 0);
    const amountData = months.map(month => monthlyData[month].amount || 0);
    
    console.log('Months:', months);
    console.log('Hours Data:', hoursData);
    console.log('Amount Data:', amountData);

    const ctx = document.getElementById('monthlyChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
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
            label: '<?php echo __('hours_worked'); ?>',
            data: hoursData,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1,
            yAxisID: 'y'
        }, {
            label: '<?php echo __('revenue'); ?>',
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
                    text: '<?php echo __('month'); ?>'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: '<?php echo __('hours'); ?>'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: '<?php echo __('revenue'); ?>'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        }
    });
    }
} else {
    console.log('<?php echo __('no_monthly_data_available_for_chart'); ?>');
}
</script>

<?php require_once '../../../includes/footer.php'; ?>