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

// Get available employees
$stmt = $conn->prepare("SELECT e.id, e.employee_code, e.name, e.position FROM employees e WHERE e.company_id = ? AND e.status = 'active' ORDER BY e.name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'date', 'check_in_time'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate date
        $date = $_POST['date'];
        $today = date('Y-m-d');
        if ($date > $today) {
            throw new Exception("Attendance date cannot be in the future.");
        }

        // Check if attendance already exists for this employee on this date
        $stmt = $conn->prepare("SELECT id FROM employee_attendance WHERE company_id = ? AND employee_id = ? AND date = ?");
        $stmt->execute([$company_id, $_POST['employee_id'], $date]);
        if ($stmt->fetch()) {
            throw new Exception("Attendance record already exists for this employee on this date.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Create attendance record
        $stmt = $conn->prepare("
            INSERT INTO employee_attendance (
                company_id, employee_id, date, check_in_time, check_out_time,
                hours_worked, status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $check_out_time = $_POST['check_out_time'] ?? null;
        $hours_worked = null;
        
        // Calculate hours worked if both check-in and check-out times are provided
        if ($check_out_time) {
            $check_in = strtotime($_POST['check_in_time']);
            $check_out = strtotime($check_out_time);
            $hours_worked = round(($check_out - $check_in) / 3600, 2);
        }

        $status = $check_out_time ? 'completed' : 'present';

        $stmt->execute([
            $company_id,
            $_POST['employee_id'],
            $date,
            $_POST['check_in_time'],
            $check_out_time,
            $hours_worked,
            $status,
            $_POST['notes'] ?? ''
        ]);

        $attendance_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Attendance record added successfully!";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";

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
            <i class="fas fa-clock"></i> <?php echo __('add_attendance'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_attendance'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Add Attendance Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('attendance_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="employee_id" class="form-label"><?php echo __('employee'); ?> *</label>
                            <select class="form-control" id="employee_id" name="employee_id" required>
                                <option value=""><?php echo __('select_employee'); ?></option>
                                <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['id']; ?>" <?php echo (isset($_POST['employee_id']) && $_POST['employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['name'] . ' (' . $employee['position'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="date" class="form-label"><?php echo __('date'); ?> *</label>
                            <input type="date" class="form-control" id="date" name="date" 
                                   value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="check_in_time" class="form-label"><?php echo __('check_in_time'); ?> *</label>
                            <input type="time" class="form-control" id="check_in_time" name="check_in_time" 
                                   value="<?php echo htmlspecialchars($_POST['check_in_time'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="check_out_time" class="form-label"><?php echo __('check_out_time'); ?></label>
                            <input type="time" class="form-control" id="check_out_time" name="check_out_time" 
                                   value="<?php echo htmlspecialchars($_POST['check_out_time'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('add_attendance'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>