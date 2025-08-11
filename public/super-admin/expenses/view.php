<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$expense_id = (int)($_GET['id'] ?? 0);

if (!$expense_id) {
    header('Location: index.php');
    exit;
}

// Get expense details - only super admin expenses (company_id = 1)
$stmt = $conn->prepare("
    SELECT e.*, c.company_name 
    FROM expenses e 
    LEFT JOIN companies c ON e.company_id = c.id 
    WHERE e.id = ? AND e.company_id IS NULL
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: index.php');
    exit;
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye"></i> <?php echo __('view_expense'); ?>
        </h1>
        <div class="d-flex">
            <a href="edit.php?id=<?php echo $expense_id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> <?php echo __('edit_expense'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_expenses'); ?>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expense_details'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('expense_code'); ?></label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($expense['expense_code']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('category'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-info">
                                        <?php echo ucwords(str_replace('_', ' ', $expense['category'] ?? 'other')); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('description'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($expense['description']); ?></p>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('amount'); ?></label>
                                <p class="form-control-plaintext">
                                    <strong class="text-danger"><?php echo formatCurrencyAmount($expense['amount'], $expense['currency'] ?? 'USD'); ?></strong>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('currency'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($expense['currency'] ?? 'USD'); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('expense_date'); ?></label>
                                <p class="form-control-plaintext"><?php echo formatDate($expense['expense_date']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('payment_method'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary">
                                        <?php echo ucwords(str_replace('_', ' ', $expense['payment_method'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('reference_number'); ?></label>
                                <p class="form-control-plaintext">
                                    <?php echo $expense['reference_number'] ? htmlspecialchars($expense['reference_number']) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if ($expense['notes']): ?>
                    <div class="mb-3">
                            <label class="form-label fw-bold"><?php echo __('notes'); ?></label>
                        <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($expense['notes'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('created_at'); ?></label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($expense['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('updated_at'); ?></label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($expense['updated_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Expense Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expense_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><?php echo __('expense_code'); ?>:</strong> <?php echo htmlspecialchars($expense['expense_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('category'); ?>:</strong> 
                        <span class="badge bg-info">
                            <?php echo ucwords(str_replace('_', ' ', $expense['category'] ?? 'other')); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('amount'); ?>:</strong> 
                        <span class="text-danger fw-bold"><?php echo formatCurrencyAmount($expense['amount'], $expense['currency'] ?? 'USD'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('currency'); ?>:</strong> 
                        <span class="badge bg-primary"><?php echo htmlspecialchars($expense['currency'] ?? 'USD'); ?></span>
                    </div>

                    <div class="mb-3">
                        <strong><?php echo __('date'); ?>:</strong> <?php echo formatDate($expense['expense_date']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('payment_method'); ?>:</strong> 
                        <span class="badge bg-secondary">
                            <?php echo ucwords(str_replace('_', ' ', $expense['payment_method'])); ?>
                        </span>
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
                        <a href="edit.php?id=<?php echo $expense_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> <?php echo __('edit_expense'); ?>
                        </a>
                        <a href="delete.php?id=<?php echo $expense_id; ?>" class="btn btn-danger"
                           onclick="return confirm('<?php echo __('are_you_sure_you_want_to_delete_this_expense'); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_expense'); ?>
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> <?php echo __('back_to_list'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>