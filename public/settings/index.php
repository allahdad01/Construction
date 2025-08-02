<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();

$current_user = getCurrentUser();
$company_id = getCurrentCompanyId();
$is_super_admin = isSuperAdmin();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_company_info') {
            // Update company information
            $company_name = trim($_POST['company_name'] ?? '');
            $company_email = trim($_POST['company_email'] ?? '');
            $company_phone = trim($_POST['company_phone'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $company_website = trim($_POST['company_website'] ?? '');
            $company_description = trim($_POST['company_description'] ?? '');
            
            // Validate required fields
            if (empty($company_name)) {
                throw new Exception('Company name is required.');
            }
            
            if (!empty($company_email) && !filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }
            
            // Update company information
            $stmt = $conn->prepare("
                UPDATE companies 
                SET company_name = ?, email = ?, phone = ?, address = ?, website = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([$company_name, $company_email, $company_phone, $company_address, $company_website, $company_description, $company_id]);
            
            $success = 'Company information updated successfully!';
            
        } elseif ($action === 'update_preferences') {
            // Update company preferences
            $currency_id = (int)($_POST['currency_id'] ?? 1);
            $date_format_id = (int)($_POST['date_format_id'] ?? 1);
            $default_language_id = (int)($_POST['default_language_id'] ?? 1);
            $timezone = trim($_POST['timezone'] ?? 'UTC');
            $working_hours_start = trim($_POST['working_hours_start'] ?? '08:00');
            $working_hours_end = trim($_POST['working_hours_end'] ?? '17:00');
            $weekend_days = $_POST['weekend_days'] ?? ['saturday', 'sunday'];
            
            // Update company settings
            $stmt = $conn->prepare("
                UPDATE company_settings 
                SET currency_id = ?, date_format_id = ?, default_language_id = ?, timezone = ?, 
                    working_hours_start = ?, working_hours_end = ?, weekend_days = ?
                WHERE company_id = ?
            ");
            $stmt->execute([
                $currency_id, $date_format_id, $default_language_id, $timezone,
                $working_hours_start, $working_hours_end, json_encode($weekend_days), $company_id
            ]);
            
            $success = 'Company preferences updated successfully!';
            
        } elseif ($action === 'update_notifications') {
            // Update company notification settings
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            $notification_sound = isset($_POST['notification_sound']) ? 1 : 0;
            $auto_reminders = isset($_POST['auto_reminders']) ? 1 : 0;
            $reminder_frequency = $_POST['reminder_frequency'] ?? 'daily';
            
            $settings = [
                'email_notifications' => $email_notifications,
                'sms_notifications' => $sms_notifications,
                'push_notifications' => $push_notifications,
                'notification_sound' => $notification_sound,
                'auto_reminders' => $auto_reminders,
                'reminder_frequency' => $reminder_frequency
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO company_settings (company_id, setting_key, setting_value) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$company_id, $key, $value, $value]);
            }
            
            $success = 'Notification settings updated successfully!';
            
        } elseif ($action === 'update_security') {
            // Update company security settings
            $session_timeout = (int)($_POST['session_timeout'] ?? 30);
            $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
            $password_min_length = (int)($_POST['password_min_length'] ?? 8);
            $require_strong_password = isset($_POST['require_strong_password']) ? 1 : 0;
            $enable_two_factor = isset($_POST['enable_two_factor']) ? 1 : 0;
            $ip_whitelist = trim($_POST['ip_whitelist'] ?? '');
            
            $settings = [
                'session_timeout' => $session_timeout,
                'max_login_attempts' => $max_login_attempts,
                'password_min_length' => $password_min_length,
                'require_strong_password' => $require_strong_password,
                'enable_two_factor' => $enable_two_factor,
                'ip_whitelist' => $ip_whitelist
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO company_settings (company_id, setting_key, setting_value) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$company_id, $key, $value, $value]);
            }
            
            $success = 'Security settings updated successfully!';
            
        } elseif ($action === 'update_integrations') {
            // Update company integration settings
            $smtp_host = trim($_POST['smtp_host'] ?? '');
            $smtp_port = trim($_POST['smtp_port'] ?? '');
            $smtp_username = trim($_POST['smtp_username'] ?? '');
            $smtp_password = trim($_POST['smtp_password'] ?? '');
            $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
            $api_key = trim($_POST['api_key'] ?? '');
            $webhook_url = trim($_POST['webhook_url'] ?? '');
            
            $settings = [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_username' => $smtp_username,
                'smtp_password' => $smtp_password,
                'smtp_encryption' => $smtp_encryption,
                'api_key' => $api_key,
                'webhook_url' => $webhook_url
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO company_settings (company_id, setting_key, setting_value) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$company_id, $key, $value, $value]);
            }
            
            $success = 'Integration settings updated successfully!';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current company information
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// Get company settings
function getCompanySetting($conn, $company_id, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM company_settings WHERE company_id = ? AND setting_key = ?");
    $stmt->execute([$company_id, $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

// Get all available currencies
$stmt = $conn->prepare("SELECT * FROM currencies ORDER BY currency_name");
$stmt->execute();
$currencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available date formats
$stmt = $conn->prepare("SELECT * FROM date_formats ORDER BY format_name");
$stmt->execute();
$date_formats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available languages
$stmt = $conn->prepare("SELECT * FROM languages ORDER BY language_name");
$stmt->execute();
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current company settings
$current_settings = [
    'currency_id' => getCompanySetting($conn, $company_id, 'currency_id', '1'),
    'date_format_id' => getCompanySetting($conn, $company_id, 'date_format_id', '1'),
    'default_language_id' => getCompanySetting($conn, $company_id, 'default_language_id', '1'),
    'timezone' => getCompanySetting($conn, $company_id, 'timezone', 'UTC'),
    'working_hours_start' => getCompanySetting($conn, $company_id, 'working_hours_start', '08:00'),
    'working_hours_end' => getCompanySetting($conn, $company_id, 'working_hours_end', '17:00'),
    'weekend_days' => json_decode(getCompanySetting($conn, $company_id, 'weekend_days', '["saturday","sunday"]'), true),
    'email_notifications' => getCompanySetting($conn, $company_id, 'email_notifications', '1'),
    'sms_notifications' => getCompanySetting($conn, $company_id, 'sms_notifications', '0'),
    'push_notifications' => getCompanySetting($conn, $company_id, 'push_notifications', '1'),
    'notification_sound' => getCompanySetting($conn, $company_id, 'notification_sound', '1'),
    'auto_reminders' => getCompanySetting($conn, $company_id, 'auto_reminders', '1'),
    'reminder_frequency' => getCompanySetting($conn, $company_id, 'reminder_frequency', 'daily'),
    'session_timeout' => getCompanySetting($conn, $company_id, 'session_timeout', '30'),
    'max_login_attempts' => getCompanySetting($conn, $company_id, 'max_login_attempts', '5'),
    'password_min_length' => getCompanySetting($conn, $company_id, 'password_min_length', '8'),
    'require_strong_password' => getCompanySetting($conn, $company_id, 'require_strong_password', '1'),
    'enable_two_factor' => getCompanySetting($conn, $company_id, 'enable_two_factor', '0'),
    'ip_whitelist' => getCompanySetting($conn, $company_id, 'ip_whitelist', ''),
    'smtp_host' => getCompanySetting($conn, $company_id, 'smtp_host', ''),
    'smtp_port' => getCompanySetting($conn, $company_id, 'smtp_port', '587'),
    'smtp_username' => getCompanySetting($conn, $company_id, 'smtp_username', ''),
    'smtp_password' => getCompanySetting($conn, $company_id, 'smtp_password', ''),
    'smtp_encryption' => getCompanySetting($conn, $company_id, 'smtp_encryption', 'tls'),
    'api_key' => getCompanySetting($conn, $company_id, 'api_key', ''),
    'webhook_url' => getCompanySetting($conn, $company_id, 'webhook_url', '')
];

// Get timezone list
$timezones = DateTimeZone::listIdentifiers();
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-cogs"></i> Company Settings
        </h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Settings Navigation -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="company-info-tab" data-bs-toggle="tab" data-bs-target="#company-info" type="button" role="tab">
                                <i class="fas fa-building"></i> Company Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                                <i class="fas fa-sliders-h"></i> Preferences
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="fas fa-bell"></i> Notifications
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="fas fa-shield-alt"></i> Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="integrations-tab" data-bs-toggle="tab" data-bs-target="#integrations" type="button" role="tab">
                                <i class="fas fa-plug"></i> Integrations
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="settingsTabContent">
                        
                        <!-- Company Information -->
                        <div class="tab-pane fade show active" id="company-info" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_company_info">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_name" class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" 
                                                   value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_email" class="form-label">Company Email</label>
                                            <input type="email" class="form-control" id="company_email" name="company_email" 
                                                   value="<?php echo htmlspecialchars($company['email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_phone" class="form-label">Company Phone</label>
                                            <input type="text" class="form-control" id="company_phone" name="company_phone" 
                                                   value="<?php echo htmlspecialchars($company['phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="company_website" class="form-label">Company Website</label>
                                            <input type="url" class="form-control" id="company_website" name="company_website" 
                                                   value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="company_address" class="form-label">Company Address</label>
                                    <textarea class="form-control" id="company_address" name="company_address" rows="3"><?php echo htmlspecialchars($company['address']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="company_description" class="form-label">Company Description</label>
                                    <textarea class="form-control" id="company_description" name="company_description" rows="4"><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Company Info
                                </button>
                            </form>
                        </div>
                        
                        <!-- Preferences -->
                        <div class="tab-pane fade" id="preferences" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_preferences">
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="currency_id" class="form-label">Default Currency</label>
                                            <select class="form-control" id="currency_id" name="currency_id">
                                                <?php foreach ($currencies as $currency): ?>
                                                <option value="<?php echo $currency['id']; ?>" 
                                                        <?php echo $current_settings['currency_id'] == $currency['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($currency['currency_name']); ?> (<?php echo htmlspecialchars($currency['currency_code']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="date_format_id" class="form-label">Date Format</label>
                                            <select class="form-control" id="date_format_id" name="date_format_id">
                                                <?php foreach ($date_formats as $format): ?>
                                                <option value="<?php echo $format['id']; ?>" 
                                                        <?php echo $current_settings['date_format_id'] == $format['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($format['format_name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="default_language_id" class="form-label">Default Language</label>
                                            <select class="form-control" id="default_language_id" name="default_language_id">
                                                <?php foreach ($languages as $language): ?>
                                                <option value="<?php echo $language['id']; ?>" 
                                                        <?php echo $current_settings['default_language_id'] == $language['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($language['language_name']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-control" id="timezone" name="timezone">
                                                <?php foreach ($timezones as $timezone): ?>
                                                <option value="<?php echo $timezone; ?>" 
                                                        <?php echo $current_settings['timezone'] === $timezone ? 'selected' : ''; ?>>
                                                    <?php echo $timezone; ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="working_hours_start" class="form-label">Working Hours Start</label>
                                            <input type="time" class="form-control" id="working_hours_start" name="working_hours_start" 
                                                   value="<?php echo htmlspecialchars($current_settings['working_hours_start']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="working_hours_end" class="form-label">Working Hours End</label>
                                            <input type="time" class="form-control" id="working_hours_end" name="working_hours_end" 
                                                   value="<?php echo htmlspecialchars($current_settings['working_hours_end']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Weekend Days</label>
                                    <div class="row">
                                        <?php
                                        $days = [
                                            'monday' => 'Monday',
                                            'tuesday' => 'Tuesday',
                                            'wednesday' => 'Wednesday',
                                            'thursday' => 'Thursday',
                                            'friday' => 'Friday',
                                            'saturday' => 'Saturday',
                                            'sunday' => 'Sunday'
                                        ];
                                        foreach ($days as $day_key => $day_name):
                                        ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="weekend_<?php echo $day_key; ?>" 
                                                       name="weekend_days[]" value="<?php echo $day_key; ?>"
                                                       <?php echo in_array($day_key, $current_settings['weekend_days']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="weekend_<?php echo $day_key; ?>">
                                                    <?php echo $day_name; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Preferences
                                </button>
                            </form>
                        </div>
                        
                        <!-- Notification Settings -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_notifications">
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                               <?php echo $current_settings['email_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">
                                            Enable Email Notifications
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" 
                                               <?php echo $current_settings['sms_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sms_notifications">
                                            Enable SMS Notifications
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" 
                                               <?php echo $current_settings['push_notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="push_notifications">
                                            Enable Push Notifications
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="notification_sound" name="notification_sound" 
                                               <?php echo $current_settings['notification_sound'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notification_sound">
                                            Enable Notification Sounds
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="auto_reminders" name="auto_reminders" 
                                               <?php echo $current_settings['auto_reminders'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="auto_reminders">
                                            Enable Auto Reminders
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reminder_frequency" class="form-label">Reminder Frequency</label>
                                    <select class="form-control" id="reminder_frequency" name="reminder_frequency">
                                        <option value="daily" <?php echo $current_settings['reminder_frequency'] === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                        <option value="weekly" <?php echo $current_settings['reminder_frequency'] === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                        <option value="monthly" <?php echo $current_settings['reminder_frequency'] === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Notifications
                                </button>
                            </form>
                        </div>
                        
                        <!-- Security Settings -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_security">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                   value="<?php echo htmlspecialchars($current_settings['session_timeout']); ?>" min="5" max="1440">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                   value="<?php echo htmlspecialchars($current_settings['max_login_attempts']); ?>" min="3" max="10">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                            <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                   value="<?php echo htmlspecialchars($current_settings['password_min_length']); ?>" min="6" max="20">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ip_whitelist" class="form-label">IP Whitelist</label>
                                            <input type="text" class="form-control" id="ip_whitelist" name="ip_whitelist" 
                                                   value="<?php echo htmlspecialchars($current_settings['ip_whitelist']); ?>" 
                                                   placeholder="192.168.1.1, 10.0.0.0/24">
                                            <small class="text-muted">Comma-separated IP addresses or ranges</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="require_strong_password" name="require_strong_password" 
                                               <?php echo $current_settings['require_strong_password'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="require_strong_password">
                                            Require Strong Passwords
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_two_factor" name="enable_two_factor" 
                                               <?php echo $current_settings['enable_two_factor'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_two_factor">
                                            Enable Two-Factor Authentication
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Security
                                </button>
                            </form>
                        </div>
                        
                        <!-- Integration Settings -->
                        <div class="tab-pane fade" id="integrations" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_integrations">
                                
                                <h6 class="mb-3">SMTP Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_port']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">SMTP Password</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_password']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="smtp_encryption" class="form-label">SMTP Encryption</label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo $current_settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $current_settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo $current_settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                
                                <h6 class="mb-3 mt-4">API Configuration</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="api_key" class="form-label">API Key</label>
                                            <input type="text" class="form-control" id="api_key" name="api_key" 
                                                   value="<?php echo htmlspecialchars($current_settings['api_key']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="webhook_url" class="form-label">Webhook URL</label>
                                            <input type="url" class="form-control" id="webhook_url" name="webhook_url" 
                                                   value="<?php echo htmlspecialchars($current_settings['webhook_url']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Integrations
                                </button>
                            </form>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const triggerTabList = [].slice.call(document.querySelectorAll('#settingsTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>