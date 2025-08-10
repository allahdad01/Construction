<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get payment ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$payment_id) {
    header('Location: index.php');
    exit;
}

// Fetch payment with employee for display
$stmt = $conn->prepare("\n    SELECT sp.*, e.name AS employee_name, e.employee_code\n    FROM salary_payments sp\n    LEFT JOIN employees e ON sp.employee_id = e.id\n    WHERE sp.id = ? AND sp.company_id = ?\n");
$stmt->execute([$payment_id, $company_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payment) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $conn->beginTransaction();

        $del = $conn->prepare('DELETE FROM salary_payments WHERE id = ? AND company_id = ?');
        $del->execute([$payment_id, $company_id]);
        if ($del->rowCount() === 0) {
            throw new Exception('No records were deleted.');
        }

        $conn->commit();
        $success = "Salary payment '" . ($payment['payment_code'] ?? ('#'.$payment_id)) . "' for " . ($payment['employee_name'] ?? 'employee') . " deleted successfully!";
        header('Location: index.php?success=' . urlencode($success));
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $error = $e->getMessage();
    }
}

require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-trash"></i> <?php echo __('delete_payment'); ?></h1>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> <?php echo __('back'); ?></a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger"><?php echo __('confirm_deletion'); ?></h6>
        </div>
        <div class="card-body">
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i>
                <?php echo __('this_action_cannot_be_undone'); ?>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <h6 class="mb-2"><?php echo __('payment_details'); ?></h6>
                    <p class="mb-1"><strong><?php echo __('payment_code'); ?>:</strong> <?php echo htmlspecialchars($payment['payment_code'] ?? ('#'.$payment_id)); ?></p>
                    <p class="mb-1"><strong><?php echo __('employee'); ?>:</strong> <?php echo htmlspecialchars($payment['employee_name'] ?? ''); ?> (<?php echo htmlspecialchars($payment['employee_code'] ?? ''); ?>)</p>
                    <p class="mb-1"><strong><?php echo __('payment_date'); ?>:</strong> <?php echo !empty($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : '-'; ?></p>
                </div>
                <div class="col-md-6">
                    <h6 class="mb-2"><?php echo __('amount'); ?></h6>
                    <p class="mb-1"><strong><?php echo __('amount'); ?>:</strong> <?php echo formatCurrencyAmount((float)($payment['amount_paid'] ?? 0), $payment['currency'] ?? 'USD'); ?></p>
                    <p class="mb-1"><strong><?php echo __('status'); ?>:</strong> <?php echo htmlspecialchars($payment['status'] ?? ''); ?></p>
                    <p class="mb-1"><strong><?php echo __('payment_method'); ?>:</strong> <?php echo htmlspecialchars($payment['payment_method'] ?? ''); ?></p>
                </div>
            </div>

            <form method="POST">
                <div class="text-end">
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> <?php echo __('cancel'); ?></a>
                    <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash"></i> <?php echo __('confirm_delete'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>