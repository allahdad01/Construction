<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

$db = new Database();
$conn = $db->getConnection();
$current_user = getCurrentUser();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(__('field_required', ['field' => $field]));
            }
        }

        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception(__('invalid_email_format'));
        }

        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $current_user['id']]);
        if ($stmt->fetch()) {
            throw new Exception(__('email_already_exists'));
        }

        // Start transaction
        $conn->beginTransaction();

        // Update user information
        $stmt = $conn->prepare("
            UPDATE users SET 
                first_name = ?, 
                last_name = ?, 
                email = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $current_user['id']
        ]);

        // Update employee information if user is an employee
        if ($current_user['role'] === 'driver' || $current_user['role'] === 'driver_assistant') {
            $stmt = $conn->prepare("
                UPDATE employees SET 
                    first_name = ?, 
                    last_name = ?, 
                    email = ?,
                    phone = ?,
                    address = ?,
                    updated_at = NOW()
                WHERE user_id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'] ?? '',
                $_POST['address'] ?? '',
                $current_user['id'],
                $company_id
            ]);
        }

        // Commit transaction
        $conn->commit();

        $success = __('profile_updated_successfully');
        
        // Refresh user session data
        $_SESSION['user_name'] = $_POST['first_name'] . ' ' . $_POST['last_name'];
        $_SESSION['user_email'] = $_POST['email'];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Handle password change
