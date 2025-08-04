<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$plan_id = (int)($_GET['id'] ?? 0);

if (!$plan_id) {
    header('Location: index.php');
    exit;
}

// Get pricing plan details
$stmt = $conn->prepare("SELECT * FROM pricing_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: index.php');
    exit;
}

$features = json_decode($plan['features'], true) ?: [];
$features_text = implode("\n", $features);

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['plan_name', 'plan_code', 'price', 'billing_cycle'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate price
        if (!is_numeric($_POST['price']) || $_POST['price'] <= 0) {
            throw new Exception("Price must be a positive number.");
        }

        // Validate plan code uniqueness (excluding current plan)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pricing_plans WHERE plan_code = ? AND id != ?");
        $stmt->execute([$_POST['plan_code'], $plan_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
            throw new Exception("Plan code already exists. Please choose a different one.");
        }

        // Process features
        $features = [];
        if (!empty($_POST['features'])) {
            $features = array_filter(array_map('trim', explode("\n", $_POST['features'])));
        }

        // Start transaction
        $conn->beginTransaction();

        // Update pricing plan
        $stmt = $conn->prepare("
            UPDATE pricing_plans SET 
                plan_name = ?, plan_code = ?, description = ?, price = ?, currency = ?, 
                billing_cycle = ?, is_popular = ?, is_active = ?, max_employees = ?, 
                max_machines = ?, max_projects = ?, features = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['plan_name'],
            strtoupper($_POST['plan_code']),
            $_POST['description'] ?: null,
            $_POST['price'],
            $_POST['currency'] ?: 'USD',
            $_POST['billing_cycle'],
            isset($_POST['is_popular']) ? 1 : 0,
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['max_employees'] ?: 0,
            $_POST['max_machines'] ?: 0,
            $_POST['max_projects'] ?: 0,
            json_encode($features),
            $plan_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Pricing plan updated successfully!";

        // Redirect to plan view
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$plan_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> Edit Pricing Plan
        </h1>
        <a href="view.php?id=<?php echo $plan_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Plan
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

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Pricing Plan Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="pricingForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_name" class="form-label">Plan Name *</label>
                                    <input type="text" class="form-control" id="plan_name" name="plan_name" 
                                           value="<?php echo htmlspecialchars($_POST['plan_name'] ?? $plan['plan_name']); ?>" 
                                           placeholder="e.g., Basic, Professional, Enterprise" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_code" class="form-label">Plan Code *</label>
                                    <input type="text" class="form-control" id="plan_code" name="plan_code" 
                                           value="<?php echo htmlspecialchars($_POST['plan_code'] ?? $plan['plan_code']); ?>" 
                                           placeholder="e.g., BASIC, PRO, ENTERPRISE" required>
                                    <small class="text-muted">Unique identifier for the plan</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Brief description of the plan"><?php echo htmlspecialchars($_POST['description'] ?? $plan['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? $plan['price']); ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Currency</label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="USD" <?php echo ($_POST['currency'] ?? $plan['currency']) === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                        <option value="AFN" <?php echo ($_POST['currency'] ?? $plan['currency']) === 'AFN' ? 'selected' : ''; ?>>AFN (Afghan Afghani)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="billing_cycle" class="form-label">Billing Cycle *</label>
                                    <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                        <option value="">Select Cycle</option>
                                        <option value="monthly" <?php echo ($_POST['billing_cycle'] ?? $plan['billing_cycle']) === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="quarterly" <?php echo ($_POST['billing_cycle'] ?? $plan['billing_cycle']) === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                        <option value="yearly" <?php echo ($_POST['billing_cycle'] ?? $plan['billing_cycle']) === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3">Plan Limits</h6>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_employees" class="form-label">Max Employees</label>
                                    <input type="number" class="form-control" id="max_employees" name="max_employees" 
                                           value="<?php echo htmlspecialchars($_POST['max_employees'] ?? $plan['max_employees']); ?>" 
                                           min="0">
                                    <small class="text-muted">0 = Unlimited</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_machines" class="form-label">Max Machines</label>
                                    <input type="number" class="form-control" id="max_machines" name="max_machines" 
                                           value="<?php echo htmlspecialchars($_POST['max_machines'] ?? $plan['max_machines']); ?>" 
                                           min="0">
                                    <small class="text-muted">0 = Unlimited</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_projects" class="form-label">Max Projects</label>
                                    <input type="number" class="form-control" id="max_projects" name="max_projects" 
                                           value="<?php echo htmlspecialchars($_POST['max_projects'] ?? $plan['max_projects']); ?>" 
                                           min="0">
                                    <small class="text-muted">0 = Unlimited</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3">Plan Features</h6>

                        <div class="mb-3">
                            <label for="features" class="form-label">Features</label>
                            <textarea class="form-control" id="features" name="features" rows="8" 
                                      placeholder="Enter features, one per line&#10;&#10;Example features:&#10;• Employee Management&#10;• Machine Tracking&#10;• Basic Reports&#10;• Email Support&#10;• Mobile Access&#10;• API Access"><?php echo htmlspecialchars($_POST['features'] ?? $features_text); ?></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Tip:</strong> Press Enter to add a new feature on the next line. Each line will become a separate feature.
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addFeatureLine()">
                                    <i class="fas fa-plus"></i> Add Feature Line
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="insertSampleFeatures()">
                                    <i class="fas fa-magic"></i> Insert Sample Features
                                </button>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3">Plan Settings</h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_popular" name="is_popular" 
                                               <?php echo (isset($_POST['is_popular']) || $plan['is_popular']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_popular">
                                            Mark as Popular Plan
                                        </label>
                                    </div>
                                    <small class="text-muted">Popular plans are highlighted on the landing page</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] || $plan['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            Active Plan
                                        </label>
                                    </div>
                                    <small class="text-muted">Inactive plans won't be shown to customers</small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Pricing Plan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        // Function to add a new feature line
        function addFeatureLine() {
            const textarea = document.getElementById('features');
            const currentValue = textarea.value;
            const cursorPos = textarea.selectionStart;
            
            // Add a new line at cursor position
            const beforeCursor = currentValue.substring(0, cursorPos);
            const afterCursor = currentValue.substring(cursorPos);
            const newValue = beforeCursor + '\n' + afterCursor;
            
            textarea.value = newValue;
            textarea.focus();
            textarea.setSelectionRange(cursorPos + 1, cursorPos + 1);
        }

        // Function to insert sample features
        function insertSampleFeatures() {
            const textarea = document.getElementById('features');
            const sampleFeatures = [
                'Employee Management',
                'Machine Tracking',
                'Basic Reports',
                'Email Support',
                'Mobile Access',
                'API Access',
                'Customer Support',
                'Data Backup'
            ];
            
            textarea.value = sampleFeatures.join('\n');
            textarea.focus();
        }

        // Auto-resize textarea as user types
        document.getElementById('features').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 300) + 'px';
        });
        </script>

        <div class="col-lg-4">
            <!-- Current Plan Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Current Plan Info</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Plan Name:</strong> <?php echo htmlspecialchars($plan['plan_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Plan Code:</strong> <?php echo htmlspecialchars($plan['plan_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Price:</strong> 
                        <span class="text-success fw-bold"><?php echo $plan['currency']; ?> <?php echo number_format($plan['price'], 2); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Billing Cycle:</strong> <?php echo ucfirst($plan['billing_cycle']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge <?php echo $plan['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <?php if ($plan['is_popular']): ?>
                    <div class="mb-3">
                        <strong>Popular:</strong> 
                        <span class="badge bg-warning">
                            <i class="fas fa-star"></i> Yes
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Plan Types</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-tag me-2"></i>Basic - For small companies</li>
                            <li><i class="fas fa-tag me-2"></i>Professional - For growing businesses</li>
                            <li><i class="fas fa-tag me-2"></i>Enterprise - For large companies</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Billing Cycles</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-calendar me-2"></i>Monthly - Billed every month</li>
                            <li><i class="fas fa-calendar me-2"></i>Quarterly - Billed every 3 months</li>
                            <li><i class="fas fa-calendar me-2"></i>Yearly - Billed annually</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Popular Features</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-users me-2"></i>Employee Management</li>
                            <li><i class="fas fa-cogs me-2"></i>Machine Tracking</li>
                            <li><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</li>
                            <li><i class="fas fa-mobile-alt me-2"></i>Mobile Access</li>
                            <li><i class="fas fa-headset me-2"></i>Customer Support</li>
                            <li><i class="fas fa-code me-2"></i>API Access</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Tips</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-info-circle me-2"></i>Use clear, descriptive plan names</li>
                            <li><i class="fas fa-info-circle me-2"></i>Set reasonable limits for each tier</li>
                            <li><i class="fas fa-info-circle me-2"></i>Highlight key features in descriptions</li>
                            <li><i class="fas fa-info-circle me-2"></i>Mark your best value plan as popular</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('pricingForm').addEventListener('submit', function(e) {
    const planName = document.getElementById('plan_name').value.trim();
    const planCode = document.getElementById('plan_code').value.trim();
    const price = document.getElementById('price').value;
    const billingCycle = document.getElementById('billing_cycle').value;
    
    if (!planName || !planCode || !price || !billingCycle) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (price <= 0) {
        e.preventDefault();
        alert('Price must be greater than zero.');
        return false;
    }
    
    // Validate plan code format
    const planCodeRegex = /^[A-Z0-9_]+$/;
    if (!planCodeRegex.test(planCode)) {
        e.preventDefault();
        alert('Plan code should only contain uppercase letters, numbers, and underscores.');
        return false;
    }
});

// Auto-generate plan code from plan name
document.getElementById('plan_name').addEventListener('input', function() {
    const planName = this.value.trim();
    const planCode = planName.toUpperCase().replace(/[^A-Z0-9]/g, '_');
    document.getElementById('plan_code').value = planCode;
});
</script>

<?php require_once '../../../includes/footer.php'; ?>