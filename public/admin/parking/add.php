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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['space_name', 'space_type', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate monthly rate
        if (!is_numeric($_POST['monthly_rate']) || $_POST['monthly_rate'] <= 0) {
            throw new Exception("Monthly rate must be a positive number.");
        }

        // Check if space name already exists for this company
        $stmt = $conn->prepare("SELECT id FROM parking_spaces WHERE company_id = ? AND space_name = ?");
        $stmt->execute([$company_id, $_POST['space_name']]);
        if ($stmt->fetch()) {
            throw new Exception("Parking space name already exists for this company.");
        }

        // Generate parking space code
        $space_code = generateParkingSpaceCode($company_id);

        // Add currency column if it doesn't exist
        try {
            $conn->exec("ALTER TABLE parking_spaces ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER monthly_rate");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }

        // Start transaction
        $conn->beginTransaction();

        // Create parking space record
        $stmt = $conn->prepare("
            INSERT INTO parking_spaces (
                company_id, space_code, space_name, space_type,
                size, monthly_rate, currency, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'available', NOW())
        ");

        $stmt->execute([
            $company_id,
            $space_code,
            $_POST['space_name'],
            $_POST['space_type'],
            $_POST['size'] ?? '',
            $_POST['monthly_rate'],
            $_POST['currency'] ?? 'USD'
        ]);

        $space_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Parking space added successfully! Space Code: $space_code";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Helper function to generate parking space code
function generateParkingSpaceCode($company_id) {
    global $conn;

    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];

    // Get next parking space number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_spaces WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $next_number = $count + 1;
    return strtoupper($company_code) . 'PKS' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-parking"></i> <?php echo __('add_parking_space'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_parking_spaces'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Add Parking Space Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_space_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_name" class="form-label"><?php echo __('space_name'); ?> *</label>
                            <input type="text" class="form-control" id="space_name" name="space_name" 
                                   value="<?php echo htmlspecialchars($_POST['space_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_type" class="form-label"><?php echo __('space_type'); ?> *</label>
                            <select class="form-control" id="space_type" name="space_type" required>
                                <option value=""><?php echo __('select_space_type'); ?></option>
                                <option value="standard" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'standard') ? 'selected' : ''; ?>><?php echo __('standard'); ?></option>
                                <option value="large" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'large') ? 'selected' : ''; ?>><?php echo __('large'); ?></option>
                                <option value="covered" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'covered') ? 'selected' : ''; ?>><?php echo __('covered'); ?></option>
                                <option value="uncovered" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'uncovered') ? 'selected' : ''; ?>><?php echo __('uncovered'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label"><?php echo __('monthly_rate'); ?> *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                   value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?> *</label>
                            <select class="form-control" id="currency" name="currency" required>
                                <option value="USD" <?php echo (($_POST['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                <option value="EUR" <?php echo (($_POST['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                <option value="GBP" <?php echo (($_POST['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                                <option value="JPY" <?php echo (($_POST['currency'] ?? '') == 'JPY') ? 'selected' : ''; ?>>JPY - Japanese Yen (¥)</option>
                                <option value="CAD" <?php echo (($_POST['currency'] ?? '') == 'CAD') ? 'selected' : ''; ?>>CAD - Canadian Dollar (C$)</option>
                                <option value="AUD" <?php echo (($_POST['currency'] ?? '') == 'AUD') ? 'selected' : ''; ?>>AUD - Australian Dollar (A$)</option>
                                <option value="CHF" <?php echo (($_POST['currency'] ?? '') == 'CHF') ? 'selected' : ''; ?>>CHF - Swiss Franc (CHF)</option>
                                <option value="CNY" <?php echo (($_POST['currency'] ?? '') == 'CNY') ? 'selected' : ''; ?>>CNY - Chinese Yuan (¥)</option>
                                <option value="INR" <?php echo (($_POST['currency'] ?? '') == 'INR') ? 'selected' : ''; ?>>INR - Indian Rupee (₹)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="size" class="form-label"><?php echo __('size'); ?></label>
                            <input type="text" class="form-control" id="size" name="size" 
                                   value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>" 
                                   placeholder="e.g., 3m x 6m">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label"><?php echo __('description'); ?></label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('add_parking_space'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>