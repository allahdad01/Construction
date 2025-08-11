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
        $required_fields = ['space_name', 'space_type', 'vehicle_category', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
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

        // Add missing columns if they don't exist
        try {
            $conn->exec("ALTER TABLE parking_spaces ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER monthly_rate");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
        
        try {
            $conn->exec("ALTER TABLE parking_spaces ADD COLUMN vehicle_category VARCHAR(50) DEFAULT 'general' AFTER space_type");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
        
        try {
            $conn->exec("ALTER TABLE parking_rentals ADD COLUMN vehicle_type VARCHAR(50) AFTER machine_name");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
        
        try {
            $conn->exec("ALTER TABLE parking_rentals ADD COLUMN vehicle_registration VARCHAR(100) AFTER vehicle_type");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
        
        try {
            $conn->exec("ALTER TABLE parking_rentals ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER daily_rate");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }
        
        try {
            $conn->exec("ALTER TABLE parking_spaces ADD COLUMN description TEXT AFTER currency");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }

        // Start transaction
        $conn->beginTransaction();

        // Create parking space record (column-aware for optional description)
        $cols = [];
        try {
            $cols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM parking_spaces")->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            $cols = [];
        }
        $columns = ['company_id','space_code','space_name','space_type','vehicle_category','size','monthly_rate','currency'];
        $placeholders = array_fill(0, count($columns), '?');
        $paramsIns = [$company_id, $space_code, $_POST['space_name'], $_POST['space_type'], $_POST['vehicle_category'] ?? 'general', $_POST['size'] ?? '', $_POST['monthly_rate'], $_POST['currency'] ?? 'USD'];
        if (in_array('description', $cols, true)) { $columns[] = 'description'; $placeholders[] = '?'; $paramsIns[] = $_POST['description'] ?? ''; }
        if (in_array('status', $cols, true)) { $columns[] = 'status'; $placeholders[] = '?'; $paramsIns[] = 'available'; }
        if (in_array('created_at', $cols, true)) { $columns[] = 'created_at'; $placeholders[] = 'NOW()'; }
        $sqlIns = 'INSERT INTO parking_spaces (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $conn->prepare($sqlIns);
        $stmt->execute($paramsIns);

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
                           value="<?php echo htmlspecialchars($_POST['space_name'] ?? ''); ?>" 
                           placeholder="<?php echo __('e_g_main_parking_lot_a_construction_zone_1_etc'); ?>" 
                           style="text-transform: none; text-indent: 0; letter-spacing: normal;" 
                           autocomplete="off" spellcheck="false" required>
                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_the_name'); ?></small>
                </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_type" class="form-label"><?php echo __('space_type'); ?> *</label>
                            <select class="form-control" id="space_type" name="space_type" required>
                                <option value=""><?php echo __('select_space_type'); ?></option>
                                <option value="covered" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'covered') ? 'selected' : ''; ?>><?php echo __('covered'); ?></option>
                                <option value="uncovered" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'uncovered') ? 'selected' : ''; ?>><?php echo __('uncovered'); ?></option>
                                <option value="indoor" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'indoor') ? 'selected' : ''; ?>><?php echo __('indoor'); ?></option>
                                <option value="outdoor" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'outdoor') ? 'selected' : ''; ?>><?php echo __('outdoor'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="vehicle_category" class="form-label"><?php echo __('vehicle_category'); ?> *</label>
                            <select class="form-control" id="vehicle_category" name="vehicle_category" required>
                                <option value=""><?php echo __('select_vehicle_category'); ?></option>
                                <option value="machines" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'machines') ? 'selected' : ''; ?>><?php echo __('construction_machines'); ?></option>
                                <option value="cars" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'cars') ? 'selected' : ''; ?>><?php echo __('cars'); ?></option>
                                <option value="trucks" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'trucks') ? 'selected' : ''; ?>><?php echo __('trucks'); ?></option>
                                <option value="vans" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'vans') ? 'selected' : ''; ?>><?php echo __('vans'); ?></option>
                                <option value="motorcycles" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'motorcycles') ? 'selected' : ''; ?>><?php echo __('motorcycles'); ?></option>
                                <option value="trailers" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'trailers') ? 'selected' : ''; ?>><?php echo __('trailers'); ?></option>
                                <option value="general" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'general') ? 'selected' : ''; ?>><?php echo __('general'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="size" class="form-label">Space Size</label>
                            <select class="form-control" id="size" name="size">
                                <option value=""><?php echo __('auto_detect_from_category'); ?></option>
                                <option value="small" <?php echo (isset($_POST['size']) && $_POST['size'] == 'small') ? 'selected' : ''; ?>><?php echo __('small'); ?> (<?php echo __('cars'); ?>, <?php echo __('motorcycles'); ?>)</option>
                                <option value="medium" <?php echo (isset($_POST['size']) && $_POST['size'] == 'medium') ? 'selected' : ''; ?>><?php echo __('medium'); ?> (<?php echo __('vans'); ?>, <?php echo __('small_trucks'); ?>)</option>
                                <option value="large" <?php echo (isset($_POST['size']) && $_POST['size'] == 'large') ? 'selected' : ''; ?>><?php echo __('large'); ?> (<?php echo __('trucks'); ?>, <?php echo __('small_machines'); ?>)</option>
                                <option value="xlarge" <?php echo (isset($_POST['size']) && $_POST['size'] == 'xlarge') ? 'selected' : ''; ?>><?php echo __('extra_large'); ?> (<?php echo __('heavy_machines'); ?>)</option>
                                <option value="custom" <?php echo (isset($_POST['size']) && $_POST['size'] == 'custom') ? 'selected' : ''; ?>><?php echo __('custom_size'); ?></option>
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
                                <option value="USD" <?php echo (($_POST['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>><?php echo __('usd'); ?> - <?php echo __('us_dollar'); ?> ($)</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>><?php echo __('afn'); ?> - <?php echo __('afghan_afghani'); ?> (؋)</option>
                                <option value="EUR" <?php echo (($_POST['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>><?php echo __('eur'); ?> - <?php echo __('euro'); ?> (€)</option>
                                <option value="GBP" <?php echo (($_POST['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>><?php echo __('gbp'); ?> - <?php echo __('british_pound'); ?> (£)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="capacity" class="form-label"><?php echo __('vehicle_capacity'); ?></label>
                            <input type="number" min="1" max="10" class="form-control" id="capacity" name="capacity" 
                                   value="<?php echo htmlspecialchars($_POST['capacity'] ?? '1'); ?>" 
                                   placeholder="<?php echo __('e_g_1_2_3_etc'); ?>">
                            <small class="form-text text-muted"><?php echo __('how_many_vehicles_can_park_in_this_space'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label"><?php echo __('description_features'); ?></label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="<?php echo __('e_g_additional_features_security_cameras_charging_stations_loading_dock_etc'); ?>"
                              style="text-transform: none; text-indent: 0; letter-spacing: normal; resize: vertical;"
                              autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <small class="form-text text-muted"><?php echo __('describe_any_special_features_restrictions_or_notes_about_this_parking_space'); ?></small>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const vehicleCategorySelect = document.getElementById('vehicle_category');
    const sizeSelect = document.getElementById('size');
    const spaceNameInput = document.getElementById('space_name');
    const descriptionTextarea = document.getElementById('description');
    
    // Enable spaces in text inputs
    if (spaceNameInput) {
        // Remove any existing event listeners that might block spaces
        spaceNameInput.removeEventListener('keydown', null);
        spaceNameInput.removeEventListener('keypress', null);
        spaceNameInput.removeEventListener('keyup', null);
        
        // Add space handling
        spaceNameInput.addEventListener('keydown', function(e) {
            // Explicitly allow space key
            if (e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                e.stopPropagation();
                
                // Manually insert space
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const value = this.value;
                this.value = value.substring(0, start) + ' ' + value.substring(end);
                this.selectionStart = this.selectionEnd = start + 1;
                
                return false;
            }
        });
        
        // Ensure the field is properly configured
        spaceNameInput.setAttribute('type', 'text');
        spaceNameInput.style.textTransform = 'none';
        spaceNameInput.style.letterSpacing = 'normal';
    }
    
    // Enable spaces in textarea
    if (descriptionTextarea) {
        // Remove any existing event listeners that might block spaces
        descriptionTextarea.removeEventListener('keydown', null);
        descriptionTextarea.removeEventListener('keypress', null);
        descriptionTextarea.removeEventListener('keyup', null);
        
        // Add space handling
        descriptionTextarea.addEventListener('keydown', function(e) {
            // Explicitly allow space key
            if (e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                e.stopPropagation();
                
                // Manually insert space
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const value = this.value;
                this.value = value.substring(0, start) + ' ' + value.substring(end);
                this.selectionStart = this.selectionEnd = start + 1;
                
                return false;
            }
        });
        
        // Ensure the textarea is properly configured
        descriptionTextarea.style.textTransform = 'none';
        descriptionTextarea.style.letterSpacing = 'normal';
    }
    
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