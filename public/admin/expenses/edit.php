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

// Get expense ID from URL
$expense_id = $_GET['id'] ?? null;

if (!$expense_id) {
    header('Location: index.php');
    exit;
}

// Get expense details
$stmt = $conn->prepare("
    SELECT * FROM expenses 
    WHERE id = ? AND company_id = ?
");
$stmt->execute([$expense_id, $company_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: index.php');
    exit;
}

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

        // Validate expense date
        $expense_date = $_POST['expense_date'];
        if (!strtotime($expense_date)) {
            throw new Exception("Invalid expense date format.");
        }

        // Validate amount
        $amount = floatval($_POST['amount']);
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update expense record
        $stmt = $conn->prepare("
            UPDATE expenses SET
                category = ?, amount = ?, currency = ?, expense_date = ?, 
                description = ?, payment_method = ?, reference_number = ?, 
                notes = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['category'],
            $amount,
            $_POST['currency'] ?? 'USD',
            $expense_date,
            $_POST['description'],
            $_POST['payment_method'] ?? 'cash',
            $_POST['reference_number'] ?: null,
            $_POST['notes'] ?: null,
            $expense_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Expense updated successfully!";

        // Refresh expense data
        $stmt = $conn->prepare("
            SELECT * FROM expenses 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$expense_id, $company_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$expense_id'; }, 2000);</script>";

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
            <i class="fas fa-edit"></i> <?php echo __('edit_expense'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $expense_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_expense'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_expenses'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Expense Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo __('edit_expense_details'); ?> - <?php echo htmlspecialchars($expense['expense_code']); ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="category" class="form-label"><?php echo __('category'); ?> *</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">Select category...</option>
                                <option value="fuel" <?php echo ($expense['category'] == 'fuel') ? 'selected' : ''; ?>>Fuel</option>
                                <option value="maintenance" <?php echo ($expense['category'] == 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="materials" <?php echo ($expense['category'] == 'materials') ? 'selected' : ''; ?>>Materials</option>
                                <option value="equipment" <?php echo ($expense['category'] == 'equipment') ? 'selected' : ''; ?>>Equipment</option>
                                <option value="office" <?php echo ($expense['category'] == 'office') ? 'selected' : ''; ?>>Office Supplies</option>
                                <option value="utilities" <?php echo ($expense['category'] == 'utilities') ? 'selected' : ''; ?>>Utilities</option>
                                <option value="travel" <?php echo ($expense['category'] == 'travel') ? 'selected' : ''; ?>>Travel</option>
                                <option value="other" <?php echo ($expense['category'] == 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="expense_date" class="form-label"><?php echo __('expense_date'); ?> *</label>
                            <input type="date" class="form-control" id="expense_date" name="expense_date" 
                                   value="<?php echo htmlspecialchars($expense['expense_date']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="amount" class="form-label"><?php echo __('amount'); ?> *</label>
                            <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                   value="<?php echo htmlspecialchars($expense['amount']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?></label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo ($expense['currency'] == 'USD') ? 'selected' : ''; ?>>USD</option>
                                <option value="AFN" <?php echo ($expense['currency'] == 'AFN') ? 'selected' : ''; ?>>AFN</option>
                                <option value="EUR" <?php echo ($expense['currency'] == 'EUR') ? 'selected' : ''; ?>>EUR</option>
                                <option value="GBP" <?php echo ($expense['currency'] == 'GBP') ? 'selected' : ''; ?>>GBP</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?></label>
                            <select class="form-control" id="payment_method" name="payment_method">
                                <option value="cash" <?php echo ($expense['payment_method'] == 'cash') ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                                <option value="bank_transfer" <?php echo ($expense['payment_method'] == 'bank_transfer') ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                                <option value="credit_card" <?php echo ($expense['payment_method'] == 'credit_card') ? 'selected' : ''; ?>><?php echo __('credit_card'); ?></option>
                                <option value="check" <?php echo ($expense['payment_method'] == 'check') ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                                <option value="mobile_money" <?php echo ($expense['payment_method'] == 'mobile_money') ? 'selected' : ''; ?>><?php echo __('mobile_money'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="reference_number" class="form-label"><?php echo __('reference_number'); ?></label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                   value="<?php echo htmlspecialchars($expense['reference_number'] ?? ''); ?>"
                                   placeholder="Optional reference number">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo __('description'); ?> *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required><?php echo htmlspecialchars($expense['description']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($expense['notes'] ?? ''); ?></textarea>
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
                                <p><strong><?php echo __('expense_code'); ?>:</strong> <?php echo htmlspecialchars($expense['expense_code']); ?></p>
                                <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($expense['created_at']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo __('last_updated'); ?>:</strong> 
                                    <?php echo $expense['updated_at'] ? formatDateTime($expense['updated_at']) : __('never'); ?>
                                </p>
                                <p><strong><?php echo __('current_amount'); ?>:</strong> <?php echo formatCurrency($expense['amount'], $expense['currency']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="view.php?id=<?php echo $expense_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_expense'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-format amount as user types
document.getElementById('amount').addEventListener('input', function() {
    let value = this.value;
    // Remove any non-numeric characters except decimal point
    value = value.replace(/[^0-9.]/g, '');
    
    // Ensure only one decimal point
    let parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }
    
    this.value = value;
});

// Update currency symbol display
document.getElementById('currency').addEventListener('change', function() {
    const currency = this.value;
    console.log(`Currency changed to: ${currency}`);
    // You can add currency symbol display logic here if needed
});
</script>

<?php require_once '../../../includes/footer.php'; ?>