<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and has super admin role
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_branding') {
            // Update branding settings
            $platform_name = trim($_POST['platform_name'] ?? '');
            $platform_description = trim($_POST['platform_description'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $support_phone = trim($_POST['support_phone'] ?? '');
            $website_url = trim($_POST['website_url'] ?? '');
            
            // Validate required fields
            if (empty($platform_name)) {
                throw new Exception('Platform name is required.');
            }
            
            // Update system settings
            $settings = [
                'platform_name' => $platform_name,
                'platform_description' => $platform_description,
                'contact_email' => $contact_email,
                'support_phone' => $support_phone,
                'website_url' => $website_url
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Branding settings updated successfully!';
            
        } elseif ($action === 'update_appearance') {
            // Update appearance settings
            $primary_color = trim($_POST['primary_color'] ?? '');
            $secondary_color = trim($_POST['secondary_color'] ?? '');
            $accent_color = trim($_POST['accent_color'] ?? '');
            $sidebar_style = $_POST['sidebar_style'] ?? 'default';
            $theme_mode = $_POST['theme_mode'] ?? 'light';
            
            // Validate colors
            if (!empty($primary_color) && !preg_match('/^#[a-fA-F0-9]{6}$/', $primary_color)) {
                throw new Exception('Invalid primary color format. Use hex format (e.g., #4e73df).');
            }
            
            $settings = [
                'primary_color' => $primary_color,
                'secondary_color' => $secondary_color,
                'accent_color' => $accent_color,
                'sidebar_style' => $sidebar_style,
                'theme_mode' => $theme_mode
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Appearance settings updated successfully!';
            
        } elseif ($action === 'update_logo') {
            // Handle logo upload
            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
                    ];
                    $error_msg = $upload_errors[$_FILES['logo']['error']] ?? 'Unknown upload error.';
                    throw new Exception('Upload failed: ' . $error_msg);
                } else {
                    throw new Exception('Please select a logo file to upload.');
                }
            }
            
            $file = $_FILES['logo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and SVG files are allowed.');
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }
                
                // Create uploads directory if it doesn't exist
                $upload_dir = __DIR__ . '/../../../public/uploads/logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $filename = 'platform_logo_' . time() . '_' . uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Clean up old logo if exists
                    $old_logo = getSystemSettingLocal($conn, 'platform_logo', '');
                    if (!empty($old_logo)) {
                        $old_filepath = __DIR__ . '/../../../' . $old_logo;
                        if (file_exists($old_filepath) && is_file($old_filepath)) {
                            unlink($old_filepath);
                        }
                    }
                    
                    // Update logo path in database (relative to public directory)
                    $logo_path = 'public/uploads/logos/' . $filename;
                    $stmt = $conn->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES ('platform_logo', ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$logo_path, $logo_path]);
                    
                    $success = 'Logo uploaded successfully!';
                } else {
                    throw new Exception('Failed to upload logo. Please check directory permissions.');
                }
        } elseif ($action === 'remove_logo') {
            // Remove current logo
            $current_logo = getSystemSettingLocal($conn, 'platform_logo', '');
            if (!empty($current_logo)) {
                $logo_filepath = __DIR__ . '/../../../' . $current_logo;
                if (file_exists($logo_filepath) && is_file($logo_filepath)) {
                    unlink($logo_filepath);
                }
                
                // Remove from database
                $stmt = $conn->prepare("DELETE FROM system_settings WHERE setting_key = 'platform_logo'");
                $stmt->execute();
                
                $success = 'Logo removed successfully!';
            } else {
                throw new Exception('No logo to remove.');
            }
        } elseif ($action === 'update_favicon') {
            // Handle favicon upload (nav logo)
            if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
                if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $upload_errors = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
                    ];
                    $error_msg = $upload_errors[$_FILES['favicon']['error']] ?? 'Unknown upload error.';
                    throw new Exception('Upload failed: ' . $error_msg);
                } else {
                    throw new Exception('Please select a favicon file to upload.');
                }
            }
            $file = $_FILES['favicon'];
            $allowed_types = ['image/x-icon', 'image/png', 'image/svg+xml'];
            $max_size = 1024 * 1024; // 1MB
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only ICO, PNG, or SVG allowed.');
            }
            if ($file['size'] > $max_size) {
                throw new Exception('File size too large. Maximum size is 1MB.');
            }
            $upload_dir = __DIR__ . '/../../../public/uploads/logos/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'platform_favicon_' . time() . '_' . uniqid() . '.' . $ext;
            $filepath = $upload_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to upload favicon.');
            }
            // Remove old favicon
            $old_favicon = getSystemSettingLocal($conn, 'platform_favicon', '');
            if (!empty($old_favicon)) {
                $old_path = __DIR__ . '/../../../' . $old_favicon;
                if (file_exists($old_path) && is_file($old_path)) { @unlink($old_path); }
            }
            // Save new relative path
            $fav_path = 'public/uploads/logos/' . $filename;
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('platform_favicon', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([$fav_path]);
            $success = 'Favicon uploaded successfully!';
        } elseif ($action === 'update_security') {
            // Update security settings
            $session_timeout = (int)($_POST['session_timeout'] ?? 30);
            $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
            $password_min_length = (int)($_POST['password_min_length'] ?? 8);
            $require_strong_password = isset($_POST['require_strong_password']) ? 1 : 0;
            $enable_two_factor = isset($_POST['enable_two_factor']) ? 1 : 0;
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            
            $settings = [
                'session_timeout' => $session_timeout,
                'max_login_attempts' => $max_login_attempts,
                'password_min_length' => $password_min_length,
                'require_strong_password' => $require_strong_password,
                'enable_two_factor' => $enable_two_factor,
                'maintenance_mode' => $maintenance_mode
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Security settings updated successfully!';
            
        } elseif ($action === 'update_notifications') {
            // Update notification settings
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
            $notification_sound = isset($_POST['notification_sound']) ? 1 : 0;
            
            $settings = [
                'email_notifications' => $email_notifications,
                'sms_notifications' => $sms_notifications,
                'push_notifications' => $push_notifications,
                'notification_sound' => $notification_sound
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Notification settings updated successfully!';
            
        } elseif ($action === 'update_integrations') {
            // Update integration settings
            $smtp_host = trim($_POST['smtp_host'] ?? '');
            $smtp_port = trim($_POST['smtp_port'] ?? '');
            $smtp_username = trim($_POST['smtp_username'] ?? '');
            $smtp_password = trim($_POST['smtp_password'] ?? '');
            $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
            
            $settings = [
                'smtp_host' => $smtp_host,
                'smtp_port' => $smtp_port,
                'smtp_username' => $smtp_username,
                'smtp_password' => $smtp_password,
                'smtp_encryption' => $smtp_encryption
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Integration settings updated successfully!';
            
        } elseif ($action === 'update_contact') {
            // Update contact information
            $contact_address = trim($_POST['contact_address'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $contact_website = trim($_POST['contact_website'] ?? '');
            $contact_facebook = trim($_POST['contact_facebook'] ?? '');
            $contact_twitter = trim($_POST['contact_twitter'] ?? '');
            $contact_linkedin = trim($_POST['contact_linkedin'] ?? '');
            $contact_instagram = trim($_POST['contact_instagram'] ?? '');
            
            $settings = [
                'contact_address' => $contact_address,
                'contact_phone' => $contact_phone,
                'contact_email' => $contact_email,
                'contact_website' => $contact_website,
                'contact_facebook' => $contact_facebook,
                'contact_twitter' => $contact_twitter,
                'contact_linkedin' => $contact_linkedin,
                'contact_instagram' => $contact_instagram
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success = 'Contact information updated successfully!';
        } elseif ($action === 'update_push_vapid') {
            // Update VAPID keys (subject, public, private)
            $vapid_subject = trim($_POST['vapid_subject'] ?? '');
            $vapid_public_key = trim($_POST['vapid_public_key'] ?? '');
            $vapid_private_key = trim($_POST['vapid_private_key'] ?? '');

            $settings = [
                'vapid_subject' => $vapid_subject,
                'vapid_public_key' => $vapid_public_key,
                'vapid_private_key' => $vapid_private_key,
            ];
            foreach ($settings as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->execute([$key, $value]);
            }
            $success = 'Push settings updated successfully!';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current settings using the global function from header.php

// Get all current settings
$current_settings = [
    'platform_name' => getSystemSettingLocal($conn, 'platform_name', 'Construction SaaS Platform'),
    'platform_description' => getSystemSettingLocal($conn, 'platform_description', 'Comprehensive construction management platform'),
    'contact_email' => getSystemSettingLocal($conn, 'contact_email', 'admin@construction.com'),
    'support_phone' => getSystemSettingLocal($conn, 'support_phone', '+1-555-0123'),
    'website_url' => getSystemSettingLocal($conn, 'website_url', 'https://construction.com'),
    'platform_logo' => getSystemSettingLocal($conn, 'platform_logo', ''),
    'platform_favicon' => getSystemSettingLocal($conn, 'platform_favicon', ''),
    'primary_color' => getSystemSettingLocal($conn, 'primary_color', '#243447'),
    'secondary_color' => getSystemSettingLocal($conn, 'secondary_color', '#222E3D'),
    'accent_color' => getSystemSettingLocal($conn, 'accent_color', '#F17300'),
    'sidebar_style' => getSystemSettingLocal($conn, 'sidebar_style', 'default'),
    'theme_mode' => getSystemSettingLocal($conn, 'theme_mode', 'light'),
    'session_timeout' => getSystemSettingLocal($conn, 'session_timeout', '30'),
    'max_login_attempts' => getSystemSettingLocal($conn, 'max_login_attempts', '5'),
    'password_min_length' => getSystemSettingLocal($conn, 'password_min_length', '8'),
    'require_strong_password' => getSystemSettingLocal($conn, 'require_strong_password', '1'),
    'enable_two_factor' => getSystemSettingLocal($conn, 'enable_two_factor', '0'),
    'maintenance_mode' => getSystemSettingLocal($conn, 'maintenance_mode', '0'),
    'email_notifications' => getSystemSettingLocal($conn, 'email_notifications', '1'),
    'sms_notifications' => getSystemSettingLocal($conn, 'sms_notifications', '0'),
    'push_notifications' => getSystemSettingLocal($conn, 'push_notifications', '1'),
    'notification_sound' => getSystemSettingLocal($conn, 'notification_sound', '1'),
    'smtp_host' => getSystemSettingLocal($conn, 'smtp_host', ''),
    'smtp_port' => getSystemSettingLocal($conn, 'smtp_port', '587'),
    'smtp_username' => getSystemSettingLocal($conn, 'smtp_username', ''),
    'smtp_password' => getSystemSettingLocal($conn, 'smtp_password', ''),
    'smtp_encryption' => getSystemSettingLocal($conn, 'smtp_encryption', 'tls'),
    'contact_address' => getSystemSettingLocal($conn, 'contact_address', ''),
    'contact_phone' => getSystemSettingLocal($conn, 'contact_phone', ''),
    'contact_email' => getSystemSettingLocal($conn, 'contact_email', ''),
    'contact_website' => getSystemSettingLocal($conn, 'contact_website', ''),
    'contact_facebook' => getSystemSettingLocal($conn, 'contact_facebook', ''),
    'contact_twitter' => getSystemSettingLocal($conn, 'contact_twitter', ''),
    'contact_linkedin' => getSystemSettingLocal($conn, 'contact_linkedin', ''),
    'contact_instagram' => getSystemSettingLocal($conn, 'contact_instagram', ''),
    'vapid_subject' => getSystemSettingLocal($conn, 'vapid_subject', ''),
    'vapid_public_key' => getSystemSettingLocal($conn, 'vapid_public_key', ''),
    'vapid_private_key' => getSystemSettingLocal($conn, 'vapid_private_key', '')
];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-cogs"></i> <?php echo __('platform_settings'); ?>
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
                            <button class="nav-link active" id="branding-tab" data-bs-toggle="tab" data-bs-target="#branding" type="button" role="tab">
                                <i class="fas fa-palette"></i> <?php echo __('branding'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">
                                <i class="fas fa-paint-brush"></i> <?php echo __('appearance'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="logo-tab" data-bs-toggle="tab" data-bs-target="#logo" type="button" role="tab">
                                <i class="fas fa-image"></i> <?php echo __('logo'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="favicon-tab" data-bs-toggle="tab" data-bs-target="#favicon" type="button" role="tab">
                                <i class="fas fa-star"></i> Favicon
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="fas fa-shield-alt"></i> <?php echo __('security'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="fas fa-bell"></i> <?php echo __('notifications'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="integrations-tab" data-bs-toggle="tab" data-bs-target="#integrations" type="button" role="tab">
                                <i class="fas fa-plug"></i> <?php echo __('integrations'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                <i class="fas fa-address-book"></i> <?php echo __('contact_info'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="push-tab" data-bs-toggle="tab" data-bs-target="#push" type="button" role="tab">
                                <i class="fas fa-bell"></i> Push (VAPID)
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="settingsTabContent">
                        
                        <!-- Branding Settings -->
                        <div class="tab-pane fade show active" id="branding" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_branding">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="platform_name" class="form-label"><?php echo __('platform_name'); ?> *</label>
                                            <input type="text" class="form-control" id="platform_name" name="platform_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['platform_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_email" class="form-label"><?php echo __('contact_email'); ?></label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="support_phone" class="form-label"><?php echo __('support_phone'); ?></label>
                                            <input type="text" class="form-control" id="support_phone" name="support_phone" 
                                                   value="<?php echo htmlspecialchars($current_settings['support_phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                                <label for="website_url" class="form-label"><?php echo __('website_url'); ?></label>
                                            <input type="url" class="form-control" id="website_url" name="website_url" 
                                                   value="<?php echo htmlspecialchars($current_settings['website_url']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="platform_description" class="form-label"><?php echo __('platform_description'); ?></label>
                                    <textarea class="form-control" id="platform_description" name="platform_description" rows="3"><?php echo htmlspecialchars($current_settings['platform_description']); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo __('update_branding'); ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Appearance Settings -->
                        <div class="tab-pane fade" id="appearance" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_appearance">
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="primary_color" class="form-label"><?php echo __('primary_color'); ?></label>
                                            <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" 
                                                   value="<?php echo htmlspecialchars($current_settings['primary_color']); ?>">
                                            <small class="text-muted"><?php echo __('main_brand_color'); ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="secondary_color" class="form-label"><?php echo __('secondary_color'); ?></label>
                                            <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" 
                                                   value="<?php echo htmlspecialchars($current_settings['secondary_color']); ?>">
                                            <small class="text-muted"><?php echo __('secondary_brand_color'); ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="accent_color" class="form-label"><?php echo __('accent_color'); ?></label>
                                            <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" 
                                                   value="<?php echo htmlspecialchars($current_settings['accent_color']); ?>">
                                            <small class="text-muted"><?php echo __('highlight_color'); ?></small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sidebar_style" class="form-label"><?php echo __('sidebar_style'); ?></label>
                                            <select class="form-control" id="sidebar_style" name="sidebar_style">
                                                <option value="default" <?php echo $current_settings['sidebar_style'] === 'default' ? 'selected' : ''; ?>><?php echo __('default'); ?></option>
                                                <option value="compact" <?php echo $current_settings['sidebar_style'] === 'compact' ? 'selected' : ''; ?>><?php echo __('compact'); ?></option>
                                                <option value="modern" <?php echo $current_settings['sidebar_style'] === 'modern' ? 'selected' : ''; ?>><?php echo __('modern'); ?></option>
                                                <option value="minimal" <?php echo $current_settings['sidebar_style'] === 'minimal' ? 'selected' : ''; ?>><?php echo __('minimal'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="theme_mode" class="form-label"><?php echo __('theme_mode'); ?></label>
                                            <select class="form-control" id="theme_mode" name="theme_mode">
                                                <option value="light" <?php echo $current_settings['theme_mode'] === 'light' ? 'selected' : ''; ?>><?php echo __('light'); ?></option>
                                                <option value="dark" <?php echo $current_settings['theme_mode'] === 'dark' ? 'selected' : ''; ?>><?php echo __('dark'); ?></option>
                                                <option value="auto" <?php echo $current_settings['theme_mode'] === 'auto' ? 'selected' : ''; ?>><?php echo __('auto'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo __('update_appearance'); ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Logo Settings -->
                        <div class="tab-pane fade" id="logo" role="tabpanel">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_logo">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="logo" class="form-label"><?php echo __('upload_logo'); ?></label>
                                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" onchange="previewLogo(this)">
                                            <small class="text-muted"><?php echo __('recommended_size'); ?>: 200x60px. <?php echo __('max_size'); ?>: 5MB. <?php echo __('formats'); ?>: JPEG, PNG, GIF, SVG</small>
                                        </div>
                                        
                                        <!-- Preview Area -->
                                        <div class="mb-3" id="logoPreview" style="display: none;">
                                            <label class="form-label"><?php echo __('preview'); ?></label>
                                            <div class="border rounded p-3 text-center">
                                                <img id="previewImage" src="" alt="Logo Preview" style="max-height: 60px; max-width: 200px;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($current_settings['platform_logo']): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><?php echo __('current_logo'); ?></label>
                                            <div class="border rounded p-3 text-center">
                                                <img src="<?php echo $base_url; ?>public/<?php echo htmlspecialchars($current_settings['platform_logo']); ?>" 
                                                     alt="Current Logo" style="max-height: 60px; max-width: 200px;">
                                            </div>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeLogo()">
                                                    <i class="fas fa-trash"></i> <?php echo __('remove_logo'); ?>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> <?php echo __('upload_logo'); ?>
                                </button>
                            </form>
                            
                            <script>
                            function previewLogo(input) {
                                const preview = document.getElementById('logoPreview');
                                const previewImage = document.getElementById('previewImage');
                                
                                if (input.files && input.files[0]) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        previewImage.src = e.target.result;
                                        preview.style.display = 'block';
                                    };
                                    reader.readAsDataURL(input.files[0]);
                                } else {
                                    preview.style.display = 'none';
                                }
                            }
                            
                            function removeLogo() {
                                if (confirm('Are you sure you want to remove the current logo?')) {
                                    // Create a form to submit the remove action
                                    const form = document.createElement('form');
                                    form.method = 'POST';
                                    form.innerHTML = '<input type="hidden" name="action" value="remove_logo">';
                                    document.body.appendChild(form);
                                    form.submit();
                                }
                            }
                            </script>
                            </form>
                        </div>

                        <!-- Favicon Settings -->
                        <div class="tab-pane fade" id="favicon" role="tabpanel">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_favicon">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="favicon" class="form-label">Upload Favicon (ICO/PNG/SVG)</label>
                                            <input type="file" class="form-control" id="favicon" name="favicon" accept=".ico,.png,.svg">
                                            <small class="text-muted">Recommended: square image, max 1MB</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($current_settings['platform_favicon']): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Current Favicon</label>
                                            <div class="border rounded p-3 text-center">
                                                <img src="<?php echo $base_url; ?>public/<?php echo htmlspecialchars($current_settings['platform_favicon']); ?>" alt="Favicon" style="height:32px;width:32px;">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Favicon
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
                                            <label for="session_timeout" class="form-label"><?php echo __('session_timeout'); ?> (minutes)</label>
                                            <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                                   value="<?php echo htmlspecialchars($current_settings['session_timeout']); ?>" min="5" max="1440">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="max_login_attempts" class="form-label"><?php echo __('max_login_attempts'); ?></label>
                                            <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                   value="<?php echo htmlspecialchars($current_settings['max_login_attempts']); ?>" min="3" max="10">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="password_min_length" class="form-label"><?php echo __('minimum_password_length'); ?></label>
                                            <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                                   value="<?php echo htmlspecialchars($current_settings['password_min_length']); ?>" min="6" max="20">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="require_strong_password" name="require_strong_password" 
                                               <?php echo $current_settings['require_strong_password'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="require_strong_password">
                                            <?php echo __('require_strong_passwords'); ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enable_two_factor" name="enable_two_factor" 
                                               <?php echo $current_settings['enable_two_factor'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_two_factor">
                                            <?php echo __('enable_two_factor_authentication'); ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                               <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            <?php echo __('enable_maintenance_mode'); ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo __('update_security'); ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Notifications Settings -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_notifications">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" <?php echo ((int)$current_settings['email_notifications'] ? 'checked' : ''); ?>>
                                            <label class="form-check-label" for="email_notifications">Email Notifications</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="sms_notifications" name="sms_notifications" <?php echo ((int)$current_settings['sms_notifications'] ? 'checked' : ''); ?>>
                                            <label class="form-check-label" for="sms_notifications">SMS Notifications</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" <?php echo ((int)$current_settings['push_notifications'] ? 'checked' : ''); ?>>
                                            <label class="form-check-label" for="push_notifications">Push Notifications</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="notification_sound" name="notification_sound" <?php echo ((int)$current_settings['notification_sound'] ? 'checked' : ''); ?>>
                                            <label class="form-check-label" for="notification_sound">Notification Sound</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Notifications</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Integration Settings -->
                        <div class="tab-pane fade" id="integrations" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_integrations">
                                
                                <h6 class="mb-3"><?php echo __('smtp_configuration'); ?></h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label"><?php echo __('smtp_host'); ?></label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_port" class="form-label"><?php echo __('smtp_port'); ?></label>
                                            <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_port']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_username" class="form-label"><?php echo __('smtp_username'); ?></label>
                                            <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label"><?php echo __('smtp_password'); ?></label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($current_settings['smtp_password']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="smtp_encryption" class="form-label"><?php echo __('smtp_encryption'); ?></label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo $current_settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>><?php echo __('tls'); ?></option>
                                        <option value="ssl" <?php echo $current_settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>><?php echo __('ssl'); ?></option>
                                        <option value="none" <?php echo $current_settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>><?php echo __('none'); ?></option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo __('update_integrations'); ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Contact Settings -->
                        <div class="tab-pane fade" id="contact" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_contact">
                                
                                <h6 class="mb-3"><?php echo __('contact_information'); ?></h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_address" class="form-label"><?php echo __('address'); ?></label>
                                            <textarea class="form-control" id="contact_address" name="contact_address" rows="3"><?php echo htmlspecialchars($current_settings['contact_address']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_phone" class="form-label"><?php echo __('phone'); ?></label>
                                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_phone']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_email" class="form-label"><?php echo __('email'); ?></label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_website" class="form-label"><?php echo __('website'); ?></label>
                                            <input type="url" class="form-control" id="contact_website" name="contact_website" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_website']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="mb-3 mt-4"><?php echo __('social_media_links'); ?></h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_facebook" class="form-label"><?php echo __('facebook'); ?></label>
                                            <input type="url" class="form-control" id="contact_facebook" name="contact_facebook" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_facebook']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_twitter" class="form-label"><?php echo __('twitter'); ?></label>
                                            <input type="url" class="form-control" id="contact_twitter" name="contact_twitter" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_twitter']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_linkedin" class="form-label"><?php echo __('linkedin'); ?></label>
                                            <input type="url" class="form-control" id="contact_linkedin" name="contact_linkedin" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_linkedin']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_instagram" class="form-label"><?php echo __('instagram'); ?></label>
                                            <input type="url" class="form-control" id="contact_instagram" name="contact_instagram" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_instagram']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo __('update_contact_info'); ?>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Push (VAPID) Settings -->
                        <div class="tab-pane fade" id="push" role="tabpanel">
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="update_push_vapid">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">VAPID Subject</label>
                                        <input class="form-control" name="vapid_subject" placeholder="mailto:admin@example.com" value="<?php echo htmlspecialchars(getSystemSettingLocal($conn, 'vapid_subject', '')); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">VAPID Public Key</label>
                                        <input class="form-control" name="vapid_public_key" value="<?php echo htmlspecialchars(getSystemSettingLocal($conn, 'vapid_public_key', '')); ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">VAPID Private Key</label>
                                        <input class="form-control" name="vapid_private_key" value="<?php echo htmlspecialchars(getSystemSettingLocal($conn, 'vapid_private_key', '')); ?>">
                                    </div>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Push Settings</button>
                                    <button type="button" id="btnTestPush" class="btn btn-outline-secondary"><i class="fas fa-paper-plane"></i> Send Test Push</button>
                                </div>
                            </form>
                            <div class="mt-3 text-muted small">
                                <p>Enter your VAPID keys here. The system will not auto-generate keys. If keys are missing, web push subscription will be skipped.</p>
                            </div>
                            <script>
                            (function(){
                                const btn = document.getElementById('btnTestPush');
                                if (!btn) return;
                                btn.addEventListener('click', async function(){
                                    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
                                    try {
                                        const res = await fetch('<?php echo $base_url; ?>api/test-push.php');
                                        const data = await res.json();
                                        if (data.success) {
                                            alert('Test push sent. Check your device notifications.');
                                        } else if (data.code === 'no_subscription') {
                                            alert('No push subscription found for your user. Visit a page to allow subscription, then try again.');
                                        } else {
                                            alert('Failed to send test push.');
                                        }
                                    } catch(e) { alert('Error sending test push.'); }
                                    finally { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Test Push'; }
                                });
                            })();
                            </script>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Color preview functionality
document.addEventListener('DOMContentLoaded', function() {
    // Update CSS variables when colors change
    function updateColors() {
        const primaryColor = document.getElementById('primary_color').value;
        const secondaryColor = document.getElementById('secondary_color').value;
        const accentColor = document.getElementById('accent_color').value;
        
        document.documentElement.style.setProperty('--primary-color', primaryColor);
        document.documentElement.style.setProperty('--secondary-color', secondaryColor);
        document.documentElement.style.setProperty('--accent-color', accentColor);
    }
    
    // Add event listeners for color inputs
    document.getElementById('primary_color').addEventListener('change', updateColors);
    document.getElementById('secondary_color').addEventListener('change', updateColors);
    document.getElementById('accent_color').addEventListener('change', updateColors);
    
    // Initialize colors
    updateColors();
    
    // Tab functionality
    const triggerTabList = [].slice.call(document.querySelectorAll('#settingsTabs button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        // Click should activate without cancelling default to preserve keyboard behaviors
        triggerEl.addEventListener('click', function () {
            tabTrigger.show();
        });
        // Ensure keyboard Space/Enter activate the tab
        triggerEl.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Spacebar' || e.code === 'Space' || e.key === 'Enter') {
                e.preventDefault();
                tabTrigger.show();
            }
        });
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>