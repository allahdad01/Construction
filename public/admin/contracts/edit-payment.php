<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    header('Location: index.php');
    exit();
}

// Get payment details
$stmt = $conn->prepare("
    SELECT cp.*, c.contract_code, c.currency, p.name as project_name 
    FROM contract_payments cp
    JOIN contracts c ON cp.contract_id = c.id
    LEFT JOIN projects p ON c.project_id = p.id
    WHERE cp.id = ? AND cp.company_id = ?
");
$stmt->execute([$payment_id, getCurrentCompanyId()]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: index.php');
    exit();
}

$contract_id = $payment['contract_id'];
$contract_currency = $payment['currency'] ?? 'USD';

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
            $stmt = $conn->prepare("
                UPDATE contract_payments 
                SET payment_date = ?, amount = ?, payment_method = ?, reference_number = ?, status = ?, notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $payment_date,
                $amount,
                $payment_method,
                $reference_number,
                $status,
                $notes,
                $payment_id,
                getCurrentCompanyId()
            ]);
            
            $success = 'Payment updated successfully!';
            
            // Refresh payment data
            $stmt = $conn->prepare("
                SELECT cp.*, c.contract_code, c.currency, p.name as project_name 
                FROM contract_payments cp
                JOIN contracts c ON cp.contract_id = c.id
                LEFT JOIN projects p ON c.project_id = p.id
                WHERE cp.id = ? AND cp.company_id = ?
            ");
            $stmt->execute([$payment_id, getCurrentCompanyId()]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Failed to update payment: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Contract Payment</h1>
        <div>
            <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Timesheet
            </a>
        </div>
    </div>

    <!-- Contract Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Payment Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract:</strong></td>
                            <td><?php echo htmlspecialchars($payment['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Project:</strong></td>
                            <td><?php echo htmlspecialchars($payment['project_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Payment Code:</strong></td>
                            <td><?php echo htmlspecialchars($payment['payment_code']); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Currency:</strong></td>
                            <td>
                                <span class="badge bg-info"><?php echo getCurrencyDisplay($contract_currency); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td><?php echo date('M j, Y \a\t g:i A', strtotime($payment['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td><?php echo date('M j, Y \a\t g:i A', strtotime($payment['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Payment Form -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Payment Details</h6>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_date" class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                   value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="amount" class="form-label">
                                Amount (<?php echo getCurrencySymbol($contract_currency); ?>) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo getCurrencySymbol($contract_currency); ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" 
                                       value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="cash" <?php echo $payment['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                <option value="bank_transfer" <?php echo $payment['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="credit_card" <?php echo $payment['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                <option value="debit_card" <?php echo $payment['payment_method'] === 'debit_card' ? 'selected' : ''; ?>>Debit Card</option>
                                <option value="check" <?php echo $payment['payment_method'] === 'check' ? 'selected' : ''; ?>>Check</option>
                                <option value="mobile_payment" <?php echo $payment['payment_method'] === 'mobile_payment' ? 'selected' : ''; ?>>Mobile Payment</option>
                                <option value="online_payment" <?php echo $payment['payment_method'] === 'online_payment' ? 'selected' : ''; ?>>Online Payment</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                   value="<?php echo htmlspecialchars($payment['reference_number'] ?? ''); ?>"
                                   placeholder="Transaction ID, Check number, etc.">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pending" <?php echo $payment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $payment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo $payment['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="cancelled" <?php echo $payment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Additional notes about this payment"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>