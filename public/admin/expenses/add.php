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
            <i class="fas fa-plus"></i> Add New Expense
        </h1>
        <div>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Expenses
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
            <h6 class="m-0 font-weight-bold text-primary">Expense Information</h6>
        </div>
        <div class="card-body">
            <form method="POST" id="expenseForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="category" class="form-label">Category *</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Select expense type...</option>
                                <option value="fuel">Fuel</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="repairs">Repairs</option>
                                <option value="supplies">Supplies</option>
                                <option value="utilities">Utilities</option>
                                <option value="rent">Rent</option>
                                <option value="insurance">Insurance</option>
                                <option value="licenses">Licenses</option>
                                <option value="transportation">Transportation</option>
                                <option value="meals">Meals</option>
                                <option value="office">Office Expenses</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount *</label>
                            <input type="number" class="form-control" id="amount" name="amount" 
                                   step="0.01" min="0.01" required
                                   value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="currency" class="form-label">Currency *</label>
                            <select class="form-control" id="currency" name="currency" required>
                                <option value="USD" <?php echo ($_POST['currency'] ?? 'USD') === 'USD' ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                <option value="AFN" <?php echo ($_POST['currency'] ?? '') === 'AFN' ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                <option value="EUR" <?php echo ($_POST['currency'] ?? '') === 'EUR' ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                <option value="GBP" <?php echo ($_POST['currency'] ?? '') === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                                <option value="JPY" <?php echo ($_POST['currency'] ?? '') === 'JPY' ? 'selected' : ''; ?>>JPY - Japanese Yen (¥)</option>
                                <option value="CAD" <?php echo ($_POST['currency'] ?? '') === 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar (C$)</option>
                                <option value="AUD" <?php echo ($_POST['currency'] ?? '') === 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar (A$)</option>
                                <option value="CHF" <?php echo ($_POST['currency'] ?? '') === 'CHF' ? 'selected' : ''; ?>>CHF - Swiss Franc (CHF)</option>
                                <option value="CNY" <?php echo ($_POST['currency'] ?? '') === 'CNY' ? 'selected' : ''; ?>>CNY - Chinese Yuan (¥)</option>
                                <option value="INR" <?php echo ($_POST['currency'] ?? '') === 'INR' ? 'selected' : ''; ?>>INR - Indian Rupee (₹)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="expense_date" class="form-label">Expense Date *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Payment Method</label>
                            <select class="form-control" id="payment_method" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                   placeholder="Optional reference number">
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="4" placeholder="Enter detailed description of the expense..." required></textarea>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Expense
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
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
        alert('Amount must be greater than 0.');
        return false;
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>