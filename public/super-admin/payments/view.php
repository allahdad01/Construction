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

// Get payment details with company information
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
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye"></i> View Payment
        </h1>
        <div class="d-flex">
            <a href="edit.php?id=<?php echo $payment_id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> Edit Payment
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Payments
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Code</label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($payment['payment_code']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Company</label>
                                <p class="form-control-plaintext">
                                    <?php echo htmlspecialchars($payment['company_name']); ?>
                                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($payment['company_code']); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Amount</label>
                                <p class="form-control-plaintext">
                                    <strong class="text-success"><?php echo formatCurrency($payment['amount']); ?></strong>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Currency</label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($payment['currency']); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
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
                                <label class="form-label fw-bold">Payment Status</label>
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
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Payment Date</label>
                                <p class="form-control-plaintext"><?php echo formatDate($payment['payment_date']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Transaction ID</label>
                                <p class="form-control-plaintext">
                                    <?php echo $payment['transaction_id'] ? htmlspecialchars($payment['transaction_id']) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if ($payment['billing_period_start'] || $payment['billing_period_end']): ?>
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
                    <?php endif; ?>

                    <?php if ($payment['subscription_plan']): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subscription Plan</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-primary"><?php echo ucfirst($payment['subscription_plan']); ?></span>
                        </p>
                    </div>
                    <?php endif; ?>

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
                        <strong>Company:</strong> <?php echo htmlspecialchars($payment['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Code:</strong> <?php echo htmlspecialchars($payment['company_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Payment Amount:</strong> 
                        <span class="text-success fw-bold"><?php echo formatCurrency($payment['amount']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Currency:</strong> 
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($payment['currency']); ?></span>
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
                        <a href="edit.php?id=<?php echo $payment_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Edit Payment
                        </a>
                        <?php if ($payment['payment_status'] === 'pending'): ?>
                        <a href="approve.php?id=<?php echo $payment_id; ?>" class="btn btn-success"
                           onclick="return confirm('Are you sure you want to approve this payment?')">
                            <i class="fas fa-check"></i> Approve Payment
                        </a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>