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
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get payment details
$stmt = $conn->prepare("SELECT * FROM company_payments WHERE id = ? AND company_id = ?");
$stmt->execute([$payment_id, $company_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill"></i> Payment Details
        </h1>
        <div>
            <a href="payments.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>
            <a href="payment-edit.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit"></i> Edit Payment
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Payment Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Code</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($payment['payment_code']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount</label>
                                <p class="form-control-plaintext">
                                    <span class="text-success fw-bold">
                                        <?php echo formatCurrencyAmount($payment['amount'], $payment['currency']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Currency</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($payment['currency']); ?></span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Method</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-info">
                                        <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <p class="form-control-plaintext">
                                    <span class="badge <?php 
                                        echo $payment['payment_status'] === 'completed' ? 'bg-success' : 
                                            ($payment['payment_status'] === 'pending' ? 'bg-warning' : 
                                            ($payment['payment_status'] === 'failed' ? 'bg-danger' : 'bg-secondary')); 
                                    ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Date</label>
                                <p class="form-control-plaintext"><?php echo formatDate($payment['payment_date']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Transaction ID</label>
                                <p class="form-control-plaintext">
                                    <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Subscription Plan</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary">
                                        <?php echo ucfirst($payment['subscription_plan'] ?? 'N/A'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Billing Period Start</label>
                                <p class="form-control-plaintext">
                                    <?php echo $payment['billing_period_start'] ? formatDate($payment['billing_period_start']) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Billing Period End</label>
                                <p class="form-control-plaintext">
                                    <?php echo $payment['billing_period_end'] ? formatDate($payment['billing_period_end']) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if ($payment['notes']): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Created At</label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($payment['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Updated At</label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($payment['updated_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Company Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Company Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Company:</strong> <?php echo htmlspecialchars($company['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Company ID:</strong> <?php echo $company_id; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge <?php echo $company['subscription_status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Plan:</strong> <?php echo htmlspecialchars($company['subscription_plan'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="payment-edit.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Payment
                        </a>
                        <?php if ($payment['payment_status'] === 'pending'): ?>
                            <a href="payment-approve.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-success"
                               onclick="return confirm('Are you sure you want to approve this payment?')">
                                <i class="fas fa-check"></i> Approve Payment
                            </a>
                        <?php endif; ?>
                        <a href="payment-delete.php?id=<?php echo $payment_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to delete this payment?')">
                            <i class="fas fa-trash"></i> Delete Payment
                        </a>
                        <a href="payments.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Back to Payments
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h4 class="text-primary"><?php echo ucfirst($payment['payment_status']); ?></h4>
                                <small class="text-muted">Status</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo formatCurrencyAmount($payment['amount'], $payment['currency']); ?></h4>
                            <small class="text-muted">Amount</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>