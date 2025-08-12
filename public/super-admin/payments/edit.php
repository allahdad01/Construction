<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$payment_id = (int)($_GET['id'] ?? 0);

if (!$payment_id) {
    header('Location: index.php');
    exit;
}

// Get payment details
$stmt = $conn->prepare("
    SELECT cp.*, c.company_name, c.company_code
    FROM company_payments cp 
    LEFT JOIN companies c ON cp.company_id = c.id 
    WHERE cp.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: index.php');
    exit;
}

// Get companies for selection
$stmt = $conn->prepare("SELECT id, company_name, company_code FROM companies WHERE is_active = 1 ORDER BY company_name");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

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

        // Start transaction
        $conn->beginTransaction();

        // Update payment record
        $stmt = $conn->prepare("
            UPDATE company_payments SET 
                company_id = ?, amount = ?, currency = ?, payment_method = ?, 
                payment_status = ?, payment_date = ?, billing_period_start = ?, 
                billing_period_end = ?, subscription_plan = ?, transaction_id = ?, 
                notes = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['company_id'],
            $_POST['amount'],
            $_POST['currency'],
            $_POST['payment_method'],
            $_POST['payment_status'],
            $_POST['payment_date'],
            $_POST['billing_period_start'] ?: null,
            $_POST['billing_period_end'] ?: null,
            $_POST['subscription_plan'] ?: null,
            $_POST['transaction_id'] ?: null,
            $_POST['notes'] ?: null,
            $payment_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Payment updated successfully!";

        // Redirect to payment view
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$payment_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_payment'); ?>
        </h1>
        <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_payment'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payment_information'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_id" class="form-label"><?php echo __('company'); ?> *</label>
                                    <select class="form-control" id="company_id" name="company_id" required>
                                        <option value=""><?php echo __('select_company'); ?></option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>" <?php echo ($_POST['company_id'] ?? $payment['company_id']) == $company['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($company['company_name'] . ' (' . $company['company_code'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label"><?php echo __('amount'); ?> *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? $payment['amount']); ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency" class="form-label"><?php echo __('currency'); ?> *</label>
                                    <select class="form-control" id="currency" name="currency" required>
                                        <option value=""><?php echo __('select_currency'); ?></option>
                                        <option value="USD" <?php echo ($_POST['currency'] ?? $payment['currency']) === 'USD' ? 'selected' : ''; ?>><?php echo __('usd'); ?></option>
                                        <option value="AFN" <?php echo ($_POST['currency'] ?? $payment['currency']) === 'AFN' ? 'selected' : ''; ?>><?php echo __('afn'); ?> (<?php echo __('afghan_afghani'); ?>)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?> *</label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value=""><?php echo __('select_payment_method'); ?></option>
                                        <option value="credit_card" <?php echo ($_POST['payment_method'] ?? $payment['payment_method']) === 'credit_card' ? 'selected' : ''; ?>><?php echo __('credit_card'); ?></option>
                                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? $payment['payment_method']) === 'bank_transfer' ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                        <option value="cash" <?php echo ($_POST['payment_method'] ?? $payment['payment_method']) === 'cash' ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                        <option value="check" <?php echo ($_POST['payment_method'] ?? $payment['payment_method']) === 'check' ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                                        <option value="paypal" <?php echo ($_POST['payment_method'] ?? $payment['payment_method']) === 'paypal' ? 'selected' : ''; ?>><?php echo __('paypal'); ?></option>
                                        <option value="other" <?php echo ($_POST['payment_method'] ?? $payment['payment_method']) === 'other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label"><?php echo __('payment_status'); ?> *</label>
                                    <select class="form-control" id="payment_status" name="payment_status" required>
                                        <option value=""><?php echo __('select_status'); ?></option>
                                        <option value="completed" <?php echo ($_POST['payment_status'] ?? $payment['payment_status']) === 'completed' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                        <option value="pending" <?php echo ($_POST['payment_status'] ?? $payment['payment_status']) === 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                        <option value="failed" <?php echo ($_POST['payment_status'] ?? $payment['payment_status']) === 'failed' ? 'selected' : ''; ?>><?php echo __('failed'); ?></option>
                                        <option value="cancelled" <?php echo ($_POST['payment_status'] ?? $payment['payment_status']) === 'cancelled' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label"><?php echo __('payment_date'); ?> *</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? $payment['payment_date']); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_start" class="form-label"><?php echo __('billing_period_start'); ?></label>
                                    <input type="date" class="form-control" id="billing_period_start" name="billing_period_start" 
                                           value="<?php echo htmlspecialchars($_POST['billing_period_start'] ?? $payment['billing_period_start'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_end" class="form-label"><?php echo __('billing_period_end'); ?></label>
                                    <input type="date" class="form-control" id="billing_period_end" name="billing_period_end" 
                                           value="<?php echo htmlspecialchars($_POST['billing_period_end'] ?? $payment['billing_period_end'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subscription_plan" class="form-label"><?php echo __('subscription_plan'); ?></label>
                                    <select class="form-control" id="subscription_plan" name="subscription_plan">
                                        <option value=""><?php echo __('select_plan'); ?></option>
                                        <option value="basic" <?php echo ($_POST['subscription_plan'] ?? $payment['subscription_plan']) === 'basic' ? 'selected' : ''; ?>><?php echo __('basic'); ?></option>
                                        <option value="professional" <?php echo ($_POST['subscription_plan'] ?? $payment['subscription_plan']) === 'professional' ? 'selected' : ''; ?>><?php echo __('professional'); ?></option>
                                        <option value="enterprise" <?php echo ($_POST['subscription_plan'] ?? $payment['subscription_plan']) === 'enterprise' ? 'selected' : ''; ?>><?php echo __('enterprise'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="transaction_id" class="form-label"><?php echo __('transaction_id'); ?></label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           value="<?php echo htmlspecialchars($_POST['transaction_id'] ?? $payment['transaction_id'] ?? ''); ?>" 
                                           placeholder="e.g., TXN-2024-001">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes about this payment"><?php echo htmlspecialchars($_POST['notes'] ?? $payment['notes'] ?? ''); ?></textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('update_payment'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Current Payment Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('current_payment_info'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><?php echo __('payment_code'); ?>:</strong> <?php echo htmlspecialchars($payment['payment_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('company'); ?>:</strong> <?php echo htmlspecialchars($payment['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('amount'); ?>:</strong> 
                        <span class="text-success fw-bold"><?php echo formatCurrency($payment['amount']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('currency'); ?>:</strong> 
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($payment['currency']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('status'); ?>:</strong> 
                        <span class="badge <?php 
                            echo $payment['payment_status'] === 'completed' ? 'bg-success' : 
                                ($payment['payment_status'] === 'pending' ? 'bg-warning' : 
                                ($payment['payment_status'] === 'failed' ? 'bg-danger' : 'bg-secondary')); 
                        ?>">
                            <?php echo ucfirst($payment['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('payment_methods'); ?></h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-credit-card me-2"></i><?php echo __('credit_card'); ?></li>
                            <li><i class="fas fa-university me-2"></i><?php echo __('bank_transfer'); ?></li>
                            <li><i class="fas fa-money-bill me-2"></i><?php echo __('cash'); ?></li>
                            <li><i class="fas fa-file-invoice me-2"></i><?php echo __('check'); ?></li>
                            <li><i class="fab fa-paypal me-2"></i><?php echo __('paypal'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('currencies'); ?></h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-dollar-sign me-2"></i><?php echo __('usd'); ?></li>
                            <li><i class="fas fa-coins me-2"></i><?php echo __('afn'); ?> (<?php echo __('afghan_afghani'); ?>)</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('payment_status'); ?></h6>
                        <ul class="list-unstyled">
                            <li><span class="badge bg-success me-2"><?php echo __('completed'); ?></span><?php echo __('payment_received_and_processed'); ?></li>
                            <li><span class="badge bg-warning me-2"><?php echo __('pending'); ?></span><?php echo __('payment_awaiting_confirmation'); ?></li>
                            <li><span class="badge bg-danger me-2"><?php echo __('failed'); ?></span><?php echo __('payment_processing_failed'); ?></li>
                            <li><span class="badge bg-secondary me-2"><?php echo __('cancelled'); ?></span><?php echo __('payment_was_cancelled'); ?></li>
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
        alert('<?php echo __('please_fill_in_all_required_fields'); ?>');
        return false;
    }
    
    if (amount <= 0) {
        e.preventDefault();
        alert('<?php echo __('amount_must_be_greater_than_zero'); ?>');
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