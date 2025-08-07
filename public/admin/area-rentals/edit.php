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
$rental_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$rental_id) {
    header('Location: index.php');
    exit;
}

// Get rental details with enhanced information
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        ra.area_name,
        ra.area_code,
        ra.area_type,
        ra.area_size_sqm,
        ra.has_electricity,
        ra.has_water,
        ra.has_security,
        ra.has_parking,
        ra.has_loading_dock,
        ra.is_covered,
        ra.currency as area_currency,
        ra.monthly_rate as area_monthly_rate,
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

// Get available rental areas (including current one)
$stmt = $conn->prepare("
    SELECT 
        ra.*,
        COUNT(ar.id) as active_rentals,
        COALESCE(SUM(arp.amount), 0) as total_earnings
    FROM rental_areas ra
    LEFT JOIN area_rentals ar ON ra.id = ar.rental_area_id AND ar.status = 'active' AND ar.id != ?
    LEFT JOIN area_rental_payments arp ON ar.id = arp.area_rental_id
    WHERE ra.company_id = ? AND (ra.status = 'available' OR ra.id = ?)
    GROUP BY ra.id
    ORDER BY ra.area_name
");
$stmt->execute([$rental_id, $company_id, $rental['rental_area_id']]);
$rental_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['rental_area_id', 'client_name', 'start_date', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate monthly rate
        if (!is_numeric($_POST['monthly_rate']) || $_POST['monthly_rate'] <= 0) {
            throw new Exception("Monthly rate must be a positive number.");
        }

        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        
        if ($end_date && strtotime($end_date) <= strtotime($start_date)) {
            throw new Exception("End date must be after start date.");
        }

        // Get rental area details for validation
        $stmt = $conn->prepare("SELECT * FROM rental_areas WHERE id = ? AND company_id = ?");
        $stmt->execute([$_POST['rental_area_id'], $company_id]);
        $selected_area = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selected_area) {
            throw new Exception("Invalid rental area selected.");
        }

        // Check if area is available (unless it's the current area)
        if ($selected_area['id'] != $rental['rental_area_id'] && $selected_area['status'] !== 'available') {
            throw new Exception("Selected rental area is not available.");
        }

        // Calculate total days and amount if end date is provided
        $total_days = null;
        $total_amount = null;
        if ($end_date) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $total_days = $end->diff($start)->days + 1; // Include both start and end day
            $total_amount = $total_days * ($_POST['monthly_rate'] / 30); // Daily rate calculation
        }

        // Start transaction
        $conn->beginTransaction();

        // Update area rental record with enhanced fields
        $stmt = $conn->prepare("
            UPDATE area_rentals SET 
                rental_area_id = ?, client_name = ?, client_contact = ?, purpose = ?,
                rental_type = ?, business_type = ?, expected_income = ?, security_deposit = ?,
                currency = ?, payment_frequency = ?, late_fee_percentage = ?, grace_period_days = ?,
                auto_renewal = ?, renewal_notice_days = ?, special_conditions = ?, emergency_contact = ?,
                emergency_phone = ?, insurance_required = ?, insurance_provider = ?, insurance_policy_number = ?,
                insurance_expiry_date = ?, permit_required = ?, permit_number = ?, permit_expiry_date = ?,
                start_date = ?, end_date = ?, monthly_rate = ?, total_days = ?, total_amount = ?,
                notes = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['rental_area_id'],
            trim($_POST['client_name']),
            trim($_POST['client_contact'] ?? ''),
            trim($_POST['purpose'] ?? ''),
            $_POST['rental_type'] ?? 'standard',
            trim($_POST['business_type'] ?? ''),
            $_POST['expected_income'] ?? null,
            $_POST['security_deposit'] ?? 0,
            $_POST['currency'] ?? 'USD',
            $_POST['payment_frequency'] ?? 'monthly',
            $_POST['late_fee_percentage'] ?? 5.00,
            $_POST['grace_period_days'] ?? 5,
            isset($_POST['auto_renewal']) ? 1 : 0,
            $_POST['renewal_notice_days'] ?? 30,
            trim($_POST['special_conditions'] ?? ''),
            trim($_POST['emergency_contact'] ?? ''),
            trim($_POST['emergency_phone'] ?? ''),
            isset($_POST['insurance_required']) ? 1 : 0,
            trim($_POST['insurance_provider'] ?? ''),
            trim($_POST['insurance_policy_number'] ?? ''),
            $_POST['insurance_expiry_date'] ?? null,
            isset($_POST['permit_required']) ? 1 : 0,
            trim($_POST['permit_number'] ?? ''),
            $_POST['permit_expiry_date'] ?? null,
            $start_date,
            $end_date,
            $_POST['monthly_rate'],
            $total_days,
            $total_amount,
            trim($_POST['notes'] ?? ''),
            $rental_id,
            $company_id
        ]);

        // Handle area status changes
        if ($_POST['rental_area_id'] != $rental['rental_area_id']) {
            // Update old area status back to available
            $stmt = $conn->prepare("UPDATE rental_areas SET status = 'available' WHERE id = ?");
            $stmt->execute([$rental['rental_area_id']]);
            
            // Update new area status to in_use
            $stmt = $conn->prepare("UPDATE rental_areas SET status = 'in_use' WHERE id = ?");
            $stmt->execute([$_POST['rental_area_id']]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Area rental updated successfully!";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";

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
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-edit"></i> Edit Area Rental
            </h1>
            <p class="text-muted mb-0">Update rental details for <?php echo htmlspecialchars($rental['rental_code']); ?></p>
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

    <!-- Edit Area Rental Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit"></i> Rental Information
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="areaRentalForm">
                        <!-- Area Selection -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-map-marker-alt"></i> Select Rental Area
                                </h6>
                                <?php if (empty($rental_areas)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        No available rental areas found.
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($rental_areas as $area): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100 border-<?php echo $area['area_type'] === 'commercial' ? 'primary' : ($area['area_type'] === 'industrial' ? 'warning' : 'success'); ?> <?php echo $area['id'] == $rental['rental_area_id'] ? 'border-success' : ''; ?>">
                                                    <div class="card-body">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="rental_area_id" 
                                                                   id="area_<?php echo $area['id']; ?>" value="<?php echo $area['id']; ?>" 
                                                                   data-rate="<?php echo $area['monthly_rate']; ?>" 
                                                                   data-currency="<?php echo $area['currency'] ?? 'USD'; ?>"
                                                                   data-type="<?php echo $area['area_type']; ?>"
                                                                   <?php echo $area['id'] == $rental['rental_area_id'] ? 'checked' : ''; ?>
                                                                   required>
                                                            <label class="form-check-label" for="area_<?php echo $area['id']; ?>">
                                                                <strong><?php echo htmlspecialchars($area['area_name']); ?></strong>
                                                                <?php if ($area['id'] == $rental['rental_area_id']): ?>
                                                                    <span class="badge bg-success">Current</span>
                                                                <?php endif; ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($area['area_code']); ?></small>
                                                                <br>
                                                                <span class="badge bg-<?php echo $area['area_type'] === 'commercial' ? 'primary' : ($area['area_type'] === 'industrial' ? 'warning' : 'success'); ?>">
                                                                    <?php echo ucfirst($area['area_type']); ?>
                                                                </span>
                                                                <br>
                                                                <strong class="text-success">
                                                                    <?php echo formatCurrencyAmount($area['monthly_rate'], $area['currency'] ?? 'USD'); ?>/month
                                                                </strong>
                                                                <?php if (!empty($area['area_size_sqm'])): ?>
                                                                    <br>
                                                                    <small class="text-muted"><?php echo number_format($area['area_size_sqm'], 1); ?> sqm</small>
                                                                <?php endif; ?>
                                                                <div class="mt-2">
                                                                    <?php if ($area['has_electricity']): ?>
                                                                        <i class="fas fa-bolt text-success" title="Electricity"></i>
                                                                    <?php endif; ?>
                                                                    <?php if ($area['has_water']): ?>
                                                                        <i class="fas fa-tint text-info" title="Water"></i>
                                                                    <?php endif; ?>
                                                                    <?php if ($area['has_security']): ?>
                                                                        <i class="fas fa-shield-alt text-warning" title="Security"></i>
                                                                    <?php endif; ?>
                                                                    <?php if ($area['has_parking']): ?>
                                                                        <i class="fas fa-car text-primary" title="Parking"></i>
                                                                    <?php endif; ?>
                                                                    <?php if ($area['is_covered']): ?>
                                                                        <i class="fas fa-home text-secondary" title="Covered"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Client Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user"></i> Client Information
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_name" class="form-label">Client Name *</label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" 
                                           value="<?php echo htmlspecialchars($rental['client_name']); ?>" 
                                           style="text-transform: none;" autocomplete="off" spellcheck="false" required>
                                    <small class="form-text text-muted">You can use spaces in client names.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_contact" class="form-label">Client Contact</label>
                                    <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                           value="<?php echo htmlspecialchars($rental['client_contact'] ?? ''); ?>"
                                           placeholder="Phone, Email, or Address"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in contact information.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-briefcase"></i> Business Information
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rental_type" class="form-label">Rental Type *</label>
                                    <select class="form-control" id="rental_type" name="rental_type" required>
                                        <option value="">Select Rental Type</option>
                                        <option value="commercial" <?php echo $rental['rental_type'] === 'commercial' ? 'selected' : ''; ?>>🏢 Commercial</option>
                                        <option value="residential" <?php echo $rental['rental_type'] === 'residential' ? 'selected' : ''; ?>>🏠 Residential</option>
                                        <option value="industrial" <?php echo $rental['rental_type'] === 'industrial' ? 'selected' : ''; ?>>🏭 Industrial</option>
                                        <option value="container" <?php echo $rental['rental_type'] === 'container' ? 'selected' : ''; ?>>📦 Container</option>
                                        <option value="event" <?php echo $rental['rental_type'] === 'event' ? 'selected' : ''; ?>>🎉 Event</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="business_type" class="form-label">Business Type</label>
                                    <input type="text" class="form-control" id="business_type" name="business_type" 
                                           value="<?php echo htmlspecialchars($rental['business_type'] ?? ''); ?>"
                                           placeholder="e.g., Retail Shop, Restaurant, Workshop"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in business types.</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="purpose" class="form-label">Purpose of Rental</label>
                                    <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                                              placeholder="Describe the intended use of the area..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($rental['purpose'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in descriptions.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Rental Terms -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-calendar-alt"></i> Rental Terms
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($rental['start_date']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date (Optional)</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($rental['end_date'] ?? ''); ?>">
                                    <small class="text-muted">Leave empty for ongoing rental</small>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Terms -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-dollar-sign"></i> Financial Terms
                                </h6>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="monthly_rate" class="form-label">Monthly Rate *</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="currency-symbol">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                               value="<?php echo htmlspecialchars($rental['monthly_rate']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Currency</label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="USD" <?php echo ($rental['currency'] ?? 'USD') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                        <option value="AFN" <?php echo ($rental['currency'] ?? '') == 'AFN' ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                        <option value="EUR" <?php echo ($rental['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                        <option value="GBP" <?php echo ($rental['currency'] ?? '') == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="security_deposit" class="form-label">Security Deposit</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="security_deposit" name="security_deposit" 
                                           value="<?php echo htmlspecialchars($rental['security_deposit'] ?? '0'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_frequency" class="form-label">Payment Frequency</label>
                                    <select class="form-control" id="payment_frequency" name="payment_frequency">
                                        <option value="monthly" <?php echo ($rental['payment_frequency'] ?? 'monthly') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="quarterly" <?php echo ($rental['payment_frequency'] ?? '') == 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                        <option value="annually" <?php echo ($rental['payment_frequency'] ?? '') == 'annually' ? 'selected' : ''; ?>>Annually</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expected_income" class="form-label">Expected Income</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="expected_income" name="expected_income" 
                                           value="<?php echo htmlspecialchars($rental['expected_income'] ?? ''); ?>"
                                           placeholder="Client's expected monthly income">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Terms -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cog"></i> Additional Terms
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="late_fee_percentage" class="form-label">Late Fee Percentage</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" class="form-control" id="late_fee_percentage" name="late_fee_percentage" 
                                               value="<?php echo htmlspecialchars($rental['late_fee_percentage'] ?? '5.00'); ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="grace_period_days" class="form-label">Grace Period (Days)</label>
                                    <input type="number" min="0" class="form-control" id="grace_period_days" name="grace_period_days" 
                                           value="<?php echo htmlspecialchars($rental['grace_period_days'] ?? '5'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="renewal_notice_days" class="form-label">Renewal Notice (Days)</label>
                                    <input type="number" min="0" class="form-control" id="renewal_notice_days" name="renewal_notice_days" 
                                           value="<?php echo htmlspecialchars($rental['renewal_notice_days'] ?? '30'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="auto_renewal" name="auto_renewal" 
                                               <?php echo $rental['auto_renewal'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="auto_renewal">
                                            Auto-renewal enabled
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-phone"></i> Emergency Contact
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact" class="form-label">Emergency Contact Name</label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?php echo htmlspecialchars($rental['emergency_contact'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in contact names.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_phone" class="form-label">Emergency Phone</label>
                                    <input type="text" class="form-control" id="emergency_phone" name="emergency_phone" 
                                           value="<?php echo htmlspecialchars($rental['emergency_phone'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in phone numbers.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Insurance & Permits -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-shield-alt"></i> Insurance & Permits
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="insurance_required" name="insurance_required" 
                                               <?php echo $rental['insurance_required'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="insurance_required">
                                            Insurance required
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="insurance_provider" class="form-label">Insurance Provider</label>
                                    <input type="text" class="form-control" id="insurance_provider" name="insurance_provider" 
                                           value="<?php echo htmlspecialchars($rental['insurance_provider'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in provider names.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="insurance_policy_number" class="form-label">Policy Number</label>
                                    <input type="text" class="form-control" id="insurance_policy_number" name="insurance_policy_number" 
                                           value="<?php echo htmlspecialchars($rental['insurance_policy_number'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in policy numbers.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="insurance_expiry_date" class="form-label">Insurance Expiry Date</label>
                                    <input type="date" class="form-control" id="insurance_expiry_date" name="insurance_expiry_date" 
                                           value="<?php echo htmlspecialchars($rental['insurance_expiry_date'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="permit_required" name="permit_required" 
                                               <?php echo $rental['permit_required'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="permit_required">
                                            Permit required
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="permit_number" class="form-label">Permit Number</label>
                                    <input type="text" class="form-control" id="permit_number" name="permit_number" 
                                           value="<?php echo htmlspecialchars($rental['permit_number'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in permit numbers.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="permit_expiry_date" class="form-label">Permit Expiry Date</label>
                                    <input type="date" class="form-control" id="permit_expiry_date" name="permit_expiry_date" 
                                           value="<?php echo htmlspecialchars($rental['permit_expiry_date'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Special Conditions & Notes -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-sticky-note"></i> Special Conditions & Notes
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="special_conditions" class="form-label">Special Conditions</label>
                                    <textarea class="form-control" id="special_conditions" name="special_conditions" rows="3" 
                                              placeholder="Any special terms, conditions, or requirements..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($rental['special_conditions'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in special conditions.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Additional Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Any additional notes or comments..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($rental['notes'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in notes.</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Area Rental
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar with Rental Information -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Current Rental Details
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo htmlspecialchars($rental['rental_code']); ?></h6>
                        <p class="text-muted mb-2">Rental Code</p>
                        <span class="badge bg-<?php echo $rental['status'] === 'active' ? 'success' : ($rental['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($rental['status']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-secondary">Area Information</h6>
                        <p class="mb-1"><strong><?php echo htmlspecialchars($rental['area_name']); ?></strong></p>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($rental['area_code']); ?></p>
                        <span class="badge bg-info"><?php echo ucfirst($rental['area_type']); ?></span>
                        <?php if (!empty($rental['area_size_sqm'])): ?>
                            <br><small class="text-muted"><?php echo number_format($rental['area_size_sqm'], 1); ?> sqm</small>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-secondary">Financial Summary</h6>
                        <div class="d-flex justify-content-between">
                            <span>Monthly Rate:</span>
                            <strong class="text-success"><?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Paid:</span>
                            <strong class="text-info"><?php echo formatCurrencyAmount($rental['total_paid'], $rental['currency'] ?? 'USD'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Payments:</span>
                            <span class="badge bg-light text-dark"><?php echo $rental['payment_count']; ?> records</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-secondary">Rental Period</h6>
                        <p class="mb-1">Start: <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                        <?php if (!empty($rental['end_date'])): ?>
                            <p class="mb-1">End: <?php echo date('M j, Y', strtotime($rental['end_date'])); ?></p>
                        <?php else: ?>
                            <p class="mb-1 text-info">Ongoing rental</p>
                        <?php endif; ?>
                        <?php if (!empty($rental['total_days'])): ?>
                            <p class="mb-1">Duration: <?php echo $rental['total_days']; ?> days</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator"></i> Rental Calculator
                    </h6>
                </div>
                <div class="card-body" id="rentalCalculator">
                    <div class="text-center text-muted">
                        <i class="fas fa-calculator fa-3x mb-3"></i>
                        <p>Enter rental details to calculate</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enable spaces in all text inputs and textareas
    const textInputs = document.querySelectorAll('input[type="text"], textarea');
    const currencySelect = document.getElementById('currency');
    const currencySymbol = document.getElementById('currency-symbol');
    
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
    
    // Handle area selection
    const areaRadios = document.querySelectorAll('input[name="rental_area_id"]');
    const monthlyRateInput = document.getElementById('monthly_rate');
    const rentalTypeSelect = document.getElementById('rental_type');
    
    areaRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const rate = this.dataset.rate;
                const currency = this.dataset.currency;
                const areaType = this.dataset.type;
                
                // Update monthly rate
                monthlyRateInput.value = rate;
                
                // Update currency
                currencySelect.value = currency;
                updateCurrencySymbol();
                
                // Auto-suggest rental type based on area type
                rentalTypeSelect.value = areaType;
                
                updateRentalCalculator();
            }
        });
    });
    
    // Update currency symbol when currency changes
    currencySelect.addEventListener('change', updateCurrencySymbol);
    
    // Set initial currency symbol
    updateCurrencySymbol();
    
    // Function to update rental calculator
    function updateRentalCalculator() {
        const monthlyRate = parseFloat(monthlyRateInput.value) || 0;
        const securityDeposit = parseFloat(document.getElementById('security_deposit').value) || 0;
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        
        if (monthlyRate > 0) {
            const dailyRate = monthlyRate / 30;
            const weeklyRate = monthlyRate / 4;
            const yearlyRate = monthlyRate * 12;
            
            let totalAmount = 0;
            let totalDays = 0;
            
            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                totalDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
                totalAmount = totalDays * dailyRate;
            }
            
            const currency = currencySelect.value;
            const symbol = currency === 'AFN' ? '؋' : (currency === 'EUR' ? '€' : '$');
            
            document.getElementById('rentalCalculator').innerHTML = `
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h6 class="text-primary">${formatCurrency(dailyRate, currency)}</h6>
                        <small class="text-muted">Daily Rate</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-success">${formatCurrency(weeklyRate, currency)}</h6>
                        <small class="text-muted">Weekly Rate</small>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h6 class="text-info">${formatCurrency(monthlyRate, currency)}</h6>
                        <small class="text-muted">Monthly Rate</small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-warning">${formatCurrency(yearlyRate, currency)}</h6>
                        <small class="text-muted">Yearly Rate</small>
                    </div>
                </div>
                ${totalDays > 0 ? `
                <hr>
                <div class="text-center">
                    <h6 class="text-primary">${totalDays} days</h6>
                    <small class="text-muted">Total Duration</small>
                    <br>
                    <h6 class="text-success">${formatCurrency(totalAmount, currency)}</h6>
                    <small class="text-muted">Total Amount</small>
                </div>
                ` : ''}
                ${securityDeposit > 0 ? `
                <hr>
                <div class="text-center">
                    <h6 class="text-warning">${formatCurrency(securityDeposit, currency)}</h6>
                    <small class="text-muted">Security Deposit</small>
                </div>
                ` : ''}
            `;
        }
    }
    
    // Function to format currency
    function formatCurrency(amount, currency) {
        const symbols = {
            'USD': '$',
            'AFN': '؋',
            'EUR': '€',
            'GBP': '£'
        };
        const symbol = symbols[currency] || '$';
        return symbol + parseFloat(amount).toFixed(2);
    }
    
    // Update calculator when values change
    monthlyRateInput.addEventListener('input', updateRentalCalculator);
    document.getElementById('security_deposit').addEventListener('input', updateRentalCalculator);
    document.getElementById('start_date').addEventListener('change', updateRentalCalculator);
    document.getElementById('end_date').addEventListener('change', updateRentalCalculator);
    
    // Initialize calculator
    updateRentalCalculator();
    
    // Form validation
    const form = document.getElementById('areaRentalForm');
    form.addEventListener('submit', function(e) {
        const selectedArea = document.querySelector('input[name="rental_area_id"]:checked');
        if (!selectedArea) {
            e.preventDefault();
            alert('Please select a rental area.');
            return false;
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>