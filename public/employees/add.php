<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = $_POST['position'] ?? '';
    $monthly_salary = (float)($_POST['monthly_salary'] ?? 0);
    $hire_date = $_POST['hire_date'] ?? '';
    
    // Validation
    if (empty($name)) {
        $error = 'Name is required';
    } elseif (empty($position)) {
        $error = 'Position is required';
    } elseif ($monthly_salary <= 0) {
        $error = 'Monthly salary must be greater than 0';
    } elseif (empty($hire_date)) {
        $error = 'Hire date is required';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if email already exists
        if (!empty($email)) {
            $stmt = $conn->prepare("SELECT id FROM employees WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already exists';
            }
        }
        
        if (empty($error)) {
            // Generate employee code
            $employee_code = generateCode('EMP');
            
            // Calculate daily rate
            $daily_rate = calculateDailyRate($monthly_salary);
            
            try {
                $stmt = $conn->prepare("
                    INSERT INTO employees (employee_code, name, email, phone, position, monthly_salary, hire_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $employee_code,
                    $name,
                    $email,
                    $phone,
                    $position,
                    $monthly_salary,
                    $hire_date
                ]);
                
                $success = 'Employee added successfully!';
                
                // Clear form data
                $name = $email = $phone = $position = $monthly_salary = $hire_date = '';
                
            } catch (PDOException $e) {
                $error = 'Error adding employee: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add Employee</h1>
        <a href="index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Employees
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Employee Information</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="employeeForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="position" class="form-label">Position *</label>
                                    <select class="form-control" id="position" name="position" required>
                                        <option value="">Select Position</option>
                                        <option value="driver" <?php echo ($position ?? '') === 'driver' ? 'selected' : ''; ?>>Driver</option>
                                        <option value="driver_assistant" <?php echo ($position ?? '') === 'driver_assistant' ? 'selected' : ''; ?>>Driver Assistant</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_salary" class="form-label">Monthly Salary *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="monthly_salary" name="monthly_salary" 
                                               step="0.01" min="0" 
                                               value="<?php echo htmlspecialchars($monthly_salary ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hire_date" class="form-label">Hire Date *</label>
                                    <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                           value="<?php echo htmlspecialchars($hire_date ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="daily_rate" class="form-label">Daily Rate (Auto-calculated)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" id="daily_rate" readonly>
                                    </div>
                                    <small class="text-muted">Calculated as Monthly Salary ÷ 30 days</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="working_days" class="form-label">Working Days (for calculation)</label>
                                    <input type="number" class="form-control" id="working_days" min="1" max="30" value="30">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="total_salary" class="form-label">Total Salary (Auto-calculated)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" id="total_salary" readonly>
                                    </div>
                                    <small class="text-muted">Daily Rate × Working Days</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Employee
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Salary Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Salary Calculation Method</h6>
                        <p class="text-muted small">
                            The company uses a 30-day month system for salary calculations:
                        </p>
                        <ul class="text-muted small">
                            <li>Daily Rate = Monthly Salary ÷ 30</li>
                            <li>Final Salary = Daily Rate × Actual Working Days</li>
                            <li>Example: $15,000 monthly salary = $500 daily rate</li>
                            <li>If terminated on 15th day: $500 × 15 = $7,500</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Position Types</h6>
                        <ul class="text-muted small">
                            <li><strong>Driver:</strong> Primary vehicle operator</li>
                            <li><strong>Driver Assistant:</strong> Supports driver operations</li>
                        </ul>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> All salary calculations are automatically handled by the system based on the 30-day month standard.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Calculate daily rate and total salary when monthly salary changes
    $('#monthly_salary, #working_days').on('input', function() {
        var monthlySalary = parseFloat($('#monthly_salary').val()) || 0;
        var workingDays = parseInt($('#working_days').val()) || 30;
        
        var dailyRate = monthlySalary / 30;
        var totalSalary = dailyRate * workingDays;
        
        $('#daily_rate').val(dailyRate.toFixed(2));
        $('#total_salary').val(totalSalary.toFixed(2));
    });
    
    // Trigger calculation on page load
    $('#monthly_salary').trigger('input');
});
</script>

<?php require_once '../../includes/footer.php'; ?>