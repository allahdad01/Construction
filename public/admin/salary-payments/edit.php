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

// Get payment ID from URL
$payment_id = $_GET['id'] ?? null;

if (!$payment_id) {
    header('Location: index.php');
    exit;
}

// Get payment details
$stmt = $conn->prepare("
    SELECT sp.*, e.name as employee_name
    FROM salary_payments sp 
    LEFT JOIN employees e ON sp.employee_id = e.id
    WHERE sp.id = ? AND sp.company_id = ?
");
$stmt->execute([$payment_id, $company_id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: index.php');
    exit;
}

// Get employees for dropdown
$stmt = $conn->prepare("SELECT id, employee_code, name, position, monthly_salary FROM employees WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to fetch days worked in current month for an employee
function getDaysWorkedThisMonth($employeeId, $companyId) {
    global $conn;
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) FROM employee_attendance WHERE company_id = ? AND employee_id = ? AND status = 'present' AND date BETWEEN ? AND ?");
    $stmt->execute([$companyId, $employeeId, $startOfMonth, $endOfMonth]);
    return (int)$stmt->fetchColumn();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['employee_id', 'payment_date', 'amount_paid'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate payment date
        $payment_date = $_POST['payment_date'];
        if (!strtotime($payment_date)) {
            throw new Exception("Invalid payment date format.");
        }

        // Validate amount
        $amount_paid = floatval($_POST['amount_paid']);
        if ($amount_paid <= 0) {
            throw new Exception("Amount paid must be greater than 0.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update salary payment record
        $stmt = $conn->prepare("
            UPDATE salary_payments SET
                employee_id = ?, payment_date = ?, amount_paid = ?, payment_method = ?, 
                notes = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['employee_id'],
            $payment_date,
            $amount_paid,
            $_POST['payment_method'] ?? 'cash',
            $_POST['notes'] ?? '',
            $_POST['status'] ?? 'completed',
            $payment_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Salary payment updated successfully!";

        // Refresh payment data
        $stmt = $conn->prepare("
            SELECT sp.*, e.name as employee_name
            FROM salary_payments sp 
            LEFT JOIN employees e ON sp.employee_id = e.id
            WHERE sp.id = ? AND sp.company_id = ?
        ");
        $stmt->execute([$payment_id, $company_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$payment_id'; }, 2000);</script>";

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
            <i class="fas fa-edit"></i> <?php echo __('edit_salary_payment'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_payment'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_payments'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Payment Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo __('edit_payment_details'); ?> - <?php echo htmlspecialchars($payment['employee_name']); ?>
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
                                <?php 
                                    $workedDays = getDaysWorkedThisMonth($employee['id'], $company_id);
                                    $isDriver = in_array($employee['position'], ['driver','driver_assistant']);
                                    $displaySalary = $isDriver ? (($employee['monthly_salary'] / 30) * $workedDays) : $employee['monthly_salary'];
                                ?>
                                <option value="<?php echo $employee['id']; ?>" 
                                        <?php echo ($payment['employee_id'] == $employee['id']) ? 'selected' : ''; ?>
                                        data-salary="<?php echo $displaySalary; ?>"
                                        data-worked-days="<?php echo $workedDays; ?>"
                                        data-is-driver="<?php echo $isDriver ? '1':'0'; ?>">
                                    <?php echo htmlspecialchars($employee['employee_code'] . ' - ' . $employee['name'] . ' (' . $employee['position'] . ') - ' . ($isDriver ? ('Worked Days: ' . $workedDays . ' => ') : 'Monthly: ') . formatCurrency($displaySalary)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_date" class="form-label"><?php echo __('payment_date'); ?> *</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                   value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="amount_paid" class="form-label"><?php echo __('amount_paid'); ?> *</label>
                            <input type="number" step="0.01" class="form-control" id="amount_paid" name="amount_paid" 
                                   value="<?php echo htmlspecialchars($payment['amount_paid']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?></label>
                            <select class="form-control" id="payment_method" name="payment_method">
                                <option value="cash" <?php echo ($payment['payment_method'] == 'cash') ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                <option value="bank_transfer" <?php echo ($payment['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                <option value="check" <?php echo ($payment['payment_method'] == 'check') ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                                <option value="mobile_money" <?php echo ($payment['payment_method'] == 'mobile_money') ? 'selected' : ''; ?>><?php echo __('mobile_money'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="pending" <?php echo ($payment['status'] == 'pending') ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                <option value="completed" <?php echo ($payment['status'] == 'completed') ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                <option value="cancelled" <?php echo ($payment['status'] == 'cancelled') ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></textarea>
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
                                <p><strong><?php echo __('payment_code'); ?>:</strong> <?php echo htmlspecialchars($payment['payment_code']); ?></p>
                                <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($payment['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('last_updated'); ?>:</strong> 
                                    <?php echo $payment['updated_at'] ? formatDateTime($payment['updated_at']) : __('never'); ?>
                                </p>
                                <?php if ($payment['total_amount']): ?>
                                <p><strong><?php echo __('total_amount'); ?>:</strong> <?php echo formatCurrency($payment['total_amount']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($payment['payment_month'] && $payment['payment_year']): ?>
                        <hr>
                        <div class="row">
                            <div class="col-md-12">
                                <h6 class="text-info"><?php echo __('payroll_details'); ?></h6>
                                <p><strong><?php echo __('payment_period'); ?>:</strong> 
                                    <?php echo date('F Y', mktime(0, 0, 0, $payment['payment_month'], 1, $payment['payment_year'])); ?>
                                </p>
                                <p><strong><?php echo __('working_days'); ?>:</strong> <?php echo $payment['working_days']; ?> days</p>
                                <p><strong><?php echo __('leave_days'); ?>:</strong> <?php echo $payment['leave_days']; ?> days</p>
                                <p><strong><?php echo __('daily_rate'); ?>:</strong> <?php echo formatCurrency($payment['daily_rate']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-end">
                    <a href="view.php?id=<?php echo $payment_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_payment'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update amount when employee is selected (worked days for drivers/assistants)
document.getElementById('employee_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const salary = parseFloat(opt.getAttribute('data-salary')) || 0;
    document.getElementById('amount_paid').value = salary.toFixed(2);
});
</script>

<?php require_once '../../../includes/footer.php'; ?>