if (isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate current password
        if (!password_verify($current_password, $current_user['password'])) {
            throw new Exception(__('current_password_incorrect'));
        }

        // Validate new password
        if (strlen($new_password) < 6) {
            throw new Exception(__('password_min_length'));
        }

        if ($new_password !== $confirm_password) {
            throw new Exception(__('passwords_do_not_match'));
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $current_user['id']]);

        $success = __('password_changed_successfully');
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user details with employee information if applicable
$stmt = $conn->prepare("
    SELECT u.*, e.phone, e.employee_code, e.position, e.monthly_salary, e.daily_rate
    FROM users u
    LEFT JOIN employees e ON u.id = e.user_id AND e.company_id = ?
    WHERE u.id = ?
");
$stmt->execute([$company_id, $current_user['id']]);
$user_details = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user activity (simplified since user_logs table doesn't exist)
$user_activity = [
    [
        'action' => 'Profile viewed',
        'created_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]
];

// Get company information (for super admin, show all companies; for others, show their company)
if ($current_user['role'] === 'super_admin') {
    $stmt = $conn->prepare("
        SELECT c.*, c.subscription_plan as plan_name
        FROM companies c 
        WHERE c.id = ?
    ");
    $stmt->execute([$company_id]);
    $company_info = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("
        SELECT c.*, c.subscription_plan as plan_name
        FROM companies c 
        WHERE c.id = ?
    ");
    $stmt->execute([$company_id]);
    $company_info = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user"></i> <?php echo __('my_profile'); ?>
        </h1>
        <a href="../dashboard/" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_dashboard'); ?>
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
        <!-- Profile Information -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('profile_information'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label"><?php echo __('first_name'); ?> *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user_details['first_name']); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label"><?php echo __('last_name'); ?> *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user_details['last_name']); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label"><?php echo __('email_address'); ?> *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user_details['email']); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label"><?php echo __('phone_number'); ?></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user_details['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('update_profile'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('change_password'); ?></h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label"><?php echo __('current_password'); ?> *</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label"><?php echo __('new_password'); ?> *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label"><?php echo __('confirm_password'); ?> *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key"></i> <?php echo __('change_password'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Profile Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('profile_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                            <span class="text-white font-weight-bold" style="font-size: 2rem;">
                                <?php echo strtoupper(substr($user_details['first_name'], 0, 1) . substr($user_details['last_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <h5 class="mt-2"><?php echo htmlspecialchars($user_details['first_name'] . ' ' . $user_details['last_name']); ?></h5>
                        <p class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $user_details['role'])); ?></p>
                        
                        <?php if ($user_details['employee_code']): ?>
                        <p class="text-muted">
                            <span class="badge bg-primary"><?php echo htmlspecialchars($user_details['employee_code']); ?></span>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-2">
                        <strong><?php echo __('email'); ?>:</strong> <?php echo htmlspecialchars($user_details['email']); ?>
                    </div>
                    <div class="mb-2">
                        <strong><?php echo __('status'); ?>:</strong> 
                        <span class="badge bg-<?php echo $user_details['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($user_details['status']); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong><?php echo __('member_since'); ?>:</strong> <?php echo date('M j, Y', strtotime($user_details['created_at'])); ?>
                    </div>
                    
                    <?php if ($user_details['position']): ?>
                    <div class="mb-2">
                        <strong><?php echo __('position'); ?>:</strong> <?php echo ucfirst(str_replace('_', ' ', $user_details['position'])); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user_details['monthly_salary']): ?>
                    <div class="mb-2">
                        <strong><?php echo __('monthly_salary'); ?>:</strong> $<?php echo number_format($user_details['monthly_salary'], 2); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Company Information -->
            <?php if ($company_info): ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('company_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong><?php echo __('company'); ?>:</strong> <?php echo htmlspecialchars($company_info['company_name']); ?>
                    </div>
                    <div class="mb-2">
                        <strong><?php echo __('plan'); ?>:</strong> <?php echo htmlspecialchars($company_info['plan_name'] ?? __('no_plan')); ?>
                    </div>
                    <div class="mb-2">
                        <strong><?php echo __('status'); ?>:</strong> 
                        <span class="badge bg-<?php echo $company_info['subscription_status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($company_info['subscription_status']); ?>
                        </span>
                    </div>
                    <?php if ($company_info['trial_ends_at']): ?>
                    <div class="mb-2">
                        <strong><?php echo __('trial_ends'); ?>:</strong> <?php echo date('M j, Y', strtotime($company_info['trial_ends_at'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('recent_activity'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($user_activity)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p><?php echo __('no_recent_activity'); ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($user_activity as $activity): ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="flex-shrink-0">
                                <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="fas fa-<?php echo $activity['action'] === 'login' ? 'sign-in-alt' : 'cog'; ?> text-white" style="font-size: 0.75rem;"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <small class="text-muted"><?php echo ucfirst($activity['action']); ?></small><br>
                                <small class="text-muted"><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');

    // Profile form validation
    profileForm.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = profileForm.querySelectorAll('[required]');
        
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

        // Email validation
        const emailInput = document.getElementById('email');
        if (emailInput.value && !isValidEmail(emailInput.value)) {
            isValid = false;
            emailInput.classList.add('is-invalid');
            
            if (!emailInput.nextElementSibling || !emailInput.nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Please enter a valid email address.';
                emailInput.parentNode.appendChild(errorDiv);
            }
        }

        if (!isValid) {
            e.preventDefault();
            showNotification('Please fix the errors in the form.', 'error');
        }
    });

    // Password form validation
    passwordForm.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = passwordForm.querySelectorAll('[required]');
        
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

        // Password validation
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (newPassword && newPassword.length < 6) {
            isValid = false;
            document.getElementById('new_password').classList.add('is-invalid');
            
            if (!document.getElementById('new_password').nextElementSibling || !document.getElementById('new_password').nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Password must be at least 6 characters long.';
                document.getElementById('new_password').parentNode.appendChild(errorDiv);
            }
        }

        if (newPassword && confirmPassword && newPassword !== confirmPassword) {
            isValid = false;
            document.getElementById('confirm_password').classList.add('is-invalid');
            
            if (!document.getElementById('confirm_password').nextElementSibling || !document.getElementById('confirm_password').nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Passwords do not match.';
                document.getElementById('confirm_password').parentNode.appendChild(errorDiv);
            }
        }

        if (!isValid) {
            e.preventDefault();
            showNotification('Please fix the errors in the form.', 'error');
        }
    });

    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Real-time validation
    const inputs = document.querySelectorAll('input');
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

        // Email validation
        if (field.type === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
        }

        // Password validation
        if (field.name === 'new_password' && value && value.length < 6) {
            isValid = false;
            errorMessage = 'Password must be at least 6 characters long.';
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