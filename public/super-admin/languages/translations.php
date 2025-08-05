<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$language_id = (int)($_GET['language_id'] ?? 0);
$error = '';
$success = '';

// Handle bulk form submission for translations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_save'])) {
    $target_language_id = (int)($_POST['language_id'] ?? $language_id);
    $translations_data = $_POST['translations'] ?? [];
    $saved_count = 0;
    $updated_count = 0;
    
    if (empty($translations_data)) {
        $error = 'No translations to save.';
    } else {
        try {
            $conn->beginTransaction();
            
            foreach ($translations_data as $key => $value) {
                $translation_key = trim($key);
                $translation_value = trim($value);
                
                if (!empty($translation_key) && !empty($translation_value)) {
                    // Check if translation already exists
                    $stmt = $conn->prepare("SELECT id FROM language_translations WHERE language_id = ? AND translation_key = ?");
                    $stmt->execute([$target_language_id, $translation_key]);
                    
                    if ($stmt->fetch()) {
                        // Update existing translation
                        $stmt = $conn->prepare("UPDATE language_translations SET translation_value = ? WHERE language_id = ? AND translation_key = ?");
                        $stmt->execute([$translation_value, $target_language_id, $translation_key]);
                        $updated_count++;
                    } else {
                        // Add new translation
                        $stmt = $conn->prepare("INSERT INTO language_translations (language_id, translation_key, translation_value) VALUES (?, ?, ?)");
                        $stmt->execute([$target_language_id, $translation_key, $translation_value]);
                        $saved_count++;
                    }
                }
            }
            
            $conn->commit();
            
            if ($saved_count > 0 && $updated_count > 0) {
                $success = "Successfully saved $saved_count new translations and updated $updated_count existing translations!";
            } elseif ($saved_count > 0) {
                $success = "Successfully saved $saved_count new translations!";
            } elseif ($updated_count > 0) {
                $success = "Successfully updated $updated_count translations!";
            } else {
                $success = "No changes were made.";
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            $error = 'Error saving translations: ' . $e->getMessage();
        }
    }
}

// Get all languages
$stmt = $conn->prepare("SELECT * FROM languages ORDER BY language_name");
$stmt->execute();
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available translation keys from the database
$stmt = $conn->prepare("SELECT DISTINCT translation_key FROM language_translations ORDER BY translation_key");
$stmt->execute();
$all_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Debug: Log the count
error_log("Total translation keys found: " . count($all_keys));

// If no keys found in database, use a basic set
if (empty($all_keys)) {
    $all_keys = [
        'dashboard', 'employees', 'machines', 'contracts', 'parking', 'area_rentals', 
        'expenses', 'salary_payments', 'reports', 'users', 'settings', 'profile', 
        'logout', 'add', 'edit', 'delete', 'view', 'save', 'cancel', 'back',
        'search', 'filter', 'status', 'active', 'inactive', 'pending', 'completed',
        'name', 'email', 'phone', 'address', 'position', 'salary', 'hire_date',
        'machine_code', 'machine_type', 'model', 'year', 'capacity', 'fuel_type',
        'purchase_date', 'purchase_cost', 'contract_code', 'project_name', 'client_name',
        'start_date', 'end_date', 'rate_amount', 'total_amount', 'working_hours',
        'parking_space', 'monthly_rate', 'daily_rate', 'rental_code', 'client_contact',
        'expense_type', 'expense_amount', 'expense_date', 'expense_description',
        'payment_method', 'payment_date', 'payment_status', 'transaction_id',
        'company_name', 'company_code', 'subscription_plan', 'subscription_status',
        'trial_ends_at', 'max_employees', 'max_machines', 'max_projects',
        'total_revenue', 'total_expenses', 'net_income', 'working_hours_per_day',
        'overtime_hours', 'leave_days', 'attendance', 'timesheet', 'payroll',
        'reports_overview', 'reports_financial', 'reports_employee', 'reports_contract',
        'reports_machine', 'system_settings', 'platform_settings', 'user_management',
        'language_settings', 'currency_settings', 'date_format_settings', 'timezone_settings',
        'backup_restore', 'system_logs', 'audit_trail', 'notifications', 'alerts',
        'help_support', 'documentation', 'api_documentation', 'developer_tools'
    ];
}

// No pagination - show all keys at once
$all_keys_to_display = $all_keys;

// Get translations for selected language
$translations = [];
if ($language_id) {
    $stmt = $conn->prepare("SELECT * FROM language_translations WHERE language_id = ? ORDER BY translation_key");
    $stmt->execute([$language_id]);
    $existing_translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of existing translations
    $translation_map = [];
    foreach ($existing_translations as $translation) {
        $translation_map[$translation['translation_key']] = $translation['translation_value'];
    }
    
    // Build complete translations array with all keys
    foreach ($all_keys_to_display as $key) {
        $translations[] = [
            'translation_key' => $key,
            'translation_value' => $translation_map[$key] ?? '',
            'exists' => isset($translation_map[$key])
        ];
    }
}

