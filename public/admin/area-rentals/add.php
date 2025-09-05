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


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['client_name', 'start_date', 'monthly_rate'];
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


        // Generate area rental code
        $rental_code = generateAreaRentalCode($company_id);

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

        // Create area rental record with enhanced fields
        $stmt = $conn->prepare("
            INSERT INTO area_rentals (
                company_id, rental_code, client_name, client_contact,
                purpose, rental_type, business_type, expected_income, security_deposit,
                currency, payment_frequency, late_fee_percentage, grace_period_days,
                auto_renewal, renewal_notice_days, special_conditions, emergency_contact,
                emergency_phone, insurance_required, insurance_provider, insurance_policy_number,
                insurance_expiry_date, permit_required, permit_number, permit_expiry_date,
                start_date, end_date, monthly_rate, total_days, total_amount,
                status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $rental_code,
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
            trim($_POST['notes'] ?? '')
        ]);

        $rental_id = $conn->lastInsertId();


        // If security deposit is provided, create a payment record
        if (!empty($_POST['security_deposit']) && $_POST['security_deposit'] > 0) {
            $deposit_code = generateAreaRentalPaymentCode($company_id);
            $stmt = $conn->prepare("
                INSERT INTO area_rental_payments (
                    company_id, area_rental_id, payment_code, payment_date, amount,
                    currency, payment_method, payment_type, reference_number, notes
                ) VALUES (?, ?, ?, CURDATE(), ?, ?, 'cash', 'deposit', ?, 'Security deposit payment')
            ");
            $stmt->execute([
                $company_id,
                $rental_id,
                $deposit_code,
                $_POST['security_deposit'],
                $_POST['currency'] ?? 'USD',
                'DEP' . $rental_code
            ]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Area rental added successfully! Rental Code: $rental_code";

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

// Helper function to generate area rental code
function generateAreaRentalCode($company_id) {
    global $conn;
    
    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $prefix = $company['company_code'] ?? 'COMP';
    
    // Generate unique code
    $code = $prefix . 'AR' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if code already exists
    $stmt = $conn->prepare("SELECT id FROM area_rentals WHERE rental_code = ?");
    $stmt->execute([$code]);
    
    if ($stmt->fetch()) {
        // If exists, generate new code
        return generateAreaRentalCode($company_id);
    }
    
    return $code;
}

// Helper function to generate payment code
function generateAreaRentalPaymentCode($company_id) {
    global $conn;
    
    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $prefix = $company['company_code'] ?? 'COMP';
    
    // Generate unique code
    $code = $prefix . 'ARP' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if code already exists
    $stmt = $conn->prepare("SELECT id FROM area_rental_payments WHERE payment_code = ?");
    $stmt->execute([$code]);
    
    if ($stmt->fetch()) {
        // If exists, generate new code
        return generateAreaRentalPaymentCode($company_id);
    }
    
    return $code;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-plus-circle"></i> <?php echo __('add_new_area_rental'); ?>
            </h1>
            <p class="text-muted mb-0"><?php echo __('create_a_new_area_rental_for_commercial_residential_or_industrial_use'); ?></p>
        </div>
        <div class="btn-group" role="group">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_rentals'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Add Area Rental Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit"></i> <?php echo __('rental_information'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="areaRentalForm">
                        

                        <!-- Client Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-user"></i> <?php echo __('client_information'); ?>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_name" class="form-label"><?php echo __('client_name'); ?> *</label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" 
                                           value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" 
                                           style="text-transform: none;" autocomplete="off" spellcheck="false" required>
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_client_names'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_contact" class="form-label"><?php echo __('client_contact'); ?></label>
                                    <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                           value="<?php echo htmlspecialchars($_POST['client_contact'] ?? ''); ?>"
                                           placeholder="<?php echo __('phone_email_or_address'); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_contact_information'); ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Business Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-briefcase"></i> <?php echo __('business_information'); ?>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rental_type" class="form-label"><?php echo __('rental_type'); ?> *</label>
                                    <select class="form-control" id="rental_type" name="rental_type" required>
                                        <option value=""><?php echo __('select_rental_type'); ?></option>
                                        <option value="commercial" <?php echo (($_POST['rental_type'] ?? '') === 'commercial') ? 'selected' : ''; ?>><?php echo __('commercial'); ?></option>
                                        <option value="residential" <?php echo (($_POST['rental_type'] ?? '') === 'residential') ? 'selected' : ''; ?>><?php echo __('residential'); ?></option>
                                        <option value="industrial" <?php echo (($_POST['rental_type'] ?? '') === 'industrial') ? 'selected' : ''; ?>><?php echo __('industrial'); ?></option>
                                        <option value="container" <?php echo (($_POST['rental_type'] ?? '') === 'container') ? 'selected' : ''; ?>><?php echo __('container'); ?></option>
                                        <option value="event" <?php echo (($_POST['rental_type'] ?? '') === 'event') ? 'selected' : ''; ?>><?php echo __('event'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="business_type" class="form-label"><?php echo __('business_type'); ?></label>
                                    <input type="text" class="form-control" id="business_type" name="business_type" 
                                           value="<?php echo htmlspecialchars($_POST['business_type'] ?? ''); ?>"
                                           placeholder="<?php echo __('e_g_retail_shop_restaurant_workshop'); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_business_types'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="purpose" class="form-label"><?php echo __('purpose_of_rental'); ?></label>
                                    <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                                              placeholder="<?php echo __('describe_the_intended_use_of_the_area'); ?>"
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_descriptions'); ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Rental Terms -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-calendar-alt"></i> <?php echo __('rental_terms'); ?>
                                </h6>
                            </div>
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

                        <!-- Financial Terms -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-dollar-sign"></i> <?php echo __('financial_terms'); ?>
                                </h6>
                            </div>
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
                                    <option value="AFN" <?php echo (($_POST['currency'] ?? '') == 'AFN') ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                    <option value="USD" <?php echo (($_POST['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                        </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="security_deposit" class="form-label"><?php echo __('security_deposit'); ?></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="security_deposit" name="security_deposit" 
                                           value="<?php echo htmlspecialchars($_POST['security_deposit'] ?? '0'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_frequency" class="form-label"><?php echo __('payment_frequency'); ?></label>
                                    <select class="form-control" id="payment_frequency" name="payment_frequency">
                                        <option value="monthly" <?php echo (($_POST['payment_frequency'] ?? 'monthly') == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                        <option value="quarterly" <?php echo (($_POST['payment_frequency'] ?? '') == 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                        <option value="annually" <?php echo (($_POST['payment_frequency'] ?? '') == 'annually') ? 'selected' : ''; ?>>Annually</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expected_income" class="form-label"><?php echo __('expected_income'); ?></label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="expected_income" name="expected_income" 
                                           value="<?php echo htmlspecialchars($_POST['expected_income'] ?? ''); ?>"
                                           placeholder="Client's expected monthly income">
                                </div>
                            </div>
                        </div>

                        <!-- Additional Terms -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cog"></i> <?php echo __('additional_terms'); ?>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="late_fee_percentage" class="form-label"><?php echo __('late_fee_percentage'); ?></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" max="100" class="form-control" id="late_fee_percentage" name="late_fee_percentage" 
                                               value="<?php echo htmlspecialchars($_POST['late_fee_percentage'] ?? '5.00'); ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="grace_period_days" class="form-label"><?php echo __('grace_period_days'); ?></label>
                                    <input type="number" min="0" class="form-control" id="grace_period_days" name="grace_period_days" 
                                           value="<?php echo htmlspecialchars($_POST['grace_period_days'] ?? '5'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="renewal_notice_days" class="form-label"><?php echo __('renewal_notice_days'); ?></label>
                                    <input type="number" min="0" class="form-control" id="renewal_notice_days" name="renewal_notice_days" 
                                           value="<?php echo htmlspecialchars($_POST['renewal_notice_days'] ?? '30'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="auto_renewal" name="auto_renewal" 
                                               <?php echo isset($_POST['auto_renewal']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="auto_renewal">
                                            <?php echo __('auto_renewal_enabled'); ?>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-phone"></i> <?php echo __('emergency_contact'); ?>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_contact" class="form-label"><?php echo __('emergency_contact_name'); ?></label>
                                    <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_contact_names'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="emergency_phone" class="form-label"><?php echo __('emergency_phone'); ?></label>
                                    <input type="text" class="form-control" id="emergency_phone" name="emergency_phone" 
                                           value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_phone_numbers'); ?></small>
                                </div>
                            </div>
                        </div>

                        <!-- Insurance & Permits -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-shield-alt"></i> <?php echo __('insurance_permits'); ?>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="insurance_required" name="insurance_required" 
                                               <?php echo isset($_POST['insurance_required']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="insurance_required">
                                            <?php echo __('insurance_required'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="insurance_provider" class="form-label"><?php echo __('insurance_provider'); ?></label>
                                    <input type="text" class="form-control" id="insurance_provider" name="insurance_provider" 
                                           value="<?php echo htmlspecialchars($_POST['insurance_provider'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_provider_names'); ?></small>
                                </div>
                                <div class="mb-3">
                                    <label for="insurance_policy_number" class="form-label"><?php echo __('policy_number'); ?></label>
                                    <input type="text" class="form-control" id="insurance_policy_number" name="insurance_policy_number" 
                                           value="<?php echo htmlspecialchars($_POST['insurance_policy_number'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_policy_numbers'); ?></small>
                                </div>
                                <div class="mb-3">
                                    <label for="insurance_expiry_date" class="form-label"><?php echo __('insurance_expiry_date'); ?></label>
                                    <input type="date" class="form-control" id="insurance_expiry_date" name="insurance_expiry_date" 
                                           value="<?php echo htmlspecialchars($_POST['insurance_expiry_date'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="permit_required" name="permit_required" 
                                               <?php echo isset($_POST['permit_required']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="permit_required">
                                            <?php echo __('permit_required'); ?>
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="permit_number" class="form-label"><?php echo __('permit_number'); ?></label>
                                    <input type="text" class="form-control" id="permit_number" name="permit_number" 
                                           value="<?php echo htmlspecialchars($_POST['permit_number'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_permit_numbers'); ?></small>
                                </div>
                                <div class="mb-3">
                                    <label for="permit_expiry_date" class="form-label"><?php echo __('permit_expiry_date'); ?></label>
                                    <input type="date" class="form-control" id="permit_expiry_date" name="permit_expiry_date" 
                                           value="<?php echo htmlspecialchars($_POST['permit_expiry_date'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Special Conditions & Notes -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-sticky-note"></i> <?php echo __('special_conditions_notes'); ?>
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="special_conditions" class="form-label"><?php echo __('special_conditions'); ?></label>
                                    <textarea class="form-control" id="special_conditions" name="special_conditions" rows="3" 
                                              placeholder="Any special terms, conditions, or requirements..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['special_conditions'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_special_conditions'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="notes" class="form-label"><?php echo __('additional_notes'); ?></label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                                              placeholder="Any additional notes or comments..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_notes'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo __('create_area_rental'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar with Area Information -->
        <div class="col-lg-4">
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator"></i> <?php echo __('rental_calculator'); ?>
                    </h6>
                </div>
                <div class="card-body" id="rentalCalculator">
                    <div class="text-center text-muted">
                        <i class="fas fa-calculator fa-3x mb-3"></i>
                        <p><?php echo __('enter_rental_details_to_calculate'); ?></p>
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
    const areaDetails = document.getElementById('areaDetails');
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
                
                // Update area details display
                updateAreaDetails(this);
            }
        });
    });
    
    // Update currency symbol when currency changes
    currencySelect.addEventListener('change', updateCurrencySymbol);
    
    // Set initial currency symbol
    updateCurrencySymbol();
    
    // Function to update area details
    function updateAreaDetails(selectedRadio) {
        const card = selectedRadio.closest('.card');
        const areaName = card.querySelector('strong').textContent;
        const areaCode = card.querySelector('small').textContent;
        const areaType = card.querySelector('.badge').textContent;
        const monthlyRate = selectedRadio.dataset.rate;
        const currency = selectedRadio.dataset.currency;
        
        areaDetails.innerHTML = `
            <div class="mb-3">
                <h6 class="text-primary">${areaName}</h6>
                <p class="text-muted mb-2">${areaCode}</p>
                <span class="badge bg-info mb-2">${areaType}</span>
                <div class="d-flex justify-content-between align-items-center">
                    <strong class="text-success">${formatCurrency(monthlyRate, currency)}/month</strong>
                </div>
            </div>
            <div class="mb-3">
                <h6 class="text-secondary"><?php echo __('amenities'); ?></h6>
                <div class="d-flex flex-wrap gap-1">
                    ${card.querySelectorAll('i').length > 0 ? Array.from(card.querySelectorAll('i')).map(icon => 
                        `<span class="badge bg-light text-dark">${icon.title}</span>`
                    ).join('') : '<span class="text-muted"><?php echo __('no_amenities_listed'); ?></span>'}
                </div>
            </div>
        `;
        
        updateRentalCalculator();
    }
    
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
                        <small class="text-muted"><?php echo __('daily_rate'); ?></small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-success">${formatCurrency(weeklyRate, currency)}</h6>
                        <small class="text-muted"><?php echo __('weekly_rate'); ?></small>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <h6 class="text-info">${formatCurrency(monthlyRate, currency)}</h6>
                        <small class="text-muted"><?php echo __('monthly_rate'); ?></small>
                    </div>
                    <div class="col-6 mb-3">
                        <h6 class="text-warning">${formatCurrency(yearlyRate, currency)}</h6>
                        <small class="text-muted"><?php echo __('yearly_rate'); ?></small>
                    </div>
                </div>
                ${totalDays > 0 ? `
                <hr>
                <div class="text-center">
                    <h6 class="text-primary">${totalDays} <?php echo __('days'); ?></h6>
                    <small class="text-muted"><?php echo __('total_duration'); ?></small>
                    <br>
                    <h6 class="text-success">${formatCurrency(totalAmount, currency)}</h6>
                    <small class="text-muted"><?php echo __('total_amount'); ?></small>
                </div>
                ` : ''}
                ${securityDeposit > 0 ? `
                <hr>
                <div class="text-center">
                    <h6 class="text-warning">${formatCurrency(securityDeposit, currency)}</h6>
                    <small class="text-muted"><?php echo __('security_deposit'); ?></small>
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
    
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>