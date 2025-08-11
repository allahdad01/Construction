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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['category', 'amount', 'expense_date', 'description'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate amount
        if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
            throw new Exception("Amount must be a positive number.");
        }

        // Generate expense code
        $expense_code = generateExpenseCode($company_id);

        // Start transaction
        $conn->beginTransaction();

        // Create expense record
        $stmt = $conn->prepare("
            INSERT INTO expenses (
                company_id, expense_code, category, amount, currency,
                expense_date, description, payment_method, 
                reference_number, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $expense_code,
            $_POST['category'],
            $_POST['amount'],
            $_POST['currency'] ?? 'USD',
            $_POST['expense_date'],
            $_POST['description'],
            $_POST['payment_method'] ?? 'cash',
            $_POST['reference_number'] ?? null
        ]);

        $expense_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Expense added successfully! Expense Code: $expense_code";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$expense_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate expense code
function generateExpenseCode($company_id) {
    global $conn;

    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];

    // Get next expense number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM expenses WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $next_number = $count + 1;
    return strtoupper($company_code) . 'EXP' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus"></i> <?php echo __('add_new_expense'); ?>
        </h1>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_expenses'); ?>
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

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expense_information'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST" id="expenseForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="category" class="form-label"><?php echo __('category'); ?> *</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value=""><?php echo __('select_expense_type'); ?>...</option>
                                <option value="fuel"><?php echo __('fuel'); ?></option>
                                <option value="maintenance"><?php echo __('maintenance'); ?></option>
                                <option value="repairs"><?php echo __('repairs'); ?></option>
                                <option value="supplies"><?php echo __('supplies'); ?></option>
                                <option value="utilities"><?php echo __('utilities'); ?></option>
                                <option value="rent"><?php echo __('rent'); ?></option>
                                <option value="insurance"><?php echo __('insurance'); ?></option>
                                <option value="licenses"><?php echo __('licenses'); ?></option>
                                <option value="transportation"><?php echo __('transportation'); ?></option>
                                <option value="meals"><?php echo __('meals'); ?></option>
                                <option value="office"><?php echo __('office_expenses'); ?></option>
                                <option value="other"><?php echo __('other'); ?></option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label"><?php echo __('amount'); ?> *</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0.01" required
                                   value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?> *</label>
                            <select class="form-control" id="currency" name="currency" required>
                                <option value="USD" <?php echo ($_POST['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>><?php echo __('usd'); ?> - <?php echo __('us_dollar'); ?> ($)</option>
                                <option value="AFN" <?php echo ($_POST['currency'] ?? '') === 'AFN' ? 'selected' : ''; ?>><?php echo __('afn'); ?> - <?php echo __('afghan_afghani'); ?> (؋)</option>
                                <option value="EUR" <?php echo ($_POST['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>><?php echo __('eur'); ?> - <?php echo __('euro'); ?> (€)</option>
                                <option value="GBP" <?php echo ($_POST['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>><?php echo __('gbp'); ?> - <?php echo __('british_pound'); ?> (£)</option>
                                <option value="JPY" <?php echo ($_POST['currency'] ?? '') === 'JPY' ? 'selected' : ''; ?>><?php echo __('jpy'); ?> - <?php echo __('japanese_yen'); ?> (¥)</option>
                                <option value="CAD" <?php echo ($_POST['currency'] ?? '') === 'CAD' ? 'selected' : ''; ?>><?php echo __('cad'); ?> - <?php echo __('canadian_dollar'); ?> (C$)</option>
                                <option value="AUD" <?php echo ($_POST['currency'] ?? '') === 'AUD' ? 'selected' : ''; ?>><?php echo __('aud'); ?> - <?php echo __('australian_dollar'); ?> (A$)</option>
                                <option value="CHF" <?php echo ($_POST['currency'] ?? '') === 'CHF' ? 'selected' : ''; ?>><?php echo __('chf'); ?> - <?php echo __('swiss_franc'); ?> (CHF)</option>
                                <option value="CNY" <?php echo ($_POST['currency'] ?? '') === 'CNY' ? 'selected' : ''; ?>><?php echo __('cny'); ?> - <?php echo __('chinese_yuan'); ?> (¥)</option>
                                <option value="INR" <?php echo ($_POST['currency'] ?? '') === 'INR' ? 'selected' : ''; ?>><?php echo __('inr'); ?> - <?php echo __('indian_rupee'); ?> (₹)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="expense_date" class="form-label"><?php echo __('expense_date'); ?> *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?></label>
                            <select class="form-control" id="payment_method" name="payment_method">
                                <option value="cash"><?php echo __('cash'); ?></option>
                                <option value="credit_card"><?php echo __('credit_card'); ?></option>
                                <option value="debit_card"><?php echo __('debit_card'); ?></option>
                                <option value="bank_transfer"><?php echo __('bank_transfer'); ?></option>
                                <option value="check"><?php echo __('check'); ?></option>
                                <option value="other"><?php echo __('other'); ?></option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="reference_number" class="form-label"><?php echo __('reference_number'); ?></label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                   placeholder="Optional reference number">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo __('description'); ?> *</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" placeholder="Enter detailed description of the expense..." required></textarea>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo __('add_expense'); ?>
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 5000);

// Form validation
document.getElementById('expenseForm').addEventListener('submit', function(e) {
    const amount = document.getElementById('amount').value;
    if (parseFloat(amount) <= 0) {
        e.preventDefault();
        alert('<?php echo __('amount_must_be_greater_than_0'); ?>');
        return false;
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>