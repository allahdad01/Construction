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

// Get machine ID from URL
$machine_id = $_GET['id'] ?? null;

if (!$machine_id) {
    header('Location: index.php');
    exit;
}

// Get machine details
$stmt = $conn->prepare("
    SELECT * FROM machines 
    WHERE id = ? AND company_id = ?
");
$stmt->execute([$machine_id, $company_id]);
$machine = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$machine) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['name', 'type'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate year if provided
        if (!empty($_POST['year_manufactured'])) {
            $year = intval($_POST['year_manufactured']);
            if ($year < 1900 || $year > date('Y') + 1) {
                throw new Exception("Invalid year manufactured.");
            }
        }

        // Validate purchase cost if provided
        if (!empty($_POST['purchase_cost'])) {
            $cost = floatval($_POST['purchase_cost']);
            if ($cost < 0) {
                throw new Exception("Purchase cost cannot be negative.");
            }
        }

        // Validate purchase date if provided
        if (!empty($_POST['purchase_date']) && !strtotime($_POST['purchase_date'])) {
            throw new Exception("Invalid purchase date format.");
        }

        // Add currency column if it doesn't exist (outside transaction)
        try {
            $conn->exec("ALTER TABLE machines ADD COLUMN purchase_currency VARCHAR(3) DEFAULT 'USD' AFTER purchase_cost");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }

        // Start transaction
        $conn->beginTransaction();

        // Update machine record
        $stmt = $conn->prepare("
            UPDATE machines SET
                name = ?, type = ?, model = ?, year_manufactured = ?, capacity = ?, 
                fuel_type = ?, status = ?, purchase_date = ?, purchase_cost = ?, 
                purchase_currency = ?, is_active = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['name'],
            $_POST['type'],
            $_POST['model'] ?: null,
            $_POST['year_manufactured'] ?: null,
            $_POST['capacity'] ?: null,
            $_POST['fuel_type'] ?: null,
            $_POST['status'],
            $_POST['purchase_date'] ?: null,
            $_POST['purchase_cost'] ?: null,
            $_POST['purchase_currency'] ?: 'USD',
            isset($_POST['is_active']) ? 1 : 0,
            $machine_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Machine updated successfully!";

        // Refresh machine data
        $stmt = $conn->prepare("
            SELECT * FROM machines 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$machine_id, $company_id]);
        $machine = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$machine_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_machine'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $machine_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_machine'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_machines'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Machine Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo __('edit_machine_details'); ?> - <?php echo htmlspecialchars($machine['machine_code']); ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label"><?php echo __('machine_name'); ?> *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($machine['name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="type" class="form-label"><?php echo __('machine_type'); ?> *</label>
                            <select class="form-control" id="type" name="type" required>
                                <option value="">Select machine type...</option>
                                <option value="excavator" <?php echo ($machine['type'] == 'excavator') ? 'selected' : ''; ?>>Excavator</option>
                                <option value="bulldozer" <?php echo ($machine['type'] == 'bulldozer') ? 'selected' : ''; ?>>Bulldozer</option>
                                <option value="crane" <?php echo ($machine['type'] == 'crane') ? 'selected' : ''; ?>>Crane</option>
                                <option value="loader" <?php echo ($machine['type'] == 'loader') ? 'selected' : ''; ?>>Loader</option>
                                <option value="truck" <?php echo ($machine['type'] == 'truck') ? 'selected' : ''; ?>>Truck</option>
                                <option value="compactor" <?php echo ($machine['type'] == 'compactor') ? 'selected' : ''; ?>>Compactor</option>
                                <option value="mixer" <?php echo ($machine['type'] == 'mixer') ? 'selected' : ''; ?>>Concrete Mixer</option>
                                <option value="generator" <?php echo ($machine['type'] == 'generator') ? 'selected' : ''; ?>>Generator</option>
                                <option value="other" <?php echo ($machine['type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="model" class="form-label"><?php echo __('model'); ?></label>
                            <input type="text" class="form-control" id="model" name="model" 
                                   value="<?php echo htmlspecialchars($machine['model'] ?? ''); ?>"
                                   placeholder="e.g., CAT 320D, Komatsu PC200">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="year_manufactured" class="form-label"><?php echo __('year_manufactured'); ?></label>
                            <input type="number" class="form-control" id="year_manufactured" name="year_manufactured" 
                                   value="<?php echo htmlspecialchars($machine['year_manufactured'] ?? ''); ?>"
                                   min="1900" max="<?php echo date('Y') + 1; ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="capacity" class="form-label"><?php echo __('capacity'); ?></label>
                            <input type="text" class="form-control" id="capacity" name="capacity" 
                                   value="<?php echo htmlspecialchars($machine['capacity'] ?? ''); ?>"
                                   placeholder="e.g., 20 tons, 5000L, 150 HP">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fuel_type" class="form-label"><?php echo __('fuel_type'); ?></label>
                            <select class="form-control" id="fuel_type" name="fuel_type">
                                <option value="">Select fuel type...</option>
                                <option value="diesel" <?php echo ($machine['fuel_type'] == 'diesel') ? 'selected' : ''; ?>>Diesel</option>
                                <option value="petrol" <?php echo ($machine['fuel_type'] == 'petrol') ? 'selected' : ''; ?>>Petrol</option>
                                <option value="electric" <?php echo ($machine['fuel_type'] == 'electric') ? 'selected' : ''; ?>>Electric</option>
                                <option value="hybrid" <?php echo ($machine['fuel_type'] == 'hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                <option value="other" <?php echo ($machine['fuel_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="available" <?php echo ($machine['status'] == 'available') ? 'selected' : ''; ?>><?php echo __('available'); ?></option>
                                <option value="in_use" <?php echo ($machine['status'] == 'in_use') ? 'selected' : ''; ?>><?php echo __('in_use'); ?></option>
                                <option value="maintenance" <?php echo ($machine['status'] == 'maintenance') ? 'selected' : ''; ?>><?php echo __('maintenance'); ?></option>
                                <option value="out_of_service" <?php echo ($machine['status'] == 'out_of_service') ? 'selected' : ''; ?>><?php echo __('out_of_service'); ?></option>
                                <option value="retired" <?php echo ($machine['status'] == 'retired') ? 'selected' : ''; ?>><?php echo __('retired'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="purchase_date" class="form-label"><?php echo __('purchase_date'); ?></label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                   value="<?php echo htmlspecialchars($machine['purchase_date'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="purchase_cost" class="form-label"><?php echo __('purchase_cost'); ?></label>
                            <input type="number" step="0.01" class="form-control" id="purchase_cost" name="purchase_cost" 
                                   value="<?php echo htmlspecialchars($machine['purchase_cost'] ?? ''); ?>"
                                   min="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="purchase_currency" class="form-label"><?php echo __('currency'); ?></label>
                            <select class="form-control" id="purchase_currency" name="purchase_currency">
                                <option value="USD" <?php echo (($machine['purchase_currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD</option>
                                <option value="AFN" <?php echo (($machine['purchase_currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>>AFN</option>
                                <option value="EUR" <?php echo (($machine['purchase_currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                                <option value="GBP" <?php echo (($machine['purchase_currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>>GBP</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo $machine['is_active'] ? 'checked' : ''; ?>>
                                <label for="is_active" class="form-check-label"><?php echo __('is_active'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-info"><?php echo __('current_information'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo __('machine_code'); ?>:</strong> <?php echo htmlspecialchars($machine['machine_code']); ?></p>
                                <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($machine['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('last_updated'); ?>:</strong> 
                                    <?php echo $machine['updated_at'] ? formatDateTime($machine['updated_at']) : __('never'); ?>
                                </p>
                                <p><strong><?php echo __('current_status'); ?>:</strong> 
                                    <span class="badge badge-<?php echo $machine['status'] === 'available' ? 'success' : ($machine['status'] === 'in_use' ? 'info' : ($machine['status'] === 'maintenance' ? 'warning' : 'secondary')); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $machine['status'])); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="view.php?id=<?php echo $machine_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_machine'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-format purchase cost as user types
document.getElementById('purchase_cost').addEventListener('input', function() {
    let value = this.value;
    // Remove any non-numeric characters except decimal point
    value = value.replace(/[^0-9.]/g, '');
    
    // Ensure only one decimal point
    let parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }
    
    this.value = value;
});

// Show/hide capacity examples based on machine type
document.getElementById('type').addEventListener('change', function() {
    const capacityField = document.getElementById('capacity');
    const type = this.value;
    
    let placeholder = '';
    switch(type) {
        case 'excavator':
            placeholder = 'e.g., 20 tons operating weight';
            break;
        case 'bulldozer':
            placeholder = 'e.g., 150 HP, blade width 3.2m';
            break;
        case 'crane':
            placeholder = 'e.g., 50 tons lifting capacity';
            break;
        case 'loader':
            placeholder = 'e.g., 3 cubic meter bucket';
            break;
        case 'truck':
            placeholder = 'e.g., 10 tons payload';
            break;
        case 'mixer':
            placeholder = 'e.g., 8 cubic meter drum';
            break;
        case 'generator':
            placeholder = 'e.g., 100 kVA';
            break;
        default:
            placeholder = 'e.g., capacity specifications';
    }
    
    capacityField.placeholder = placeholder;
});

// Trigger change event on page load
document.getElementById('type').dispatchEvent(new Event('change'));
</script>

<?php require_once '../../../includes/footer.php'; ?>