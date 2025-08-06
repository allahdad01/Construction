<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);

$db = new Database();
$conn = $db->getConnection();

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    header('Location: index.php');
    exit();
}

// Get payment details
$stmt = $conn->prepare("
    SELECT cp.*, c.contract_code, c.currency, c.id as contract_id, p.name as project_name 
    FROM contract_payments cp
    JOIN contracts c ON cp.contract_id = c.id
    LEFT JOIN projects p ON c.project_id = p.id
    WHERE cp.id = ? AND cp.company_id = ?
");
$stmt->execute([$payment_id, getCurrentCompanyId()]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    header('Location: index.php');
    exit();
}

$contract_id = $payment['contract_id'];
$contract_currency = $payment['currency'] ?? 'USD';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm_delete = $_POST['confirm_delete'] ?? '';
    $confirm_text = $_POST['confirm_text'] ?? '';
    
    if ($confirm_delete === 'yes' && $confirm_text === 'DELETE') {
        try {
            $stmt = $conn->prepare("
                DELETE FROM contract_payments 
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([$payment_id, getCurrentCompanyId()]);
            
            // Redirect back to timesheet with success message
            header("Location: timesheet.php?contract_id={$contract_id}&payment_deleted=1");
            exit();
            
        } catch (Exception $e) {
            $error = 'Failed to delete payment: ' . $e->getMessage();
        }
    } else {
        $error = 'Please confirm the deletion by typing "DELETE" in the confirmation field.';
    }
}

require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Delete Contract Payment</h1>
        <div>
            <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Timesheet
            </a>
        </div>
    </div>

    <!-- Payment Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-danger">Payment to be Deleted</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Contract:</strong></td>
                            <td><?php echo htmlspecialchars($payment['contract_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Project:</strong></td>
                            <td><?php echo htmlspecialchars($payment['project_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Payment Code:</strong></td>
                            <td><?php echo htmlspecialchars($payment['payment_code']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Payment Date:</strong></td>
                            <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>Amount:</strong></td>
                            <td><strong class="text-danger"><?php echo formatCurrencyAmount($payment['amount'], $contract_currency); ?></strong></td>
                        </tr>
                        <tr>
                            <td><strong>Payment Method:</strong></td>
                            <td>
                                <span class="badge <?php echo $payment['payment_method'] === 'credit_card' ? 'bg-primary' : 'bg-success'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Reference:</strong></td>
                            <td><?php echo htmlspecialchars($payment['reference_number'] ?? 'N/A'); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge <?php echo $payment['status'] === 'completed' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <?php if (!empty($payment['notes'])): ?>
            <div class="mt-3">
                <strong>Notes:</strong>
                <p class="mt-2 p-3 bg-light border rounded"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation -->
    <div class="card shadow border-danger">
        <div class="card-header py-3 bg-danger text-white">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-exclamation-triangle"></i> Confirm Payment Deletion
            </h6>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-warning" role="alert">
                <h5><i class="fas fa-exclamation-triangle"></i> Warning!</h5>
                <p><strong>This action cannot be undone.</strong> Deleting this payment will:</p>
                <ul>
                    <li>Permanently remove the payment record from the database</li>
                    <li>Update contract financial calculations and totals</li>
                    <li>Affect payment history and reporting</li>
                    <li>Impact the remaining balance calculations</li>
                </ul>
                <p class="mb-0"><strong>Are you absolutely sure you want to delete this payment?</strong></p>
            </div>

            <form method="POST">
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirm_delete" name="confirm_delete" value="yes" required>
                        <label class="form-check-label" for="confirm_delete">
                            <strong>Yes, I understand the consequences and want to delete this payment</strong>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="confirm_text" class="form-label">
                        <strong>Type "DELETE" to confirm:</strong>
                    </label>
                    <input type="text" class="form-control" id="confirm_text" name="confirm_text" 
                           placeholder="Type DELETE to confirm" required>
                    <small class="form-text text-muted">This field is case-sensitive. You must type exactly "DELETE".</small>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="timesheet.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Prevent accidental form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const confirmText = document.getElementById('confirm_text');
    const submitButton = form.querySelector('button[type="submit"]');
    
    function checkConfirmation() {
        if (confirmText.value === 'DELETE') {
            submitButton.disabled = false;
            submitButton.classList.remove('btn-secondary');
            submitButton.classList.add('btn-danger');
        } else {
            submitButton.disabled = true;
            submitButton.classList.remove('btn-danger');
            submitButton.classList.add('btn-secondary');
        }
    }
    
    // Initially disable the submit button
    submitButton.disabled = true;
    submitButton.classList.remove('btn-danger');
    submitButton.classList.add('btn-secondary');
    
    // Check on every input
    confirmText.addEventListener('input', checkConfirmation);
    
    // Additional confirmation on form submit
    form.addEventListener('submit', function(e) {
        if (confirmText.value !== 'DELETE') {
            e.preventDefault();
            alert('Please type "DELETE" exactly to confirm the deletion.');
            return false;
        }
        
        if (!confirm('This will permanently delete the payment. Are you absolutely sure?')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>