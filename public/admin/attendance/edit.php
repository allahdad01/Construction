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

// Get attendance ID from URL
$attendance_id = $_GET['id'] ?? null;

if (!$attendance_id) {
    header('Location: index.php');
    exit;
}

// Get attendance details
$stmt = $conn->prepare("
    SELECT ea.*, e.name as employee_name
    FROM employee_attendance ea 
    LEFT JOIN employees e ON ea.employee_id = e.id
    WHERE ea.id = ? AND ea.company_id = ?
");
$stmt->execute([$attendance_id, $company_id]);
$attendance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attendance) {
    header('Location: index.php');
    exit;
}

// Get employees for dropdown
$stmt = $conn->prepare("SELECT id, employee_code, name, position FROM employees WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'date', 'status'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate date
        $date = $_POST['date'];
        if (!strtotime($date)) {
            throw new Exception("Invalid date format.");
        }

        // Start transaction
        $conn->beginTransaction();

        $check_out_time = $_POST['check_out_time'] ?? null;
        $working_hours = null;
        
        // Calculate working hours if both check-in and check-out times are provided
        if (!empty($_POST['check_in_time']) && $check_out_time) {
            $check_in = strtotime($_POST['check_in_time']);
            $check_out = strtotime($check_out_time);
            if ($check_out > $check_in) {
                $working_hours = round(($check_out - $check_in) / 3600, 2);
            }
        }

        // Update attendance record
        $stmt = $conn->prepare("
            UPDATE employee_attendance SET
                employee_id = ?, date = ?, status = ?, check_in_time = ?, check_out_time = ?,
                working_hours = ?, leave_type = ?, notes = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['employee_id'],
            $date,
            $_POST['status'],
            $_POST['check_in_time'] ?: null,
            $check_out_time,
            $working_hours,
            $_POST['leave_type'] ?: null,
            $_POST['notes'] ?? '',
            $attendance_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Attendance record updated successfully!";

        // Refresh attendance data
        $stmt = $conn->prepare("
            SELECT ea.*, e.name as employee_name
            FROM employee_attendance ea 
            LEFT JOIN employees e ON ea.employee_id = e.id
            WHERE ea.id = ? AND ea.company_id = ?
        ");
        $stmt->execute([$attendance_id, $company_id]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$attendance_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_attendance'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $attendance_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_attendance'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_attendance'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Attendance Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo __('edit_attendance_details'); ?> - <?php echo htmlspecialchars($attendance['employee_name']); ?>
            </h6>
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
                                <option value="<?php echo $employee['id']; ?>" <?php echo ($attendance['employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($attendance['date']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?> *</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value=""><?php echo __('select_status'); ?></option>
                                <option value="present" <?php echo ($attendance['status'] == 'present') ? 'selected' : ''; ?>><?php echo __('present'); ?></option>
                                <option value="absent" <?php echo ($attendance['status'] == 'absent') ? 'selected' : ''; ?>><?php echo __('absent'); ?></option>
                                <option value="late" <?php echo ($attendance['status'] == 'late') ? 'selected' : ''; ?>><?php echo __('late'); ?></option>
                                <option value="half_day" <?php echo ($attendance['status'] == 'half_day') ? 'selected' : ''; ?>><?php echo __('half_day'); ?></option>
                                <option value="leave" <?php echo ($attendance['status'] == 'leave') ? 'selected' : ''; ?>><?php echo __('leave'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="leave_type" class="form-label"><?php echo __('leave_type'); ?></label>
                            <select class="form-control" id="leave_type" name="leave_type">
                                <option value=""><?php echo __('select_leave_type'); ?></option>
                                <option value="sick" <?php echo ($attendance['leave_type'] == 'sick') ? 'selected' : ''; ?>><?php echo __('sick_leave'); ?></option>
                                <option value="annual" <?php echo ($attendance['leave_type'] == 'annual') ? 'selected' : ''; ?>><?php echo __('annual_leave'); ?></option>
                                <option value="emergency" <?php echo ($attendance['leave_type'] == 'emergency') ? 'selected' : ''; ?>><?php echo __('emergency_leave'); ?></option>
                                <option value="maternity" <?php echo ($attendance['leave_type'] == 'maternity') ? 'selected' : ''; ?>><?php echo __('maternity_leave'); ?></option>
                                <option value="paternity" <?php echo ($attendance['leave_type'] == 'paternity') ? 'selected' : ''; ?>><?php echo __('paternity_leave'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="check_in_time" class="form-label"><?php echo __('check_in_time'); ?></label>
                            <input type="time" class="form-control" id="check_in_time" name="check_in_time" 
                                   value="<?php echo htmlspecialchars($attendance['check_in_time'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="check_out_time" class="form-label"><?php echo __('check_out_time'); ?></label>
                            <input type="time" class="form-control" id="check_out_time" name="check_out_time" 
                                   value="<?php echo htmlspecialchars($attendance['check_out_time'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($attendance['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Current Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-info"><?php echo __('current_information'); ?></h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo __('current_working_hours'); ?>:</strong> 
                                    <?php echo $attendance['working_hours'] ? $attendance['working_hours'] . ' hours' : 'Not calculated'; ?>
                                </p>
                                <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($attendance['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('last_updated'); ?>:</strong> 
                                    <?php echo $attendance['updated_at'] ? formatDateTime($attendance['updated_at']) : __('never'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="view.php?id=<?php echo $attendance_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_attendance'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide leave type based on status
document.getElementById('status').addEventListener('change', function() {
    const leaveTypeField = document.getElementById('leave_type').parentElement;
    if (this.value === 'leave') {
        leaveTypeField.style.display = 'block';
    } else {
        leaveTypeField.style.display = 'none';
        document.getElementById('leave_type').value = '';
    }
});

// Calculate working hours automatically
function calculateHours() {
    const checkIn = document.getElementById('check_in_time').value;
    const checkOut = document.getElementById('check_out_time').value;
    
    if (checkIn && checkOut) {
        const checkInTime = new Date('1970-01-01T' + checkIn + ':00');
        const checkOutTime = new Date('1970-01-01T' + checkOut + ':00');
        
        if (checkOutTime > checkInTime) {
            const diffMs = checkOutTime - checkInTime;
            const diffHours = diffMs / (1000 * 60 * 60);
            console.log(`Working hours: ${diffHours.toFixed(2)}`);
        }
    }
}

document.getElementById('check_in_time').addEventListener('change', calculateHours);
document.getElementById('check_out_time').addEventListener('change', calculateHours);

// Initialize leave type visibility
document.getElementById('status').dispatchEvent(new Event('change'));
</script>

<?php require_once '../../../includes/footer.php'; ?>