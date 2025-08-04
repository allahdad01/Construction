<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['company_id'] ?? 0);

if (!$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? 'employee';
    $password = trim($_POST['password'] ?? '');

    $errors = [];

    // Validation
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    if (empty($password)) $errors[] = 'Password is required';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters';

    // Check if email exists
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    }

    // Check if username exists
    if (!empty($username)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Generate user code
            $user_code = 'USR' . strtoupper(uniqid());

            // Insert user
            $stmt = $conn->prepare("
                INSERT INTO users (user_code, first_name, last_name, email, username, phone, 
                                 password_hash, role, status, company_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $user_code,
                $first_name,
                $last_name,
                $email,
                $username,
                $phone,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $company_id
            ]);

            $user_id = $conn->lastInsertId();

            $conn->commit();

            $_SESSION['success_message'] = 'User added successfully';
            header("Location: user-view.php?id=$user_id&company_id=$company_id");
            exit;

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Error adding user: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-plus"></i> Add User to Company
        </h1>
        <div>
            <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
            <a href="view.php?id=<?php echo $company_id; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> View Company
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Add New User</h6>
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
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-control" id="role" name="role">
                                        <option value="employee" <?php echo ($_POST['role'] ?? '') === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                        <option value="company_admin" <?php echo ($_POST['role'] ?? '') === 'company_admin' ? 'selected' : ''; ?>>Company Admin</option>
                                        <option value="driver" <?php echo ($_POST['role'] ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo ($_POST['role'] ?? '') === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                        <option value="parking_user" <?php echo ($_POST['role'] ?? '') === 'parking_user' ? 'selected' : ''; ?>>Parking User</option>
                                        <option value="area_renter" <?php echo ($_POST['role'] ?? '') === 'area_renter' ? 'selected' : ''; ?>>Area Renter</option>
                                        <option value="container_renter" <?php echo ($_POST['role'] ?? '') === 'container_renter' ? 'selected' : ''; ?>>Container Renter</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <input type="text" class="form-control" value="Active (default)" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($company['company_name']); ?>" readonly>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Company Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Company Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Company:</strong> <?php echo htmlspecialchars($company['company_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Company ID:</strong> <?php echo $company_id; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge <?php echo $company['subscription_status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Plan:</strong> <?php echo htmlspecialchars($company['subscription_plan'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>

            <!-- User Guidelines -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Guidelines</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Roles:</strong>
                        <ul class="small text-muted mt-1">
                            <li><strong>Company Admin:</strong> Full company management</li>
                            <li><strong>Employee:</strong> Basic system access</li>
                            <li><strong>Driver:</strong> Vehicle and delivery management</li>
                            <li><strong>Driver Assistant:</strong> Support for drivers</li>
                            <li><strong>Parking User:</strong> Parking management</li>
                            <li><strong>Area Renter:</strong> Area rental access</li>
                            <li><strong>Container Renter:</strong> Container rental access</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <strong>Password Requirements:</strong>
                        <ul class="small text-muted mt-1">
                            <li>Minimum 6 characters</li>
                            <li>User will be set to Active status</li>
                            <li>Email must be unique</li>
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
                    <div class="d-grid gap-2">
                        <a href="users.php?company_id=<?php echo $company_id; ?>" class="btn btn-info">
                            <i class="fas fa-list"></i> View All Users
                        </a>
                        <a href="view.php?id=<?php echo $company_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-eye"></i> View Company
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>