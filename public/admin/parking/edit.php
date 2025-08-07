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
        // Add currency column if it doesn't exist
        try {
            $conn->exec("ALTER TABLE parking_spaces ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER monthly_rate");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }

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

        // Check if space name already exists for this company (excluding current space)
        $stmt = $conn->prepare("SELECT id FROM parking_spaces WHERE company_id = ? AND space_name = ? AND id != ?");
        $stmt->execute([$company_id, $_POST['space_name'], $space_id]);
        if ($stmt->fetch()) {
            throw new Exception("Parking space name already exists for this company.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update parking space record
        $stmt = $conn->prepare("
            UPDATE parking_spaces SET
                space_name = ?, space_type = ?, vehicle_category = ?, size = ?,
                monthly_rate = ?, currency = ?, status = ?, description = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['space_name'],
            $_POST['space_type'],
            $_POST['vehicle_category'] ?? 'general',
            $_POST['size'] ?? '',
            $_POST['monthly_rate'],
            $_POST['currency'] ?? 'USD',
            $_POST['status'] ?? 'available',
            $_POST['description'] ?? '',
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
                            <label for="space_name" class="form-label">Space Name *</label>
                            <input type="text" class="form-control" id="space_name" name="space_name" 
                                   value="<?php echo htmlspecialchars($_POST['space_name'] ?? $space['space_name'] ?? ''); ?>" required>
                            <small class="form-text text-muted">You can use spaces in the name (e.g., "Main Parking Lot A")</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_type" class="form-label">Space Type *</label>
                            <select class="form-control" id="space_type" name="space_type" required>
                                <option value="">Select Space Type</option>
                                <option value="covered" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'covered') ? 'selected' : ''; ?>>🏠 Covered</option>
                                <option value="uncovered" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'uncovered') ? 'selected' : ''; ?>>🌤️ Uncovered</option>
                                <option value="indoor" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'indoor') ? 'selected' : ''; ?>>🏢 Indoor</option>
                                <option value="outdoor" <?php echo (($_POST['space_type'] ?? $space['space_type']) == 'outdoor') ? 'selected' : ''; ?>>🌳 Outdoor</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="vehicle_category" class="form-label">Vehicle Category *</label>
                            <select class="form-control" id="vehicle_category" name="vehicle_category" required>
                                <option value="">Select Vehicle Category</option>
                                <option value="machines" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'machines') ? 'selected' : ''; ?>>🏗️ Construction Machines</option>
                                <option value="cars" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'cars') ? 'selected' : ''; ?>>🚗 Cars</option>
                                <option value="trucks" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'trucks') ? 'selected' : ''; ?>>🚛 Trucks</option>
                                <option value="vans" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'vans') ? 'selected' : ''; ?>>🚐 Vans</option>
                                <option value="motorcycles" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'motorcycles') ? 'selected' : ''; ?>>🏍️ Motorcycles</option>
                                <option value="trailers" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'trailers') ? 'selected' : ''; ?>>🚛 Trailers</option>
                                <option value="general" <?php echo (($_POST['vehicle_category'] ?? $space['vehicle_category']) == 'general') ? 'selected' : ''; ?>>🅿️ General</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="size" class="form-label">Space Size</label>
                            <select class="form-control" id="size" name="size">
                                <option value="">Auto-detect from category</option>
                                <option value="small" <?php echo (($_POST['size'] ?? $space['size']) == 'small') ? 'selected' : ''; ?>>Small (Cars, Motorcycles)</option>
                                <option value="medium" <?php echo (($_POST['size'] ?? $space['size']) == 'medium') ? 'selected' : ''; ?>>Medium (Vans, Small Trucks)</option>
                                <option value="large" <?php echo (($_POST['size'] ?? $space['size']) == 'large') ? 'selected' : ''; ?>>Large (Trucks, Small Machines)</option>
                                <option value="xlarge" <?php echo (($_POST['size'] ?? $space['size']) == 'xlarge') ? 'selected' : ''; ?>>Extra Large (Heavy Machines)</option>
                                <option value="custom" <?php echo (($_POST['size'] ?? $space['size']) == 'custom') ? 'selected' : ''; ?>>Custom Size</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label">Monthly Rate *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                   value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? $space['monthly_rate'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo (($_POST['currency'] ?? $space['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                <option value="EUR" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                <option value="GBP" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                                <option value="JPY" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'JPY') ? 'selected' : ''; ?>>JPY - Japanese Yen (¥)</option>
                                <option value="CAD" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'CAD') ? 'selected' : ''; ?>>CAD - Canadian Dollar (C$)</option>
                                <option value="AUD" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'AUD') ? 'selected' : ''; ?>>AUD - Australian Dollar (A$)</option>
                                <option value="CHF" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'CHF') ? 'selected' : ''; ?>>CHF - Swiss Franc (CHF)</option>
                                <option value="CNY" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'CNY') ? 'selected' : ''; ?>>CNY - Chinese Yuan (¥)</option>
                                <option value="INR" <?php echo (($_POST['currency'] ?? $space['currency'] ?? '') == 'INR') ? 'selected' : ''; ?>>INR - Indian Rupee (₹)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="available" <?php echo (($_POST['status'] ?? $space['status']) == 'available') ? 'selected' : ''; ?>>✅ Available</option>
                                <option value="occupied" <?php echo (($_POST['status'] ?? $space['status']) == 'occupied') ? 'selected' : ''; ?>>🚗 Occupied</option>
                                <option value="maintenance" <?php echo (($_POST['status'] ?? $space['status']) == 'maintenance') ? 'selected' : ''; ?>>🔧 Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description & Features</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="Additional features: security cameras, charging stations, loading dock, etc. You can use spaces in descriptions."><?php echo htmlspecialchars($_POST['description'] ?? $space['description'] ?? ''); ?></textarea>
                    <small class="form-text text-muted">Describe any special features, restrictions, or notes about this parking space</small>
                </div>



                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Parking Space
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const vehicleCategorySelect = document.getElementById('vehicle_category');
    const sizeSelect = document.getElementById('size');
    
    vehicleCategorySelect.addEventListener('change', function() {
        const category = this.value;
        
        // Auto-suggest size based on vehicle category
        switch(category) {
            case 'motorcycles':
                sizeSelect.value = 'small';
                break;
            case 'cars':
                sizeSelect.value = 'small';
                break;
            case 'vans':
                sizeSelect.value = 'medium';
                break;
            case 'trucks':
                sizeSelect.value = 'large';
                break;
            case 'machines':
                sizeSelect.value = 'xlarge';
                break;
            case 'trailers':
                sizeSelect.value = 'large';
                break;
            default:
                sizeSelect.value = 'medium';
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>