<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get rental ID from URL
$rental_id = $_GET['id'] ?? null;

if (!$rental_id) {
    header('Location: index.php');
    exit;
}

// Get rental details
$stmt = $conn->prepare("
    SELECT pr.*, ps.space_code, ps.space_name, ps.vehicle_category
    FROM parking_rentals pr
    JOIN parking_spaces ps ON pr.parking_space_id = ps.id
    WHERE pr.id = ? AND pr.company_id = ?
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    header('Location: index.php');
    exit;
}

// Now include header after all potential redirects
require_once '../../../includes/header.php';

// Check if rental is ended and has no payments
$is_ended_without_payments = ($rental['status'] === 'ended' && empty($rental['total_amount']));

// Get parking space details
$stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
$stmt->execute([$rental['parking_space_id'], $company_id]);
$space = $stmt->fetch(PDO::FETCH_ASSOC);

// Get payment history for this rental
$stmt = $conn->prepare("
    SELECT * FROM parking_payments 
    WHERE rental_id = ? AND company_id = ? 
    ORDER BY payment_date DESC
");
$stmt->execute([$rental_id, $company_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total paid and current amount for ongoing rentals
$total_paid = array_sum(array_column($payments, 'amount'));

// Calculate current amount for ongoing rentals
$current_date = new DateTime();
$start_date = new DateTime($rental['start_date']);
$end_date = !empty($rental['end_date']) ? new DateTime($rental['end_date']) : null;

if ($end_date && $end_date > $start_date) {
    // Fixed rental period (ended rental)
    $total_amount = $rental['total_amount'] ?? 0;
    $current_amount = $total_amount;
    $remaining_amount = max(0, $total_amount - $total_paid);
} else {
    // Ongoing rental - calculate current amount based on days
    $current_days = $start_date->diff($current_date)->days;
    $daily_rate = $rental['monthly_rate'] / 30;
    $current_amount = $current_days * $daily_rate;
    $total_amount = $current_amount; // For ongoing rentals, total = current
    $remaining_amount = max(0, $current_amount - $total_paid);
}

// For ended rentals without total_amount, calculate based on actual days
if ($rental['status'] === 'ended' && empty($rental['total_amount']) && $end_date) {
    $actual_days = $start_date->diff($end_date)->days;
    $daily_rate = $rental['monthly_rate'] / 30;
    $total_amount = $actual_days * $daily_rate;
    $current_amount = $total_amount;
    $remaining_amount = max(0, $total_amount - $total_paid);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['payment_amount', 'payment_method', 'payment_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate payment amount
        $payment_amount = (float)$_POST['payment_amount'];
        if ($payment_amount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }

        if ($payment_amount > $remaining_amount) {
            throw new Exception("Payment amount cannot exceed remaining amount.");
        }

        // Generate payment code
        $payment_code = 'PAY-' . strtoupper(uniqid());

        // Start transaction
        $conn->beginTransaction();

        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO parking_payments (
                company_id, rental_id, payment_code, amount, currency,
                payment_method, payment_date, reference_number, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $rental_id,
            $payment_code,
            $payment_amount,
            $rental['currency'] ?? 'USD',
            $_POST['payment_method'],
            $_POST['payment_date'],
            $_POST['reference_number'] ?? null,
            $_POST['notes'] ?? null
        ]);

        $conn->commit();
        
        $success = "Payment recorded successfully!";
        
        // Refresh payment data
        $stmt = $conn->prepare("
            SELECT * FROM parking_payments 
            WHERE rental_id = ? AND company_id = ? 
            ORDER BY payment_date DESC
        ");
        $stmt->execute([$rental_id, $company_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Recalculate totals
        $total_paid = array_sum(array_column($payments, 'amount'));
        
        // Recalculate based on rental type
        if ($end_date && $end_date > $start_date) {
            // Fixed rental period
            $total_amount = $rental['total_amount'] ?? 0;
            $current_amount = $total_amount;
            $remaining_amount = max(0, $total_amount - $total_paid);
        } else {
            // Ongoing rental - recalculate current amount
            $current_days = $start_date->diff($current_date)->days;
            $daily_rate = $rental['monthly_rate'] / 30;
            $current_amount = $current_days * $daily_rate;
            $total_amount = $current_amount;
            $remaining_amount = max(0, $current_amount - $total_paid);
        }

    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-credit-card"></i> Parking Rental Payment
        </h1>
        <div>
            <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Rental
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Payment Summary -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <?php if ($end_date && $end_date > $start_date): ?>
                                <h4 class="text-primary"><?php echo formatCurrencyAmount($total_amount, $rental['currency'] ?? 'USD'); ?></h4>
                                <small class="text-muted">Total Amount</small>
                            <?php else: ?>
                                <h4 class="text-primary"><?php echo formatCurrencyAmount($current_amount, $rental['currency'] ?? 'USD'); ?></h4>
                                <small class="text-muted">Current Amount</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo formatCurrencyAmount($total_paid, $rental['currency'] ?? 'USD'); ?></h4>
                            <small class="text-muted">Total Paid</small>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h4 class="text-<?php echo $remaining_amount > 0 ? 'warning' : 'success'; ?>">
                            <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                        </h4>
                        <small class="text-muted">Remaining Amount</small>
                        <?php if ($remaining_amount <= 0): ?>
                            <br><span class="badge bg-success">Fully Paid</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$end_date || $end_date <= $start_date): ?>
                        <hr>
                        <div class="text-center">
                            <small class="text-info">
                                <i class="fas fa-info-circle"></i> 
                                Ongoing rental - amount increases daily (<?php echo $current_days; ?> days so far)
                            </small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($rental['status'] === 'ended' && $remaining_amount > 0): ?>
                        <hr>
                        <div class="text-center">
                            <small class="text-warning">
                                <i class="fas fa-clock"></i> 
                                Late payment for ended rental (ended on <?php echo date('M j, Y', strtotime($rental['end_date'])); ?>)
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Record Payment</h6>
                </div>
                <div class="card-body">
                    <?php if ($remaining_amount > 0): ?>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_amount" class="form-label">Payment Amount *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <?php 
                                                $currency = $rental['currency'] ?? 'USD';
                                                echo $currency === 'AFN' ? '؋' : ($currency === 'EUR' ? '€' : '$'); 
                                                ?>
                                            </span>
                                            <input type="number" step="0.01" class="form-control" id="payment_amount" name="payment_amount" 
                                                   value="<?php echo $remaining_amount; ?>" max="<?php echo $remaining_amount; ?>" required>
                                        </div>
                                        <small class="text-muted">Maximum: <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_date" class="form-label">Payment Date *</label>
                                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="payment_method" class="form-label">Payment Method *</label>
                                        <select class="form-control" id="payment_method" name="payment_method" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="cash">Cash</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="credit_card">Credit Card</option>
                                            <option value="debit_card">Debit Card</option>
                                            <option value="mobile_payment">Mobile Payment</option>
                                            <option value="check">Check</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="reference_number" class="form-label">Reference Number</label>
                                        <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                               placeholder="Transaction ID, Check #, etc.">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Payment Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" 
                                          placeholder="Additional payment details..."
                                          style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                                <small class="form-text text-muted">You can use spaces in payment notes.</small>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Record Payment
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-success">Fully Paid!</h5>
                            <p class="text-muted">This rental has been fully paid.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Payment History</h6>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No payments recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="paymentsTable">
                        <thead>
                            <tr>
                                <th>Payment Code</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($payment['payment_code']); ?></strong>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>
                                    <strong><?php echo formatCurrencyAmount($payment['amount'], $payment['currency'] ?? 'USD'); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($payment['reference_number'])): ?>
                                        <?php echo htmlspecialchars($payment['reference_number']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($payment['notes'])): ?>
                                        <?php echo htmlspecialchars($payment['notes']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rental Information -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Rental Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Rental Code:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                    <p><strong>Client:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                    <p><strong>Parking Space:</strong> <?php echo htmlspecialchars($space['space_name']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                    <?php if (!empty($rental['end_date'])): ?>
                        <p><strong>End Date:</strong> <?php echo date('M j, Y', strtotime($rental['end_date'])); ?></p>
                    <?php endif; ?>
                    <p><strong>Monthly Rate:</strong> <?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></p>
                    <?php if ($rental['status'] === 'ended'): ?>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-secondary">Ended</span>
                            <?php if ($remaining_amount > 0): ?>
                                <span class="badge bg-warning">Pending Payment</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#paymentsTable').DataTable({
        "order": [[1, "desc"]],
        "pageLength": 10
    });
    
    // Enable spaces in textarea
    const notesTextarea = document.getElementById('notes');
    if (notesTextarea) {
        // Remove any existing event listeners that might block spaces
        notesTextarea.removeEventListener('keydown', null);
        notesTextarea.removeEventListener('keypress', null);
        notesTextarea.removeEventListener('keyup', null);
        
        // Add space handling
        notesTextarea.addEventListener('keydown', function(e) {
            // Explicitly allow space key
            if (e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                e.stopPropagation();
                
                // Manually insert space
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const value = this.value;
                this.value = value.substring(0, start) + ' ' + value.substring(end);
                this.selectionStart = this.selectionEnd = start + 1;
                
                return false;
            }
        });
        
        // Ensure the textarea is properly configured
        notesTextarea.style.textTransform = 'none';
        notesTextarea.style.letterSpacing = 'normal';
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>