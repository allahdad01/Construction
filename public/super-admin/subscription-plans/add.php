<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole(['super_admin']);

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['plan_name', 'description', 'monthly_price', 'max_employees', 'max_machines', 'max_contracts'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate numeric fields
        $numeric_fields = ['monthly_price', 'max_employees', 'max_machines', 'max_contracts'];
        foreach ($numeric_fields as $field) {
            if (!is_numeric($_POST[$field]) || $_POST[$field] <= 0) {
                throw new Exception("Field '$field' must be a positive number.");
            }
        }

        // Start transaction
        $conn->beginTransaction();

        // Create subscription plan
        $stmt = $conn->prepare("
            INSERT INTO subscription_plans (
                plan_name, description, monthly_price, max_employees, 
                max_machines, max_contracts, features, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");

        $features = json_encode([
            'reports' => isset($_POST['feature_reports']),
            'export' => isset($_POST['feature_export']),
            'api_access' => isset($_POST['feature_api']),
            'customization' => isset($_POST['feature_customization']),
            'support' => isset($_POST['feature_support'])
        ]);

        $stmt->execute([
            $_POST['plan_name'],
            $_POST['description'],
            $_POST['monthly_price'],
            $_POST['max_employees'],
            $_POST['max_machines'],
            $_POST['max_contracts'],
            $features
        ]);

        $plan_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Subscription plan created successfully!";
        
        // Redirect to view page after 2 seconds
        header("refresh:2;url=view.php?id=$plan_id");
        
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
            <i class="fas fa-plus"></i> Add Subscription Plan
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Plans
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

    <!-- Add Plan Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Plan Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="planForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_name" class="form-label">Plan Name *</label>
                                    <input type="text" class="form-control" id="plan_name" name="plan_name" 
                                           value="<?php echo htmlspecialchars($_POST['plan_name'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_price" class="form-label">Monthly Price *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="monthly_price" name="monthly_price" 
                                               value="<?php echo htmlspecialchars($_POST['monthly_price'] ?? ''); ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_employees" class="form-label">Max Employees *</label>
                                    <input type="number" class="form-control" id="max_employees" name="max_employees" 
                                           value="<?php echo htmlspecialchars($_POST['max_employees'] ?? ''); ?>" 
                                           min="1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_machines" class="form-label">Max Machines *</label>
                                    <input type="number" class="form-control" id="max_machines" name="max_machines" 
                                           value="<?php echo htmlspecialchars($_POST['max_machines'] ?? ''); ?>" 
                                           min="1" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_contracts" class="form-label">Max Contracts *</label>
                                    <input type="number" class="form-control" id="max_contracts" name="max_contracts" 
                                           value="<?php echo htmlspecialchars($_POST['max_contracts'] ?? ''); ?>" 
                                           min="1" required>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3">Plan Features</h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="feature_reports" name="feature_reports" 
                                           <?php echo isset($_POST['feature_reports']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_reports">
                                        <i class="fas fa-chart-bar text-info"></i> Advanced Reports
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="feature_export" name="feature_export" 
                                           <?php echo isset($_POST['feature_export']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_export">
                                        <i class="fas fa-download text-success"></i> Data Export
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="feature_api" name="feature_api" 
                                           <?php echo isset($_POST['feature_api']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_api">
                                        <i class="fas fa-code text-warning"></i> API Access
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="feature_customization" name="feature_customization" 
                                           <?php echo isset($_POST['feature_customization']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_customization">
                                        <i class="fas fa-palette text-primary"></i> Customization
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="feature_support" name="feature_support" 
                                           <?php echo isset($_POST['feature_support']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="feature_support">
                                        <i class="fas fa-headset text-danger"></i> Priority Support
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Plan
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
                        <h6><i class="fas fa-info-circle text-info"></i> Plan Creation</h6>
                        <p class="text-muted">Create subscription plans that companies can choose from.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-users text-primary"></i> Limits</h6>
                        <p class="text-muted">Set maximum limits for employees, machines, and contracts per company.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-star text-warning"></i> Features</h6>
                        <p class="text-muted">Enable or disable specific features for each plan level.</p>
                    </div>
                </div>
            </div>

            <!-- Pricing Guide -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pricing Guide</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-success">Basic Plan</h6>
                        <ul class="text-muted small">
                            <li>5-10 employees</li>
                            <li>5-10 machines</li>
                            <li>Basic reports</li>
                            <li>$29-99/month</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary">Professional Plan</h6>
                        <ul class="text-muted small">
                            <li>25-50 employees</li>
                            <li>25-50 machines</li>
                            <li>Advanced reports</li>
                            <li>$199-399/month</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-warning">Enterprise Plan</h6>
                        <ul class="text-muted small">
                            <li>Unlimited employees</li>
                            <li>Unlimited machines</li>
                            <li>All features</li>
                            <li>$599+/month</li>
                        </ul>
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
                            <i class="fas fa-list me-2"></i>View All Plans
                        </a>
                        <a href="../companies/" class="list-group-item list-group-item-action">
                            <i class="fas fa-building me-2"></i>Manage Companies
                        </a>
                        <a href="../reports/" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                        <a href="../settings/" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i>Platform Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('planForm');

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
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

        // Numeric validation
        const numericFields = ['monthly_price', 'max_employees', 'max_machines', 'max_contracts'];
        numericFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field.value) {
                const value = parseFloat(field.value);
                if (isNaN(value) || value <= 0) {
                    isValid = false;
                    field.classList.add('is-invalid');
                    
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Must be a positive number.';
                        field.parentNode.appendChild(errorDiv);
                    }
                }
            }
        });

        if (!isValid) {
            e.preventDefault();
            showNotification('Please fix the errors in the form.', 'error');
        }
    });

    // Real-time validation
    const inputs = form.querySelectorAll('input, textarea');
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

        // Numeric validation
        const numericFields = ['monthly_price', 'max_employees', 'max_machines', 'max_contracts'];
        if (numericFields.includes(field.name) && value) {
            const val = parseFloat(value);
            if (isNaN(val) || val <= 0) {
                isValid = false;
                errorMessage = 'Must be a positive number.';
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
});
</script>

<?php require_once '../../includes/footer.php'; ?>