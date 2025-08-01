<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Get current company settings
$company_id = getCurrentCompanyId();
$current_currency = getCompanyCurrency($company_id);
$current_date_format = getCompanyDateFormat($company_id);
$current_language = getCompanyLanguage($company_id);

// Get available options
$currencies = getAvailableCurrencies();
$date_formats = getAvailableDateFormats();
$languages = getAvailableLanguages();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currency_id = (int)($_POST['currency_id'] ?? 0);
    $date_format_id = (int)($_POST['date_format_id'] ?? 0);
    $language_id = (int)($_POST['language_id'] ?? 0);
    $timezone = trim($_POST['timezone'] ?? 'UTC');
    
    // Validation
    if ($currency_id <= 0) {
        $error = 'Please select a valid currency.';
    } elseif ($date_format_id <= 0) {
        $error = 'Please select a valid date format.';
    } elseif ($language_id <= 0) {
        $error = 'Please select a valid language.';
    } else {
        try {
            // Update company settings
            if (updateCompanySettings($company_id, $currency_id, $date_format_id)) {
                // Update language separately
                updateCompanyLanguage($company_id, $language_id);
                
                $success = 'Company settings updated successfully!';
                
                // Refresh current settings
                $current_currency = getCompanyCurrency($company_id);
                $current_date_format = getCompanyDateFormat($company_id);
            } else {
                $error = 'Failed to update company settings.';
            }
        } catch (Exception $e) {
            $error = 'Error updating settings: ' . $e->getMessage();
        }
    }
}

