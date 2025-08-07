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

        // Create parking space record
        $stmt = $conn->prepare("
            INSERT INTO parking_spaces (
                company_id, space_code, space_name, space_type, vehicle_category,
                size, monthly_rate, currency, description, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())
        ");

        $stmt->execute([
            $company_id,
            $space_code,
            $_POST['space_name'],
            $_POST['space_type'],
            $_POST['vehicle_category'] ?? 'general',
            $_POST['size'] ?? '',
            $_POST['monthly_rate'],
            $_POST['currency'] ?? 'USD',
            $_POST['description'] ?? ''
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
            <i class="fas fa-parking"></i> Add Parking Space
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Parking Spaces
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
            <h6 class="m-0 font-weight-bold text-primary">Parking Space Details</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                                        <div class="mb-3">
                    <label for="space_name" class="form-label">Space Name *</label>
                    <input type="text" class="form-control" id="space_name" name="space_name" 
                           value="<?php echo htmlspecialchars($_POST['space_name'] ?? ''); ?>" 
                           placeholder="e.g., Main Parking Lot A, Construction Zone 1, etc." 
                           style="text-transform: none; text-indent: 0; letter-spacing: normal;" 
                           autocomplete="off" spellcheck="false" required>
                    <small class="form-text text-muted">You can use spaces in the name (e.g., "Main Parking Lot A")</small>
                </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="space_type" class="form-label">Space Type *</label>
                            <select class="form-control" id="space_type" name="space_type" required>
                                <option value="">Select Space Type</option>
                                <option value="covered" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'covered') ? 'selected' : ''; ?>>Covered</option>
                                <option value="uncovered" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'uncovered') ? 'selected' : ''; ?>>Uncovered</option>
                                <option value="indoor" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'indoor') ? 'selected' : ''; ?>>Indoor</option>
                                <option value="outdoor" <?php echo (isset($_POST['space_type']) && $_POST['space_type'] == 'outdoor') ? 'selected' : ''; ?>>Outdoor</option>
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
                                <option value="machines" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'machines') ? 'selected' : ''; ?>>Construction Machines</option>
                                <option value="cars" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'cars') ? 'selected' : ''; ?>>Cars</option>
                                <option value="trucks" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'trucks') ? 'selected' : ''; ?>>Trucks</option>
                                <option value="vans" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'vans') ? 'selected' : ''; ?>>Vans</option>
                                <option value="motorcycles" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'motorcycles') ? 'selected' : ''; ?>>Motorcycles</option>
                                <option value="trailers" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'trailers') ? 'selected' : ''; ?>>Trailers</option>
                                <option value="general" <?php echo (isset($_POST['vehicle_category']) && $_POST['vehicle_category'] == 'general') ? 'selected' : ''; ?>>General</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="size" class="form-label">Space Size</label>
                            <select class="form-control" id="size" name="size">
                                <option value="">Auto-detect from category</option>
                                <option value="small" <?php echo (isset($_POST['size']) && $_POST['size'] == 'small') ? 'selected' : ''; ?>>Small (Cars, Motorcycles)</option>
                                <option value="medium" <?php echo (isset($_POST['size']) && $_POST['size'] == 'medium') ? 'selected' : ''; ?>>Medium (Vans, Small Trucks)</option>
                                <option value="large" <?php echo (isset($_POST['size']) && $_POST['size'] == 'large') ? 'selected' : ''; ?>>Large (Trucks, Small Machines)</option>
                                <option value="xlarge" <?php echo (isset($_POST['size']) && $_POST['size'] == 'xlarge') ? 'selected' : ''; ?>>Extra Large (Heavy Machines)</option>
                                <option value="custom" <?php echo (isset($_POST['size']) && $_POST['size'] == 'custom') ? 'selected' : ''; ?>>Custom Size</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label">Monthly Rate *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                   value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label">Currency *</label>
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
                            <label for="capacity" class="form-label">Vehicle Capacity</label>
                            <input type="number" min="1" max="10" class="form-control" id="capacity" name="capacity" 
                                   value="<?php echo htmlspecialchars($_POST['capacity'] ?? '1'); ?>" 
                                   placeholder="Number of vehicles">
                            <small class="form-text text-muted">How many vehicles can park in this space</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description & Features</label>
                    <textarea class="form-control" id="description" name="description" rows="3" 
                              placeholder="Additional features: security cameras, charging stations, loading dock, etc. You can use spaces in descriptions."
                              style="text-transform: none; text-indent: 0; letter-spacing: normal; resize: vertical;"
                              autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <small class="form-text text-muted">Describe any special features, restrictions, or notes about this parking space</small>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Parking Space
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