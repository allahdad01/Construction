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

// Remove any dependency on parking spaces
echo '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ensure DB allows NULL for parking_space_id in case column still exists
        try {
            $conn->exec("ALTER TABLE parking_rentals MODIFY COLUMN parking_space_id INT NULL");
        } catch (Exception $e) {}

        // Validate required fields
        $required_fields = ['client_name', 'start_date', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        if ($end_date && strtotime($end_date) <= strtotime($start_date)) {
            throw new Exception("End date must be after start date.");
        }

        // Validate monthly rate
        if (!is_numeric($_POST['monthly_rate']) || $_POST['monthly_rate'] <= 0) {
            throw new Exception("Monthly rate must be a positive number.");
        }

        // Generate rental code
        $rental_code = generateParkingRentalCode($company_id);

        // Calculate totals if end date is provided
        $total_days = null;
        $total_amount = null;
        if ($end_date) {
            $total_days = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
            $daily_rate = $_POST['monthly_rate'] / 30;
            $total_amount = $total_days * $daily_rate;
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert rental without any parking space
        $stmt = $conn->prepare("
            INSERT INTO parking_rentals (
                company_id, parking_space_id, rental_code, client_name, 
                client_contact, vehicle_type, vehicle_registration,
                start_date, end_date, monthly_rate, currency,
                total_days, total_amount, status, notes, created_at
            ) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $rental_code,
            $_POST['client_name'],
            $_POST['client_contact'] ?? '',
            $_POST['vehicle_type'] ?? '',
            $_POST['vehicle_registration'] ?? '',
            $start_date,
            $end_date,
            $_POST['monthly_rate'],
            $_POST['currency'] ?? 'USD',
            $total_days,
            $total_amount,
            $_POST['notes'] ?? ''
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Parking rental added successfully! Rental Code: $rental_code";

        // Redirect to index
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Helper function to generate rental code
function generateParkingRentalCode($company_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'] ?? 'COMP';
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_rentals WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $next_number = $count + 1;
    return strtoupper($company_code) . 'PKR' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-plus-circle"></i> <?php echo __('add_parking_rental'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Add Parking Rental Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_rental_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_name" class="form-label"><?php echo __('client_name'); ?> *</label>
                            <input type="text" class="form-control" id="client_name" name="client_name" 
                                   value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" 
                                   style="text-transform: none;" autocomplete="off" spellcheck="false" required>
                            <small class="form-text text-muted">You can use spaces in client names.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_contact" class="form-label"><?php echo __('client_contact'); ?></label>
                            <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                   value="<?php echo htmlspecialchars($_POST['client_contact'] ?? ''); ?>"
                                   placeholder="Phone, Email, or Address"
                                   style="text-transform: none;" autocomplete="off" spellcheck="false">
                            <small class="form-text text-muted">You can use spaces in contact information.</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="vehicle_type" class="form-label"><?php echo __('vehicle_type'); ?></label>
                            <input type="text" class="form-control" id="vehicle_type" name="vehicle_type" 
                                   value="<?php echo htmlspecialchars($_POST['vehicle_type'] ?? ''); ?>"
                                   style="text-transform: none;" autocomplete="off" spellcheck="false">
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_vehicle_types'); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="vehicle_registration" class="form-label"><?php echo __('vehicle_registration'); ?>/<?php echo __('license_plate'); ?></label>
                            <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" 
                                   value="<?php echo htmlspecialchars($_POST['vehicle_registration'] ?? ''); ?>"
                                   placeholder="License plate number or ID"
                                   style="text-transform: none;" autocomplete="off" spellcheck="false">
                            <small class="form-text text-muted">You can use spaces in registration numbers.</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label"><?php echo __('end_date'); ?> (<?php echo __('optional'); ?>)</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                            <small class="text-muted"><?php echo __('leave_empty_for_ongoing_rental'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label"><?php echo __('monthly_rate'); ?> *</label>
                            <div class="input-group">
                                <span class="input-group-text" id="currency-symbol">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                       value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?></label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo (($_POST['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>><?php echo __('usd'); ?> - <?php echo __('us_dollar'); ?> ($)</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>><?php echo __('afn'); ?> - <?php echo __('afghan_afghani'); ?> (؋)</option>
                                <option value="EUR" <?php echo (($_POST['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>><?php echo __('eur'); ?> - <?php echo __('euro'); ?> (€)</option>
                                <option value="GBP" <?php echo (($_POST['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>><?php echo __('gbp'); ?> - <?php echo __('british_pound'); ?> (£)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?> & <?php echo __('special_instructions'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="1" 
                                      placeholder="Any special instructions, parking rules, or additional information..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('add_parking_rental'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySelect = document.getElementById('currency');
    const currencySymbol = document.getElementById('currency-symbol');
    const notesTextarea = document.getElementById('notes');
    const clientNameInput = document.getElementById('client_name');
    const clientContactInput = document.getElementById('client_contact');
    const vehicleRegistrationInput = document.getElementById('vehicle_registration');

    function enableSpacesInInput(input) {
        if (input) {
            input.removeEventListener('keydown', null);
            input.removeEventListener('keypress', null);
            input.removeEventListener('keyup', null);
            input.addEventListener('keydown', function(e) {
                if (e.key === ' ' || e.keyCode === 32) {
                    e.preventDefault();
                    e.stopPropagation();
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    const value = this.value;
                    this.value = value.substring(0, start) + ' ' + value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 1;
                    return false;
                }
            });
            input.setAttribute('type', 'text');
            input.style.textTransform = 'none';
            input.style.letterSpacing = 'normal';
        }
    }

    function updateCurrencySymbol() {
        const currency = currencySelect.value;
        const symbols = { 'USD': '$', 'AFN': '؋', 'EUR': '€', 'GBP': '£' };
        currencySymbol.textContent = symbols[currency] || '$';
    }

    // Enable spaces
    enableSpacesInInput(clientNameInput);
    enableSpacesInInput(clientContactInput);
    enableSpacesInInput(vehicleRegistrationInput);

    // Notes textarea spaces
    if (notesTextarea) {
        notesTextarea.removeEventListener('keydown', null);
        notesTextarea.removeEventListener('keypress', null);
        notesTextarea.removeEventListener('keyup', null);
        notesTextarea.addEventListener('keydown', function(e) {
            if (e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                e.stopPropagation();
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const value = this.value;
                this.value = value.substring(0, start) + ' ' + value.substring(end);
                this.selectionStart = this.selectionEnd = start + 1;
                return false;
            }
        });
        notesTextarea.style.textTransform = 'none';
        notesTextarea.style.letterSpacing = 'normal';
    }

    currencySelect.addEventListener('change', updateCurrencySymbol);
    updateCurrencySymbol();
});
</script>

<?php require_once '../../../includes/footer.php'; ?>