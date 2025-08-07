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

// Get parking space ID from URL
$space_id = $_GET['id'] ?? null;

if (!$space_id) {
    header('Location: index.php');
    exit;
}

// Get parking space details
$stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
$stmt->execute([$space_id, $company_id]);
$space = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$space) {
    header('Location: index.php');
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $conn->beginTransaction();

        // Check if parking space has rentals
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_rentals WHERE parking_space_id = ? AND company_id = ?");
        $stmt->execute([$space_id, $company_id]);
        $rentals_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($rentals_count > 0) {
            throw new Exception("Cannot delete parking space with rentals. Please delete rentals first.");
        }

        // Delete parking space
        $stmt = $conn->prepare("DELETE FROM parking_spaces WHERE id = ? AND company_id = ?");
        $stmt->execute([$space_id, $company_id]);

        // Commit transaction
        $conn->commit();

        $success = "Parking space deleted successfully!";
        
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
            <i class="fas fa-trash"></i> <?php echo __('delete_parking_space'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_parking_spaces'); ?>
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
                <strong><?php echo __('warning'); ?>:</strong> <?php echo __('delete_parking_space_warning'); ?>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <h6><?php echo __('parking_space_details'); ?>:</h6>
                    <p><strong><?php echo __('space_code'); ?>:</strong> <?php echo htmlspecialchars($space['space_code']); ?></p>
                    <p><strong><?php echo __('space_number'); ?>:</strong> <?php echo htmlspecialchars($space['space_number']); ?></p>
                    <p><strong><?php echo __('space_type'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($space['space_type'])); ?></p>
                    <p><strong><?php echo __('daily_rate'); ?>:</strong> <?php echo formatCurrency($space['daily_rate'], $space['currency']); ?></p>
                </div>
                <div class="col-md-6">
                    <h6><?php echo __('deletion_effects'); ?>:</h6>
                    <ul>
                        <li><?php echo __('parking_space_will_be_permanently_deleted'); ?></li>
                        <li><?php echo __('all_parking_space_data_will_be_lost'); ?></li>
                        <li><?php echo __('this_action_cannot_be_undone'); ?></li>
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