// Get company information
$company = getCurrentCompany();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Company Settings</h1>
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Currency & Date Format Settings</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_id" class="form-label">
                                        <i class="fas fa-dollar-sign"></i> Default Currency *
                                    </label>
                                    <select class="form-control" id="currency_id" name="currency_id" required>
                                        <option value="">Select Currency</option>
                                        <?php foreach ($currencies as $currency): ?>
                                            <option value="<?php echo $currency['id']; ?>" 
                                                    <?php echo $current_currency['id'] == $currency['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($currency['currency_name']); ?> 
                                                (<?php echo htmlspecialchars($currency['currency_symbol']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        This currency will be used for all financial calculations in your company.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_format_id" class="form-label">
                                        <i class="fas fa-calendar"></i> Date Format *
                                    </label>
                                    <select class="form-control" id="date_format_id" name="date_format_id" required>
                                        <option value="">Select Date Format</option>
                                        <?php foreach ($date_formats as $format): ?>
                                            <option value="<?php echo $format['id']; ?>" 
                                                    <?php echo $current_date_format['id'] == $format['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($format['format_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        This format will be used for displaying dates throughout the system.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="language_id" class="form-label">
                                        <i class="fas fa-language"></i> Language *
                                    </label>
                                    <select class="form-control" id="language_id" name="language_id" required>
                                        <option value="">Select Language</option>
                                        <?php foreach ($languages as $language): ?>
                                            <option value="<?php echo $language['id']; ?>" 
                                                    <?php echo $current_language['id'] == $language['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($language['language_name_native']); ?> 
                                                (<?php echo htmlspecialchars($language['language_name']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">
                                        This language will be used for all text throughout the system.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">
                                        <i class="fas fa-clock"></i> Timezone
                                    </label>
                                    <select class="form-control" id="timezone" name="timezone">
                                        <option value="UTC" <?php echo ($_POST['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        <option value="Asia/Kabul" <?php echo ($_POST['timezone'] ?? '') === 'Asia/Kabul' ? 'selected' : ''; ?>>Asia/Kabul (Afghanistan)</option>
                                        <option value="America/New_York" <?php echo ($_POST['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                        <option value="America/Chicago" <?php echo ($_POST['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>America/Chicago (CST)</option>
                                        <option value="America/Denver" <?php echo ($_POST['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>America/Denver (MST)</option>
                                        <option value="America/Los_Angeles" <?php echo ($_POST['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>America/Los_Angeles (PST)</option>
                                        <option value="Europe/London" <?php echo ($_POST['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT)</option>
                                        <option value="Europe/Paris" <?php echo ($_POST['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Europe/Paris (CET)</option>
                                    </select>
                                    <small class="text-muted">
                                        Timezone for date and time displays.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-info-circle"></i> Current Settings
                                    </label>
                                    <div class="form-control-plaintext">
                                        <strong>Currency:</strong> <?php echo htmlspecialchars($current_currency['currency_name']); ?> 
                                        (<?php echo htmlspecialchars($current_currency['currency_symbol']); ?>)<br>
                                        <strong>Date Format:</strong> <?php echo htmlspecialchars($current_date_format['format_name']); ?><br>
                                        <strong>Language:</strong> <?php echo htmlspecialchars($current_language['language_name_native']); ?> 
                                        (<?php echo htmlspecialchars($current_language['language_name']); ?>)<br>
                                        <strong>Example:</strong> <?php echo formatDate(date('Y-m-d')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Information</h6>
                </div>
                <div class="card-body">
                    <h6>Currency Settings</h6>
                    <p>Changing the default currency will affect:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success"></i> Employee salaries</li>
                        <li><i class="fas fa-check text-success"></i> Contract rates</li>
                        <li><i class="fas fa-check text-success"></i> Parking and rental rates</li>
                        <li><i class="fas fa-check text-success"></i> Expense tracking</li>
                        <li><i class="fas fa-check text-success"></i> Payment calculations</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Date Format Settings</h6>
                    <p>Available formats:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-calendar text-info"></i> <strong>Gregorian:</strong> 2023-12-25</li>
                        <li><i class="fas fa-calendar text-info"></i> <strong>Shamsi:</strong> 1402/10/04</li>
                        <li><i class="fas fa-calendar text-info"></i> <strong>European:</strong> 25/12/2023</li>
                        <li><i class="fas fa-calendar text-info"></i> <strong>American:</strong> 12/25/2023</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Language Settings</h6>
                    <p>Available languages:</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-language text-info"></i> <strong>English:</strong> Default language</li>
                        <li><i class="fas fa-language text-info"></i> <strong>Dari:</strong> دری (Afghan Persian)</li>
                        <li><i class="fas fa-language text-info"></i> <strong>Pashto:</strong> پښتو (Afghan Pashto)</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Company Information</h6>
                    <p><strong>Company:</strong> <?php echo htmlspecialchars($company['company_name']); ?></p>
                    <p><strong>Code:</strong> <?php echo htmlspecialchars($company['company_code']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $company['subscription_status'] === 'active' ? 'bg-success' : 'bg-warning'; ?>">
                            <?php echo ucfirst($company['subscription_status']); ?>
                        </span>
                    </p>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="employees/" class="list-group-item list-group-item-action">
                            <i class="fas fa-users"></i> Manage Employees
                        </a>
                        <a href="contracts/" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-contract"></i> Manage Contracts
                        </a>
                        <a href="expenses/" class="list-group-item list-group-item-action">
                            <i class="fas fa-receipt"></i> Manage Expenses
                        </a>
                        <a href="parking/" class="list-group-item list-group-item-action">
                            <i class="fas fa-parking"></i> Manage Parking
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Currency Preview -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Currency Preview</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($currencies as $currency): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($currency['currency_name']); ?></h6>
                                <p class="mb-1">
                                    <strong>Symbol:</strong> <?php echo htmlspecialchars($currency['currency_symbol']); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Code:</strong> <?php echo htmlspecialchars($currency['currency_code']); ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Example:</strong> <?php echo formatCurrency(1234.56, $currency['id']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Date Format Preview -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Date Format Preview</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($date_formats as $format): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-success">
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($format['format_name']); ?></h6>
                                <p class="mb-1">
                                    <strong>Pattern:</strong> <?php echo htmlspecialchars($format['format_pattern']); ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Example:</strong> 
                                    <?php 
                                    $dateFormat = $format;
                                    $dateObj = new DateTime('2023-12-25');
                                    if ($format['format_code'] === 'shamsi') {
                                        echo convertToShamsi($dateObj);
                                    } else {
                                        echo $dateObj->format($format['format_pattern']);
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Language Preview -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Language Preview</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($languages as $language): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-info">
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($language['language_name_native']); ?> 
                                    (<?php echo htmlspecialchars($language['language_name']); ?>)</h6>
                                <p class="mb-1">
                                    <strong>Code:</strong> <?php echo htmlspecialchars($language['language_code']); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Direction:</strong> <?php echo strtoupper($language['direction']); ?>
                                </p>
                                <p class="mb-0">
                                    <strong>Example:</strong> 
                                    <?php 
                                    // Show some sample translations
                                    $sample_key = 'dashboard';
                                    $stmt = $conn->prepare("
                                        SELECT translation_value FROM language_translations 
                                        WHERE language_id = ? AND translation_key = ?
                                    ");
                                    $stmt->execute([$language['id'], $sample_key]);
                                    $translation = $stmt->fetch(PDO::FETCH_ASSOC);
                                    echo $translation ? htmlspecialchars($translation['translation_value']) : $sample_key;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Update preview when currency changes
    $('#currency_id').change(function() {
        var selectedCurrency = $(this).find('option:selected').text();
        $('.form-control-plaintext strong:first').next().text(selectedCurrency);
    });
    
    // Update preview when date format changes
    $('#date_format_id').change(function() {
        var selectedFormat = $(this).find('option:selected').text();
        $('.form-control-plaintext strong:eq(1)').next().text(selectedFormat);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>