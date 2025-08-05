<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);

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
                INSERT INTO contract_payments (company_id, contract_id, payment_code, payment_date, amount, payment_method, reference_number, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                getCurrentCompanyId(),
                $contract_id,
                $payment_code,
                $payment_date,
                $amount,
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
        <h1 class="h3 mb-0 text-gray-800">Add Contract Payment</h1>
        <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Timesheet
        </a>
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
                            <td><strong>Contract:</strong></td>
                            <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Project:</strong></td>
                            <td><?php echo htmlspecialchars($contract['project_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Machine:</strong></td>
                            <td><?php echo htmlspecialchars($contract['machine_name']); ?></td>
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
                            <td><?php echo formatCurrency($contract['rate_amount']); ?> per <?php echo $contract['contract_type'] === 'hourly' ? 'hour' : ($contract['contract_type'] === 'daily' ? 'day' : 'month'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
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
                                Total Hours</div>
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
                                Total Earned</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_earned); ?></div>
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
                                Total Paid</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_paid); ?></div>
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
                                Remaining</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($remaining_amount); ?></div>
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
                    <h6 class="m-0 font-weight-bold text-primary">Add Payment</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">
                                        <i class="fas fa-calendar"></i> Payment Date *
                                    </label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">
                                        <i class="fas fa-dollar-sign"></i> Amount *
                                    </label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                           step="0.01" min="0.01" required>
                                    <small class="text-muted">Maximum: <?php echo formatCurrency($remaining_amount); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">
                                        <i class="fas fa-credit-card"></i> Payment Method *
                                    </label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                        <option value="credit_card" <?php echo ($_POST['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                        <option value="cash" <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        <option value="check" <?php echo ($_POST['payment_method'] ?? '') === 'check' ? 'selected' : ''; ?>>Check</option>
                                        <option value="paypal" <?php echo ($_POST['payment_method'] ?? '') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">
                                        <i class="fas fa-check-circle"></i> Payment Status
                                    </label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="completed" <?php echo ($_POST['status'] ?? 'completed') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="pending" <?php echo ($_POST['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="failed" <?php echo ($_POST['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="reference_number" class="form-label">
                                        <i class="fas fa-hashtag"></i> Reference Number
                                    </label>
                                    <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                           value="<?php echo htmlspecialchars($_POST['reference_number'] ?? ''); ?>" 
                                           placeholder="Transaction ID, Check number, etc.">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_amount" class="form-label">
                                        <i class="fas fa-info-circle"></i> Maximum Payment
                                    </label>
                                    <input type="text" class="form-control" id="max_amount" readonly 
                                           value="<?php echo formatCurrency($remaining_amount); ?>">
                                    <small class="text-muted">Remaining amount to be paid</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note"></i> Notes
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Enter any notes about this payment..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Add Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6>Hours Worked</h6>
                            <p class="mb-1"><strong><?php echo number_format($total_hours, 1); ?></strong></p>
                        </div>
                        <div class="col-6">
                            <h6>Rate</h6>
                            <p class="mb-1"><strong><?php echo formatCurrency($contract['rate_amount']); ?></strong></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h6>Total Earned</h6>
                            <p class="mb-1"><strong class="text-success"><?php echo formatCurrency($total_earned); ?></strong></p>
                        </div>
                        <div class="col-6">
                            <h6>Total Paid</h6>
                            <p class="mb-1"><strong class="text-info"><?php echo formatCurrency($total_paid); ?></strong></p>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6>Remaining Amount</h6>
                        <h4 class="text-warning"><?php echo formatCurrency($remaining_amount); ?></h4>
                        <small class="text-muted">Amount that can be paid</small>
                    </div>
                    
                    <hr>
                    
                    <h6>Payment Guidelines</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Payment cannot exceed remaining amount</li>
                        <li><i class="fas fa-check text-success"></i> Reference number is optional</li>
                        <li><i class="fas fa-check text-success"></i> Status can be updated later</li>
                        <li><i class="fas fa-check text-success"></i> Payment code generated automatically</li>
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