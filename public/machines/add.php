<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['machine_name', 'machine_type', 'model', 'year', 'current_value'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate current value
        if (!is_numeric($_POST['current_value']) || $_POST['current_value'] <= 0) {
            throw new Exception("Current value must be a positive number.");
        }

        // Generate machine code
        $machine_code = generateMachineCode($company_id);

        // Start transaction
        $conn->beginTransaction();

        // Create machine record
        $stmt = $conn->prepare("
            INSERT INTO machines (
                company_id, machine_code, machine_name, machine_type, model, 
                year, manufacturer, serial_number, current_value, 
                purchase_date, status, location, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', ?, ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $machine_code,
            $_POST['machine_name'],
            $_POST['machine_type'],
            $_POST['model'],
            $_POST['year'],
            $_POST['manufacturer'] ?? '',
            $_POST['serial_number'] ?? '',
            $_POST['current_value'],
            $_POST['purchase_date'] ?? null,
            $_POST['location'] ?? '',
            $_POST['notes'] ?? ''
        ]);

        $machine_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Machine added successfully! Machine Code: $machine_code";
        
        // Redirect to view page after 2 seconds
        header("refresh:2;url=view.php?id=$machine_id");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate machine code
function generateMachineCode($company_id) {
    global $conn;
    
    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];
    
    // Get next machine number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM machines WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $next_number = $count + 1;
    return strtoupper($company_code) . 'MCH' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-truck"></i> Add Machine
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Machines
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Add Machine Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Machine Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="machineForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="machine_name" class="form-label">Machine Name *</label>
                                    <input type="text" class="form-control" id="machine_name" name="machine_name" 
                                           value="<?php echo htmlspecialchars($_POST['machine_name'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="machine_type" class="form-label">Machine Type *</label>
                                    <select class="form-control" id="machine_type" name="machine_type" required>
                                        <option value="">Select Type</option>
                                        <option value="excavator" <?php echo ($_POST['machine_type'] ?? '') === 'excavator' ? 'selected' : ''; ?>>Excavator</option>
                                        <option value="bulldozer" <?php echo ($_POST['machine_type'] ?? '') === 'bulldozer' ? 'selected' : ''; ?>>Bulldozer</option>
                                        <option value="loader" <?php echo ($_POST['machine_type'] ?? '') === 'loader' ? 'selected' : ''; ?>>Loader</option>
                                        <option value="crane" <?php echo ($_POST['machine_type'] ?? '') === 'crane' ? 'selected' : ''; ?>>Crane</option>
                                        <option value="dump_truck" <?php echo ($_POST['machine_type'] ?? '') === 'dump_truck' ? 'selected' : ''; ?>>Dump Truck</option>
                                        <option value="concrete_mixer" <?php echo ($_POST['machine_type'] ?? '') === 'concrete_mixer' ? 'selected' : ''; ?>>Concrete Mixer</option>
                                        <option value="compactor" <?php echo ($_POST['machine_type'] ?? '') === 'compactor' ? 'selected' : ''; ?>>Compactor</option>
                                        <option value="other" <?php echo ($_POST['machine_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" 
                                           value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="year" class="form-label">Year *</label>
                                    <input type="number" class="form-control" id="year" name="year" 
                                           value="<?php echo htmlspecialchars($_POST['year'] ?? ''); ?>" 
                                           min="1900" max="<?php echo date('Y') + 1; ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="manufacturer" class="form-label">Manufacturer</label>
                                    <input type="text" class="form-control" id="manufacturer" name="manufacturer" 
                                           value="<?php echo htmlspecialchars($_POST['manufacturer'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="serial_number" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number" 
                                           value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="current_value" class="form-label">Current Value *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="current_value" name="current_value" 
                                               value="<?php echo htmlspecialchars($_POST['current_value'] ?? ''); ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">Purchase Date</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                           value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                           placeholder="e.g., Site A, Warehouse B">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Machine Code</label>
                                    <input type="text" class="form-control" id="machine_code_preview" readonly>
                                    <div class="form-text">Will be automatically generated</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Machine
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="fas fa-info-circle text-info"></i> Machine Code</h6>
                        <p class="text-muted">A unique machine code will be automatically generated.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-truck text-primary"></i> Machine Types</h6>
                        <ul class="text-muted">
                            <li><strong>Excavator:</strong> For digging and earth moving</li>
                            <li><strong>Bulldozer:</strong> For pushing and leveling</li>
                            <li><strong>Loader:</strong> For loading materials</li>
                            <li><strong>Crane:</strong> For lifting heavy objects</li>
                            <li><strong>Dump Truck:</strong> For transporting materials</li>
                            <li><strong>Concrete Mixer:</strong> For mixing concrete</li>
                            <li><strong>Compactor:</strong> For compacting soil</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-dollar-sign text-success"></i> Value Tracking</h6>
                        <p class="text-muted">Track the current market value of your machines for insurance and accounting purposes.</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-list me-2"></i>View All Machines
                        </a>
                        <a href="../contracts/" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-contract me-2"></i>Manage Contracts
                        </a>
                        <a href="../employees/" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i>Manage Employees
                        </a>
                        <a href="../reports/" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('machineForm');
    const machineNameInput = document.getElementById('machine_name');
    const machineTypeInput = document.getElementById('machine_type');
    const machineCodePreview = document.getElementById('machine_code_preview');

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Add error message if not exists
                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = 'This field is required.';
                    field.parentNode.appendChild(errorDiv);
                }
            } else {
                field.classList.remove('is-invalid');
                const errorDiv = field.parentNode.querySelector('.invalid-feedback');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });

        // Value validation
        const currentValue = parseFloat(document.getElementById('current_value').value);
        if (document.getElementById('current_value').value && (isNaN(currentValue) || currentValue <= 0)) {
            isValid = false;
            document.getElementById('current_value').classList.add('is-invalid');
            
            if (!document.getElementById('current_value').nextElementSibling || !document.getElementById('current_value').nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Value must be a positive number.';
                document.getElementById('current_value').parentNode.appendChild(errorDiv);
            }
        }

        // Year validation
        const year = parseInt(document.getElementById('year').value);
        const currentYear = new Date().getFullYear();
        if (document.getElementById('year').value && (year < 1900 || year > currentYear + 1)) {
            isValid = false;
            document.getElementById('year').classList.add('is-invalid');
            
            if (!document.getElementById('year').nextElementSibling || !document.getElementById('year').nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = `Year must be between 1900 and ${currentYear + 1}.`;
                document.getElementById('year').parentNode.appendChild(errorDiv);
            }
        }

        if (!isValid) {
            e.preventDefault();
            showNotification('Please fix the errors in the form.', 'error');
        }
    });

    // Real-time validation
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });

        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });

    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let errorMessage = '';

        // Remove existing error styling
        field.classList.remove('is-invalid');
        const existingError = field.parentNode.querySelector('.invalid-feedback');
        if (existingError) {
            existingError.remove();
        }

        // Required field validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required.';
        }

        // Value validation
        if (field.name === 'current_value' && value) {
            const val = parseFloat(value);
            if (isNaN(val) || val <= 0) {
                isValid = false;
                errorMessage = 'Value must be a positive number.';
            }
        }

        // Year validation
        if (field.name === 'year' && value) {
            const year = parseInt(value);
            const currentYear = new Date().getFullYear();
            if (year < 1900 || year > currentYear + 1) {
                isValid = false;
                errorMessage = `Year must be between 1900 and ${currentYear + 1}.`;
            }
        }

        // Apply validation result
        if (!isValid) {
            field.classList.add('is-invalid');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            errorDiv.textContent = errorMessage;
            field.parentNode.appendChild(errorDiv);
        }
    }

    // Auto-generate machine code preview
    function updateMachineCodePreview() {
        const machineName = machineNameInput.value.trim();
        const machineType = machineTypeInput.value;
        
        if (machineName && machineType) {
            // This would be the actual generation logic
            const preview = 'MCH' + machineName.substring(0, 3).toUpperCase() + machineType.substring(0, 3).toUpperCase() + '001';
            machineCodePreview.value = preview;
        }
    }

    machineNameInput.addEventListener('input', updateMachineCodePreview);
    machineTypeInput.addEventListener('change', updateMachineCodePreview);
});
</script>

<?php require_once '../../includes/footer.php'; ?>