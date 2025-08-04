<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$expense_id = (int)($_GET['id'] ?? 0);

if (!$expense_id) {
    header('Location: index.php');
    exit;
}

// Get expense details - only super admin expenses (company_id = 1)
$stmt = $conn->prepare("
    SELECT e.*, c.company_name 
    FROM expenses e 
    LEFT JOIN companies c ON e.company_id = c.id 
    WHERE e.id = ? AND e.company_id = 1
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['expense_type', 'description', 'amount', 'expense_date', 'payment_method'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate amount
        if (!is_numeric($_POST['amount']) || $_POST['amount'] <= 0) {
            throw new Exception("Amount must be a positive number.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update expense record
        $stmt = $conn->prepare("
            UPDATE expenses SET 
                category = ?, description = ?, amount = ?, expense_date = ?, 
                payment_method = ?, reference_number = ?, notes = ?, updated_at = NOW()
            WHERE id = ? AND company_id = 1
        ");

        $stmt->execute([
            $_POST['expense_type'],
            $_POST['description'],
            $_POST['amount'],
            $_POST['expense_date'],
            $_POST['payment_method'],
            $_POST['reference_number'] ?: null,
            $_POST['notes'] ?: null,
            $expense_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Expense updated successfully!";

        // Redirect to expense view
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$expense_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> Edit Expense
        </h1>
        <a href="view.php?id=<?php echo $expense_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Expense
        </a>
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

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Expense Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="expenseForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expense_type" class="form-label">Expense Type *</label>
                                    <select class="form-control" id="expense_type" name="expense_type" required>
                                        <option value="">Select Type</option>
                                        <option value="office_supplies" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'office_supplies' ? 'selected' : ''; ?>>Office Supplies</option>
                                        <option value="utilities" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'utilities' ? 'selected' : ''; ?>>Utilities</option>
                                        <option value="rent" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'rent' ? 'selected' : ''; ?>>Rent</option>
                                        <option value="maintenance" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="marketing" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'marketing' ? 'selected' : ''; ?>>Marketing</option>
                                        <option value="software" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'software' ? 'selected' : ''; ?>>Software</option>
                                        <option value="travel" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'travel' ? 'selected' : ''; ?>>Travel</option>
                                        <option value="other" <?php echo ($_POST['expense_type'] ?? $expense['category']) === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? $expense['amount']); ?>" 
                                           step="0.01" min="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <input type="text" class="form-control" id="description" name="description" 
                                   value="<?php echo htmlspecialchars($_POST['description'] ?? $expense['description']); ?>" 
                                   placeholder="Brief description of the expense" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expense_date" class="form-label">Expense Date *</label>
                                    <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                           value="<?php echo htmlspecialchars($_POST['expense_date'] ?? $expense['expense_date']); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method *</label>
                                    <select class="form-control" id="payment_method" name="payment_method" required>
                                        <option value="">Select Method</option>
                                        <option value="cash" <?php echo ($_POST['payment_method'] ?? $expense['payment_method']) === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                        <option value="credit_card" <?php echo ($_POST['payment_method'] ?? $expense['payment_method']) === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                                        <option value="bank_transfer" <?php echo ($_POST['payment_method'] ?? $expense['payment_method']) === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                        <option value="check" <?php echo ($_POST['payment_method'] ?? $expense['payment_method']) === 'check' ? 'selected' : ''; ?>>Check</option>
                                        <option value="paypal" <?php echo ($_POST['payment_method'] ?? $expense['payment_method']) === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                        <option value="other" <?php echo ($_POST['payment_method'] ?? $expense['payment_method']) === 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="reference_number" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                   value="<?php echo htmlspecialchars($_POST['reference_number'] ?? $expense['reference_number'] ?? ''); ?>" 
                                   placeholder="Receipt or invoice number">
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes about this expense"><?php echo htmlspecialchars($_POST['notes'] ?? $expense['notes'] ?? ''); ?></textarea>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Current Expense Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Current Expense Info</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Expense Code:</strong> <?php echo htmlspecialchars($expense['expense_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Category:</strong> 
                        <span class="badge bg-info">
                            <?php echo ucwords(str_replace('_', ' ', $expense['category'] ?? 'other')); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Amount:</strong> 
                        <span class="text-danger fw-bold"><?php echo formatCurrency($expense['amount']); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> <?php echo formatDate($expense['expense_date']); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Payment Method:</strong> 
                        <span class="badge bg-secondary">
                            <?php echo ucwords(str_replace('_', ' ', $expense['payment_method'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Information Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Expense Types</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-paperclip me-2"></i>Office Supplies</li>
                            <li><i class="fas fa-bolt me-2"></i>Utilities</li>
                            <li><i class="fas fa-home me-2"></i>Rent</li>
                            <li><i class="fas fa-tools me-2"></i>Maintenance</li>
                            <li><i class="fas fa-bullhorn me-2"></i>Marketing</li>
                            <li><i class="fas fa-laptop me-2"></i>Software</li>
                            <li><i class="fas fa-plane me-2"></i>Travel</li>
                            <li><i class="fas fa-ellipsis-h me-2"></i>Other</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Payment Methods</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-money-bill me-2"></i>Cash</li>
                            <li><i class="fas fa-credit-card me-2"></i>Credit Card</li>
                            <li><i class="fas fa-university me-2"></i>Bank Transfer</li>
                            <li><i class="fas fa-file-invoice me-2"></i>Check</li>
                            <li><i class="fab fa-paypal me-2"></i>PayPal</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Tips</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-info-circle me-2"></i>Keep receipts for all expenses</li>
                            <li><i class="fas fa-info-circle me-2"></i>Use descriptive names</li>
                            <li><i class="fas fa-info-circle me-2"></i>Record expenses promptly</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('expenseForm').addEventListener('submit', function(e) {
    const expenseType = document.getElementById('expense_type').value;
    const description = document.getElementById('description').value.trim();
    const amount = document.getElementById('amount').value;
    const expenseDate = document.getElementById('expense_date').value;
    const paymentMethod = document.getElementById('payment_method').value;
    
    if (!expenseType || !description || !amount || !expenseDate || !paymentMethod) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (amount <= 0) {
        e.preventDefault();
        alert('Amount must be greater than zero.');
        return false;
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>