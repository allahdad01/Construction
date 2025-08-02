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

// Get employee ID from URL
$employee_id = (int)($_GET['id'] ?? 0);

if (!$employee_id) {
    header('Location: index.php');
    exit;
}

// Get employee details
$stmt = $conn->prepare("
    SELECT e.*, u.email as user_email, u.status as user_status
    FROM employees e 
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.id = ? AND e.company_id = ?
");
$stmt->execute([$employee_id, $company_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'employee_type', 'monthly_salary'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate salary
        if (!is_numeric($_POST['monthly_salary']) || $_POST['monthly_salary'] <= 0) {
            throw new Exception("Monthly salary must be a positive number.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update employee record
        $stmt = $conn->prepare("
            UPDATE employees SET 
                first_name = ?, 
                last_name = ?, 
                employee_type = ?, 
                email = ?, 
                phone = ?, 
                address = ?, 
                monthly_salary = ?, 
                daily_rate = ?, 
                status = ?,
                updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $daily_rate = $_POST['monthly_salary'] / 30; // Calculate daily rate
        
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['employee_type'],
            $_POST['email'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['monthly_salary'],
            $daily_rate,
            $_POST['status'] ?? 'active',
            $employee_id,
            $company_id
        ]);

        // Update user account if email changed
        if ($employee['user_id'] && !empty($_POST['email']) && $_POST['email'] !== $employee['email']) {
            // Check if new email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$_POST['email'], $employee['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists in the system.");
            }

            // Update user email
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$_POST['email'], $employee['user_id']]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Employee updated successfully!";
        
        // Redirect to view page after 2 seconds
        header("refresh:2;url=view.php?id=$employee_id");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> Edit Employee
        </h1>
        <div class="d-flex">
            <a href="view.php?id=<?php echo $employee_id; ?>" class="btn btn-info me-2">
                <i class="fas fa-eye"></i> View Employee
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Employees
            </a>
        </div>
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

    <!-- Edit Employee Form -->
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
                                           value="<?php echo htmlspecialchars($employee['first_name']); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($employee['last_name']); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($employee['email']); ?>">
                                    <div class="form-text">This will update the user account email if changed.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($employee['phone']); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="employee_type" class="form-label">Employee Type *</label>
                                    <select class="form-control" id="employee_type" name="employee_type" required>
                                        <option value="">Select Type</option>
                                        <option value="driver" <?php echo $employee['employee_type'] === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo $employee['employee_type'] === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label">Monthly Salary *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" 
                                               value="<?php echo htmlspecialchars($employee['monthly_salary']); ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                    <div class="form-text">Daily rate will be calculated automatically (Monthly Salary ÷ 30)</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="active" <?php echo $employee['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $employee['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Employee Code</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['employee_code']); ?>" readonly>
                                    <div class="form-text">Employee code cannot be changed.</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Daily Rate</label>
                                    <input type="text" class="form-control" id="daily_rate_display" 
                                           value="$<?php echo number_format($employee['daily_rate'], 2); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Created Date</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('M j, Y', strtotime($employee['created_at'])); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Employee
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Employee Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Summary</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                            <span class="text-white font-weight-bold" style="font-size: 1.5rem;">
                                <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <h5 class="mt-2"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($employee['employee_code']); ?></p>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <h6 class="text-success">$<?php echo number_format($employee['monthly_salary'], 2); ?></h6>
                            <small class="text-muted">Monthly Salary</small>
                        </div>
                        <div class="col-6">
                            <h6 class="text-info">$<?php echo number_format($employee['daily_rate'], 2); ?></h6>
                            <small class="text-muted">Daily Rate</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-2">
                        <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $employee['employee_type'])); ?>
                    </div>
                    <div class="mb-2">
                        <strong>Status:</strong> 
                        <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($employee['status']); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong>User Account:</strong> 
                        <?php if ($employee['user_id']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">No Account</span>
                        <?php endif; ?>
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
                        <a href="view.php?id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-eye me-2"></i>View Employee Details
                        </a>
                        <a href="../attendance/?employee_id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-clock me-2"></i>View Attendance
                        </a>
                        <a href="../salary-payments/?employee_id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-money-bill me-2"></i>Salary Payments
                        </a>
                        <a href="../contracts/?employee_id=<?php echo $employee_id; ?>" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-contract me-2"></i>View Contracts
                        </a>
                    </div>
                </div>
            </div>

            <!-- Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><i class="fas fa-info-circle text-info"></i> Employee Code</h6>
                        <p class="text-muted">Employee codes are automatically generated and cannot be changed.</p>
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
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('employeeForm');
    const monthlySalaryInput = document.getElementById('monthly_salary');
    const dailyRateDisplay = document.getElementById('daily_rate_display');

    // Real-time salary calculation
    monthlySalaryInput.addEventListener('input', function() {
        const salary = parseFloat(this.value) || 0;
        const dailyRate = salary / 30;
        dailyRateDisplay.value = '$' + dailyRate.toFixed(2);
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
});
</script>

<?php require_once '../../includes/footer.php'; ?>