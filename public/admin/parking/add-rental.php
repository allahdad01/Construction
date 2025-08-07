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

// Get parking space ID from URL
$space_id = isset($_GET['space_id']) ? (int)$_GET['space_id'] : 0;

// Get parking space details
if ($space_id) {
    $stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
    $stmt->execute([$space_id, $company_id]);
    $parking_space = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parking_space) {
        header("Location: index.php");
        exit;
    }
} else {
    // Get all available parking spaces
    $stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE company_id = ? AND status = 'available' ORDER BY space_name");
    $stmt->execute([$company_id]);
    $available_spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['parking_space_id', 'client_name', 'start_date', 'monthly_rate'];
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

        // Get parking space details for validation
        $stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
        $stmt->execute([$_POST['parking_space_id'], $company_id]);
        $selected_space = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selected_space) {
            throw new Exception("Invalid parking space selected.");
        }

        // Check if space is available
        if ($selected_space['status'] !== 'available') {
            throw new Exception("Selected parking space is not available.");
        }

        // Generate rental code
        $rental_code = generateParkingRentalCode($company_id);

        // Calculate total days and amount if end date is provided
        $total_days = null;
        $total_amount = null;
        if ($end_date) {
            $total_days = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
            $daily_rate = $_POST['monthly_rate'] / 30;
            $total_amount = $total_days * $daily_rate;
        }

        // Start transaction
        $conn->beginTransaction();

        // Create parking rental record
        $stmt = $conn->prepare("
            INSERT INTO parking_rentals (
                company_id, parking_space_id, rental_code, client_name, 
                client_contact, vehicle_type, vehicle_registration,
                start_date, end_date, monthly_rate, currency,
                total_days, total_amount, status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $_POST['parking_space_id'],
            $rental_code,
            $_POST['client_name'],
            $_POST['client_contact'] ?? '',
            $_POST['vehicle_type'] ?? '',
            $_POST['vehicle_registration'] ?? '',
            $start_date,
            $end_date,
            $_POST['monthly_rate'],
            $_POST['currency'] ?? $selected_space['currency'] ?? 'USD',
            $total_days,
            $total_amount,
            $_POST['notes'] ?? ''
        ]);

        // Update parking space status if needed
        $stmt = $conn->prepare("UPDATE parking_spaces SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$_POST['parking_space_id']]);

        // Commit transaction
        $conn->commit();

        $success = "Parking rental added successfully! Rental Code: $rental_code";

        // Use JavaScript redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id={$_POST['parking_space_id']}'; }, 2000);</script>";

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
    
    // Get company code
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'] ?? 'COMP';
    
    // Get next rental number
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
            <i class="fas fa-plus-circle"></i> Add Parking Rental
        </h1>
        <a href="<?php echo $space_id ? "view.php?id=$space_id" : 'index.php'; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
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
            <h6 class="m-0 font-weight-bold text-primary">Parking Rental Details</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="parking_space_id" class="form-label">Parking Space *</label>
                            <?php if ($space_id): ?>
                                <input type="hidden" name="parking_space_id" value="<?php echo $space_id; ?>">
                                <input type="text" class="form-control" readonly 
                                       value="<?php echo htmlspecialchars($parking_space['space_name'] . ' (' . $parking_space['space_code'] . ')'); ?>">
                                <small class="text-muted">
                                    <?php 
                                    $category_display = [
                                        'machines' => '🏗️ Machines',
                                        'cars' => '🚗 Cars', 
                                        'trucks' => '🚛 Trucks',
                                        'vans' => '🚐 Vans',
                                        'motorcycles' => '🏍️ Motorcycles',
                                        'trailers' => '🚛 Trailers',
                                        'general' => '🅿️ General'
                                    ];
                                    $category = $parking_space['vehicle_category'] ?? 'general';
                                    echo $category_display[$category] ?? ucfirst($category);
                                    echo ' • ' . ucfirst($parking_space['space_type'] ?? 'standard');
                                    echo ' • Rate: ' . getCurrencySymbol($parking_space['currency'] ?? 'USD') . number_format($parking_space['monthly_rate'], 2) . '/month';
                                    ?>
                                </small>
                            <?php else: ?>
                                <select class="form-control" id="parking_space_id" name="parking_space_id" required>
                                    <option value="">Select Parking Space</option>
                                    <?php foreach ($available_spaces as $space): ?>
                                        <option value="<?php echo $space['id']; ?>" 
                                                data-rate="<?php echo $space['monthly_rate']; ?>"
                                                data-currency="<?php echo $space['currency'] ?? 'USD'; ?>"
                                                <?php echo (isset($_POST['parking_space_id']) && $_POST['parking_space_id'] == $space['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($space['space_name'] . ' (' . $space['space_code'] . ') - ' . getCurrencySymbol($space['currency'] ?? 'USD') . number_format($space['monthly_rate'], 2) . '/month'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_name" class="form-label">Client Name *</label>
                            <input type="text" class="form-control" id="client_name" name="client_name" 
                                   value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_contact" class="form-label">Client Contact</label>
                            <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                   value="<?php echo htmlspecialchars($_POST['client_contact'] ?? ''); ?>"
                                   placeholder="Phone, Email, or Address">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="vehicle_type" class="form-label">Vehicle Type</label>
                            <select class="form-control" id="vehicle_type" name="vehicle_type">
                                <option value="">Select Vehicle Type</option>
                                <option value="Excavator" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Excavator') ? 'selected' : ''; ?>>Excavator</option>
                                <option value="Bulldozer" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Bulldozer') ? 'selected' : ''; ?>>Bulldozer</option>
                                <option value="Crane" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Crane') ? 'selected' : ''; ?>>Crane</option>
                                <option value="Dump Truck" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Dump Truck') ? 'selected' : ''; ?>>Dump Truck</option>
                                <option value="Pickup Truck" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Pickup Truck') ? 'selected' : ''; ?>>Pickup Truck</option>
                                <option value="Van" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Van') ? 'selected' : ''; ?>>Van</option>
                                <option value="Car" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Car') ? 'selected' : ''; ?>>Car</option>
                                <option value="Motorcycle" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Motorcycle') ? 'selected' : ''; ?>>Motorcycle</option>
                                <option value="Trailer" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Trailer') ? 'selected' : ''; ?>>Trailer</option>
                                <option value="Other" <?php echo (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="vehicle_registration" class="form-label">Vehicle Registration/License Plate</label>
                            <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" 
                                   value="<?php echo htmlspecialchars($_POST['vehicle_registration'] ?? ''); ?>"
                                   placeholder="License plate number or ID">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date (Optional)</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                            <small class="text-muted">Leave empty for ongoing rental</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label">Monthly Rate *</label>
                            <div class="input-group">
                                <span class="input-group-text" id="currency-symbol">$</span>
                                <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                       value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? ($parking_space['monthly_rate'] ?? '')); ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label">Currency</label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo (($_POST['currency'] ?? $parking_space['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? $parking_space['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                <option value="EUR" <?php echo (($_POST['currency'] ?? $parking_space['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                <option value="GBP" <?php echo (($_POST['currency'] ?? $parking_space['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes & Special Instructions</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Any special instructions, parking rules, or additional information..."
                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    <small class="form-text text-muted">You can use spaces in notes and descriptions.</small>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Parking Rental
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const spaceSelect = document.getElementById('parking_space_id');
    const monthlyRateInput = document.getElementById('monthly_rate');
    const currencySelect = document.getElementById('currency');
    const currencySymbol = document.getElementById('currency-symbol');
    const notesTextarea = document.getElementById('notes');
    
    // Enable spaces in textarea
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
    
    // Update currency symbol
    function updateCurrencySymbol() {
        const currency = currencySelect.value;
        const symbols = {
            'USD': '$',
            'AFN': '؋',
            'EUR': '€',
            'GBP': '£'
        };
        currencySymbol.textContent = symbols[currency] || '$';
    }
    
    // Handle parking space selection
    if (spaceSelect) {
        spaceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                monthlyRateInput.value = selectedOption.dataset.rate || '';
                currencySelect.value = selectedOption.dataset.currency || 'USD';
                updateCurrencySymbol();
            }
        });
    }
    
    // Update currency symbol when currency changes
    currencySelect.addEventListener('change', updateCurrencySymbol);
    
    // Set initial currency symbol
    updateCurrencySymbol();
});
</script>

<?php require_once '../../../includes/footer.php'; ?>