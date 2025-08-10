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
        $required_fields = ['employee_id', 'start_date', 'end_date', 'leave_type'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }
        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        if ($start_date > $end_date) {
            throw new Exception("End date must be after start date.");
        }

        $employee_id = (int)$_POST['employee_id'];
        $leave_type = trim($_POST['leave_type']);
        $notes = trim($_POST['notes'] ?? '');

        // Start transaction
        $conn->beginTransaction();
        // Insert leave records for each day in range (all days business days for drivers covered in employee page; here we mark all days)
        $insert = $conn->prepare("INSERT INTO employee_attendance (company_id, employee_id, date, status, leave_type, notes, created_at) SELECT ?, ?, ?, 'leave', ?, ?, NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM employee_attendance ea WHERE ea.company_id = ? AND ea.employee_id = ? AND ea.date = ?)");
        $cur = new DateTime($start_date);
        $end = new DateTime($end_date);
        while ($cur <= $end) {
            $d = $cur->format('Y-m-d');
            $insert->execute([$company_id, $employee_id, $d, $leave_type, $notes, $company_id, $employee_id, $d]);
            $cur->add(new DateInterval('P1D'));
        }

        // Commit transaction
        $conn->commit();

        $success = "Leave days added successfully!";

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
            <i class="fas fa-calendar-times"></i> Add Leave
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

    <!-- Add Leave Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Leave Details</h6>
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
                            <label for="leave_type" class="form-label">Leave Type *</label>
                            <select class="form-control" id="leave_type" name="leave_type" required>
                                <option value="">Select Leave Type</option>
                                <option value="sick" <?php echo (($_POST['leave_type'] ?? '')==='sick')?'selected':''; ?>>Sick</option>
                                <option value="vacation" <?php echo (($_POST['leave_type'] ?? '')==='vacation')?'selected':''; ?>>Vacation</option>
                                <option value="personal" <?php echo (($_POST['leave_type'] ?? '')==='personal')?'selected':''; ?>>Personal</option>
                                <option value="emergency" <?php echo (($_POST['leave_type'] ?? '')==='emergency')?'selected':''; ?>>Emergency</option>
                                <option value="unpaid" <?php echo (($_POST['leave_type'] ?? '')==='unpaid')?'selected':''; ?>>Unpaid</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-01')); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Reason</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Leave
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>