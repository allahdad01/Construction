<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['company_id'] ?? 0);

if (!$company_id) {
    header('Location: '.$base_url.'super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    header('Location: '.$base_url.'super-admin/companies/');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['amount', 'currency', 'payment_method', 'payment_status', 'payment_date'];
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
        $payment_code = generatePaymentCode($company_id);

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
            $company_id,
            $payment_code,
            $_POST['amount'],
            $_POST['currency'],
            $_POST['payment_method'],
            $_POST['payment_status'],
            $_POST['payment_date'],
            $_POST['billing_period_start'] ?: null,
            $_POST['billing_period_end'] ?: null,
            $_POST['subscription_plan'] ?: $company['subscription_plan'],
            $_POST['transaction_id'] ?: null,
            $_POST['notes'] ?: null
        ]);

        $payment_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Payment added successfully! Payment Code: $payment_code";

        // Redirect to payment view
        echo "<script>setTimeout(function(){ window.location.href = 'payment-view.php?id=$payment_id&company_id=$company_id'; }, 2000);</script>";

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
            <i class="fas fa-plus"></i> <?php echo __('add_payment'); ?> - <?php echo htmlspecialchars($company['company_name']); ?>
        </h1>
        <a href="payments.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_payments'); ?>
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
                                    <label for="amount" class="form-label"><?php echo __('amount'); ?> *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency" class="form-label"><?php echo __('currency'); ?> *</label>
                                    <select class="form-control" id="currency" name="currency" required>
                                        <option value=""><?php echo __('select_currency'); ?></option>
                                        <option value="USD" <?php echo ($_POST['currency'] ?? '') === 'USD' ? 'selected' : ''; ?>><?php echo __('usd'); ?> (<?php echo __('us_dollar'); ?>)</option>
                                        <option value="AFN" <?php echo ($_POST['currency'] ?? '') === 'AFN' ? 'selected' : ''; ?>><?php echo __('afn'); ?> (<?php echo __('afghan_afghani'); ?>)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?> *</label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value=""><?php echo __('select_payment_method'); ?></option>
                                        <option value="credit_card" <?php echo ($_POST['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>><?php echo __('credit_card'); ?></option>
                                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                        <option value="cash" <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                        <option value="check" <?php echo ($_POST['payment_method'] ?? '') === 'check' ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                                        <option value="paypal" <?php echo ($_POST['payment_method'] ?? '') === 'paypal' ? 'selected' : ''; ?>><?php echo __('paypal'); ?></option>
                                        <option value="other" <?php echo ($_POST['payment_method'] ?? '') === 'other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label"><?php echo __('payment_status'); ?> *</label>
                                    <select class="form-control" id="payment_status" name="payment_status" required>
                                        <option value=""><?php echo __('select_status'); ?></option>
                                        <option value="completed" <?php echo ($_POST['payment_status'] ?? '') === 'completed' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                        <option value="pending" <?php echo ($_POST['payment_status'] ?? '') === 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                        <option value="failed" <?php echo ($_POST['payment_status'] ?? '') === 'failed' ? 'selected' : ''; ?>><?php echo __('failed'); ?></option>
                                        <option value="cancelled" <?php echo ($_POST['payment_status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label"><?php echo __('payment_date'); ?> *</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="transaction_id" class="form-label"><?php echo __('transaction_id'); ?></label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           value="<?php echo htmlspecialchars($_POST['transaction_id'] ?? ''); ?>" 
                                           placeholder="e.g., TXN-2024-001">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_start" class="form-label"><?php echo __('billing_period_start'); ?></label>
                                    <input type="date" class="form-control" id="billing_period_start" name="billing_period_start" 
                                           value="<?php echo htmlspecialchars($_POST['billing_period_start'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_end" class="form-label"><?php echo __('billing_period_end'); ?></label>
                                    <input type="date" class="form-control" id="billing_period_end" name="billing_period_end" 
                                           value="<?php echo htmlspecialchars($_POST['billing_period_end'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subscription_plan" class="form-label"><?php echo __('subscription_plan'); ?></label>
                            <select class="form-control" id="subscription_plan" name="subscription_plan">
                                <option value=""><?php echo __('use_company_default'); ?></option>
                                <option value="basic" <?php echo ($_POST['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                <option value="professional" <?php echo ($_POST['subscription_plan'] ?? '') === 'professional' ? 'selected' : ''; ?>>Professional</option>
                                <option value="enterprise" <?php echo ($_POST['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes about this payment"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('add_payment'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Company Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('company_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><?php echo __('company'); ?>:</strong> <?php echo htmlspecialchars($company['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('code'); ?>:</strong> <?php echo htmlspecialchars($company['company_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('current_plan'); ?>:</strong> 
                        <span class="badge bg-primary"><?php echo ucfirst($company['subscription_plan']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('status'); ?>:</strong> 
                        <span class="badge bg-<?php echo $company['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
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
                            <li><i class="fas fa-dollar-sign me-2"></i><?php echo __('usd'); ?> (<?php echo __('us_dollar'); ?>)</li>
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
    const amount = document.getElementById('amount').value;
    const currency = document.getElementById('currency').value;
    const paymentMethod = document.getElementById('payment_method').value;
    const paymentStatus = document.getElementById('payment_status').value;
    const paymentDate = document.getElementById('payment_date').value;
    
    if (!amount || !currency || !paymentMethod || !paymentStatus || !paymentDate) {
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