<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$payment_id = (int)($_GET['id'] ?? 0);
$company_id = (int)($_GET['company_id'] ?? 0);

if (!$payment_id || !$company_id) {
    header('Location: '.$base_url.'super-admin/companies/');
    exit;
}

// Get payment details
$stmt = $conn->prepare("SELECT * FROM company_payments WHERE id = ? AND company_id = ?");
$stmt->execute([$payment_id, $company_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: '.$base_url.'super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $payment_method = $_POST['payment_method'] ?? 'credit_card';
    $payment_status = $_POST['payment_status'] ?? 'pending';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $subscription_plan = $_POST['subscription_plan'] ?? '';
    $billing_period_start = $_POST['billing_period_start'] ?? '';
    $billing_period_end = $_POST['billing_period_end'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $errors = [];

    // Validation
    if ($amount <= 0) $errors[] = 'Amount must be greater than 0';
    if (empty($payment_date)) $errors[] = 'Payment date is required';
    if (!in_array($currency, ['USD', 'AFN'])) $errors[] = 'Invalid currency';

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Update payment
            $stmt = $conn->prepare("
                UPDATE company_payments SET 
                amount = ?, currency = ?, payment_method = ?, payment_status = ?, 
                payment_date = ?, transaction_id = ?, subscription_plan = ?, 
                billing_period_start = ?, billing_period_end = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $amount, $currency, $payment_method, $payment_status,
                $payment_date, $transaction_id, $subscription_plan,
                $billing_period_start ?: null, $billing_period_end ?: null, $notes, $payment_id
            ]);

            $conn->commit();

            $_SESSION['success_message'] = 'Payment updated successfully';
            header("Location: payment-view.php?id=$payment_id&company_id=$company_id");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Error updating payment: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_payment'); ?>
        </h1>
        <div>
            <a href="payment-view.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_payment'); ?>
            </a>
            <a href="payments.php?company_id=<?php echo $company_id; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-list"></i> <?php echo __('back_to_payments'); ?>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('edit_payment_information'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label"><?php echo __('amount'); ?> *</label>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency" class="form-label"><?php echo __('currency'); ?> *</label>
                                    <select class="form-control" id="currency" name="currency" required>
                                        <option value="USD" <?php echo $payment['currency'] === 'USD' ? 'selected' : ''; ?>><?php echo __('usd'); ?></option>
                                        <option value="AFN" <?php echo $payment['currency'] === 'AFN' ? 'selected' : ''; ?>><?php echo __('afn'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?></label>
                                    <select class="form-control" id="payment_method" name="payment_method">
                                        <option value="credit_card" <?php echo $payment['payment_method'] === 'credit_card' ? 'selected' : ''; ?>><?php echo __('credit_card'); ?></option>
                                        <option value="bank_transfer" <?php echo $payment['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                        <option value="paypal" <?php echo $payment['payment_method'] === 'paypal' ? 'selected' : ''; ?>><?php echo __('paypal'); ?></option>
                                        <option value="cash" <?php echo $payment['payment_method'] === 'cash' ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label"><?php echo __('status'); ?></label>
                                    <select class="form-control" id="payment_status" name="payment_status">
                                        <option value="pending" <?php echo $payment['payment_status'] === 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                        <option value="completed" <?php echo $payment['payment_status'] === 'completed' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                        <option value="failed" <?php echo $payment['payment_status'] === 'failed' ? 'selected' : ''; ?>><?php echo __('failed'); ?></option>
                                        <option value="cancelled" <?php echo $payment['payment_status'] === 'cancelled' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_date" class="form-label"><?php echo __('payment_date'); ?> *</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="transaction_id" class="form-label"><?php echo __('transaction_id'); ?></label>
                                    <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                           value="<?php echo htmlspecialchars($payment['transaction_id'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subscription_plan" class="form-label"><?php echo __('subscription_plan'); ?></label>
                                    <select class="form-control" id="subscription_plan" name="subscription_plan">
                                        <option value=""><?php echo __('select_plan'); ?></option>
                                        <option value="basic" <?php echo ($payment['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>><?php echo __('basic'); ?></option>
                                        <option value="professional" <?php echo ($payment['subscription_plan'] ?? '') === 'professional' ? 'selected' : ''; ?>><?php echo __('professional'); ?></option>
                                        <option value="enterprise" <?php echo ($payment['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>><?php echo __('enterprise'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="billing_period_start" class="form-label"><?php echo __('billing_period_start'); ?></label>
                                    <input type="date" class="form-control" id="billing_period_start" name="billing_period_start" 
                                           value="<?php echo htmlspecialchars($payment['billing_period_start'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                        <label for="billing_period_end" class="form-label"><?php echo __('billing_period_end'); ?></label>
                                    <input type="date" class="form-control" id="billing_period_end" name="billing_period_end" 
                                           value="<?php echo htmlspecialchars($payment['billing_period_end'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('payment_code'); ?></label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($payment['payment_code']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?php echo __('company'); ?></label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($company['company_name']); ?>" readonly>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="payment-view.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('update_payment'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Payment Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payment_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><?php echo __('payment_id'); ?>:</strong> <?php echo $payment_id; ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('company'); ?>:</strong> <?php echo htmlspecialchars($company['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('created'); ?>:</strong> <?php echo formatDate($payment['created_at']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('last_updated'); ?>:</strong> <?php echo formatDate($payment['updated_at']); ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="payment-view.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> <?php echo __('view_payment'); ?>
                        </a>
                        <?php if ($payment['payment_status'] === 'pending'): ?>
                            <a href="payment-approve.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-success"
                               onclick="return confirm('Are you sure you want to approve this payment?')">
                                <i class="fas fa-check"></i> <?php echo __('approve_payment'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="payment-delete.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to delete this payment?')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_payment'); ?>
                        </a>
                        <a href="payments.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-list"></i> <?php echo __('back_to_payments'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>