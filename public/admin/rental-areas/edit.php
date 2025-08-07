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

// Get area ID from URL
$area_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$area_id) {
    header('Location: index.php');
    exit;
}

// Get rental area details
$stmt = $conn->prepare("SELECT * FROM rental_areas WHERE id = ? AND company_id = ?");
$stmt->execute([$area_id, $company_id]);
$area = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$area) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['area_name', 'area_code', 'area_type', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate monthly rate
        if (!is_numeric($_POST['monthly_rate']) || $_POST['monthly_rate'] <= 0) {
            throw new Exception("Monthly rate must be a positive number.");
        }

        // Check if area code is unique (excluding current area)
        $stmt = $conn->prepare("SELECT id FROM rental_areas WHERE area_code = ? AND company_id = ? AND id != ?");
        $stmt->execute([trim($_POST['area_code']), $company_id, $area_id]);
        if ($stmt->fetch()) {
            throw new Exception("Area code must be unique.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update rental area record
        $stmt = $conn->prepare("
            UPDATE rental_areas SET 
                area_name = ?, area_code = ?, area_type = ?, description = ?,
                location_details = ?, amenities = ?, restrictions = ?, monthly_rate = ?,
                currency = ?, capacity = ?, area_size_sqm = ?, has_electricity = ?,
                has_water = ?, has_security = ?, has_parking = ?, has_loading_dock = ?,
                is_covered = ?, max_vehicle_size = ?, operating_hours = ?, contact_person = ?,
                contact_phone = ?, contact_email = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            trim($_POST['area_name']),
            trim($_POST['area_code']),
            $_POST['area_type'],
            trim($_POST['description'] ?? ''),
            trim($_POST['location_details'] ?? ''),
            trim($_POST['amenities'] ?? ''),
            trim($_POST['restrictions'] ?? ''),
            $_POST['monthly_rate'],
            $_POST['currency'] ?? 'USD',
            $_POST['capacity'] ?? null,
            $_POST['area_size_sqm'] ?? null,
            isset($_POST['has_electricity']) ? 1 : 0,
            isset($_POST['has_water']) ? 1 : 0,
            isset($_POST['has_security']) ? 1 : 0,
            isset($_POST['has_parking']) ? 1 : 0,
            isset($_POST['has_loading_dock']) ? 1 : 0,
            isset($_POST['is_covered']) ? 1 : 0,
            trim($_POST['max_vehicle_size'] ?? ''),
            trim($_POST['operating_hours'] ?? ''),
            trim($_POST['contact_person'] ?? ''),
            trim($_POST['contact_phone'] ?? ''),
            trim($_POST['contact_email'] ?? ''),
            $area_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Rental area updated successfully!";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$area_id'; }, 2000);</script>";

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
                <i class="fas fa-edit"></i> Edit Rental Area
            </h1>
            <p class="text-muted mb-0">Update details for <?php echo htmlspecialchars($area['area_name']); ?></p>
        </div>
        <div class="btn-group" role="group">
            <a href="view.php?id=<?php echo $area_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> View Details
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Areas
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Rental Area Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-edit"></i> Area Information
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="areaForm">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-info-circle"></i> Basic Information
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="area_name" class="form-label">Area Name *</label>
                                    <input type="text" class="form-control" id="area_name" name="area_name" 
                                           value="<?php echo htmlspecialchars($area['area_name']); ?>" 
                                           style="text-transform: none;" autocomplete="off" spellcheck="false" required>
                                    <small class="form-text text-muted">You can use spaces in area names.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="area_code" class="form-label">Area Code *</label>
                                    <input type="text" class="form-control" id="area_code" name="area_code" 
                                           value="<?php echo htmlspecialchars($area['area_code']); ?>" 
                                           style="text-transform: none;" autocomplete="off" spellcheck="false" required>
                                    <small class="form-text text-muted">You can use spaces in area codes.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="area_type" class="form-label">Area Type *</label>
                                    <select class="form-control" id="area_type" name="area_type" required>
                                        <option value="">Select Area Type</option>
                                        <option value="commercial" <?php echo $area['area_type'] === 'commercial' ? 'selected' : ''; ?>>🏢 Commercial</option>
                                        <option value="industrial" <?php echo $area['area_type'] === 'industrial' ? 'selected' : ''; ?>>🏭 Industrial</option>
                                        <option value="residential" <?php echo $area['area_type'] === 'residential' ? 'selected' : ''; ?>>🏠 Residential</option>
                                        <option value="container" <?php echo $area['area_type'] === 'container' ? 'selected' : ''; ?>>📦 Container</option>
                                        <option value="event" <?php echo $area['area_type'] === 'event' ? 'selected' : ''; ?>>🎉 Event</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_rate" class="form-label">Monthly Rate *</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="currency-symbol">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                               value="<?php echo htmlspecialchars($area['monthly_rate']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency" class="form-label">Currency</label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="USD" <?php echo ($area['currency'] ?? 'USD') == 'USD' ? 'selected' : ''; ?>>USD - US Dollar ($)</option>
                                        <option value="AFN" <?php echo ($area['currency'] ?? '') == 'AFN' ? 'selected' : ''; ?>>AFN - Afghan Afghani (؋)</option>
                                        <option value="EUR" <?php echo ($area['currency'] ?? '') == 'EUR' ? 'selected' : ''; ?>>EUR - Euro (€)</option>
                                        <option value="GBP" <?php echo ($area['currency'] ?? '') == 'GBP' ? 'selected' : ''; ?>>GBP - British Pound (£)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="area_size_sqm" class="form-label">Area Size (sqm)</label>
                                    <input type="number" step="0.1" min="0" class="form-control" id="area_size_sqm" name="area_size_sqm" 
                                           value="<?php echo htmlspecialchars($area['area_size_sqm'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Description & Details -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-align-left"></i> Description & Details
                                </h6>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Describe the area and its features..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($area['description'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in descriptions.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location_details" class="form-label">Location Details</label>
                                    <textarea class="form-control" id="location_details" name="location_details" rows="3" 
                                              placeholder="Specific location information..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($area['location_details'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in location details.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amenities" class="form-label">Amenities</label>
                                    <textarea class="form-control" id="amenities" name="amenities" rows="3" 
                                              placeholder="List available amenities..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($area['amenities'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in amenities.</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="restrictions" class="form-label">Restrictions</label>
                                    <textarea class="form-control" id="restrictions" name="restrictions" rows="3" 
                                              placeholder="Any restrictions or limitations..."
                                              style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($area['restrictions'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">You can use spaces in restrictions.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Amenities -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-cogs"></i> Amenities
                                </h6>
                            </div>
                            <div class="col-md-6">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="has_electricity" name="has_electricity" 
                                                   <?php echo $area['has_electricity'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_electricity">
                                                <i class="fas fa-bolt text-success"></i> Electricity
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="has_water" name="has_water" 
                                                   <?php echo $area['has_water'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_water">
                                                <i class="fas fa-tint text-info"></i> Water
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="has_security" name="has_security" 
                                                   <?php echo $area['has_security'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_security">
                                                <i class="fas fa-shield-alt text-warning"></i> Security
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="has_parking" name="has_parking" 
                                                   <?php echo $area['has_parking'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_parking">
                                                <i class="fas fa-car text-primary"></i> Parking
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="has_loading_dock" name="has_loading_dock" 
                                                   <?php echo $area['has_loading_dock'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="has_loading_dock">
                                                <i class="fas fa-truck text-success"></i> Loading Dock
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="is_covered" name="is_covered" 
                                                   <?php echo $area['is_covered'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_covered">
                                                <i class="fas fa-home text-secondary"></i> Covered
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="capacity" class="form-label">Capacity (People)</label>
                                    <input type="number" min="1" class="form-control" id="capacity" name="capacity" 
                                           value="<?php echo htmlspecialchars($area['capacity'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="max_vehicle_size" class="form-label">Max Vehicle Size</label>
                                    <input type="text" class="form-control" id="max_vehicle_size" name="max_vehicle_size" 
                                           value="<?php echo htmlspecialchars($area['max_vehicle_size'] ?? ''); ?>"
                                           placeholder="e.g., Large trucks, Medium trucks"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in vehicle size descriptions.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="operating_hours" class="form-label">Operating Hours</label>
                                    <input type="text" class="form-control" id="operating_hours" name="operating_hours" 
                                           value="<?php echo htmlspecialchars($area['operating_hours'] ?? ''); ?>"
                                           placeholder="e.g., 8:00 AM - 8:00 PM, 24/7"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in operating hours.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="fas fa-phone"></i> Contact Information
                                </h6>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                           value="<?php echo htmlspecialchars($area['contact_person'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in contact names.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Contact Phone</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" 
                                           value="<?php echo htmlspecialchars($area['contact_phone'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in phone numbers.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                           value="<?php echo htmlspecialchars($area['contact_email'] ?? ''); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in email addresses.</small>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Area
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Current Area Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Current Area Info
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo htmlspecialchars($area['area_name']); ?></h6>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($area['area_code']); ?></p>
                        <span class="badge bg-<?php echo $area['area_type'] === 'commercial' ? 'primary' : ($area['area_type'] === 'industrial' ? 'warning' : 'success'); ?>">
                            <?php echo ucfirst($area['area_type']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-secondary">Financial</h6>
                        <div class="d-flex justify-content-between">
                            <span>Monthly Rate:</span>
                            <strong class="text-success"><?php echo formatCurrencyAmount($area['monthly_rate'], $area['currency'] ?? 'USD'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Daily Rate:</span>
                            <strong class="text-info"><?php echo formatCurrencyAmount($area['daily_rate'], $area['currency'] ?? 'USD'); ?></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-secondary">Status</h6>
                        <span class="badge bg-<?php echo $area['status'] === 'available' ? 'success' : ($area['status'] === 'in_use' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($area['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Rate Calculator -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-calculator"></i> Rate Calculator
                    </h6>
                </div>
                <div class="card-body" id="rateCalculator">
                    <div class="text-center text-muted">
                        <i class="fas fa-calculator fa-3x mb-3"></i>
                        <p>Enter monthly rate to calculate</p>
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
    const monthlyRateInput = document.getElementById('monthly_rate');
    
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
    
    // Update currency symbol when currency changes
    currencySelect.addEventListener('change', updateCurrencySymbol);
    
    // Set initial currency symbol
    updateCurrencySymbol();
    
    // Function to update rate calculator
    function updateRateCalculator() {
        const monthlyRate = parseFloat(monthlyRateInput.value) || 0;
        const currency = currencySelect.value;
        
        if (monthlyRate > 0) {
            const dailyRate = monthlyRate / 30;
            const weeklyRate = monthlyRate / 4;
            const yearlyRate = monthlyRate * 12;
            
            document.getElementById('rateCalculator').innerHTML = `
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
    monthlyRateInput.addEventListener('input', updateRateCalculator);
    currencySelect.addEventListener('change', updateRateCalculator);
    
    // Initialize calculator
    updateRateCalculator();
});
</script>

<?php require_once '../../../includes/footer.php'; ?>