<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$user_id = (int)($_GET['id'] ?? 0);
$company_id = (int)($_GET['company_id'] ?? 0);

if (!$user_id || !$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $company_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'employee';
    $status = $_POST['status'] ?? 'active';
    $password = trim($_POST['password'] ?? '');

    $errors = [];

    // Validation
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';

    // Check if email exists (excluding current user)
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }

    // Check if username exists (excluding current user)
    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Update user
            $sql = "UPDATE users SET 
                    first_name = ?, last_name = ?, email = ?, username = ?, 
                    phone = ?, role = ?, status = ?, updated_at = NOW()";
            $params = [$first_name, $last_name, $email, $username, $phone, $role, $status];

            // Add password update if provided
            if (!empty($password)) {
                $sql .= ", password_hash = ?";
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }

            $sql .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $conn->commit();

            $_SESSION['success_message'] = 'User updated successfully';
            header("Location: user-view.php?id=$user_id&company_id=$company_id");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Error updating user: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-edit"></i> Edit User
        </h1>
        <div>
            <a href="user-view.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to User
            </a>
            <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-list"></i> Back to Users
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit User Information</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-control" id="role" name="role">
                                        <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                        <option value="company_admin" <?php echo $user['role'] === 'company_admin' ? 'selected' : ''; ?>>Company Admin</option>
                                        <option value="driver" <?php echo $user['role'] === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo $user['role'] === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                        <option value="parking_user" <?php echo $user['role'] === 'parking_user' ? 'selected' : ''; ?>>Parking User</option>
                                        <option value="area_renter" <?php echo $user['role'] === 'area_renter' ? 'selected' : ''; ?>>Area Renter</option>
                                        <option value="container_renter" <?php echo $user['role'] === 'container_renter' ? 'selected' : ''; ?>>Container Renter</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($company['company_name']); ?>" readonly>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="user-view.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- User Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>User ID:</strong> <?php echo $user_id; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Company:</strong> <?php echo htmlspecialchars($company['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Created:</strong> <?php echo formatDate($user['created_at']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Last Updated:</strong> <?php echo formatDate($user['updated_at']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Last Login:</strong> 
                        <?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="user-view.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> View User
                        </a>
                        <?php if ($user['status'] === 'active'): ?>
                            <a href="user-suspend.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-danger"
                               onclick="return confirm('Are you sure you want to suspend this user?')">
                                <i class="fas fa-pause"></i> Suspend User
                            </a>
                        <?php else: ?>
                            <a href="user-activate.php?id=<?php echo $user_id; ?>&company_id=<?php echo $company_id; ?>" class="btn btn-success"
                               onclick="return confirm('Are you sure you want to activate this user?')">
                                <i class="fas fa-play"></i> Activate User
                            </a>
                        <?php endif; ?>
                        <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Back to Users
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>