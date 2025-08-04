<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Get companies for selection
$stmt = $conn->prepare("SELECT id, company_name, company_code FROM companies WHERE is_active = 1 ORDER BY company_name");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['company_id', 'amount', 'currency', 'payment_method', 'payment_status', 'payment_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate amount
        if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
            throw new Exception("Amount must be a positive number.");
        }

        // Validate currency
        $allowed_currencies = ['USD', 'AFN'];
        if (!in_array($_POST['currency'], $allowed_currencies)) {
            throw new Exception("Invalid currency selected.");
        }

        // Generate payment code
        $payment_code = generatePaymentCode($_POST['company_id']);

        // Start transaction
        $conn->beginTransaction();

        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO company_payments (
                company_id, payment_code, amount, currency, payment_method, 
                payment_status, payment_date, billing_period_start, billing_period_end,
                subscription_plan, transaction_id, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $_POST['company_id'],
            $payment_code,
            $_POST['amount'],
            $_POST['currency'],
            $_POST['payment_method'],
            $_POST['payment_status'],
            $_POST['payment_date'],
            $_POST['billing_period_start'] ?: null,
            $_POST['billing_period_end'] ?: null,
            $_POST['subscription_plan'] ?: null,
            $_POST['transaction_id'] ?: null,
            $_POST['notes'] ?: null
        ]);

        $payment_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Payment added successfully! Payment Code: $payment_code";

        // Redirect to payment view
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$payment_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate payment code
function generatePaymentCode($company_id) {
    global $conn;
    
    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];
    
    // Get next payment number for this company
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM company_payments WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $next_number = $count + 1;
    return strtoupper($company_code) . 'PAY' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus"></i> Add Payment
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Payments
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_id" class="form-label">Company *</label>
                                    <select class="form-control" id="company_id" name="company_id" required>
                                        <option value="">Select Company</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>" <?php echo ($_POST['company_id'] ?? '') == $company['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company['company_name'] . ' (' . $company['company_code'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Currency *</label>
                                    <select class="form-control" id="currency" name="currency" required>
                                        <option value="">Select Currency</option>
                                        <option value="USD" <?php echo ($_POST['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                        <option value="AFN" <?php echo ($_POST['currency'] ?? '') === 'AFN' ? 'selected' : ''; ?>>AFN (Afghan Afghani)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method *</label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="credit_card" <?php echo ($_POST['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                        <option value="cash" <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        <option value="check" <?php echo ($_POST['payment_method'] ?? '') === 'check' ? 'selected' : ''; ?>>Check</option>
                                        <option value="paypal" <?php echo ($_POST['payment_method'] ?? '') === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                        <option value="other" <?php echo ($_POST['payment_method'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label">Payment Status *</label>
                                    <select class="form-control" id="payment_status" name="payment_status" required>
                                        <option value="">Select Status</option>
                                        <option value="completed" <?php echo ($_POST['payment_status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="pending" <?php echo ($_POST['payment_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="failed" <?php echo ($_POST['payment_status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="cancelled" <?php echo ($_POST['payment_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label">Payment Date *</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_start" class="form-label">Billing Period Start</label>
                                    <input type="date" class="form-control" id="billing_period_start" name="billing_period_start" 
                                           value="<?php echo htmlspecialchars($_POST['billing_period_start'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_end" class="form-label">Billing Period End</label>
                                    <input type="date" class="form-control" id="billing_period_end" name="billing_period_end" 
                                           value="<?php echo htmlspecialchars($_POST['billing_period_end'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subscription_plan" class="form-label">Subscription Plan</label>
                                    <select class="form-control" id="subscription_plan" name="subscription_plan">
                                        <option value="">Select Plan</option>
                                        <option value="basic" <?php echo ($_POST['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                        <option value="professional" <?php echo ($_POST['subscription_plan'] ?? '') === 'professional' ? 'selected' : ''; ?>>Professional</option>
                                        <option value="enterprise" <?php echo ($_POST['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="transaction_id" class="form-label">Transaction ID</label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           value="<?php echo htmlspecialchars($_POST['transaction_id'] ?? ''); ?>" 
                                           placeholder="e.g., TXN-2024-001">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes about this payment"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Payment Methods</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-credit-card me-2"></i>Credit Card</li>
                            <li><i class="fas fa-university me-2"></i>Bank Transfer</li>
                            <li><i class="fas fa-money-bill me-2"></i>Cash</li>
                            <li><i class="fas fa-file-invoice me-2"></i>Check</li>
                            <li><i class="fab fa-paypal me-2"></i>PayPal</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Currencies</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-dollar-sign me-2"></i>USD (US Dollar)</li>
                            <li><i class="fas fa-coins me-2"></i>AFN (Afghan Afghani)</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Payment Status</h6>
                        <ul class="list-unstyled">
                            <li><span class="badge bg-success me-2">Completed</span>Payment received and processed</li>
                            <li><span class="badge bg-warning me-2">Pending</span>Payment awaiting confirmation</li>
                            <li><span class="badge bg-danger me-2">Failed</span>Payment processing failed</li>
                            <li><span class="badge bg-secondary me-2">Cancelled</span>Payment was cancelled</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const companyId = document.getElementById('company_id').value;
    const amount = document.getElementById('amount').value;
    const currency = document.getElementById('currency').value;
    const paymentMethod = document.getElementById('payment_method').value;
    const paymentStatus = document.getElementById('payment_status').value;
    const paymentDate = document.getElementById('payment_date').value;
    
    if (!companyId || !amount || !currency || !paymentMethod || !paymentStatus || !paymentDate) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (amount <= 0) {
        e.preventDefault();
        alert('Amount must be greater than zero.');
        return false;
    }
});

// Auto-fill billing period end when start is selected
document.getElementById('billing_period_start').addEventListener('change', function() {
    const startDate = this.value;
    if (startDate) {
        const endDate = new Date(startDate);
        endDate.setMonth(endDate.getMonth() + 1);
        endDate.setDate(endDate.getDate() - 1);
        document.getElementById('billing_period_end').value = endDate.toISOString().split('T')[0];
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>