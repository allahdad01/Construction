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
            } else {
                throw new Exception('No logo file selected.');
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
    'primary_color' => getSystemSettingLocal($conn, 'primary_color', '#4e73df'),
    'secondary_color' => getSystemSettingLocal($conn, 'secondary_color', '#858796'),
    'accent_color' => getSystemSettingLocal($conn, 'accent_color', '#1cc88a'),
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
    'contact_instagram' => getSystemSettingLocal($conn, 'contact_instagram', '')
];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-cogs"></i> Platform Settings
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
                                <i class="fas fa-palette"></i> Branding
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appearance-tab" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">
                                <i class="fas fa-paint-brush"></i> Appearance
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="logo-tab" data-bs-toggle="tab" data-bs-target="#logo" type="button" role="tab">
                                <i class="fas fa-image"></i> Logo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="fas fa-shield-alt"></i> Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="fas fa-bell"></i> Notifications
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="integrations-tab" data-bs-toggle="tab" data-bs-target="#integrations" type="button" role="tab">
                                <i class="fas fa-plug"></i> Integrations
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" type="button" role="tab">
                                <i class="fas fa-address-book"></i> Contact Info
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
                                            <label for="platform_name" class="form-label">Platform Name *</label>
                                            <input type="text" class="form-control" id="platform_name" name="platform_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['platform_name']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_email" class="form-label">Contact Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="support_phone" class="form-label">Support Phone</label>
                                            <input type="text" class="form-control" id="support_phone" name="support_phone" 
                                                   value="<?php echo htmlspecialchars($current_settings['support_phone']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="website_url" class="form-label">Website URL</label>
                                            <input type="url" class="form-control" id="website_url" name="website_url" 
                                                   value="<?php echo htmlspecialchars($current_settings['website_url']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="platform_description" class="form-label">Platform Description</label>
                                    <textarea class="form-control" id="platform_description" name="platform_description" rows="3"><?php echo htmlspecialchars($current_settings['platform_description']); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Branding
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
                                            <label for="primary_color" class="form-label">Primary Color</label>
                                            <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" 
                                                   value="<?php echo htmlspecialchars($current_settings['primary_color']); ?>">
                                            <small class="text-muted">Main brand color</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="secondary_color" class="form-label">Secondary Color</label>
                                            <input type="color" class="form-control form-control-color" id="secondary_color" name="secondary_color" 
                                                   value="<?php echo htmlspecialchars($current_settings['secondary_color']); ?>">
                                            <small class="text-muted">Secondary brand color</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="accent_color" class="form-label">Accent Color</label>
                                            <input type="color" class="form-control form-control-color" id="accent_color" name="accent_color" 
                                                   value="<?php echo htmlspecialchars($current_settings['accent_color']); ?>">
                                            <small class="text-muted">Highlight color</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="sidebar_style" class="form-label">Sidebar Style</label>
                                            <select class="form-control" id="sidebar_style" name="sidebar_style">
                                                <option value="default" <?php echo $current_settings['sidebar_style'] === 'default' ? 'selected' : ''; ?>>Default</option>
                                                <option value="compact" <?php echo $current_settings['sidebar_style'] === 'compact' ? 'selected' : ''; ?>>Compact</option>
                                                <option value="modern" <?php echo $current_settings['sidebar_style'] === 'modern' ? 'selected' : ''; ?>>Modern</option>
                                                <option value="minimal" <?php echo $current_settings['sidebar_style'] === 'minimal' ? 'selected' : ''; ?>>Minimal</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="theme_mode" class="form-label">Theme Mode</label>
                                            <select class="form-control" id="theme_mode" name="theme_mode">
                                                <option value="light" <?php echo $current_settings['theme_mode'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                                <option value="dark" <?php echo $current_settings['theme_mode'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                                <option value="auto" <?php echo $current_settings['theme_mode'] === 'auto' ? 'selected' : ''; ?>>Auto</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Appearance
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
                                            <label for="logo" class="form-label">Upload Logo</label>
                                            <input type="file" class="form-control" id="logo" name="logo" accept="image/*" onchange="previewLogo(this)">
                                            <small class="text-muted">Recommended size: 200x60px. Max size: 5MB. Formats: JPEG, PNG, GIF, SVG</small>
                                        </div>
                                        
                                        <!-- Preview Area -->
                                        <div class="mb-3" id="logoPreview" style="display: none;">
                                            <label class="form-label">Preview</label>
                                            <div class="border rounded p-3 text-center">
                                                <img id="previewImage" src="" alt="Logo Preview" style="max-height: 60px; max-width: 200px;">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($current_settings['platform_logo']): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Current Logo</label>
                                            <div class="border rounded p-3 text-center">
                                                <img src="/constract360/construction/<?php echo htmlspecialchars($current_settings['platform_logo']); ?>" 
                                                     alt="Current Logo" style="max-height: 60px; max-width: 200px;">
                                            </div>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeLogo()">
                                                    <i class="fas fa-trash"></i> Remove Logo
                                                </button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Logo
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
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                               <?php echo $current_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">
                                            Enable Maintenance Mode
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Security
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
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Notifications
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
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Integrations
                                </button>
                            </form>
                        </div>
                        
                        <!-- Contact Settings -->
                        <div class="tab-pane fade" id="contact" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_contact">
                                
                                <h6 class="mb-3">Contact Information</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_address" class="form-label">Address</label>
                                            <textarea class="form-control" id="contact_address" name="contact_address" rows="3"><?php echo htmlspecialchars($current_settings['contact_address']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_phone" class="form-label">Phone</label>
                                            <input type="tel" class="form-control" id="contact_phone" name="contact_phone" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_phone']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="contact_email" name="contact_email" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="contact_website" name="contact_website" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_website']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="mb-3 mt-4">Social Media Links</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_facebook" class="form-label">Facebook</label>
                                            <input type="url" class="form-control" id="contact_facebook" name="contact_facebook" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_facebook']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_twitter" class="form-label">Twitter</label>
                                            <input type="url" class="form-control" id="contact_twitter" name="contact_twitter" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_twitter']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_linkedin" class="form-label">LinkedIn</label>
                                            <input type="url" class="form-control" id="contact_linkedin" name="contact_linkedin" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_linkedin']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="contact_instagram" class="form-label">Instagram</label>
                                            <input type="url" class="form-control" id="contact_instagram" name="contact_instagram" 
                                                   value="<?php echo htmlspecialchars($current_settings['contact_instagram']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Contact Info
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
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>