<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'employee_type', 'monthly_salary', 'email'];
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

        // Validate salary
        if (!is_numeric($_POST['monthly_salary']) || $_POST['monthly_salary'] <= 0) {
            throw new Exception("Monthly salary must be a positive number.");
        }

        // Generate employee code
        $employee_code = generateEmployeeCode($company_id);

        // Start transaction
        $conn->beginTransaction();

        // Create user account
        $user_password = password_hash(generateRandomPassword(), PASSWORD_DEFAULT);
        $user_role = $_POST['employee_type'] === 'driver' ? 'driver' : 'driver_assistant';
        
        $stmt = $conn->prepare("
            INSERT INTO users (email, password, role, company_id, status, created_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$_POST['email'], $user_password, $user_role, $company_id]);
        $user_id = $conn->lastInsertId();

        // Create employee record
        $stmt = $conn->prepare("
            INSERT INTO employees (
                company_id, user_id, first_name, last_name, employee_code, 
                employee_type, email, phone, address, monthly_salary, 
                daily_rate, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");

        $daily_rate = $_POST['monthly_salary'] / 30; // Calculate daily rate
        
        $stmt->execute([
            $company_id,
            $user_id,
            $_POST['first_name'],
            $_POST['last_name'],
            $employee_code,
            $_POST['employee_type'],
            $_POST['email'],
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['monthly_salary'],
            $daily_rate
        ]);

        $employee_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Employee added successfully! Employee Code: $employee_code";
        
        // Redirect to view page after 2 seconds
        header("refresh:2;url=view.php?id=$employee_id");
        
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
    return strtoupper($company_code) . 'EMP' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
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
            <?php echo htmlspecialchars($success); ?>
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
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           required>
                                    <div class="form-text">This will be used as login credentials.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="employee_type" class="form-label">Employee Type *</label>
                                    <select class="form-control" id="employee_type" name="employee_type" required>
                                        <option value="">Select Type</option>
                                        <option value="driver" <?php echo ($_POST['employee_type'] ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo ($_POST['employee_type'] ?? '') === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label">Monthly Salary *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" 
                                               value="<?php echo htmlspecialchars($_POST['monthly_salary'] ?? ''); ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                    <div class="form-text">Daily rate will be calculated automatically (Monthly Salary ÷ 30)</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Hire Date</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                           value="<?php echo htmlspecialchars($_POST['hire_date'] ?? date('Y-m-d')); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_phone" class="form-label">Emergency Phone</label>
                                    <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_relationship" class="form-label">Relationship</label>
                                    <input type="text" class="form-control" id="emergency_relationship" name="emergency_relationship" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_relationship'] ?? ''); ?>">
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
                        <h6><i class="fas fa-key text-warning"></i> Login Credentials</h6>
                        <p class="text-muted">A user account will be created with the provided email address. A random password will be generated.</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-calculator text-success"></i> Salary Calculation</h6>
                        <p class="text-muted">Daily rate is calculated as: Monthly Salary ÷ 30 days</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6><i class="fas fa-user-tag text-primary"></i> Employee Types</h6>
                        <ul class="text-muted">
                            <li><strong>Driver:</strong> Primary machine operator</li>
                            <li><strong>Driver Assistant:</strong> Supports driver operations</li>
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
                        <a href="../reports/" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
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
    const monthlySalaryInput = document.getElementById('monthly_salary');
    const employeeTypeSelect = document.getElementById('employee_type');
    const emailInput = document.getElementById('email');

    // Real-time salary calculation
    monthlySalaryInput.addEventListener('input', function() {
        const salary = parseFloat(this.value) || 0;
        const dailyRate = salary / 30;
        
        // Update any daily rate display if needed
        const dailyRateElement = document.getElementById('daily_rate');
        if (dailyRateElement) {
            dailyRateElement.textContent = '$' + dailyRate.toFixed(2);
        }
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Add error message if not exists
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

        // Email validation
        if (field.type === 'email' && value && !isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
        }

        // Salary validation
        if (field.name === 'monthly_salary' && value) {
            const salary = parseFloat(value);
            if (isNaN(salary) || salary <= 0) {
                isValid = false;
                errorMessage = 'Salary must be a positive number.';
            }
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

    // Auto-generate employee code preview
    const firstNameInput = document.getElementById('first_name');
    const lastNameInput = document.getElementById('last_name');
    
    function updateEmployeeCodePreview() {
        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();
        
        if (firstName && lastName) {
            // This would be the actual generation logic
            const preview = 'EMP' + firstName.substring(0, 1).toUpperCase() + lastName.substring(0, 1).toUpperCase() + '001';
            // You could display this in a preview element
        }
    }

    firstNameInput.addEventListener('input', updateEmployeeCodePreview);
    lastNameInput.addEventListener('input', updateEmployeeCodePreview);
});
</script>

<?php require_once '../../includes/footer.php'; ?>