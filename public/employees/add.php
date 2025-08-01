<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = $_POST['position'] ?? '';
    $monthly_salary = (float)($_POST['monthly_salary'] ?? 0);
    $hire_date = $_POST['hire_date'] ?? '';
    $create_user_account = isset($_POST['create_user_account']);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($position) || empty($monthly_salary) || empty($hire_date)) {
        $error = 'Please fill in all required fields.';
    } elseif ($monthly_salary <= 0) {
        $error = 'Monthly salary must be greater than 0.';
    } elseif ($create_user_account && (empty($username) || empty($password))) {
        $error = 'Username and password are required when creating user account.';
    } else {
        try {
            $conn->beginTransaction();
            
            // Generate employee code
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ?");
            $stmt->execute([getCurrentCompanyId()]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $employee_code = 'EMP' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
            
            // Create user account if requested
            $user_id = null;
            if ($create_user_account) {
                // Check if username already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND company_id = ?");
                $stmt->execute([$username, getCurrentCompanyId()]);
                if ($stmt->fetch()) {
                    throw new Exception('Username already exists.');
                }
                
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception('Email already exists.');
                }
                
                // Create user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user_role = $position === 'driver' ? 'driver' : 'driver_assistant';
                
                $stmt = $conn->prepare("
                    INSERT INTO users (company_id, username, email, password_hash, first_name, last_name, phone, role, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                $name_parts = explode(' ', $name, 2);
                $first_name = $name_parts[0];
                $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
                
                $stmt->execute([
                    getCurrentCompanyId(),
                    $username,
                    $email,
                    $password_hash,
                    $first_name,
                    $last_name,
                    $phone,
                    $user_role
                ]);
                
                $user_id = $conn->lastInsertId();
            }
            
            // Create employee
            $stmt = $conn->prepare("
                INSERT INTO employees (company_id, user_id, employee_code, name, email, phone, position, monthly_salary, hire_date, status, total_leave_days, used_leave_days, remaining_leave_days) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 20, 0, 20)
            ");
            
            $stmt->execute([
                getCurrentCompanyId(),
                $user_id,
                $employee_code,
                $name,
                $email,
                $phone,
                $position,
                $monthly_salary,
                $hire_date
            ]);
            
            $conn->commit();
            $success = 'Employee added successfully! Employee Code: ' . $employee_code;
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add New Employee</h1>
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Employees
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-user"></i> Full Name *
                                    </label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i> Email Address *
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="fas fa-phone"></i> Phone Number *
                                    </label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">
                                        <i class="fas fa-briefcase"></i> Position *
                                    </label>
                                    <select class="form-control" id="position" name="position" required>
                                        <option value="">Select Position</option>
                                        <option value="driver" <?php echo ($_POST['position'] ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo ($_POST['position'] ?? '') === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label">
                                        <i class="fas fa-dollar-sign"></i> Monthly Salary *
                                    </label>
                                    <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" 
                                           value="<?php echo htmlspecialchars($_POST['monthly_salary'] ?? ''); ?>" 
                                           step="0.01" min="0" required>
                                    <small class="text-muted">Daily rate will be calculated automatically (Monthly Salary ÷ 30)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">
                                        <i class="fas fa-calendar"></i> Hire Date *
                                    </label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                           value="<?php echo htmlspecialchars($_POST['hire_date'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Salary Calculation Display -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Daily Rate</label>
                                    <input type="text" class="form-control" id="daily_rate" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Annual Salary</label>
                                    <input type="text" class="form-control" id="annual_salary" readonly>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- User Account Creation -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="create_user_account" name="create_user_account" 
                                       <?php echo isset($_POST['create_user_account']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="create_user_account">
                                    <i class="fas fa-user-plus"></i> Create User Account (Allow login access)
                                </label>
                            </div>
                        </div>

                        <div id="user_account_fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">
                                            <i class="fas fa-user"></i> Username *
                                        </label>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                                        <small class="text-muted">Must be unique within the company</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock"></i> Password *
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password">
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="index.php" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Employee
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <h6>Employee Benefits</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> 20 leave days per year</li>
                        <li><i class="fas fa-check text-success"></i> Pro-rated salary calculation</li>
                        <li><i class="fas fa-check text-success"></i> Attendance tracking</li>
                        <li><i class="fas fa-check text-success"></i> Salary payment management</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Salary Calculation</h6>
                    <p><strong>Daily Rate:</strong> Monthly Salary ÷ 30 days</p>
                    <p><strong>Example:</strong> $15,000 ÷ 30 = $500 per day</p>
                    <p><strong>Leave Days:</strong> 20 days per year (1.67 days per month)</p>
                    
                    <hr>
                    
                    <h6>User Account</h6>
                    <p>Creating a user account allows the employee to:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-sign-in-alt text-info"></i> Login to the system</li>
                        <li><i class="fas fa-chart-line text-info"></i> View their dashboard</li>
                        <li><i class="fas fa-clock text-info"></i> Track attendance</li>
                        <li><i class="fas fa-money-bill text-info"></i> View salary information</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Show/hide user account fields
    $('#create_user_account').change(function() {
        if ($(this).is(':checked')) {
            $('#user_account_fields').show();
            $('#username, #password').prop('required', true);
        } else {
            $('#user_account_fields').hide();
            $('#username, #password').prop('required', false);
        }
    });
    
    // Trigger change event on page load
    $('#create_user_account').trigger('change');
    
    // Calculate salary information
    $('#monthly_salary').on('input', function() {
        var monthlySalary = parseFloat($(this).val()) || 0;
        var dailyRate = monthlySalary / 30;
        var annualSalary = monthlySalary * 12;
        
        $('#daily_rate').val('$' + dailyRate.toFixed(2));
        $('#annual_salary').val('$' + annualSalary.toFixed(2));
    });
    
    // Trigger calculation on page load
    $('#monthly_salary').trigger('input');
    
    // Auto-generate username from name
    $('#name').on('input', function() {
        var name = $(this).val();
        var username = name.toLowerCase().replace(/\s+/g, '');
        $('#username').val(username);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>