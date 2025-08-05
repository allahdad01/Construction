<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get user ID from URL
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'role'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $user_id]);
        if ($stmt->fetch()) {
            throw new Exception("Email already exists in the system.");
        }

        // Check if username exists (if provided and different from current)
        if (!empty($_POST['username']) && $_POST['username'] !== $user['username']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$_POST['username'], $user_id]);
            if ($stmt->fetch()) {
                throw new Exception("Username already exists.");
            }
        }

        // Start transaction
        $conn->beginTransaction();

        // Prepare update data
        $update_data = [
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $_POST['username'] ?: null,
            $_POST['phone'] ?: null,
            $_POST['role'],
            $_POST['status'] ?? 'active',
            $_POST['is_active'] ?? 1,
            $user_id,
            $company_id
        ];

        // Update user record
        $stmt = $conn->prepare("
            UPDATE users SET
                first_name = ?, last_name = ?, email = ?, username = ?,
                phone = ?, role = ?, status = ?, is_active = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute($update_data);

        // Handle password update if provided
        if (!empty($_POST['new_password'])) {
            $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
        }

        // Commit transaction
        $conn->commit();

        $success = "User updated successfully!";

        // Refresh user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
        $stmt->execute([$user_id, $company_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$user_id'; }, 2000);</script>";

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
            <i class="fas fa-user-edit"></i> <?php echo __('edit_user'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_user'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_users'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit User Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo __('edit_user_details'); ?> - <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label"><?php echo __('first_name'); ?> *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label"><?php echo __('last_name'); ?> *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label"><?php echo __('username'); ?></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                            <small class="form-text text-muted"><?php echo __('leave_empty_to_use_email'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label"><?php echo __('phone'); ?></label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label"><?php echo __('role'); ?> *</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value=""><?php echo __('select_role'); ?></option>
                                <option value="company_admin" <?php echo ($user['role'] == 'company_admin') ? 'selected' : ''; ?>><?php echo __('company_admin'); ?></option>
                                <option value="driver" <?php echo ($user['role'] == 'driver') ? 'selected' : ''; ?>><?php echo __('driver'); ?></option>
                                <option value="driver_assistant" <?php echo ($user['role'] == 'driver_assistant') ? 'selected' : ''; ?>><?php echo __('driver_assistant'); ?></option>
                                <option value="parking_user" <?php echo ($user['role'] == 'parking_user') ? 'selected' : ''; ?>><?php echo __('parking_user'); ?></option>
                                <option value="area_renter" <?php echo ($user['role'] == 'area_renter') ? 'selected' : ''; ?>><?php echo __('area_renter'); ?></option>
                                <option value="container_renter" <?php echo ($user['role'] == 'container_renter') ? 'selected' : ''; ?>><?php echo __('container_renter'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="active" <?php echo ($user['status'] == 'active') ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                <option value="inactive" <?php echo ($user['status'] == 'inactive') ? 'selected' : ''; ?>><?php echo __('inactive'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="is_active" class="form-label"><?php echo __('account_active'); ?></label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="1" <?php echo ($user['is_active']) ? 'selected' : ''; ?>><?php echo __('yes'); ?></option>
                                <option value="0" <?php echo (!$user['is_active']) ? 'selected' : ''; ?>><?php echo __('no'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Password Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-warning"><?php echo __('password_change'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label"><?php echo __('new_password'); ?></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <small class="form-text text-muted"><?php echo __('leave_empty_to_keep_current_password'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label"><?php echo __('confirm_password'); ?></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-info"><?php echo __('account_information'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo __('user_id'); ?>:</strong> #<?php echo htmlspecialchars($user['id']); ?></p>
                                <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($user['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('last_login'); ?>:</strong> 
                                    <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : __('never'); ?>
                                </p>
                                <p><strong><?php echo __('last_updated'); ?>:</strong> 
                                    <?php echo $user['updated_at'] ? formatDateTime($user['updated_at']) : __('never'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_user'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (password && confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

document.getElementById('new_password').addEventListener('input', function() {
    const confirmPassword = document.getElementById('confirm_password');
    if (confirmPassword.value) {
        confirmPassword.dispatchEvent(new Event('input'));
    }
});

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password && !confirmPassword) {
        e.preventDefault();
        alert('Please confirm the new password');
        return false;
    }
    
    if (password && confirmPassword && password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return false;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>