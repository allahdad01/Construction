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
        $required_fields = ['area_name', 'area_type', 'location'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Check if area name already exists for this company
        $stmt = $conn->prepare("SELECT id FROM rental_areas WHERE company_id = ? AND area_name = ?");
        $stmt->execute([$company_id, $_POST['area_name']]);
        if ($stmt->fetch()) {
            throw new Exception("Rental area with this name already exists for this company.");
        }

        // Generate area code
        $area_code = generateAreaCode($company_id);

        // Start transaction
        $conn->beginTransaction();

        // Create rental area record
        $stmt = $conn->prepare("
            INSERT INTO rental_areas (
                company_id, area_code, area_name, area_type,
                location, size, monthly_rate, capacity,
                facilities, status, description, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $area_code,
            $_POST['area_name'],
            $_POST['area_type'],
            $_POST['location'],
            $_POST['size'] ?? '',
            $_POST['monthly_rate'] ?? 0,
            $_POST['capacity'] ?? null,
            $_POST['facilities'] ?? '',
            $_POST['description'] ?? ''
        ]);

        $area_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Rental area added successfully! Area Code: $area_code";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$area_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Helper function to generate area code
function generateAreaCode($company_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM rental_areas WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return 'AREA' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-map-marked-alt"></i> <?php echo __('add_rental_area'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_areas'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('rental_area_information'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="area_name" class="form-label"><?php echo __('area_name'); ?> *</label>
                            <input type="text" class="form-control" id="area_name" name="area_name" 
                                   value="<?php echo htmlspecialchars($_POST['area_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="area_type" class="form-label"><?php echo __('area_type'); ?> *</label>
                            <select class="form-control" id="area_type" name="area_type" required>
                                <option value=""><?php echo __('select_area_type'); ?></option>
                                <option value="warehouse" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'warehouse') ? 'selected' : ''; ?>>Warehouse</option>
                                <option value="office" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'office') ? 'selected' : ''; ?>>Office Space</option>
                                <option value="retail" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'retail') ? 'selected' : ''; ?>>Retail Space</option>
                                <option value="storage" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'storage') ? 'selected' : ''; ?>>Storage Unit</option>
                                <option value="workshop" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'workshop') ? 'selected' : ''; ?>>Workshop</option>
                                <option value="yard" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'yard') ? 'selected' : ''; ?>>Yard/Outdoor</option>
                                <option value="conference" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'conference') ? 'selected' : ''; ?>>Conference Room</option>
                                <option value="other" <?php echo (isset($_POST['area_type']) && $_POST['area_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="location" class="form-label"><?php echo __('location'); ?> *</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required
                                   placeholder="e.g., Building A, Floor 2, Room 201">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="size" class="form-label"><?php echo __('size'); ?></label>
                            <input type="text" class="form-control" id="size" name="size" 
                                   value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>" 
                                   placeholder="e.g., 50 sqm, 10m x 5m">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label"><?php echo __('monthly_rate'); ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                   value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="capacity" class="form-label"><?php echo __('capacity'); ?></label>
                            <input type="number" min="1" class="form-control" id="capacity" name="capacity" 
                                   value="<?php echo htmlspecialchars($_POST['capacity'] ?? ''); ?>" 
                                   placeholder="Max number of people/items">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="facilities" class="form-label"><?php echo __('facilities'); ?></label>
                            <textarea class="form-control" id="facilities" name="facilities" rows="3" 
                                      placeholder="List available facilities (e.g., WiFi, AC, Parking, Security)"><?php echo htmlspecialchars($_POST['facilities'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo __('description'); ?></label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Additional details about the rental area"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo __('add_rental_area'); ?>
                        </button>
                        <a href="index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>