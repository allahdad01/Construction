<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../../includes/header.php';

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

// Get parking space details
$stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
$stmt->execute([$rental['parking_space_id'], $company_id]);
$space = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if rental is already ended
if ($rental['status'] !== 'active') {
    header('Location: view-rental.php?id=' . $rental_id);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate end date
        $end_date = $_POST['end_date'] ?? null;
        if (!$end_date) {
            throw new Exception("End date is required to end the rental.");
        }

        if (strtotime($end_date) < strtotime($rental['start_date'])) {
            throw new Exception("End date cannot be before start date.");
        }

        // Calculate total days and amount
        $total_days = ceil((strtotime($end_date) - strtotime($rental['start_date'])) / (60 * 60 * 24));
        $daily_rate = $rental['monthly_rate'] / 30;
        $total_amount = $total_days * $daily_rate;

        // Get existing payments
        $stmt = $conn->prepare("
            SELECT SUM(amount) as total_paid 
            FROM parking_payments 
            WHERE rental_id = ? AND company_id = ?
        ");
        $stmt->execute([$rental_id, $company_id]);
        $payment_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_paid = $payment_result['total_paid'] ?? 0;
        $remaining_amount = max(0, $total_amount - $total_paid);

        // Handle final payment if provided
        $final_payment_amount = 0;
        $final_payment_method = '';
        $final_payment_reference = '';
        $final_payment_notes = '';
        
        if (!empty($_POST['final_payment_amount']) && $_POST['final_payment_amount'] > 0) {
            $final_payment_amount = (float)$_POST['final_payment_amount'];
            $final_payment_method = $_POST['final_payment_method'] ?? '';
            $final_payment_reference = $_POST['final_payment_reference'] ?? '';
            $final_payment_notes = $_POST['final_payment_notes'] ?? '';
            
            if ($final_payment_amount > $remaining_amount) {
                throw new Exception("Final payment amount cannot exceed remaining amount.");
            }
        }

        // Start transaction
        $conn->beginTransaction();

        // Update rental record
        $stmt = $conn->prepare("
            UPDATE parking_rentals SET 
                end_date = ?, total_days = ?, total_amount = ?, 
                status = 'ended', updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $end_date,
            $total_days,
            $total_amount,
            $rental_id,
            $company_id
        ]);

        // Record final payment if provided
        if ($final_payment_amount > 0) {
            $payment_code = 'PAY-' . strtoupper(uniqid());
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
                $final_payment_amount,
                $rental['currency'] ?? 'USD',
                $final_payment_method,
                $end_date,
                $final_payment_reference,
                $final_payment_notes
            ]);
        }

        // Update parking space status to available
        $stmt = $conn->prepare("
            UPDATE parking_spaces SET status = 'available' 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$rental['parking_space_id'], $company_id]);

        $conn->commit();
        
        if ($final_payment_amount > 0) {
            $success = "Rental ended successfully! Final payment of " . formatCurrencyAmount($final_payment_amount, $rental['currency'] ?? 'USD') . " has been recorded. The parking space is now available.";
        } else {
            $success = "Rental ended successfully! The parking space is now available.";
        }
        
        // Set a flag to redirect after showing success message
        $redirect_after_success = true;

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
            <i class="fas fa-stop"></i> <?php echo __('end_parking_rental'); ?>
        </h1>
        <div>
            <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_rental'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php if (isset($redirect_after_success) && $redirect_after_success): ?>
            <script>
                setTimeout(function() {
                    window.location.href = 'view-rental.php?id=<?php echo $rental_id; ?>';
                }, 2000);
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- End Rental Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('end_rental'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong><?php echo __('warning'); ?>:</strong> <?php echo __('this_action_will_end_the_rental_and_make_the_parking_space_available_for_new_rentals'); ?>
                    </div>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label"><?php echo __('end_date'); ?> *</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="text-muted"><?php echo __('select_the_date_when_the_rental_should_end'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('start_date'); ?></label>
                                    <input type="text" class="form-control" value="<?php echo date('M j, Y', strtotime($rental['start_date'])); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('monthly_rate'); ?></label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('client'); ?></label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($rental['client_name']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Final Payment Section -->
                        <?php 
                        // Calculate current amount and payments
                        $current_date = new DateTime();
                        $start_date = new DateTime($rental['start_date']);
                        $current_days = $start_date->diff($current_date)->days;
                        $daily_rate = $rental['monthly_rate'] / 30;
                        $current_amount = $current_days * $daily_rate;
                        
                        // Get existing payments
                        $stmt = $conn->prepare("
                            SELECT SUM(amount) as total_paid 
                            FROM parking_payments 
                            WHERE rental_id = ? AND company_id = ?
                        ");
                        $stmt->execute([$rental_id, $company_id]);
                        $payment_result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $total_paid = $payment_result['total_paid'] ?? 0;
                        $remaining_amount = max(0, $current_amount - $total_paid);
                        ?>
                        
                        <?php if ($remaining_amount > 0): ?>
                        <hr>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong><?php echo __('final_payment'); ?>:</strong> <?php echo __('there_is_a_remaining_amount_of'); ?> <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?> <?php echo __('for_this_rental'); ?>.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="final_payment_amount" class="form-label"><?php echo __('final_payment_amount'); ?></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <?php 
                                            $currency = $rental['currency'] ?? 'USD';
                                            echo $currency === 'AFN' ? '؋' : ($currency === 'EUR' ? '€' : '$'); 
                                            ?>
                                        </span>
                                        <input type="number" step="0.01" class="form-control" id="final_payment_amount" name="final_payment_amount" 
                                               value="<?php echo $remaining_amount; ?>" max="<?php echo $remaining_amount; ?>">
                                    </div>
                                    <small class="text-muted"><?php echo __('maximum'); ?>: <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="final_payment_method" class="form-label"><?php echo __('payment_method'); ?></label>
                                    <select class="form-control" id="final_payment_method" name="final_payment_method">
                                        <option value=""><?php echo __('select_payment_method'); ?></option>
                                        <option value="cash"><?php echo __('cash'); ?></option>
                                        <option value="bank_transfer"><?php echo __('bank_transfer'); ?></option>
                                        <option value="credit_card"><?php echo __('credit_card'); ?></option>
                                        <option value="debit_card"><?php echo __('debit_card'); ?></option>
                                        <option value="mobile_payment"><?php echo __('mobile_payment'); ?></option>
                                        <option value="check"><?php echo __('check'); ?></option>
                                        <option value="other"><?php echo __('other'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="final_payment_reference" class="form-label"><?php echo __('reference_number'); ?></label>
                                    <input type="text" class="form-control" id="final_payment_reference" name="final_payment_reference" 
                                           placeholder="Transaction ID, Check #, etc.">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="final_payment_notes" class="form-label"><?php echo __('payment_notes'); ?></label>
                                    <textarea class="form-control" id="final_payment_notes" name="final_payment_notes" rows="2" 
                                              placeholder="Final payment notes..."></textarea>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <hr>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong><?php echo __('fully_paid'); ?>:</strong> <?php echo __('this_rental_has_been_fully_paid'); ?>. <?php echo __('no_remaining_amount'); ?>.
                        </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between">
                            <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                            </a>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to end this rental?')">
                                <i class="fas fa-stop"></i> <?php echo __('end_rental'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('rental_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <p><strong><?php echo __('rental_code'); ?>:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                    <p><strong><?php echo __('status'); ?>:</strong> 
                        <span class="badge bg-success"><?php echo __('active'); ?></span>
                    </p>
                    <p><strong><?php echo __('parking_space'); ?>:</strong> <?php echo htmlspecialchars($space['space_name']); ?></p>
                    <p><strong><?php echo __('start_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                    <p><strong><?php echo __('duration'); ?>:</strong> 
                        <?php 
                        $days = ceil((time() - strtotime($rental['start_date'])) / (60 * 60 * 24));
                        echo $days . ' days so far';
                        ?>
                    </p>
                    <?php if (!empty($rental['vehicle_type']) || !empty($rental['vehicle_registration'])): ?>
                        <p><strong>Vehicle:</strong> 
                            <?php 
                            $vehicle_info = [];
                            if (!empty($rental['vehicle_type'])) $vehicle_info[] = $rental['vehicle_type'];
                            if (!empty($rental['vehicle_registration'])) $vehicle_info[] = $rental['vehicle_registration'];
                            echo htmlspecialchars(implode(' - ', $vehicle_info));
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('end_date').addEventListener('change', function() {
    const startDate = new Date('<?php echo $rental['start_date']; ?>');
    const endDate = new Date(this.value);
    
    if (endDate < startDate) {
        alert('End date cannot be before start date!');
        this.value = '';
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>