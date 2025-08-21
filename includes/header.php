<?php
// Determine the correct path to config files
$base_path = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;

require_once $base_path . 'config.php';
require_once $base_path . 'database.php';

// Handle language switching
if (isset($_GET['change_language']) && isAuthenticated()) {
    $new_language_id = (int)$_GET['change_language'];
    $company_id = getCurrentCompanyId();
    
    if ($company_id) {
        updateCompanyLanguage($company_id, $new_language_id);
    }
    
    // Redirect back to the same page without the parameter
    $redirect_url = $_SERVER['REQUEST_URI'];
    $redirect_url = preg_replace('/[?&]change_language=\d+/', '', $redirect_url);
    $redirect_url = rtrim($redirect_url, '?&');
    
    header('Location: ' . $redirect_url);
    exit;
}

// Check if user is authenticated
if (!isAuthenticated()) {
    // Get the current script path to determine the correct relative path to login.php
    $script_path = $_SERVER['SCRIPT_NAME'];
    $path_parts = explode('/', $script_path);
    
    // Count how many levels deep we are
    $depth = count(array_filter($path_parts)) - 1; // -1 because first element is empty
    
    // Build the relative path to login.php
    $login_path = str_repeat('../', $depth) . 'login.php';
    
    header('Location: ' . $login_path);
    exit;
}

$current_user = getCurrentUser();
$company_id = getCurrentCompanyId();
$is_super_admin = isSuperAdmin();
$is_company_admin = isCompanyAdmin();
$is_employee = isEmployee();
$is_renter = isRenter();

// Get system settings for branding
$db = new Database();
$conn = $db->getConnection();

