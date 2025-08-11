<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['company_name', 'contact_email', 'subscription_plan'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate email
        if (!filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email address.");
        }

        // Generate company code
        $company_code = generateCompanyCode();

        // Start transaction
        $conn->beginTransaction();

        // Create company record
        $stmt = $conn->prepare("
            INSERT INTO companies (
                company_code, company_name, contact_person, contact_email, 
                contact_phone, address, city, state, country, subscription_plan,
                subscription_status, trial_ends_at, max_employees, max_machines, 
                max_projects, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $trial_ends_at = date('Y-m-d', strtotime('+30 days')); // 30-day trial

        $stmt->execute([
            $company_code,
            $_POST['company_name'],
            $_POST['contact_person'] ?? '',
            $_POST['contact_email'],
            $_POST['contact_phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['city'] ?? '',
            $_POST['state'] ?? '',
            $_POST['country'] ?? '',
            $_POST['subscription_plan'],
            'trial',
            $trial_ends_at,
            $_POST['max_employees'] ?? 25,
            $_POST['max_machines'] ?? 50,
            $_POST['max_projects'] ?? 25,
            1
        ]);

        $company_id = $conn->lastInsertId();

        // Create default company settings
        $default_settings = [
            'default_currency_id' => 1, // USD
            'default_date_format_id' => 1, // MM/DD/YYYY
            'default_language_id' => 1, // English
            'timezone' => 'UTC',
            'working_hours_per_day' => 8,
            'overtime_rate' => 1.5
        ];

        foreach ($default_settings as $key => $value) {
            $stmt = $conn->prepare("
                INSERT INTO company_settings (company_id, setting_key, setting_value) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$company_id, $key, $value]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Company added successfully! Company Code: $company_code";

        // Redirect to view page
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$company_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate company code
function generateCompanyCode() {
    global $conn;
    
    // Get next company number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $next_number = $count + 1;
    return 'COMP' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus"></i> <?php echo __('add_new_company'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_companies'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('company_information'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="companyForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label"><?php echo __('company_name'); ?> *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label"><?php echo __('contact_email'); ?> *</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                           value="<?php echo htmlspecialchars($_POST['contact_email'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label"><?php echo __('contact_person'); ?></label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                           value="<?php echo htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label"><?php echo __('contact_phone'); ?></label>
                                    <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                           value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label"><?php echo __('address'); ?></label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="city" class="form-label"><?php echo __('city'); ?></label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="state" class="form-label"><?php echo __('state'); ?></label>
                                    <input type="text" class="form-control" id="state" name="state" 
                                           value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="country" class="form-label"><?php echo __('country'); ?></label>
                                    <input type="text" class="form-control" id="country" name="country" 
                                           value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h6 class="font-weight-bold text-primary mb-3"><?php echo __('subscription_settings'); ?></h6>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                        <label for="subscription_plan" class="form-label"><?php echo __('subscription_plan'); ?> *</label>
                                    <select class="form-control" id="subscription_plan" name="subscription_plan" required>
                                        <option value=""><?php echo __('select_plan'); ?></option>
                                        <option value="basic" <?php echo ($_POST['subscription_plan'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic</option>
                                        <option value="professional" <?php echo ($_POST['subscription_plan'] ?? '') === 'professional' ? 'selected' : ''; ?>>Professional</option>
                                        <option value="enterprise" <?php echo ($_POST['subscription_plan'] ?? '') === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_employees" class="form-label"><?php echo __('max_employees'); ?></label>
                                    <input type="number" class="form-control" id="max_employees" name="max_employees" 
                                           value="<?php echo htmlspecialchars($_POST['max_employees'] ?? '25'); ?>" 
                                           min="1" max="1000">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_machines" class="form-label"><?php echo __('max_machines'); ?></label>
                                    <input type="number" class="form-control" id="max_machines" name="max_machines" 
                                           value="<?php echo htmlspecialchars($_POST['max_machines'] ?? '50'); ?>" 
                                           min="1" max="1000">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="max_projects" class="form-label"><?php echo __('max_projects'); ?></label>
                                    <input type="number" class="form-control" id="max_projects" name="max_projects" 
                                           value="<?php echo htmlspecialchars($_POST['max_projects'] ?? '25'); ?>" 
                                           min="1" max="1000">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('add_company'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('subscription_plans'); ?></h6>
                        <ul class="list-unstyled">
                            <li><strong><?php echo __('basic'); ?>:</strong> <?php echo __('up_to_10_employees_25_machines'); ?></li>
                            <li><strong><?php echo __('professional'); ?>:</strong> <?php echo __('up_to_50_employees_100_machines'); ?></li>
                            <li><strong><?php echo __('enterprise'); ?>:</strong> <?php echo __('unlimited_employees_and_machines'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('trial_period'); ?></h6>
                        <p class="text-muted"><?php echo __('all_new_companies_get_a_30_day_free_trial_period'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold"><?php echo __('default_settings'); ?></h6>
                        <ul class="list-unstyled">
                            <li><?php echo __('currency'); ?>: USD</li>
                            <li><?php echo __('date_format'); ?>: MM/DD/YYYY</li>
                            <li><?php echo __('language'); ?>: English</li>
                            <li><?php echo __('timezone'); ?>: UTC</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('companyForm').addEventListener('submit', function(e) {
    const companyName = document.getElementById('company_name').value.trim();
    const contactEmail = document.getElementById('contact_email').value.trim();
    const subscriptionPlan = document.getElementById('subscription_plan').value;
    
    if (!companyName || !contactEmail || !subscriptionPlan) {
        e.preventDefault();
        alert('<?php echo __('please_fill_in_all_required_fields'); ?>');
        return false;
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(contactEmail)) {
        e.preventDefault();
        alert('<?php echo __('please_enter_a_valid_email_address'); ?>');
        return false;
    }
});
</script>