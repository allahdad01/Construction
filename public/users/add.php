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

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            throw new Exception("Email already exists in the system.");
        }

        // Check if username exists (if provided)
        if (!empty($_POST['username'])) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            if ($stmt->fetch()) {
                throw new Exception("Username already exists.");
            }
        }

        // Generate random password if not provided
        $password = !empty($_POST['password']) ? $_POST['password'] : generateRandomPassword();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction
        $conn->beginTransaction();

        // Create user record
        $stmt = $conn->prepare("
            INSERT INTO users (
                company_id, username, email, password_hash, first_name, last_name,
                phone, role, status, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $company_id,
            $_POST['username'] ?: null,
            $_POST['email'],
            $password_hash,
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['phone'] ?: null,
            $_POST['role'],
            $_POST['status'] ?? 'active'
        ]);

        $user_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "User added successfully! ";
        if (empty($_POST['password'])) {
            $success .= "Generated password: <strong>$password</strong> (Please share this with the user)";
        }

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 3000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Helper function to generate random password
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-plus"></i> <?php echo __('add_user'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_users'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Add User Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('user_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label"><?php echo __('first_name'); ?> *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label"><?php echo __('last_name'); ?> *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="username" class="form-label"><?php echo __('username'); ?></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            <small class="form-text text-muted"><?php echo __('leave_empty_to_use_email'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label"><?php echo __('phone'); ?></label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label"><?php echo __('role'); ?> *</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value=""><?php echo __('select_role'); ?></option>
                                <option value="company_admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'company_admin') ? 'selected' : ''; ?>><?php echo __('company_admin'); ?></option>
                                <option value="driver" <?php echo (isset($_POST['role']) && $_POST['role'] == 'driver') ? 'selected' : ''; ?>><?php echo __('driver'); ?></option>
                                <option value="driver_assistant" <?php echo (isset($_POST['role']) && $_POST['role'] == 'driver_assistant') ? 'selected' : ''; ?>><?php echo __('driver_assistant'); ?></option>
                                <option value="parking_user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'parking_user') ? 'selected' : ''; ?>><?php echo __('parking_user'); ?></option>
                                <option value="area_renter" <?php echo (isset($_POST['role']) && $_POST['role'] == 'area_renter') ? 'selected' : ''; ?>><?php echo __('area_renter'); ?></option>
                                <option value="container_renter" <?php echo (isset($_POST['role']) && $_POST['role'] == 'container_renter') ? 'selected' : ''; ?>><?php echo __('container_renter'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label"><?php echo __('password'); ?></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted"><?php echo __('leave_empty_for_auto_generation'); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>><?php echo __('inactive'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('add_user'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>