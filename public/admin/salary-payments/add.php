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
$stmt = $conn->prepare("SELECT e.id, e.employee_code, e.name, e.position, e.monthly_salary FROM employees e WHERE e.company_id = ? AND e.status = 'active' ORDER BY e.name");
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

        // Validate amount
        if (!is_numeric($_POST['amount_paid']) || $_POST['amount_paid'] <= 0) {
            throw new Exception("Amount paid must be a positive number.");
        }

        // Validate payment date
        $payment_date = $_POST['payment_date'];
        $today = date('Y-m-d');
        if ($payment_date > $today) {
            throw new Exception("Payment date cannot be in the future.");
        }

        // Generate payment code
        $payment_code = generateSalaryPaymentCode($company_id);

        // Add currency column if it doesn't exist
        try {
            $conn->exec("ALTER TABLE salary_payments ADD COLUMN currency VARCHAR(3) DEFAULT 'USD' AFTER total_amount");
        } catch (Exception $e) {
            // Column might already exist, ignore error
        }

        // Start transaction
        $conn->beginTransaction();

        // Create salary payment record
        $stmt = $conn->prepare("
            INSERT INTO salary_payments (
                company_id, payment_code, employee_id, payment_date,
                amount_paid, currency, payment_method, notes, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
        ");

        $stmt->execute([
            $company_id,
            $payment_code,
            $_POST['employee_id'],
            $payment_date,
            $_POST['amount_paid'],
            $_POST['currency'] ?? 'USD',
            $_POST['payment_method'] ?? 'cash',
            $_POST['notes'] ?? ''
        ]);

        $payment_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Salary payment added successfully! Payment Code: $payment_code";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate salary payment code
function generateSalaryPaymentCode($company_id) {
    global $conn;

    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];

    // Get next payment number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM salary_payments WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $next_number = $count + 1;
    return strtoupper($company_code) . 'SAL' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill-wave"></i> <?php echo __('add_salary_payment'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_salary_payments'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Add Salary Payment Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('salary_payment_details'); ?></h6>
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
                                <option value="<?php echo $employee['id']; ?>" data-salary="<?php echo $displaySalary; ?>" data-worked-days="<?php echo $workedDays; ?>" data-is-driver="<?php echo $isDriver ? '1':'0'; ?>" <?php echo (isset($_POST['employee_id']) && $_POST['employee_id'] == $employee['id']) ? 'selected' : ''; ?>>
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
                                   value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="amount_paid" class="form-label"><?php echo __('amount_paid'); ?> *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount_paid" name="amount_paid" 
                                   value="<?php echo htmlspecialchars($_POST['amount_paid'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?> *</label>
                            <select class="form-control" id="currency" name="currency" required>
                                <option value="USD" <?php echo (($_POST['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                <option value="EUR" <?php echo (($_POST['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                <option value="GBP" <?php echo (($_POST['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                                <option value="JPY" <?php echo (($_POST['currency'] ?? '') == 'JPY') ? 'selected' : ''; ?>>JPY - Japanese Yen (¥)</option>
                                <option value="CAD" <?php echo (($_POST['currency'] ?? '') == 'CAD') ? 'selected' : ''; ?>>CAD - Canadian Dollar (C$)</option>
                                <option value="AUD" <?php echo (($_POST['currency'] ?? '') == 'AUD') ? 'selected' : ''; ?>>AUD - Australian Dollar (A$)</option>
                                <option value="CHF" <?php echo (($_POST['currency'] ?? '') == 'CHF') ? 'selected' : ''; ?>>CHF - Swiss Franc (CHF)</option>
                                <option value="CNY" <?php echo (($_POST['currency'] ?? '') == 'CNY') ? 'selected' : ''; ?>>CNY - Chinese Yuan (¥)</option>
                                <option value="INR" <?php echo (($_POST['currency'] ?? '') == 'INR') ? 'selected' : ''; ?>>INR - Indian Rupee (₹)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="payment_date" class="form-label"><?php echo __('payment_date'); ?> *</label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                   value="<?php echo htmlspecialchars($_POST['payment_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?></label>
                            <select class="form-control" id="payment_method" name="payment_method">
                                <option value="cash" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'cash') ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                <option value="bank_transfer" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                <option value="check" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'check') ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                                <option value="mobile_money" <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == 'mobile_money') ? 'selected' : ''; ?>><?php echo __('mobile_money'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('add_salary_payment'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-fill amount based on selected employee
const employeeSelect = document.getElementById('employee_id');
const amountInput = document.getElementById('amount_paid');
if (employeeSelect && amountInput) {
  employeeSelect.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const isDriver = opt.getAttribute('data-is-driver') === '1';
    const salary = parseFloat(opt.getAttribute('data-salary')) || 0;
    if (isDriver) {
      amountInput.value = salary.toFixed(2);
    } else {
      amountInput.value = salary.toFixed(2);
    }
  });
}
</script>
<?php require_once '../../../includes/footer.php'; ?>