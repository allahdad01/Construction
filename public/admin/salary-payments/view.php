<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get payment ID from URL
$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    header('Location: index.php');
    exit;
}

// Get payment details with employee information
$stmt = $conn->prepare("
    SELECT sp.*, 
           e.employee_code,
           e.name as employee_name,
           e.position,
           e.monthly_salary
    FROM salary_payments sp 
    LEFT JOIN employees e ON sp.employee_id = e.id
    WHERE sp.id = ? AND sp.company_id = ?
");
$stmt->execute([$payment_id, $company_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: index.php');
    exit;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill-wave"></i> <?php echo __('salary_payment_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $payment_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_payment'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_payments'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Payment Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payment_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('payment_code'); ?>:</strong> <?php echo htmlspecialchars($payment['payment_code']); ?></p>
                            <p><strong><?php echo __('employee'); ?>:</strong> <?php echo htmlspecialchars($payment['employee_name']); ?></p>
                            <p><strong><?php echo __('employee_code'); ?>:</strong> <?php echo htmlspecialchars($payment['employee_code']); ?></p>
                            <p><strong><?php echo __('position'); ?>:</strong> <?php echo htmlspecialchars($payment['position']); ?></p>
                            <p><strong><?php echo __('monthly_salary'); ?>:</strong> <?php echo formatCurrency($payment['monthly_salary']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('payment_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></p>
                            <p><strong><?php echo __('amount_paid'); ?>:</strong> <?php echo formatCurrency($payment['amount_paid']); ?></p>
                            <p><strong><?php echo __('payment_method'); ?>:</strong> <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></p>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $payment['status'] == 'completed' ? 'success' : ($payment['status'] == 'pending' ? 'warning' : 'secondary'); ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <?php if ($payment['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong><?php echo __('notes'); ?>:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Additional Information -->
            <?php if ($payment['payment_month'] && $payment['payment_year']): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payroll_details'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('payment_period'); ?>:</strong> 
                                <?php echo date('F Y', mktime(0, 0, 0, $payment['payment_month'], 1, $payment['payment_year'])); ?>
                            </p>
                            <p><strong><?php echo __('working_days'); ?>:</strong> <?php echo $payment['working_days']; ?> days</p>
                            <p><strong><?php echo __('leave_days'); ?>:</strong> <?php echo $payment['leave_days']; ?> days</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('daily_rate'); ?>:</strong> <?php echo formatCurrency($payment['daily_rate']); ?></p>
                            <p><strong><?php echo __('total_amount'); ?>:</strong> <?php echo formatCurrency($payment['total_amount']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Payment Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payment_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('amount_paid'); ?></h6>
                        <h4 class="text-success"><?php echo formatCurrency($payment['amount_paid']); ?></h4>
                    </div>
                    
                    <?php if ($payment['total_amount']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('total_amount_due'); ?></h6>
                        <h5 class="text-info"><?php echo formatCurrency($payment['total_amount']); ?></h5>
                    </div>
                    
                    <?php 
                    $remaining = $payment['total_amount'] - $payment['amount_paid'];
                    if ($remaining > 0): 
                    ?>
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('remaining_balance'); ?></h6>
                        <h5 class="text-warning"><?php echo formatCurrency($remaining); ?></h5>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('payment_status'); ?></h6>
                        <span class="badge badge-<?php echo $payment['status'] == 'completed' ? 'success' : ($payment['status'] == 'pending' ? 'warning' : 'secondary'); ?> badge-lg">
                            <?php echo ucfirst($payment['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Payment Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('timeline'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('payment_recorded'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($payment['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('payment_date'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($payment['updated_at'] && $payment['updated_at'] != $payment['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('last_updated'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($payment['updated_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
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
                        <a href="edit.php?id=<?php echo $payment_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> <?php echo __('edit_payment'); ?>
                        </a>
                        
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $payment_id; ?>, '<?php echo htmlspecialchars($payment['payment_code']); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_payment'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.timeline-text {
    margin: 0;
    color: #6c757d;
    font-size: 0.9em;
}

.badge-lg {
    font-size: 1em;
    padding: 0.5em 1em;
}
</style>

<script>
function confirmDelete(paymentId, paymentCode) {
    const message = `Are you sure you want to delete payment "${paymentCode}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${paymentId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>