// Get selected language details
$selected_language = null;
if ($language_id) {
    $stmt = $conn->prepare("SELECT * FROM languages WHERE id = ?");
    $stmt->execute([$language_id]);
    $selected_language = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-language"></i> Manage Translations
        </h1>
        <div>
            <a href="/constract360/construction/public/super-admin/languages/" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Languages
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
        <!-- Language Selection -->
        <div class="col-md-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Select Language</h6>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div class="mb-3">
                            <label for="language_id" class="form-label">Choose Language</label>
                            <select class="form-control" id="language_id" name="language_id" onchange="this.form.submit()">
                                <option value="">Select a language...</option>
                                <?php foreach ($languages as $lang): ?>
                                    <option value="<?php echo $lang['id']; ?>" <?php echo $language_id == $lang['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lang['language_name']); ?> (<?php echo htmlspecialchars($lang['language_name_native']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <?php if ($selected_language): ?>
                        <?php
                        $total_keys = count($all_keys);
                        $translated_keys = 0;
                        foreach ($translations as $translation) {
                            if ($translation['exists']) {
                                $translated_keys++;
                            }
                        }
                        $translation_percentage = $total_keys > 0 ? round(($translated_keys / $total_keys) * 100, 1) : 0;
                        ?>
                        <div class="alert alert-info">
                            <strong>Language:</strong> <?php echo htmlspecialchars($selected_language['language_name']); ?><br>
                            <strong>Native Name:</strong> <?php echo htmlspecialchars($selected_language['language_name_native']); ?><br>
                            <strong>Direction:</strong> <?php echo strtoupper($selected_language['direction']); ?><br>
                            <strong>Status:</strong> 
                            <span class="badge <?php echo $selected_language['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                <?php echo $selected_language['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span><br>
                            <strong>Translation Progress:</strong> 
                            <span class="badge bg-info"><?php echo $translated_keys; ?> / <?php echo $total_keys; ?> (<?php echo $translation_percentage; ?>%)</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bulk Translation Form -->
            <?php if ($language_id): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Bulk Translation Management</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Instructions:</strong> Fill in the translation values for each key. Empty fields will be skipped. 
                            Click "Save All Translations" at the bottom to save everything at once.
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="fillEmptyFields()">
                                    <i class="fas fa-magic"></i> Fill Empty Fields
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllFields()">
                                    <i class="fas fa-eraser"></i> Clear All
                                </button>
                            </div>
                            <div>
                                <span class="badge bg-info" id="filledCount">0</span> filled / 
                                <span class="badge bg-secondary" id="totalCount">0</span> total
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Translations List -->
        <div class="col-md-8">
            <?php if ($language_id): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Translations for <?php echo htmlspecialchars($selected_language['language_name']); ?>
                        </h6>
                        <div class="d-flex align-items-center">
                            <input type="text" id="searchKeys" class="form-control form-control-sm me-2" 
                                   placeholder="Search keys..." style="width: 200px;">
                            <span class="badge bg-primary"><?php echo count($all_keys); ?> total keys</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="bulkTranslationForm">
                            <input type="hidden" name="language_id" value="<?php echo $language_id; ?>">
                            <input type="hidden" name="bulk_save" value="1">
                            
                            <?php if (empty($translations)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-language fa-3x text-gray-300 mb-3"></i>
                                    <p class="text-gray-500">No translations found for this language.</p>
                                    <p class="text-muted">Add your first translation using the form on the left.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">Translation Key</th>
                                                <th style="width: 60%;">Translation Value</th>
                                                <th style="width: 10%;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($translations as $translation): ?>
                                                <tr class="<?php echo $translation['exists'] ? '' : 'table-warning'; ?>">
                                                    <td>
                                                        <code><?php echo htmlspecialchars($translation['translation_key']); ?></code>
                                                    </td>
                                                    <td>
                                                        <input type="text" 
                                                               class="form-control translation-input" 
                                                               name="translations[<?php echo htmlspecialchars($translation['translation_key']); ?>]"
                                                               value="<?php echo htmlspecialchars($translation['translation_value']); ?>"
                                                               placeholder="Enter translation for <?php echo htmlspecialchars($translation['translation_key']); ?>"
                                                               data-original="<?php echo htmlspecialchars($translation['translation_value']); ?>">
                                                    </td>
                                                    <td>
                                                        <?php if ($translation['exists']): ?>
                                                            <span class="badge bg-success">Translated</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Missing</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Save All Button -->
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Save All Translations
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-lg ms-2" onclick="resetForm()">
                                        <i class="fas fa-undo"></i> Reset Changes
                                    </button>
                                </div>
                            <?php endif; ?>

                </div>
            <?php else: ?>
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-language fa-4x text-gray-300 mb-3"></i>
                        <h5 class="text-gray-600">Select a Language</h5>
                        <p class="text-muted">Choose a language from the dropdown to manage its translations.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchKeys').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(function(row) {
        const keyCell = row.querySelector('td:first-child code');
        const keyText = keyCell.textContent.toLowerCase();
        
        if (keyText.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Fill empty fields with placeholder text
function fillEmptyFields() {
    const inputs = document.querySelectorAll('.translation-input');
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            const key = input.getAttribute('name').replace('translations[', '').replace(']', '');
            input.value = 'Enter translation for ' + key;
        }
    });
    updateCounters();
}

// Clear all fields
function clearAllFields() {
    const inputs = document.querySelectorAll('.translation-input');
    inputs.forEach(function(input) {
        input.value = '';
    });
    updateCounters();
}

// Reset form to original values
function resetForm() {
    const inputs = document.querySelectorAll('.translation-input');
    inputs.forEach(function(input) {
        const original = input.getAttribute('data-original');
        input.value = original;
    });
    updateCounters();
}

// Update filled/total counters
function updateCounters() {
    const inputs = document.querySelectorAll('.translation-input');
    let filled = 0;
    let total = inputs.length;
    
    inputs.forEach(function(input) {
        if (input.value.trim()) {
            filled++;
        }
    });
    
    document.getElementById('filledCount').textContent = filled;
    document.getElementById('totalCount').textContent = total;
}

// Update counters on input change
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('translation-input')) {
        updateCounters();
    }
});

// Initialize counters on page load
document.addEventListener('DOMContentLoaded', function() {
    updateCounters();
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        alert.style.display = 'none';
    });
}, 5000);
</script>

<?php require_once '../../../includes/footer.php'; ?>