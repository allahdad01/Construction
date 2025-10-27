<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Include centralized currency helper
require_once '../../../config/currency_helper.php';

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

// Ensure currency column exists on contract_payments
try {
    $conn->exec("ALTER TABLE contract_payments ADD COLUMN currency VARCHAR(3) DEFAULT NULL AFTER amount");
} catch (Exception $e) { /* ignore if exists */ }

// Calculate contract earnings and payments
$stmt = $conn->prepare("
    SELECT SUM(hours_worked) as total_hours 
    FROM working_hours 
    WHERE contract_id = ? AND company_id = ?
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$total_hours = $stmt->fetch(PDO::FETCH_ASSOC)['total_hours'] ?? 0;

// Calculate total earned amount
$total_earned = 0;
if ($contract['contract_type'] === 'hourly') {
    $total_earned = $total_hours * $contract['rate_amount'];
} elseif ($contract['contract_type'] === 'daily') {
    $total_earned = $total_hours * ($contract['rate_amount'] / $contract['working_hours_per_day']);
} elseif ($contract['contract_type'] === 'monthly') {
    $total_earned = $total_hours * ($contract['rate_amount'] / ($contract['total_hours_required'] ?: 270));
}

// Get total paid amount
$stmt = $conn->prepare("
    SELECT SUM(amount) as total_paid 
    FROM contract_payments 
    WHERE contract_id = ? AND company_id = ? AND status = 'completed'
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;

$remaining_amount = $total_earned - $total_paid;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_date = $_POST['payment_date'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? '';
    $reference_number = trim($_POST['reference_number'] ?? '');
    $status = $_POST['status'] ?? 'completed';
    $notes = trim($_POST['notes'] ?? '');
    $payment_currency = $contract['currency'] ?? 'USD';
    
    // Validation
    if (empty($payment_date)) {
        $error = 'Please select a payment date.';
    } elseif ($amount <= 0) {
        $error = 'Payment amount must be greater than 0.';
    } elseif (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } else {
        try {
            // Generate payment code
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contract_payments WHERE company_id = ?");
            $stmt->execute([getCurrentCompanyId()]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $payment_code = 'PAY' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                INSERT INTO contract_payments (company_id, contract_id, payment_code, payment_date, amount, currency, payment_method, reference_number, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                getCurrentCompanyId(),
                $contract_id,
                $payment_code,
                $payment_date,
                $amount,
                $payment_currency,
                $payment_method,
                $reference_number,
                $status,
                $notes
            ]);
            
            $success = 'Payment added successfully! Payment Code: ' . $payment_code;
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $error = 'Failed to add payment: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('add_contract_payment'); ?></h1>
        <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_timesheet'); ?>
        </a>
    </div>

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
                            <td><strong><?php echo __('contract'); ?>:</strong></td>
                            <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('project'); ?>:</strong></td>
                            <td><?php echo htmlspecialchars($contract['project_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('machine'); ?>:</strong></td>
                            <td><?php echo htmlspecialchars($contract['machine_name']); ?></td>
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
                            <td><strong><?php echo __('rate'); ?>:</strong></td>
                            <td><?php echo formatCurrencyAmount($contract['rate_amount'], $contract['currency'] ?? 'USD'); ?> per <?php echo $contract['contract_type'] === 'hourly' ? 'hour' : ($contract['contract_type'] === 'daily' ? 'day' : 'month'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo __('status'); ?>:</strong></td>
                            <td>
                                <span class="badge <?php echo $contract['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($contract['status']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_hours'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_hours, 1); ?> hrs</div>
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
                                <?php echo __('total_earned'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($total_earned, $contract['currency'] ?? 'USD'); ?></div>
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
                                <?php echo __('total_paid'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($total_paid, $contract['currency'] ?? 'USD'); ?></div>
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
                                <?php echo __('remaining'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($remaining_amount, $contract['currency'] ?? 'USD'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('add_payment'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">
                                        <i class="fas fa-calendar"></i> <?php echo __('payment_date'); ?> *
                                    </label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">
                                        <i class="fas fa-money-bill-wave"></i> <?php echo __('amount'); ?> (<?php echo getCurrencySymbol($contract['currency'] ?? 'USD'); ?>) *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><?php echo getCurrencySymbol($contract['currency'] ?? 'USD'); ?></span>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                               step="0.01" min="0.01" required>
                                    </div>
                                    <small class="text-muted">Maximum: <?php echo formatCurrencyAmount($remaining_amount, $contract['currency'] ?? 'USD'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">
                                        <i class="fas fa-credit-card"></i> <?php echo __('payment_method'); ?> *
                                    </label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value=""><?php echo __('select_payment_method'); ?></option>
                                        <option value="cash" <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                        <option value="credit_card" <?php echo ($_POST['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>><?php echo __('credit_card'); ?></option>
                                        
                                        <option value="check" <?php echo ($_POST['payment_method'] ?? '') === 'check' ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                                        <option value="paypal" <?php echo ($_POST['payment_method'] ?? '') === 'paypal' ? 'selected' : ''; ?>><?php echo __('paypal'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">
                                        <i class="fas fa-check-circle"></i> <?php echo __('payment_status'); ?>
                                    </label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="completed" <?php echo ($_POST['status'] ?? 'completed') === 'completed' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                        <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                        <option value="failed" <?php echo ($_POST['status'] ?? '') === 'failed' ? 'selected' : ''; ?>><?php echo __('failed'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="reference_number" class="form-label">
                                        <i class="fas fa-hashtag"></i> <?php echo __('reference_number'); ?>
                                    </label>
                                    <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                           value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>" 
                                           placeholder="<?php echo __('transaction_id_check_number_etc'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_amount" class="form-label">
                                        <i class="fas fa-info-circle"></i> <?php echo __('maximum_payment'); ?>
                                    </label>
                                    <input type="text" class="form-control" id="max_amount" readonly 
                                           value="<?php echo formatCurrencyAmount($remaining_amount, $contract['currency'] ?? 'USD'); ?>">
                                    <small class="text-muted"><?php echo __('remaining_amount_to_be_paid'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note"></i> <?php echo __('notes'); ?>
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="<?php echo __('enter_any_notes_about_this_payment'); ?>..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo __('add_payment'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payment_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6><?php echo __('hours_worked'); ?></h6>
                            <p class="mb-1"><strong><?php echo number_format($total_hours, 1); ?></strong></p>
                        </div>
                        <div class="col-6">
                            <h6><?php echo __('rate'); ?></h6>
                            <p class="mb-1"><strong><?php echo formatCurrencyAmount($contract['rate_amount'], $contract['currency'] ?? 'USD'); ?></strong></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h6><?php echo __('total_earned'); ?></h6>
                            <p class="mb-1"><strong class="text-success"><?php echo formatCurrencyAmount($total_earned, $contract['currency'] ?? 'USD'); ?></strong></p>
                        </div>
                        <div class="col-6">
                            <h6><?php echo __('total_paid'); ?></h6>
                            <p class="mb-1"><strong class="text-info"><?php echo formatCurrencyAmount($total_paid, $contract['currency'] ?? 'USD'); ?></strong></p>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6><?php echo __('remaining_amount'); ?></h6>
                        <h4 class="text-warning"><?php echo formatCurrencyAmount($remaining_amount, $contract['currency'] ?? 'USD'); ?></h4>
                        <small class="text-muted"><?php echo __('amount_that_can_be_paid'); ?></small>
                    </div>
                    
                    <hr>
                    
                    <h6><?php echo __('payment_guidelines'); ?></h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> <?php echo __('payment_cannot_exceed_remaining_amount'); ?></li>
                        <li><i class="fas fa-check text-success"></i> <?php echo __('reference_number_is_optional'); ?></li>
                        <li><i class="fas fa-check text-success"></i> <?php echo __('status_can_be_updated_later'); ?></li>
                        <li><i class="fas fa-check text-success"></i> <?php echo __('payment_code_generated_automatically'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Validate payment amount
    $('#amount').on('input', function() {
        var amount = parseFloat($(this).val()) || 0;
        var maxAmount = <?php echo $remaining_amount; ?>;
        
        if (amount > maxAmount) {
            $(this).addClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
            $(this).after('<div class="invalid-feedback">Amount cannot exceed ' + maxAmount.toFixed(2) + '</div>');
        } else {
            $(this).removeClass('is-invalid');
            $(this).next('.invalid-feedback').remove();
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>