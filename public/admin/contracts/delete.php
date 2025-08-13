<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get contract ID from URL
$contract_id = $_GET['id'] ?? null;

if (!$contract_id) {
    header('Location: index.php');
    exit;
}

// Get contract details
$stmt = $conn->prepare("SELECT * FROM contracts WHERE id = ? AND company_id = ?");
$stmt->execute([$contract_id, $company_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Check if contract has working hours
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM working_hours WHERE contract_id = ? AND company_id = ?");
        $stmt->execute([$contract_id, $company_id]);
        $working_hours_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($working_hours_count > 0) {
            throw new Exception("Cannot delete contract with working hours. Please delete working hours first.");
        }

        // Update machine status back to available
        $stmt = $conn->prepare("UPDATE machines SET status = 'available' WHERE id = ?");
        $stmt->execute([$contract['machine_id']]);

        // Delete contract
        $stmt = $conn->prepare("DELETE FROM contracts WHERE id = ? AND company_id = ?");
        $stmt->execute([$contract_id, $company_id]);

        // Commit transaction
        $conn->commit();

        $success = "Contract deleted successfully!";
        
        // Redirect after successful deletion
        header('Location: index.php?success=' . urlencode($success));
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Include header only if not deleting
if (!isset($_POST['confirm_delete'])) {
    require_once '../../../includes/header.php';
}
?>

<?php if (!isset($_POST['confirm_delete'])): ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-trash"></i> <?php echo __('delete_contract'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_contracts'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Delete Confirmation -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger"><?php echo __('confirm_deletion'); ?></h6>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong><?php echo __('warning'); ?>:</strong> <?php echo __('delete_contract_warning'); ?>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h6><?php echo __('contract_details'); ?>:</h6>
                    <p><strong><?php echo __('contract_code'); ?>:</strong> <?php echo htmlspecialchars($contract['contract_code']); ?></p>
                    <p><strong><?php echo __('contract_type'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($contract['contract_type'])); ?></p>
                    <p><strong><?php echo __('rate_amount'); ?>:</strong> <?php echo formatCurrency($contract['rate_amount'], $contract['currency']); ?></p>
                    <p><strong><?php echo __('status'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($contract['status'])); ?></p>
                </div>
                <div class="col-md-6">
                    <h6><?php echo __('deletion_effects'); ?>:</h6>
                    <ul>
                        <li><?php echo __('contract_will_be_permanently_deleted'); ?></li>
                        <li><?php echo __('machine_will_be_marked_as_available'); ?></li>
                        <li><?php echo __('all_contract_data_will_be_lost'); ?></li>
                    </ul>
                </div>
            </div>

            <form method="POST" class="mt-4">
                <div class="text-end">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" name="confirm_delete" class="btn btn-danger">
                        <i class="fas fa-trash"></i> <?php echo __('confirm_delete'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
<?php endif; ?>