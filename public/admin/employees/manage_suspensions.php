<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check authentication and authorization
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

// Initialize variables
$error = '';
$success = '';

// Handle suspension actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $action = $_POST['action'] ?? '';
        $employee_id = (int)($_POST['employee_id'] ?? 0);
        
        if ($employee_id <= 0) {
            throw new Exception("Invalid employee ID");
        }

        // Start transaction
        $conn->beginTransaction();

        if ($action === 'create_suspension') {
            // Validate suspension details
            $suspension_start_date = $_POST['suspension_start_date'] ?? null;
            $suspension_end_date = $_POST['suspension_end_date'] ?? null;
            $suspension_reason = $_POST['suspension_reason'] ?? null;

            if (empty($suspension_start_date)) {
                throw new Exception("Suspension start date is required");
            }

            // Prepare suspension data
            $suspension_data = [
                'employee_id' => $employee_id,
                'company_id' => $company_id,
                'suspension_start_date' => $suspension_start_date,
                'suspension_end_date' => $suspension_end_date,
                'reason' => $suspension_reason,
                'status' => $suspension_end_date ? 'active' : 'active'
            ];

            // Insert suspension record
            $stmt = $conn->prepare("
                INSERT INTO employee_work_suspensions 
                (employee_id, company_id, suspension_start_date, suspension_end_date, reason, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $suspension_data['employee_id'],
                $suspension_data['company_id'],
                $suspension_data['suspension_start_date'],
                $suspension_data['suspension_end_date'],
                $suspension_data['reason'],
                $suspension_data['status']
            ]);

            // Update employee status if suspension is active
            $update_stmt = $conn->prepare("
                UPDATE employees 
                SET status = 'inactive', 
                    updated_at = NOW() 
                WHERE id = ? AND company_id = ?
            ");
            $update_stmt->execute([$employee_id, $company_id]);

            $success = "Suspension created successfully.";
        } elseif ($action === 'end_suspension') {
            // End an existing suspension
            $stmt = $conn->prepare("
                UPDATE employee_work_suspensions 
                SET status = 'ended', 
                    suspension_end_date = CURDATE(),
                    updated_at = NOW()
                WHERE employee_id = ? 
                AND company_id = ? 
                AND status = 'active'
            ");
            $stmt->execute([$employee_id, $company_id]);

            // Reactivate employee
            $update_stmt = $conn->prepare("
                UPDATE employees 
                SET status = 'active', 
                    updated_at = NOW() 
                WHERE id = ? AND company_id = ?
            ");
            $update_stmt->execute([$employee_id, $company_id]);

            $success = "Suspension ended successfully.";
        }

        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Fetch employees with active or recent suspensions
$stmt = $conn->prepare("
    SELECT 
        e.id, 
        e.employee_code, 
        e.name,
        e.status AS employee_status,
        s.id AS suspension_id,
        s.suspension_start_date,
        s.suspension_end_date,
        s.status AS suspension_status,
        s.reason AS suspension_reason
    FROM employees e
    LEFT JOIN employee_work_suspensions s ON e.id = s.employee_id AND s.status = 'active'
    WHERE e.company_id = ?
    ORDER BY s.suspension_start_date DESC
");
$stmt->execute([$company_id]);
$employees_with_suspensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active employees for suspension creation
$active_employees_stmt = $conn->prepare("
    SELECT id, employee_code, name 
    FROM employees 
    WHERE company_id = ? AND status = 'active'
    ORDER BY name
");
$active_employees_stmt->execute([$company_id]);
$active_employees = $active_employees_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Employee Suspensions Management</h1>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Create New Suspension</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_suspension">
                        
                        <div class="form-group">
                            <label for="employee_id">Select Employee</label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value="">Select an employee</option>
                                <?php foreach ($active_employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="suspension_start_date">Suspension Start Date</label>
                            <input type="date" class="form-control" id="suspension_start_date" name="suspension_start_date" required>
                        </div>

                        <div class="form-group">
                            <label for="suspension_end_date">Suspension End Date (Optional)</label>
                            <input type="date" class="form-control" id="suspension_end_date" name="suspension_end_date">
                        </div>

                        <div class="form-group">
                            <label for="suspension_reason">Suspension Reason</label>
                            <textarea class="form-control" id="suspension_reason" name="suspension_reason" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Create Suspension</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Active Suspensions</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($employees_with_suspensions)): ?>
                        <p class="text-muted">No active suspensions found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Suspension Start</th>
                                        <th>Suspension End</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees_with_suspensions as $employee): ?>
                                        <?php if ($employee['suspension_id']): ?>
                                            <tr>
                                                <td>
                                                    <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['name']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['suspension_start_date']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['suspension_end_date'] ?? 'Not Set'); ?></td>
                                                <td><?php echo htmlspecialchars($employee['suspension_reason'] ?? 'No reason provided'); ?></td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="action" value="end_suspension">
                                                        <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to end this suspension?');">
                                                            End Suspension
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>
