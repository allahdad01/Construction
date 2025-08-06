<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin', 'driver', 'driver_assistant']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Include centralized currency helper
require_once '../../../config/currency_helper.php';

// Get contract ID from URL
$contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;

if (!$contract_id) {
    header('Location: index.php');
    exit();
}

// Get contract details
$stmt = $conn->prepare("
    SELECT c.*, p.name as project_name, p.project_code, m.name as machine_name, m.machine_code
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN machines m ON c.machine_id = m.id
    WHERE c.id = ? AND c.company_id = ?
");
$stmt->execute([$contract_id, getCurrentCompanyId()]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: index.php');
    exit();
}

// Get employees for this company
$stmt = $conn->prepare("
    SELECT id, name, employee_code, position 
    FROM employees 
    WHERE company_id = ? AND status = 'active'
    ORDER BY name
");
$stmt->execute([getCurrentCompanyId()]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $hours_worked = (float)($_POST['hours_worked'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if (empty($date)) {
        $error = 'Please select a date.';
    } elseif ($employee_id <= 0) {
        $error = 'Please select an employee.';
    } elseif ($hours_worked <= 0) {
        $error = 'Hours worked must be greater than 0.';
    } elseif ($hours_worked > 24) {
        $error = 'Hours worked cannot exceed 24 hours per day.';
    } else {
        // Check if entry already exists for this date and employee
        $stmt = $conn->prepare("
            SELECT id FROM working_hours 
            WHERE contract_id = ? AND employee_id = ? AND date = ? AND company_id = ?
        ");
        $stmt->execute([$contract_id, $employee_id, $date, getCurrentCompanyId()]);
        
        if ($stmt->fetch()) {
            $error = 'An entry already exists for this employee on this date.';
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO working_hours (company_id, contract_id, machine_id, employee_id, date, hours_worked, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    getCurrentCompanyId(),
                    $contract_id,
                    $contract['machine_id'],
                    $employee_id,
                    $date,
                    $hours_worked,
                    $notes
                ]);
                
                $success = 'Work hours added successfully!';
                
                // Clear form data
                $_POST = [];
                
            } catch (Exception $e) {
                $error = 'Failed to add work hours: ' . $e->getMessage();
            }
        }
    }
}

// Calculate rate per hour for display
$rate_per_hour = 0;
if ($contract['contract_type'] === 'hourly') {
    $rate_per_hour = $contract['rate_amount'];
} elseif ($contract['contract_type'] === 'daily') {
    $rate_per_hour = $contract['rate_amount'] / $contract['working_hours_per_day'];
} elseif ($contract['contract_type'] === 'monthly') {
    $rate_per_hour = $contract['rate_amount'] / ($contract['total_hours_required'] ?: 270);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Add Work Hours</h1>
        <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Timesheet
        </a>
    </div>

    <!-- Contract Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contract Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract:</strong></td>
                            <td><?php echo htmlspecialchars($contract['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Project:</strong></td>
                            <td><?php echo htmlspecialchars($contract['project_name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Machine:</strong></td>
                            <td><?php echo htmlspecialchars($contract['machine_name']); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract Type:</strong></td>
                            <td>
                                <span class="badge <?php 
                                    echo $contract['contract_type'] === 'hourly' ? 'bg-primary' : 
                                        ($contract['contract_type'] === 'daily' ? 'bg-success' : 'bg-info'); 
                                ?>">
                                    <?php echo ucfirst($contract['contract_type']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Rate:</strong></td>
                            <td><?php echo formatCurrencyAmount($contract['rate_amount'], $contract['currency'] ?? 'USD'); ?> per <?php echo $contract['contract_type'] === 'hourly' ? 'hour' : ($contract['contract_type'] === 'daily' ? 'day' : 'month'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Rate per Hour:</strong></td>
                            <td><strong><?php echo formatCurrencyAmount($rate_per_hour, $contract['currency'] ?? 'USD'); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
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
                    <h6 class="m-0 font-weight-bold text-primary">Add Work Hours</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date" class="form-label">
                                        <i class="fas fa-calendar"></i> Date *
                                    </label>
                                    <input type="date" class="form-control" id="date" name="date" 
                                           value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" 
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">
                                        <i class="fas fa-user"></i> Employee *
                                    </label>
                                    <select class="form-control" id="employee_id" name="employee_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>" 
                                                    <?php echo ($_POST['employee_id'] ?? '') == $employee['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($employee['name']); ?> 
                                                (<?php echo htmlspecialchars($employee['employee_code']); ?>) - 
                                                <?php echo ucfirst($employee['position']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hours_worked" class="form-label">
                                        <i class="fas fa-clock"></i> Hours Worked *
                                    </label>
                                    <input type="number" class="form-control" id="hours_worked" name="hours_worked" 
                                           value="<?php echo htmlspecialchars($_POST['hours_worked'] ?? ''); ?>" 
                                           step="0.5" min="0.5" max="24" required>
                                    <small class="text-muted">Enter hours worked (0.5 to 24 hours)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="daily_amount" class="form-label">
                                        <i class="fas fa-dollar-sign"></i> Daily Amount
                                    </label>
                                    <input type="text" class="form-control" id="daily_amount" readonly>
                                    <small class="text-muted">Calculated automatically</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note"></i> Notes
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Enter any notes about the work done..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary me-md-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Work Hours
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
                    <h6>Rate Calculation</h6>
                    <p><strong>Hourly Rate:</strong> <?php echo formatCurrency($rate_per_hour); ?> per hour</p>
                    <p><strong>Daily Rate:</strong> <?php echo formatCurrency($contract['rate_amount']); ?> per day</p>
                    <p><strong>Working Hours/Day:</strong> <?php echo $contract['working_hours_per_day']; ?> hours</p>
                    
                    <hr>
                    
                    <h6>Contract Details</h6>
                    <p><strong>Type:</strong> <?php echo ucfirst($contract['contract_type']); ?></p>
                    <p><strong>Required Hours:</strong> <?php echo $contract['total_hours_required'] ?: 'N/A'; ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $contract['status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo ucfirst($contract['status']); ?>
                        </span>
                    </p>
                    
                    <hr>
                    
                    <h6>Guidelines</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Maximum 24 hours per day</li>
                        <li><i class="fas fa-check text-success"></i> Minimum 0.5 hours per entry</li>
                        <li><i class="fas fa-check text-success"></i> One entry per employee per day</li>
                        <li><i class="fas fa-check text-success"></i> Amount calculated automatically</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Calculate daily amount when hours change
    $('#hours_worked').on('input', function() {
        var hours = parseFloat($(this).val()) || 0;
        var ratePerHour = <?php echo $rate_per_hour; ?>;
        var dailyAmount = hours * ratePerHour;
        
        $('#daily_amount').val('$' + dailyAmount.toFixed(2));
    });
    
    // Trigger calculation on page load
    $('#hours_worked').trigger('input');
});
</script>

<?php require_once '../../../includes/footer.php'; ?>