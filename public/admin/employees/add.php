<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['name', 'position', 'monthly_salary', 'email'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate salary
        if (!is_numeric($_POST['monthly_salary']) || $_POST['monthly_salary'] <= 0) {
            throw new Exception("Monthly salary must be a positive number.");
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

        // Generate employee code
        $employee_code = generateEmployeeCode($company_id);

        // Generate random password
        $password = generateRandomPassword();

        // Start transaction
        $conn->beginTransaction();

        // Ensure salary_currency column exists
        try {
            $conn->exec("ALTER TABLE employees ADD COLUMN salary_currency VARCHAR(3) DEFAULT 'AFN' AFTER monthly_salary");
        } catch (Exception $e) {
            // Ignore if already exists
        }

        // Create user account
        $user_role = $_POST['position'] === 'driver' ? 'driver' : 'driver_assistant';
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users (company_id, email, password_hash, first_name, last_name, phone, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        // Split name into first and last name
        $name_parts = explode(' ', trim($_POST['name']), 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : '';

        $stmt->execute([
            $company_id,
            $_POST['email'],
            $hashed_password,
            $first_name,
            $last_name,
            $_POST['phone'] ?? '',
            $user_role
        ]);

        $user_id = $conn->lastInsertId();

        // Create employee record
        $stmt = $conn->prepare("
            INSERT INTO employees (
                company_id, user_id, employee_code, name, email, phone,
                position, monthly_salary, salary_currency, hire_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        $hire_date = $_POST['hire_date'] ?? date('Y-m-d');
        $salary_currency = $_POST['salary_currency'] ?? 'AFN';

        $stmt->execute([
            $company_id,
            $user_id,
            $employee_code,
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'] ?? '',
            $_POST['position'],
            $_POST['monthly_salary'],
            $salary_currency,
            $hire_date,
        ]);

        $employee_id = $conn->lastInsertId();

        // Backfill attendance: mark 'present' for business days from hire_date to today if none exists
        try {
            $start = new DateTime($hire_date);
            $today = new DateTime(date('Y-m-d'));
            $insertStmt = $conn->prepare(
                "INSERT INTO employee_attendance (company_id, employee_id, date, status, created_at)
                 SELECT ?, ?, ?, 'present', NOW()
                 FROM DUAL
                 WHERE NOT EXISTS (
                    SELECT 1 FROM employee_attendance ea WHERE ea.company_id = ? AND ea.employee_id = ? AND ea.date = ?
                 )"
            );
            $current = clone $start;
            while ($current <= $today) {
                $dayOfWeek = (int)$current->format('w');
                if ($dayOfWeek !== 0 && $dayOfWeek !== 6) { // Mon-Fri only
                    $dateStr = $current->format('Y-m-d');
                    $insertStmt->execute([$company_id, $employee_id, $dateStr, $company_id, $employee_id, $dateStr]);
                }
                $current->add(new DateInterval('P1D'));
            }
        } catch (Exception $e) {
            // ignore backfill errors to not block creation
        }

        // Commit transaction
        $conn->commit();

        $success = "Employee added successfully! Employee Code: $employee_code<br>Login credentials sent to: " . htmlspecialchars($_POST['email']);

        // Redirect to view page after 3 seconds
        header("refresh:3;url=view.php?id=$employee_id");

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate employee code
function generateEmployeeCode($company_id) {
    global $conn;

    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];

    // Get next employee number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $next_number = $count + 1;
    return strtoupper($company_code) . 'EMP' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}

// Helper function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-user-plus"></i> Add Employee
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Employees
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
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Add Employee Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="employeeForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">Position *</label>
                                    <select class="form-control" id="position" name="position" required>
                                        <option value="">Select Position</option>
                                        <option value="driver" <?php echo ($_POST['position'] ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo ($_POST['position'] ?? '') === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                        <option value="operator" <?php echo ($_POST['position'] ?? '') === 'operator' ? 'selected' : ''; ?>>Machine Operator</option>
                                        <option value="supervisor" <?php echo ($_POST['position'] ?? '') === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                        <option value="technician" <?php echo ($_POST['position'] ?? '') === 'technician' ? 'selected' : ''; ?>>Technician</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label">Monthly Salary *</label>
                                    <div class="input-group">
                                        <select class="form-select" id="salary_currency" name="salary_currency" style="max-width: 140px;">
                                            <option value="AFN" <?php echo (($_POST['salary_currency'] ?? 'AFN') === 'AFN') ? 'selected' : ''; ?>>AFN</option>
                                            <option value="USD" <?php echo (($_POST['salary_currency'] ?? '') === 'USD') ? 'selected' : ''; ?>>USD</option>
                                            <option value="EUR" <?php echo (($_POST['salary_currency'] ?? '') === 'EUR') ? 'selected' : ''; ?>>EUR</option>
                                            <option value="GBP" <?php echo (($_POST['salary_currency'] ?? '') === 'GBP') ? 'selected' : ''; ?>>GBP</option>
                                        </select>
                                        <input type="number" class="form-control" id="monthly_salary" name="monthly_salary"
                                               value="<?php echo htmlspecialchars($_POST['monthly_salary'] ?? ''); ?>"
                                               step="0.01" min="0" required>
                                    </div>
                                    <div class="form-text">Daily rate will be calculated automatically (Monthly Salary ÷ 30)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date"
                                           value="<?php echo htmlspecialchars($_POST['hire_date'] ?? date('Y-m-d')); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Code</label>
                                    <input type="text" class="form-control" id="employee_code_preview" readonly>
                                    <div class="form-text">Will be automatically generated</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Daily Rate</label>
                                    <input type="text" class="form-control" id="daily_rate_display" readonly>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
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
            <!-- Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="fas fa-info-circle text-info"></i> Employee Code</h6>
                        <p class="text-muted">A unique employee code will be automatically generated.</p>
                    </div>

                    <div class="mb-3">
                        <h6><i class="fas fa-calculator text-success"></i> Salary Calculation</h6>
                        <p class="text-muted">Daily rate is calculated as: Monthly Salary ÷ 30 days</p>
                    </div>

                    <div class="mb-3">
                        <h6><i class="fas fa-user-tag text-primary"></i> User Account</h6>
                        <p class="text-muted">A user account will be created automatically with login credentials.</p>
                    </div>

                    <div class="mb-3">
                        <h6><i class="fas fa-envelope text-warning"></i> Email Notification</h6>
                        <p class="text-muted">Login credentials will be sent to the employee's email address.</p>
                    </div>
                </div>
            </div>

            <!-- Position Guide -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Position Guide</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">Driver</h6>
                        <p class="text-muted small">Primary machine operator responsible for driving and operating vehicles.</p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-info">Driver Assistant</h6>
                        <p class="text-muted small">Supports driver operations and assists with machine maintenance.</p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-success">Machine Operator</h6>
                        <p class="text-muted small">Specialized operator for specific types of machinery.</p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-warning">Supervisor</h6>
                        <p class="text-muted small">Oversees operations and manages team activities.</p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-danger">Technician</h6>
                        <p class="text-muted small">Maintains and repairs machinery and equipment.</p>
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
                            <i class="fas fa-list me-2"></i>View All Employees
                        </a>
                        <a href="../machines/" class="list-group-item list-group-item-action">
                            <i class="fas fa-truck me-2"></i>Manage Machines
                        </a>
                        <a href="../contracts/" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-contract me-2"></i>Manage Contracts
                        </a>
                        <a href="../attendance/" class="list-group-item list-group-item-action">
                            <i class="fas fa-clock me-2"></i>Track Attendance
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('employeeForm');
    const nameInput = document.getElementById('name');
    const positionSelect = document.getElementById('position');
    const monthlySalaryInput = document.getElementById('monthly_salary');
    const employeeCodePreview = document.getElementById('employee_code_preview');
    const dailyRateDisplay = document.getElementById('daily_rate_display');

    // Real-time employee code preview
    function updateEmployeeCodePreview() {
        const name = nameInput.value.trim();
        const position = positionSelect.value;

        if (name && position) {
            const nameParts = name.split(' ');
            const firstName = nameParts[0];
            const lastName = nameParts.length > 1 ? nameParts[1] : '';
            const preview = 'EMP' + firstName.substring(0, 3).toUpperCase() + lastName.substring(0, 3).toUpperCase() + '001';
            employeeCodePreview.value = preview;
        }
    }

    // Real-time salary calculation
    function updateDailyRate() {
        const salary = parseFloat(monthlySalaryInput.value) || 0;
        const dailyRate = salary / 30;
        dailyRateDisplay.value = dailyRate.toFixed(2) + ' per day';
    }

    nameInput.addEventListener('input', updateEmployeeCodePreview);
    positionSelect.addEventListener('change', updateEmployeeCodePreview);
    monthlySalaryInput.addEventListener('input', updateDailyRate);

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

        // Salary validation
        const salary = parseFloat(monthlySalaryInput.value);
        if (monthlySalaryInput.value && (isNaN(salary) || salary <= 0)) {
            isValid = false;
            monthlySalaryInput.classList.add('is-invalid');

            if (!monthlySalaryInput.nextElementSibling || !monthlySalaryInput.nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'Salary must be a positive number.';
                monthlySalaryInput.parentNode.appendChild(errorDiv);
            }
        }

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

    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Real-time validation
    const inputs = form.querySelectorAll('input, select');
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

        // Salary validation
        if (field.name === 'monthly_salary' && value) {
            const val = parseFloat(value);
            if (isNaN(val) || val <= 0) {
                isValid = false;
                errorMessage = 'Salary must be a positive number.';
            }
        }

        // Email validation
        if (field.type === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
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

<?php require_once '../../../includes/footer.php'; ?>