function getSystemSettingLocal($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

$platform_name = getSystemSettingLocal($conn, 'platform_name', 'Construction SaaS Platform');
$platform_logo = getSystemSettingLocal($conn, 'platform_logo', '');
$primary_color = getSystemSettingLocal($conn, 'primary_color', '#243447');
$secondary_color = getSystemSettingLocal($conn, 'secondary_color', '#222E3D');
$accent_color = getSystemSettingLocal($conn, 'accent_color', '#F17300');
$theme_mode = getSystemSettingLocal($conn, 'theme_mode', 'light');
$sidebar_style = getSystemSettingLocal($conn, 'sidebar_style', 'default');
$platform_favicon = getSystemSettingLocal($conn, 'platform_favicon', '');
$notification_sound_enabled = (int)getSystemSettingLocal($conn, 'notification_sound', '1');
?>
<!DOCTYPE html>
<html lang="en" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform_name); ?></title>
    <?php if (!empty($platform_favicon)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars(str_replace('public/public/', 'public/', $base_url . $platform_favicon)); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars(str_replace('public/public/', 'public/', $base_url . $platform_favicon)); ?>">
    <?php endif; ?>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: <?php echo $primary_color; ?>;
            --secondary-color: <?php echo $secondary_color; ?>;
            --accent-color: <?php echo $accent_color; ?>;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #F6F0E6;
            --dark-color: #222E3D;
            --white-color: #ffffff;
            --gray-100: #F6F0E6;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #243447;
            --gray-800: #222E3D;
            --gray-900: #1b2532;
            --border-radius: 0.5rem;
            --border-radius-lg: 0.75rem;
            --border-radius-xl: 1rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --box-shadow-xl: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --transition: all 0.15s ease-in-out;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            font-family: var(--font-family);
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--gray-700);
            background-color: var(--gray-100);
            margin: 0;
            padding: 0;
        }

        /* Modern Sidebar (default) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            z-index: 1030;
            transition: var(--transition);
            box-shadow: var(--box-shadow-lg);
            overflow-y: auto;
        }
        .sidebar.collapsed { width: 70px; }
        .sidebar-header { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); text-align: center; }
        .sidebar-logo { height: 40px; width: auto; margin-bottom: 0.5rem; }
        .sidebar-brand { font-size: 1.25rem; font-weight: 700; color: white; text-decoration: none; margin: 0; }
        .sidebar-nav { padding: 1rem 0; }
        .nav-item { margin: 0.25rem 0; }
        .nav-link { display: flex; align-items: center; padding: 0.75rem 1.5rem; color: rgba(255, 255, 255, 0.8); text-decoration: none; transition: var(--transition); border-radius: 0.375rem; margin: 0 0.5rem; }
        .nav-link:hover { color: white; background-color: rgba(255, 255, 255, 0.1); transform: translateX(4px); }
        .nav-link.active { color: white; background-color: rgba(255, 255, 255, 0.2); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .nav-link i { width: 20px; margin-right: 0.75rem; font-size: 1rem; }
        .sidebar.collapsed .nav-link span { display: none; }
        .sidebar.collapsed .nav-link i { margin-right: 0; font-size: 1.25rem; }

        /* Sidebar style variants */
        .sidebar.compact { width: 220px; }
        .sidebar.compact .nav-link { padding: 0.5rem 1rem; }
        .sidebar.compact .sidebar-header { padding: 1rem; }
        .sidebar.minimal { width: 200px; background: #ffffff; color: var(--gray-800); border-right: 1px solid var(--gray-200); box-shadow: none; }
        .sidebar.minimal .nav-link { color: var(--gray-700); }
        .sidebar.minimal .nav-link:hover, .sidebar.minimal .nav-link.active { color: var(--primary-color); background: var(--gray-100); }
        .sidebar.modern { background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%); }

        /* Main Content */
        .main-content { margin-left: 280px; min-height: 100vh; transition: var(--transition); background-color: var(--gray-100); }
        .main-content.expanded { margin-left: 70px; }
        /* Adjust main-content for sidebar variants (desktop) */
        @media (min-width: 769px) {
          .sidebar.compact + .main-content { margin-left: 220px; }
          .sidebar.minimal + .main-content { margin-left: 200px; }
        }

        /* Top Navigation */
        .top-navbar { background: white; box-shadow: var(--box-shadow); padding: 1rem 2rem; position: sticky; top: 0; z-index: 1020; width: 100%; max-width: 100%; overflow-x: hidden; }
        .navbar-brand { font-weight: 700; color: var(--primary-color); text-decoration: none; }
        .navbar-nav { align-items: center; }
        .nav-link { color: var(--gray-600); text-decoration: none; padding: 0.5rem 1rem; border-radius: var(--border-radius); transition: var(--transition); }
        .nav-link:hover { color: var(--primary-color); background-color: var(--gray-100); }

        /* User Dropdown */
        .user-dropdown { position: relative; }
        .dropdown { position: relative; }
        .dropdown-menu { position: absolute; top: 100%; left: auto; right: 0; margin-top: 0.5rem; max-height: 80vh; overflow-y: auto; z-index: 1050; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 280px; position: fixed; top: 60px; bottom: 0; left: 0; z-index: 1020; max-width: 100%; height: calc(100vh - 60px); overflow-y: auto; }
            .sidebar.show { transform: translateX(0); }
            .sidebar.collapsed { width: 0; transform: translateX(-100%); overflow: hidden; }
            .main-content { margin-left: 0; padding-top: 60px; }
            .top-navbar { padding: 0.5rem 1rem; position: fixed; width: 100%; left: 0; right: 0; top: 0; z-index: 1030; height: 60px; display: flex; align-items: center; background-color: white; overflow: visible; }
            .top-navbar .container-fluid { width: 100%; padding: 0; overflow: visible; }
            .top-navbar .d-flex { width: 100%; justify-content: space-between !important; align-items: center; overflow: visible; }
            .dropdown-menu { position: fixed !important; top: 60px !important; left: auto !important; right: 10px !important; width: calc(100% - 20px); max-width: 300px; transform: none !important; border-radius: var(--border-radius); box-shadow: var(--box-shadow-lg); z-index: 1050 !important; }
            .top-navbar .dropdown { position: static; }
        }

        /* Ensure dropdowns are fully visible */
        .dropdown-menu { max-height: 80vh; overflow-y: auto; }

        /* Prevent navbar from becoming scrollable */
        .top-navbar, .top-navbar .container-fluid, .top-navbar .d-flex { overflow: visible !important; }

        /* Dark Mode Support */
        [data-theme="dark"] {
            --gray-100: #1a1a1a;
            --gray-200: #2d2d2d;
            --gray-300: #404040;
            --gray-400: #525252;
            --gray-500: #737373;
            --gray-600: #a3a3a3;
            --gray-700: #d4d4d4;
            --gray-800: #e5e5e5;
            --gray-900: #f5f5f5;
            --white-color: #1a1a1a;
        }

        /* Loading Animation */
        .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255, 255, 255, 0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--gray-200); }
        ::-webkit-scrollbar-thumb { background: var(--gray-400); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gray-500); }

        /* Global modal and chart stability fixes */
        .modal, .modal-backdrop { position: fixed; }
        body.modal-open { padding-right: 0 !important; }
        .card .card-body { position: relative; }
        canvas { display: block; }
        .chart-area, .chart-pie { min-height: 220px; }

        /* Fixed chart utility for donut/compact charts inside side cards */
        .fixed-chart { position: relative; width: 100%; height: 260px; overflow: hidden; }
        .fixed-chart > canvas { position: absolute; top: 0; left: 0; width: 100% !important; height: 100% !important; }

        /* Utility: stack multi-currency amounts neatly */
        .stacked-amounts > div { line-height: 1.2; }
        .stacked-amounts > div.small { font-size: 0.85rem; opacity: 0.85; }

        /* Utility Classes */
        .text-gradient { background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .bg-gradient-primary { background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); }
        .bg-gradient-success { background: linear-gradient(135deg, var(--success-color), #20c997); }
        .bg-gradient-warning { background: linear-gradient(135deg, var(--warning-color), #fd7e14); }
        .bg-gradient-danger { background: linear-gradient(135deg, var(--danger-color), #e74c3c); }

        /* Animation Classes */
        .fade-in { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .slide-in { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(-100%); } to { transform: translateX(0); } }
    </style>
    <style>
        /* Mobile adjustments without off-canvas overlay */
        @media (max-width: 768px) {
        .sidebar { 
            width: 240px; 
            transform: translateX(-100%); 
            transition: transform 0.3s ease-in-out;
        }
        .sidebar.show { transform: translateX(0); }
        .sidebar.collapsed { width: 0; transform: translateX(-100%); overflow: hidden; }
        .sidebar.collapsed .nav-link span { display: none; }
        .main-content { margin-left: 0 !important; }
        .main-content.expanded { margin-left: 0 !important; }
        .top-navbar { padding: 0.75rem 1rem; }
        }
    </style>
    <link rel="manifest" href="<?php echo $base_url; ?>manifest.json">
    <meta name="theme-color" content="<?php echo htmlspecialchars($accent_color); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars(str_replace('public/public/', 'public/', $base_url . $platform_logo)); ?>">
  </head>
  <body data-theme="<?php echo $theme_mode; ?>">
      <!-- Sidebar -->
      <div id="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar <?php echo htmlspecialchars($sidebar_style); ?>">
            <div class="sidebar-header">
                <?php if ($platform_logo): ?>
                    <img src="<?php echo htmlspecialchars(str_replace('public/public/', 'public/', $base_url . $platform_logo)); ?>" alt="Logo" class="sidebar-logo">
                <?php endif; ?>
                <h1 class="sidebar-brand"><?php echo htmlspecialchars($platform_name); ?></h1>
            </div>
            
            <ul class="sidebar-nav">
                <li class="nav-item">
                    <?php
                    $dashboardUrl = $base_url . 'dashboard/';
                    if ($is_super_admin) {
                        $dashboardUrl = $base_url . 'super-admin/';
                    } elseif ($is_company_admin) {
                        $dashboardUrl = $base_url . 'admin/dashboard/';
                    } elseif ($is_employee) {
                        $dashboardUrl = $base_url . 'employee/dashboard/';
                    }
                    ?>
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo $dashboardUrl; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span><?php echo __('dashboard'); ?></span>
                    </a>
                </li>
                
                <?php if ($is_super_admin): ?>
                    <!-- Super Admin Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'companies') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/companies/">
                            <i class="fas fa-building"></i>
                            <span><?php echo __('companies'); ?></span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'languages') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/languages/">
                            <i class="fas fa-language"></i>
                            <span><?php echo __('languages'); ?></span>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'expenses') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/expenses/">
                            <i class="fas fa-receipt"></i>
                            <span><?php echo __('expenses'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payments') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/payments/">
                            <i class="fas fa-money-bill-wave"></i>
                            <span><?php echo __('payments'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'pricing') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/pricing/">
                            <i class="fas fa-tags"></i>
                            <span><?php echo __('pricing_plans'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'tutorials') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/tutorials/">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span><?php echo __('tutorials') ?? 'Tutorials'; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'backups') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/backups/">
                            <i class="fas fa-database"></i>
                            <span>Backups</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'pages') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/pages/">
                            <i class="fas fa-file-alt"></i>
                            <span><?php echo __('pages') ?? 'Pages'; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/reports/">
                            <i class="fas fa-chart-bar"></i>
                            <span><?php echo __('reports'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>super-admin/settings/">
                            <i class="fas fa-cogs"></i>
                            <span><?php echo __('platform_settings'); ?></span>
                        </a>
                    </li>
                    
                <?php elseif ($is_company_admin): ?>
                    <!-- Company Admin Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'employees') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/employees/">
                            <i class="fas fa-users"></i>
                            <span><?php echo __('employees'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'machines') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/machines/">
                            <i class="fas fa-truck"></i>
                            <span><?php echo __('machines'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'projects') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/projects/">
                            <i class="fas fa-project-diagram"></i>
                            <span><?php echo __('projects'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'contracts') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/contracts/">
                            <i class="fas fa-file-contract"></i>
                            <span><?php echo __('contracts'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parking') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/parking/">
                            <i class="fas fa-parking"></i>
                            <span><?php echo __('parking'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'rental-areas') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/rental-areas/">
                            <i class="fas fa-map-marked-alt"></i>
                            <span><?php echo __('rental_areas'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'area-rentals') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/area-rentals/">
                            <i class="fas fa-map-marked-alt"></i>
                            <span><?php echo __('area_rentals'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'expenses') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/expenses/">
                            <i class="fas fa-receipt"></i>
                            <span><?php echo __('expenses'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'salary-payments') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/salary-payments/">
                            <i class="fas fa-money-bill-wave"></i>
                            <span><?php echo __('salary_payments'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'attendance') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>admin/attendance/">
                            <i class="fas fa-clock"></i>
                            <span><?php echo __('attendance'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>reports/">
                            <i class="fas fa-chart-bar"></i>
                            <span><?php echo __('reports'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>users/">
                            <i class="fas fa-user-cog"></i>
                            <span><?php echo __('users'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>" href="<?php echo $is_super_admin ? $base_url . 'super-admin/settings/' : ($is_company_admin ? $base_url . 'settings/' : $base_url . 'settings/'); ?>">
                            <i class="fas fa-cog"></i>
                            <span><?php echo __('settings'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'tutorials') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>tutorials/">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span><?php echo __('tutorials') ?? 'Tutorials'; ?></span>
                        </a>
                    </li>
                    
                <?php elseif ($is_employee): ?>
                    <!-- Employee Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'attendance') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>employee/attendance/">
                            <i class="fas fa-clock"></i>
                            <span><?php echo __('attendance'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'salary') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>employee/salary/">
                            <i class="fas fa-money-bill"></i>
                            <span><?php echo __('salary'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'leave') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>employee/leave/">
                            <i class="fas fa-calendar-times"></i>
                            <span><?php echo __('leave'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'contracts') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>employee/contracts/">
                            <i class="fas fa-file-contract"></i>
                            <span><?php echo __('contracts'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'profile') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>profile/">
                            <i class="fas fa-user"></i>
                            <span><?php echo __('profile'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'tutorials') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>tutorials/">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span><?php echo __('tutorials') ?? 'Tutorials'; ?></span>
                        </a>
                    </li>
                    
                <?php elseif ($is_renter): ?>
                    <!-- Renter Menu -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'rentals') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>rentals/">
                            <i class="fas fa-list"></i>
                            <span><?php echo __('rentals'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payments') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>payments/">
                            <i class="fas fa-money-bill"></i>
                            <span><?php echo __('payments'); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'profile') !== false ? 'active' : ''; ?>" href="<?php echo $base_url; ?>profile/">
                            <i class="fas fa-user"></i>
                            <span><?php echo __('profile'); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
                

                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $base_url; ?>logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span><?php echo __('logout'); ?></span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content" id="main-content">
            <!-- Top Navigation -->
            <nav class="top-navbar shadow-sm">
                <div class="container-fluid px-4">
                    <div class="d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-icon d-md-none sidebarToggle me-2" id="sidebarToggle">
                                <i class="fas fa-bars text-primary"></i>
                            </button>
                            <h4 class="mb-0 ms-2 text-primary fw-semibold"><?php echo $page_title ?? 'Dashboard'; ?></h4>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($is_company_admin || $is_employee): ?>
                            <!-- Notifications -->
                            <div class="dropdown">
                                <a class="nav-link position-relative p-2 rounded-circle bg-light-hover" href="#" role="button" data-bs-toggle="dropdown" id="notificationDropdown">
                                    <i class="fas fa-bell text-muted"></i>
                                    <span class="notification-badge position-absolute top-0 start-100 translate-middle badge bg-danger rounded-circle" id="notificationBadge" style="display:none">0</span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 overflow-hidden" id="notificationList" style="width: 380px;">
                                    <li class="px-3 py-2 bg-light border-bottom">
                                        <h6 class="mb-0 fw-semibold"><?php echo __('notifications'); ?></h6>
                                    </li>
                                    <li class="notification-items" style="max-height: 400px; overflow-y: auto;">
                                        <div class="dropdown-item text-center py-3"><small class="text-muted">Loading...</small></div>
                                    </li>
                                    <li class="p-2 bg-light border-top">
                                        <a class="btn btn-link btn-sm text-primary w-100" href="#" onclick="markAllAsRead()">
                                            <i class="fas fa-check-double me-1"></i> <?php echo __('mark_all_as_read'); ?>
                                        </a>
                                    </li>
                                </ul>

                                <style>
                                .notification-items {
                                    scrollbar-width: thin;
                                    scrollbar-color: rgba(0,0,0,.2) transparent;
                                }
                                .notification-items::-webkit-scrollbar {
                                    width: 6px;
                                }
                                .notification-items::-webkit-scrollbar-track {
                                    background: transparent;
                                }
                                .notification-items::-webkit-scrollbar-thumb {
                                    background-color: rgba(0,0,0,.2);
                                    border-radius: 3px;
                                }
                                .notification-item-content {
                                    word-wrap: break-word;
                                    overflow-wrap: break-word;
                                    word-break: break-word;
                                    hyphens: auto;
                                    max-width: 100%;
                                }
                                .notification-text {
                                    white-space: normal;
                                    overflow-wrap: break-word;
                                    word-wrap: break-word;
                                    word-break: break-word;
                                    max-width: 300px;
                                }
                                .dropdown-item {
                                    white-space: normal;
                                }
                                </style>
                            </div>
                            <?php endif; ?>
                            <!-- PWA Install Button -->
                            <button id="installAppBtn" class="btn btn-sm btn-primary d-none">
                                <i class="fas fa-download me-1"></i> <?php echo __('install_app') ?? 'Install'; ?>
                            </button>
                            <!-- User Dropdown -->
                            <div class="dropdown">
                                <a class="user-dropdown-toggle d-flex align-items-center gap-2 text-decoration-none" href="#" role="button" data-bs-toggle="dropdown">
                                    <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 14px;">
                                        <?php echo strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)); ?>
                                    </div>
                                    <span class="d-none d-md-inline text-body fw-medium"><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
                                    <i class="fas fa-chevron-down text-muted fs-12"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 py-2" style="width: 260px;">
                                    <li>
                                        <a class="dropdown-item px-3 py-2 d-flex align-items-center gap-2 hover-bg-light" href="<?php echo $base_url; ?>profile/">
                                            <i class="fas fa-user text-primary"></i>
                                            <span>Profile</span>
                                        </a>
                                    </li>
                                    <?php if (!$is_employee): ?>
                                    <li>
                                        <a class="dropdown-item px-3 py-2 d-flex align-items-center gap-2 hover-bg-light" href="<?php echo $is_super_admin ? $base_url . 'super-admin/settings/' : ($is_company_admin ? $base_url . 'admin/settings/' : $base_url . 'settings/'); ?>">
                                            <i class="fas fa-cog text-primary"></i>
                                            <span>Settings</span>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider mx-3 my-2"></li>
                                    <li>
                                        <h6 class="dropdown-header px-3 text-uppercase text-muted fs-12 fw-semibold"><?php echo __('language'); ?></h6>
                                    </li>
                                    <?php
                                    $available_languages = getAvailableLanguages();
                                    $current_language = getCompanyLanguage();
                                    foreach ($available_languages as $lang):
                                        $is_active = ($current_language['id'] == $lang['id']);
                                    ?>
                                    <li>
                                        <a class="dropdown-item px-3 py-2 d-flex align-items-center gap-2 hover-bg-light <?php echo $is_active ? 'active' : ''; ?>" 
                                           href="#" onclick="changeLanguage('<?php echo $lang['id']; ?>')">
                                            <i class="fas fa-language <?php echo $is_active ? 'text-primary' : 'text-muted'; ?>"></i>
                                            <span><?php echo htmlspecialchars($lang['language_name_native']); ?></span>
                                            <?php if ($is_active): ?>
                                                <i class="fas fa-check ms-auto text-primary"></i>
                                            <?php endif; ?>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider mx-3 my-2"></li>
                                    <li>
                                        <a class="dropdown-item px-3 py-2 d-flex align-items-center gap-2 text-danger hover-bg-light" href="<?php echo $base_url; ?>logout.php">
                                            <i class="fas fa-sign-out-alt"></i>
                                            <span>Logout</span>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <style>
            .top-navbar {
                background: #fff;
                border-bottom: 1px solid rgba(0,0,0,.05);
            }
            
            .btn-icon {
                padding: 8px;
                border-radius: 8px;
                color: var(--bs-primary);
                border: none;
                background: rgba(var(--bs-primary-rgb), 0.1);
            }
            
            .btn-icon:hover {
                background: rgba(var(--bs-primary-rgb), 0.15);
            }
            
            .bg-light-hover:hover {
                background-color: rgba(0,0,0,.05) !important;
            }
            
            .notification-badge {
                font-size: 10px;
                padding: 3px 6px;
            }
            
            .hover-bg-light:hover {
                background-color: rgba(0,0,0,.03) !important;
            }
            
            .fs-12 {
                font-size: 12px;
            }
            
            .dropdown-menu {
                margin-top: 10px;
            }
            
            .dropdown-item.active {
                background-color: rgba(var(--bs-primary-rgb), 0.1);
                color: var(--bs-primary);
            }
            </style>

            <!-- Page Content -->
            <div class="container-fluid p-4">
            
            <script>
            // Auto theme handler for 'auto' mode
            (function(){
              var mode = '<?php echo $theme_mode; ?>';
              if (mode === 'auto') {
                function applyAutoTheme(){
                  document.body.setAttribute('data-theme', window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                }
                applyAutoTheme();
                try { window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyAutoTheme); } catch(e) {}
              }
            })();
            </script>
            <script>
            // Define API base URL (absolute to avoid nested path issues)
            const apiBaseUrl = '<?php echo $base_url; ?>api/';
            
            // Load notifications from API
            function loadNotifications() {
                fetch(apiBaseUrl + 'get-notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateNotificationBadge(data.unread_count);
                            updateNotificationList(data.notifications);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading notifications:', error);
                    });
            }

            // Update notification badge
            function updateNotificationBadge(count) {
                const badge = document.getElementById('notificationBadge');
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'inline' : 'none';
                }
            }

            // Update notification list
            function updateNotificationList(notifications) {
                const list = document.getElementById('notificationList');
                if (!list) return;

                // Find the notification items container
                const itemsContainer = list.querySelector('.notification-items');
                if (!itemsContainer) return;
                
                // Clear existing notifications
                itemsContainer.innerHTML = '';
                
                if (notifications.length === 0) {
                    itemsContainer.innerHTML = '<div class="dropdown-item text-center py-3"><small class="text-muted"><?php echo __('no_notifications'); ?></small></div>';
                } else {
                    notifications.forEach(notification => {
                        const icon = getNotificationIcon(notification.type);
                        const timeAgo = getTimeAgo(notification.created_at);
                        
                        const itemHtml = `
                            <div class="dropdown-item p-3 border-bottom ${notification.is_read ? 'bg-light' : ''}" role="button" onclick="markNotificationRead(${notification.id})">
                                <div class="d-flex gap-2">
                                    <div class="flex-shrink-0" style="padding-top: 3px;">
                                        <i class="${icon}"></i>
                                    </div>
                                    <div class="notification-item-content flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-start gap-2 mb-1">
                                            <div class="notification-text ${notification.is_read ? 'text-muted' : 'text-body fw-medium'}">${notification.title}</div>
                                            ${!notification.is_read ? '<span class="flex-shrink-0 badge bg-primary rounded-pill">New</span>' : ''}
                                        </div>
                                        <div class="notification-text">
                                            <small class="text-muted">${notification.message}</small>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-muted">${timeAgo}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
                    });
                }
            }

            // Get notification icon based on type
            function getNotificationIcon(type) {
                switch (type) {
                    case 'success': return 'fas fa-check-circle text-success';
                    case 'warning': return 'fas fa-exclamation-triangle text-warning';
                    case 'error': return 'fas fa-times-circle text-danger';
                    default: return 'fas fa-info-circle text-info';
                }
            }

            // Get time ago
            function getTimeAgo(timestamp) {
                const now = new Date();
                const created = new Date(timestamp);
                const diffMs = now - created;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);

                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                if (diffHours < 24) return `${diffHours}h ago`;
                return `${diffDays}d ago`;
            }

            // Mark specific notification as read
            function markNotificationRead(notificationId) {
                const formData = new FormData();
                formData.append('notification_id', notificationId);
                formData.append('action', 'mark_read');

                fetch(apiBaseUrl + 'mark-notification-read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(data.unread_count);
                        loadNotifications(); // Reload to update the list
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
            }

            function markAllAsRead() {
                const formData = new FormData();
                formData.append('action', 'mark_all_read');

                fetch(apiBaseUrl + 'mark-notification-read.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(0);
                        loadNotifications(); // Reload to update the list
                        
                        // Show success message
                        const toast = document.createElement('div');
                        toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                        toast.innerHTML = `
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo __('all_notifications_marked_as_read'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        `;
                        document.body.appendChild(toast);
                        
                        // Auto-remove after 3 seconds
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                    }
                })
                .catch(error => {
                    console.error('Error marking all notifications as read:', error);
                });
            }

            // Load notifications when page loads
            document.addEventListener('DOMContentLoaded', function() {
                loadNotifications();
            });

            // Refresh notifications every 30 seconds
            setInterval(loadNotifications, 30000);

            // Enhanced language switching function
            function changeLanguage(languageId) {
                // Show loading indicator
                const loadingToast = document.createElement('div');
                loadingToast.className = 'alert alert-info alert-dismissible fade show position-fixed';
                loadingToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                loadingToast.innerHTML = `
                    <i class="fas fa-spinner fa-spin me-2"></i>
                    <?php echo __('changing_language'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(loadingToast);

                // Add the language change parameter to the current URL
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('change_language', languageId);
                
                // Redirect to the new URL which will trigger the language change
                window.location.href = currentUrl.toString();
            }
            </script>
            <script>
            // Improve dropdown positioning
            document.addEventListener('DOMContentLoaded', function() {
                function adjustDropdownPosition() {
                    if (window.innerWidth <= 768) {
                        const dropdowns = document.querySelectorAll('.dropdown-menu');
                        dropdowns.forEach(dropdown => {
                            const toggle = dropdown.previousElementSibling;
                            if (toggle) {
                                const toggleRect = toggle.getBoundingClientRect();
                                dropdown.style.position = 'fixed';
                                dropdown.style.top = `${toggleRect.bottom + 10}px`;
                                dropdown.style.right = `${Math.max(10, window.innerWidth - toggleRect.right - 10)}px`;
                                dropdown.style.transform = 'none';
                            }
                        });
                    } else {
                        // Reset desktop dropdown styles
                        const dropdowns = document.querySelectorAll('.dropdown-menu');
                        dropdowns.forEach(dropdown => {
                            dropdown.style.position = 'absolute';
                            dropdown.style.top = '100%';
                            dropdown.style.right = '0';
                            dropdown.style.transform = '';
                        });
                    }
                }

                // Adjust on load and resize
                adjustDropdownPosition();
                window.addEventListener('resize', adjustDropdownPosition);

                // Adjust when dropdowns are shown
                const dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                dropdownToggles.forEach(toggle => {
                    toggle.addEventListener('shown.bs.dropdown', adjustDropdownPosition);
                });
            });
            </script>
            <audio id="notifAudio" preload="auto" <?php echo $notification_sound_enabled ? '' : 'muted'; ?>>
      <source src="<?php echo $base_url; ?>assets/sounds/notify.mp3" type="audio/mpeg">
    </audio>
    <script>
    let __lastUnreadCount = null;
    function maybeNotifyClient(newCount, newestItem){
      try {
        if (typeof newCount === 'number') {
          if (__lastUnreadCount !== null && newCount > __lastUnreadCount) {
            // Play sound if enabled
            const audio = document.getElementById('notifAudio');
            if (audio && !audio.muted) { audio.currentTime = 0; audio.play().catch(()=>{}); }
            // In-page notification (Web Notifications API)
            if (Notification && Notification.permission === 'granted' && newestItem) {
              new Notification(newestItem.title || 'Notification', { body: newestItem.message || '' });
            }
            // Ask SW to show system notification (works when page is backgrounded)
            if (navigator.serviceWorker && navigator.serviceWorker.controller && newestItem) {
              navigator.serviceWorker.controller.postMessage({
                type: 'SHOW_NOTIFICATION',
                title: newestItem.title || 'Notification',
                body: newestItem.message || '',
                url: '<?php echo $base_url; ?>'
              });
            }
          }
          __lastUnreadCount = newCount;
        }
      } catch(e) {}
    }

    // Request permission once on load if push setting is on
    try {
      if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission().catch(()=>{});
      }
    } catch(e){}
    </script>
    <script>
    // Override loadNotifications to capture newest item and count
    const __origLoadNotifications = loadNotifications;
    function loadNotifications(){
      fetch(apiBaseUrl + 'get-notifications.php')
        .then(r=>r.json())
        .then(data=>{
          if (data && data.success){
            updateNotificationBadge(data.unread_count);
            updateNotificationList(data.notifications || []);
            const newest = (data.notifications||[])[0] || null;
            maybeNotifyClient(parseInt(data.unread_count||0,10), newest);
          }
        })
        .catch(err=>{ console.error('Error loading notifications:', err); });
    }
    </script>
    <script>
    const isCompanyAdmin = <?php echo $is_company_admin ? 'true' : 'false'; ?>;
    const isEmployee = <?php echo $is_employee ? 'true' : 'false'; ?>;
    // Register service worker for out-of-browser notifications
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('<?php echo $base_url; ?>sw.js').catch(()=>{});
    }
    // PWA Install flow
    (function(){
      let deferredPrompt = null;
      const installBtn = document.getElementById('installAppBtn');
      function hideInstall(){ if (installBtn) installBtn.classList.add('d-none'); }
      function showInstall(){ if (installBtn && !window.matchMedia('(display-mode: standalone)').matches) installBtn.classList.remove('d-none'); }
      window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showInstall();
      });
      window.addEventListener('appinstalled', () => { hideInstall(); deferredPrompt = null; });
      if (installBtn) {
        installBtn.addEventListener('click', async () => {
          if (!deferredPrompt) return;
          deferredPrompt.prompt();
          try { await deferredPrompt.userChoice; } catch(e){}
          deferredPrompt = null; hideInstall();
        });
      }
      // On load, hide if already installed
      if (window.matchMedia('(display-mode: standalone)').matches) hideInstall();
    })();
    </script>
    <script>
    // VAPID subscription for tenant admins
    (async function initVapid(){
      if (!isCompanyAdmin || !('serviceWorker' in navigator) || !('PushManager' in window)) return;
      try {
        const reg = await navigator.serviceWorker.ready;
        const res = await fetch(apiBaseUrl + 'push-vapid-public.php');
        const data = await res.json();
        if (!data.success) return;
        const vapidPublicKey = data.publicKey;
        function urlB64ToUint8Array(base64String) {
          const padding = '='.repeat((4 - base64String.length % 4) % 4);
          const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
          const rawData = atob(base64);
          const outputArray = new Uint8Array(rawData.length);
          for (let i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
          return outputArray;
        }
        const existing = await reg.pushManager.getSubscription();
        if (!existing) {
          const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8Array(vapidPublicKey) });
          await fetch(apiBaseUrl + 'push-subscribe.php', { method: 'POST', headers: { 'Content-Type':'application/json' }, body: JSON.stringify(sub) });
        }
      } catch(e) { console.warn('VAPID init failed', e); }
    })();
    </script>