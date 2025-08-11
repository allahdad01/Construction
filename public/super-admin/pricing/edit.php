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
            <i class="fas fa-edit"></i> <?php echo __('edit_pricing_plan'); ?>
        </h1>
        <a href="view.php?id=<?php echo $plan_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_plan'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('pricing_plan_information'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="pricingForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_name" class="form-label"><?php echo __('plan_name'); ?> *</label>
                                    <input type="text" class="form-control" id="plan_name" name="plan_name" 
                                           value="<?php echo htmlspecialchars($_POST['plan_name'] ?? $plan['plan_name']); ?>" 
                                           placeholder="e.g., Basic, Professional, Enterprise" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="plan_code" class="form-label"><?php echo __('plan_code'); ?> *</label>
                                    <input type="text" class="form-control" id="plan_code" name="plan_code" 
                                           value="<?php echo htmlspecialchars($_POST['plan_code'] ?? $plan['plan_code']); ?>" 
                                           placeholder="e.g., BASIC, PRO, ENTERPRISE" required>
                                    <small class="text-muted"><?php echo __('unique_identifier_for_the_plan'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo __('description'); ?></label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Brief description of the plan"><?php echo htmlspecialchars($_POST['description'] ?? $plan['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price" class="form-label"><?php echo __('price'); ?> *</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? $plan['price']); ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="currency" class="form-label"><?php echo __('currency'); ?></label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="USD" <?php echo ($_POST['currency'] ?? $plan['currency']) === 'USD' ? 'selected' : ''; ?>><?php echo __('usd'); ?> (<?php echo __('us_dollar'); ?>)</option>
                                        <option value="AFN" <?php echo ($_POST['currency'] ?? $plan['currency']) === 'AFN' ? 'selected' : ''; ?>><?php echo __('afn'); ?> (<?php echo __('afghan_afghani'); ?>)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="billing_cycle" class="form-label"><?php echo __('billing_cycle'); ?> *</label>
                                    <select class="form-control" id="billing_cycle" name="billing_cycle" required>
                                        <option value=""><?php echo __('select_cycle'); ?></option>
                                        <option value="monthly" <?php echo ($_POST['billing_cycle'] ?? $plan['billing_cycle']) === 'monthly' ? 'selected' : ''; ?>><?php echo __('monthly'); ?></option>
                                        <option value="quarterly" <?php echo ($_POST['billing_cycle'] ?? $plan['billing_cycle']) === 'quarterly' ? 'selected' : ''; ?>><?php echo __('quarterly'); ?></option>
                                        <option value="yearly" <?php echo ($_POST['billing_cycle'] ?? $plan['billing_cycle']) === 'yearly' ? 'selected' : ''; ?>><?php echo __('yearly'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3"><?php echo __('plan_limits'); ?></h6>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_employees" class="form-label"><?php echo __('max_employees'); ?></label>
                                    <input type="number" class="form-control" id="max_employees" name="max_employees" 
                                           value="<?php echo htmlspecialchars($_POST['max_employees'] ?? $plan['max_employees']); ?>" 
                                           min="0">
                                    <small class="text-muted"><?php echo __('0_unlimited'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_machines" class="form-label"><?php echo __('max_machines'); ?></label>
                                    <input type="number" class="form-control" id="max_machines" name="max_machines" 
                                           value="<?php echo htmlspecialchars($_POST['max_machines'] ?? $plan['max_machines']); ?>" 
                                           min="0">
                                    <small class="text-muted"><?php echo __('0_unlimited'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_projects" class="form-label"><?php echo __('max_projects'); ?></label>
                                    <input type="number" class="form-control" id="max_projects" name="max_projects" 
                                           value="<?php echo htmlspecialchars($_POST['max_projects'] ?? $plan['max_projects']); ?>" 
                                           min="0">
                                    <small class="text-muted"><?php echo __('0_unlimited'); ?></small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3"><?php echo __('plan_features'); ?></h6>

                        <div class="mb-3">
                            <label for="features" class="form-label"><?php echo __('features'); ?></label>
                            <textarea class="form-control" id="features" name="features" rows="8" 
                                      placeholder="Enter features, one per line&#10;&#10;Example features:&#10;• Employee Management&#10;• Machine Tracking&#10;• Basic Reports&#10;• Email Support&#10;• Mobile Access&#10;• API Access"><?php echo htmlspecialchars($_POST['features'] ?? $features_text); ?></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong><?php echo __('tip'); ?>:</strong> <?php echo __('press_enter_to_add_a_new_feature_on_the_next_line'); ?>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addFeatureLine()">
                                    <i class="fas fa-plus"></i> <?php echo __('add_feature_line'); ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="insertSampleFeatures()">
                                    <i class="fas fa-magic"></i> <?php echo __('insert_sample_features'); ?>
                                </button>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3"><?php echo __('plan_settings'); ?></h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_popular" name="is_popular" 
                                               <?php echo (isset($_POST['is_popular']) || $plan['is_popular']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_popular">
                                            <?php echo __('mark_as_popular_plan'); ?>
                                        </label>
                                    </div>
                                    <small class="text-muted"><?php echo __('popular_plans_are_highlighted_on_the_landing_page'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] || $plan['is_active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">
                                            <?php echo __('active_plan'); ?>
                                        </label>
                                    </div>
                                    <small class="text-muted"><?php echo __('inactive_plans_won_t_be_shown_to_customers'); ?></small>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('update_pricing_plan'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('current_plan_info'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><?php echo __('plan_name'); ?>:</strong> <?php echo htmlspecialchars($plan['plan_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('plan_code'); ?>:</strong> <?php echo htmlspecialchars($plan['plan_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('price'); ?>:</strong> 
                        <span class="text-success fw-bold"><?php echo $plan['currency']; ?> <?php echo number_format($plan['price'], 2); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('billing_cycle'); ?>:</strong> <?php echo ucfirst($plan['billing_cycle']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('status'); ?>:</strong> 
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('plan_types'); ?></h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-tag me-2"></i><?php echo __('basic'); ?> - <?php echo __('for_small_companies'); ?></li>
                            <li><i class="fas fa-tag me-2"></i><?php echo __('professional'); ?> - <?php echo __('for_growing_businesses'); ?></li>
                            <li><i class="fas fa-tag me-2"></i><?php echo __('enterprise'); ?> - <?php echo __('for_large_companies'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('billing_cycles'); ?></h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-calendar me-2"></i><?php echo __('monthly'); ?> - <?php echo __('billed_every_month'); ?></li>
                            <li><i class="fas fa-calendar me-2"></i><?php echo __('quarterly'); ?> - <?php echo __('billed_every_3_months'); ?></li>
                            <li><i class="fas fa-calendar me-2"></i><?php echo __('yearly'); ?> - <?php echo __('billed_annually'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('popular_features'); ?></h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-users me-2"></i><?php echo __('employee_management'); ?></li>
                            <li><i class="fas fa-cogs me-2"></i><?php echo __('machine_tracking'); ?></li>
                            <li><i class="fas fa-chart-bar me-2"></i><?php echo __('reports_and_analytics'); ?></li>
                            <li><i class="fas fa-mobile-alt me-2"></i><?php echo __('mobile_access'); ?></li>
                            <li><i class="fas fa-headset me-2"></i><?php echo __('customer_support'); ?></li>
                            <li><i class="fas fa-code me-2"></i><?php echo __('api_access'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('tips'); ?></h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-info-circle me-2"></i><?php echo __('use_clear_descriptive_plan_names'); ?></li>
                            <li><i class="fas fa-info-circle me-2"></i><?php echo __('set_reasonable_limits_for_each_tier'); ?></li>
                            <li><i class="fas fa-info-circle me-2"></i><?php echo __('highlight_key_features_in_descriptions'); ?></li>
                            <li><i class="fas fa-info-circle me-2"></i><?php echo __('mark_your_best_value_plan_as_popular'); ?></li>
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
        alert('<?php echo __('please_fill_in_all_required_fields'); ?>');
        return false;
    }
    
    if (price <= 0) {
        e.preventDefault();
        alert('<?php echo __('price_must_be_greater_than_zero'); ?>');
        return false;
    }
    
    // Validate plan code format
    const planCodeRegex = /^[A-Z0-9_]+$/;
    if (!planCodeRegex.test(planCode)) {
        e.preventDefault();
        alert('<?php echo __('plan_code_should_only_contain_uppercase_letters_numbers_and_underscores'); ?>');
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