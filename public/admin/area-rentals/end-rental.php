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

// Get rental details with area information and payment data
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

// Check if rental is already ended
if ($rental['status'] === 'ended') {
    header('Location: view.php?id=' . $rental_id);
    exit;
}

// Calculate amounts
$total_amount = $rental['monthly_rate'];
$total_paid = $rental['total_paid'] ?? 0;
$remaining_amount = $total_amount - $total_paid;

// Handle form submission for ending rental
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate end date
        $end_date = $_POST['end_date'];
        if (empty($end_date)) {
            throw new Exception("End date is required.");
        }

        if (strtotime($end_date) < strtotime($rental['start_date'])) {
            throw new Exception("End date cannot be before start date.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update rental status to ended
        $stmt = $conn->prepare("
            UPDATE area_rentals SET 
                status = 'ended',
                end_date = ?,
                updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$end_date, $rental_id, $company_id]);

        // Update area status back to available
        $stmt = $conn->prepare("
            UPDATE rental_areas SET 
                status = 'available',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$rental['rental_area_id']]);

        // Record final payment if provided
        if (!empty($_POST['final_payment_amount']) && $_POST['final_payment_amount'] > 0) {
            $final_payment_amount = (float)$_POST['final_payment_amount'];
            
            if ($final_payment_amount > $remaining_amount) {
                throw new Exception("Final payment amount cannot exceed remaining balance.");
            }

            $stmt = $conn->prepare("
                INSERT INTO area_rental_payments (
                    area_rental_id, amount, payment_method, reference_number, 
                    payment_date, notes, currency, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $rental_id,
                $final_payment_amount,
                trim($_POST['final_payment_method'] ?? 'other'),
                trim($_POST['final_payment_reference'] ?? ''),
                date('Y-m-d'),
                trim($_POST['final_payment_notes'] ?? 'Final payment on rental end'),
                $rental['currency'] ?? 'USD'
            ]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Rental ended successfully!";
        
        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$rental_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Move header include after all potential redirects
require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-stop-circle"></i> End Area Rental
            </h1>
            <p class="text-muted mb-0">End rental for <?php echo htmlspecialchars($rental['rental_code']); ?></p>
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

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- End Rental Form -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-triangle"></i> End Rental
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will end the rental and make the area available for new rentals.
                    </div>

                    <form method="POST" id="endRentalForm">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date *</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                            <small class="form-text text-muted">The date when the rental will end.</small>
                        </div>

                        <?php if ($remaining_amount > 0): ?>
                        <div class="mb-3">
                            <label for="final_payment_amount" class="form-label">Final Payment Amount</label>
                            <div class="input-group">
                                <span class="input-group-text" id="currency-symbol">
                                    <?php echo $rental['currency'] === 'USD' ? '$' : ($rental['currency'] === 'AFN' ? '؋' : $rental['currency']); ?>
                                </span>
                                <input type="number" step="0.01" min="0" max="<?php echo $remaining_amount; ?>" 
                                       class="form-control" id="final_payment_amount" name="final_payment_amount" 
                                       value="<?php echo $remaining_amount; ?>" placeholder="0.00">
                            </div>
                            <small class="form-text text-muted">
                                Remaining balance: <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="final_payment_method" class="form-label">Payment Method</label>
                            <select class="form-control" id="final_payment_method" name="final_payment_method">
                                <option value="cash">💵 Cash</option>
                                <option value="bank_transfer">🏦 Bank Transfer</option>
                                <option value="check">📄 Check</option>
                                <option value="credit_card">💳 Credit Card</option>
                                <option value="mobile_payment">📱 Mobile Payment</option>
                                <option value="other" selected>📋 Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="final_payment_reference" class="form-label">Payment Reference</label>
                            <input type="text" class="form-control" id="final_payment_reference" name="final_payment_reference" 
                                   placeholder="Transaction ID, check number, etc."
                                   style="text-transform: none;" autocomplete="off" spellcheck="false">
                            <small class="form-text text-muted">You can use spaces in reference numbers.</small>
                        </div>

                        <div class="mb-3">
                            <label for="final_payment_notes" class="form-label">Payment Notes</label>
                            <textarea class="form-control" id="final_payment_notes" name="final_payment_notes" rows="2" 
                                      placeholder="Notes about the final payment..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted">You can use spaces in payment notes.</small>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-stop-circle"></i> End Rental
                            </button>
                            <a href="view.php?id=<?php echo $rental_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Rental Summary -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Rental Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-secondary">Rental Information</h6>
                            <p><strong>Code:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                            <p><strong>Client:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                            <p><strong>Area:</strong> <?php echo htmlspecialchars($rental['area_name']); ?></p>
                            <p><strong>Type:</strong> <?php echo ucfirst($rental['rental_type']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-secondary">Financial Summary</h6>
                            <p><strong>Monthly Rate:</strong> <?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></p>
                            <p><strong>Total Paid:</strong> <?php echo formatCurrencyAmount($total_paid, $rental['currency'] ?? 'USD'); ?></p>
                            <p><strong>Remaining:</strong> 
                                <span class="text-<?php echo $remaining_amount > 0 ? 'warning' : 'success'; ?>">
                                    <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                                </span>
                            </p>
                            <p><strong>Payments:</strong> <?php echo $rental['payment_count']; ?> records</p>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-secondary">Rental Period</h6>
                            <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                            <p><strong>Current Status:</strong> 
                                <span class="badge bg-<?php echo $rental['status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($rental['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-secondary">Area Details</h6>
                            <p><strong>Area Code:</strong> <?php echo htmlspecialchars($rental['area_code']); ?></p>
                            <p><strong>Area Type:</strong> <?php echo ucfirst($rental['area_type']); ?></p>
                            <p><strong>Currency:</strong> <?php echo $rental['currency'] ?? 'USD'; ?></p>
                        </div>
                    </div>

                    <?php if ($remaining_amount > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Outstanding Balance:</strong> 
                        <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                        <br>
                        <small>You can record a final payment when ending the rental.</small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle"></i>
                        <strong>Fully Paid:</strong> No outstanding balance.
                    </div>
                    <?php endif; ?>
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
                    <?php
                    // Get recent payments
                    $stmt = $conn->prepare("
                        SELECT * FROM area_rental_payments 
                        WHERE area_rental_id = ? 
                        ORDER BY payment_date DESC 
                        LIMIT 5
                    ");
                    $stmt->execute([$rental_id]);
                    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($recent_payments)): ?>
                        <p class="text-muted">No payment records found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
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
    
    // Confirm before ending rental
    document.getElementById('endRentalForm').addEventListener('submit', function(e) {
        const confirmed = confirm('Are you sure you want to end this rental? This action cannot be undone.');
        if (!confirmed) {
            e.preventDefault();
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>