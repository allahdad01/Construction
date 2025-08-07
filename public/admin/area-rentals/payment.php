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
$rental_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$rental_id) {
    header('Location: index.php');
    exit;
}

// Get rental details with area information
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        ra.area_name,
        ra.area_code,
        ra.area_type,
        ra.currency as area_currency,
        COALESCE(SUM(arp.amount), 0) as total_paid,
        COUNT(arp.id) as payment_count
    FROM area_rentals ar
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    LEFT JOIN area_rental_payments arp ON ar.id = arp.area_rental_id
    WHERE ar.id = ? AND ar.company_id = ?
    GROUP BY ar.id
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    header('Location: index.php');
    exit;
}

// Calculate amounts
$total_amount = $rental['monthly_rate'];
$total_paid = $rental['total_paid'] ?? 0;
$remaining_amount = $total_amount - $total_paid;

// Handle form submission for new payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate payment amount
        $payment_amount = (float)$_POST['payment_amount'];
        if ($payment_amount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }

        if ($payment_amount > $remaining_amount) {
            throw new Exception("Payment amount cannot exceed remaining balance.");
        }

        // Validate required fields
        if (empty(trim($_POST['payment_method']))) {
            throw new Exception("Payment method is required.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert payment record
        $stmt = $conn->prepare("
            INSERT INTO area_rental_payments (
                area_rental_id, amount, payment_method, reference_number, 
                payment_date, notes, currency, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $rental_id,
            $payment_amount,
            trim($_POST['payment_method']),
            trim($_POST['reference_number'] ?? ''),
            $_POST['payment_date'] ?? date('Y-m-d'),
            trim($_POST['notes'] ?? ''),
            $rental['currency'] ?? 'USD'
        ]);

        // Update rental status if fully paid
        $new_total_paid = $total_paid + $payment_amount;
        if ($new_total_paid >= $total_amount) {
            $stmt = $conn->prepare("UPDATE area_rentals SET status = 'paid' WHERE id = ?");
            $stmt->execute([$rental_id]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Payment recorded successfully!";
        
        // Redirect to refresh the page
        header("Location: payment.php?id=$rental_id&success=1");
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get payment history
$stmt = $conn->prepare("
    SELECT * FROM area_rental_payments 
    WHERE area_rental_id = ? 
    ORDER BY payment_date DESC, created_at DESC
");
$stmt->execute([$rental_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Move header include after all potential redirects
require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-credit-card"></i> Area Rental Payment
            </h1>
            <p class="text-muted mb-0">Manage payments for <?php echo htmlspecialchars($rental['rental_code']); ?></p>
        </div>
        <div class="btn-group" role="group">
            <a href="view.php?id=<?php echo $rental_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> View Details
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Rentals
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">Payment recorded successfully!</div>
    <?php endif; ?>

    <div class="row">
        <!-- Payment Form -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus"></i> Record Payment
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($remaining_amount > 0): ?>
                        <form method="POST" id="paymentForm">
                            <div class="mb-3">
                                <label for="payment_amount" class="form-label">Payment Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="currency-symbol">
                                        <?php echo $rental['currency'] === 'USD' ? '$' : ($rental['currency'] === 'AFN' ? '؋' : $rental['currency']); ?>
                                    </span>
                                    <input type="number" step="0.01" min="0.01" max="<?php echo $remaining_amount; ?>" 
                                           class="form-control" id="payment_amount" name="payment_amount" 
                                           value="<?php echo $remaining_amount; ?>" required>
                                </div>
                                <small class="form-text text-muted">
                                    Maximum: <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method *</label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">💵 Cash</option>
                                    <option value="bank_transfer">🏦 Bank Transfer</option>
                                    <option value="check">📄 Check</option>
                                    <option value="credit_card">💳 Credit Card</option>
                                    <option value="mobile_payment">📱 Mobile Payment</option>
                                    <option value="other">📋 Other</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="payment_date" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="reference_number" class="form-label">Reference Number</label>
                                <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                       placeholder="Transaction ID, check number, etc."
                                       style="text-transform: none;" autocomplete="off" spellcheck="false">
                                <small class="form-text text-muted">You can use spaces in reference numbers.</small>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Additional payment notes..."
                                          style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                                <small class="form-text text-muted">You can use spaces in notes.</small>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> Record Payment
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h6 class="text-success">Fully Paid!</h6>
                            <p class="text-muted">This rental has been fully paid.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> Payment Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="text-primary">Total Amount</h6>
                                <h4 class="text-primary">
                                    <?php echo formatCurrencyAmount($total_amount, $rental['currency'] ?? 'USD'); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="text-success">Total Paid</h6>
                                <h4 class="text-success">
                                    <?php echo formatCurrencyAmount($total_paid, $rental['currency'] ?? 'USD'); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="text-<?php echo $remaining_amount > 0 ? 'warning' : 'success'; ?>">Remaining</h6>
                                <h4 class="text-<?php echo $remaining_amount > 0 ? 'warning' : 'success'; ?>">
                                    <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress mb-3" style="height: 25px;">
                        <?php 
                        $percentage = $total_amount > 0 ? ($total_paid / $total_amount) * 100 : 0;
                        $percentage = min(100, max(0, $percentage));
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $percentage; ?>%" 
                             aria-valuenow="<?php echo $percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                    </div>

                    <!-- Rental Details -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6 class="text-secondary">Rental Information</h6>
                            <p><strong>Code:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                            <p><strong>Client:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                            <p><strong>Area:</strong> <?php echo htmlspecialchars($rental['area_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-secondary">Payment Details</h6>
                            <p><strong>Currency:</strong> <?php echo $rental['currency'] ?? 'USD'; ?></p>
                            <p><strong>Monthly Rate:</strong> <?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></p>
                            <p><strong>Payments:</strong> <?php echo $rental['payment_count']; ?> records</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> Payment History
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No payment records found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="paymentsTable">
                                <thead>
                                    <tr>
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
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <strong class="text-success">
                                                <?php echo formatCurrencyAmount($payment['amount'], $payment['currency'] ?? 'USD'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($payment['reference_number'])): ?>
                                                <code><?php echo htmlspecialchars($payment['reference_number']); ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($payment['notes'])): ?>
                                                <?php echo nl2br(htmlspecialchars($payment['notes'])); ?>
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
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable spaces in text inputs and textareas
    const textInputs = document.querySelectorAll('input[type="text"], textarea');
    
    // Function to enable spaces in input fields
    function enableSpacesInInput(input) {
        if (input) {
            // Remove any existing event listeners that might block spaces
            input.removeEventListener('keydown', null);
            input.removeEventListener('keypress', null);
            input.removeEventListener('keyup', null);
            
            // Add space handling
            input.addEventListener('keydown', function(e) {
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
            
            // Ensure the input is properly configured
            if (input.type === 'text') {
                input.setAttribute('type', 'text');
                input.style.textTransform = 'none';
                input.style.letterSpacing = 'normal';
            }
        }
    }
    
    // Enable spaces in all text inputs and textareas
    textInputs.forEach(enableSpacesInInput);
    
    // Initialize DataTable for payments
    if (document.getElementById('paymentsTable')) {
        $('#paymentsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            language: {
                search: "Search payments:",
                lengthMenu: "Show _MENU_ payments per page",
                info: "Showing _START_ to _END_ of _TOTAL_ payments",
                infoEmpty: "Showing 0 to 0 of 0 payments",
                infoFiltered: "(filtered from _MAX_ total payments)"
            }
        });
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>