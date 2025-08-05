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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['space_number', 'space_type', 'daily_rate'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate daily rate
        if (!is_numeric($_POST['daily_rate']) || $_POST['daily_rate'] <= 0) {
            throw new Exception("Daily rate must be a positive number.");
        }

        // Check if space number already exists for this company (excluding current space)
        $stmt = $conn->prepare("SELECT id FROM parking_spaces WHERE company_id = ? AND space_number = ? AND id != ?");
        $stmt->execute([$company_id, $_POST['space_number'], $space_id]);
        if ($stmt->fetch()) {
            throw new Exception("Parking space number already exists for this company.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update parking space record
        $stmt = $conn->prepare("
            UPDATE parking_spaces SET
                space_number = ?, space_type = ?, daily_rate = ?,
                currency = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['space_number'],
            $_POST['space_type'],
            $_POST['daily_rate'],
            $_POST['currency'] ?? 'USD',
            $_POST['description'] ?? '',
            $_POST['status'] ?? 'available',
            $space_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Parking space updated successfully!";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$space_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_parking_space'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $space_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_parking_space'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_parking_spaces'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Parking Space Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_space_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_number" class="form-label"><?php echo __('space_number'); ?> *</label>
                            <input type="text" class="form-control" id="space_number" name="space_number" 
                                   value="<?php echo htmlspecialchars($_POST['space_number'] ?? $space['space_number']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_type" class="form-label"><?php echo __('space_type'); ?> *</label>
                            <select class="form-control" id="space_type" name="space_type" required>
                                <option value=""><?php echo __('select_space_type'); ?></option>
                                <option value="standard" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'standard') ? 'selected' : ''; ?>><?php echo __('standard'); ?></option>
                                <option value="large" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'large') ? 'selected' : ''; ?>><?php echo __('large'); ?></option>
                                <option value="covered" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'covered') ? 'selected' : ''; ?>><?php echo __('covered'); ?></option>
                                <option value="uncovered" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'uncovered') ? 'selected' : ''; ?>><?php echo __('uncovered'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="daily_rate" class="form-label"><?php echo __('daily_rate'); ?> *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="daily_rate" name="daily_rate" 
                                   value="<?php echo htmlspecialchars($_POST['daily_rate'] ?? $space['daily_rate']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?></label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo (($_POST['currency'] ?? $space['currency']) == 'USD') ? 'selected' : ''; ?>>USD</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? $space['currency']) == 'AFN') ? 'selected' : ''; ?>>AFN</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="available" <?php echo (($_POST['status'] ?? $space['status']) == 'available') ? 'selected' : ''; ?>><?php echo __('available'); ?></option>
                                <option value="in_use" <?php echo (($_POST['status'] ?? $space['status']) == 'in_use') ? 'selected' : ''; ?>><?php echo __('in_use'); ?></option>
                                <option value="maintenance" <?php echo (($_POST['status'] ?? $space['status']) == 'maintenance') ? 'selected' : ''; ?>><?php echo __('maintenance'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label"><?php echo __('description'); ?></label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $space['description']); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_parking_space'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>