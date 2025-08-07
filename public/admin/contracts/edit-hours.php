<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin', 'driver', 'driver_assistant']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Get working hours ID
$hours_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$hours_id) {
    header('Location: index.php');
    exit();
}

// Get working hours details
$stmt = $conn->prepare("
    SELECT wh.*, c.contract_code, c.currency, c.id as contract_id, p.name as project_name, 
           e.name as employee_name, e.employee_code
    FROM working_hours wh
    JOIN contracts c ON wh.contract_id = c.id
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN employees e ON wh.employee_id = e.id
    WHERE wh.id = ? AND wh.company_id = ?
");
$stmt->execute([$hours_id, getCurrentCompanyId()]);
$working_hours = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$working_hours) {
    header('Location: index.php');
    exit();
}

$contract_id = $working_hours['contract_id'];
$contract_currency = $working_hours['currency'] ?? 'USD';

// Get employees for dropdown
$stmt = $conn->prepare("
    SELECT id, name, employee_code 
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
    } elseif ($hours_worked <= 0 || $hours_worked > 24) {
        $error = 'Hours worked must be between 0.1 and 24.';
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE working_hours 
                SET date = ?, employee_id = ?, hours_worked = ?, notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $date,
                $employee_id,
                $hours_worked,
                $notes,
                $hours_id,
                getCurrentCompanyId()
            ]);
            
            $success = 'Working hours updated successfully!';
            
            // Refresh working hours data
            $stmt = $conn->prepare("
                SELECT wh.*, c.contract_code, c.currency, c.id as contract_id, p.name as project_name, 
                       e.name as employee_name, e.employee_code
                FROM working_hours wh
                JOIN contracts c ON wh.contract_id = c.id
                LEFT JOIN projects p ON c.project_id = p.id
                LEFT JOIN employees e ON wh.employee_id = e.id
                WHERE wh.id = ? AND wh.company_id = ?
            ");
            $stmt->execute([$hours_id, getCurrentCompanyId()]);
            $working_hours = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $error = 'Failed to update working hours: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Working Hours</h1>
        <div>
            <a href="/constract360/construction/public/admin/contracts/timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Timesheet
            </a>
        </div>
    </div>

    <!-- Working Hours Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Working Hours Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract:</strong></td>
                            <td><?php echo htmlspecialchars($working_hours['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Project:</strong></td>
                            <td><?php echo htmlspecialchars($working_hours['project_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Current Employee:</strong></td>
                            <td><?php echo htmlspecialchars($working_hours['employee_name'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Employee Code:</strong></td>
                            <td><?php echo htmlspecialchars($working_hours['employee_code'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Created:</strong></td>
                            <td><?php echo date('M j, Y \a\t g:i A', strtotime($working_hours['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td><?php echo date('M j, Y \a\t g:i A', strtotime($working_hours['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Working Hours Form -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Working Hours Details</h6>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo htmlspecialchars($working_hours['date']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                            <?php echo $employee['id'] == $working_hours['employee_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['name'] . ' (' . $employee['employee_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="hours_worked" class="form-label">Hours Worked <span class="text-danger">*</span></label>
                            <input type="number" step="0.1" min="0.1" max="24" class="form-control" 
                                   id="hours_worked" name="hours_worked" 
                                   value="<?php echo htmlspecialchars($working_hours['hours_worked']); ?>" required>
                            <small class="form-text text-muted">Enter hours in decimal format (e.g., 8.5 for 8 hours 30 minutes)</small>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Additional notes about this work entry"><?php echo htmlspecialchars($working_hours['notes'] ?? ''); ?></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="/constract360/construction/public/admin/contracts/timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Working Hours
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>