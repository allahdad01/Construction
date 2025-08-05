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
$primary_color = getSystemSettingLocal($conn, 'primary_color', '#4e73df');
$secondary_color = getSystemSettingLocal($conn, 'secondary_color', '#858796');
$accent_color = getSystemSettingLocal($conn, 'accent_color', '#1cc88a');
$theme_mode = getSystemSettingLocal($conn, 'theme_mode', 'light');
$sidebar_style = getSystemSettingLocal($conn, 'sidebar_style', 'default');

// Get company settings
function getCompanySettingLocal($conn, $company_id, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM company_settings WHERE company_id = ? AND setting_key = ?");
    $stmt->execute([$company_id, $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

$company_currency_id = getCompanySettingLocal($conn, $company_id, 'currency_id', '1');
$company_date_format_id = getCompanySettingLocal($conn, $company_id, 'date_format_id', '1');
$company_language_id = getCompanySettingLocal($conn, $company_id, 'default_language_id', '1');
$company_timezone = getCompanySettingLocal($conn, $company_id, 'timezone', 'UTC');

// Set timezone
date_default_timezone_set($company_timezone);
?>
<!DOCTYPE html>
<html lang="en" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($platform_name); ?></title>
    
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
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --white-color: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --border-radius: 0.5rem;
            --border-radius-lg: 0.75rem;
            --border-radius-xl: 1rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --box-shadow-xl: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --transition: all 0.15s ease-in-out;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--gray-700);
            background-color: var(--gray-100);
            margin: 0;
            padding: 0;
        }

        /* Modern Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
            color: white;
            z-index: 1030;
            transition: var(--transition);
            box-shadow: var(--box-shadow-lg);
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-logo {
            height: 40px;
            width: auto;
            margin-bottom: 0.5rem;
        }

        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            margin: 0;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border-radius: 0.375rem;
            margin: 0 0.5rem;
        }

        .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }

        .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .sidebar.collapsed .nav-link span {
            display: none;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.25rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: var(--transition);
            background-color: var(--gray-100);
        }

        .main-content.expanded {
            margin-left: 70px;
        }

        /* Top Navigation */
        .top-navbar {
            background: white;
            box-shadow: var(--box-shadow);
            padding: 1rem 2rem;
            position: sticky;
            top: 0;
            z-index: 1020;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .navbar-nav {
            align-items: center;
        }

        .nav-link {
            color: var(--gray-600);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .nav-link:hover {
            color: var(--primary-color);
            background-color: var(--gray-100);
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }

        .user-dropdown-toggle {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .user-dropdown-toggle:hover {
            background-color: var(--gray-100);
            color: var(--primary-color);
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 0.5rem;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            background: white;
        }

        .card:hover {
            box-shadow: var(--box-shadow-lg);
            transform: translateY(-2px);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #4a5fd8, #5a6fd8);
            transform: translateY(-1px);
            box-shadow: var(--box-shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #20c997);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #fd7e14);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
        }

        /* Forms */
        .form-control {
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            padding: 0.75rem 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        /* Tables */
        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background-color: var(--gray-100);
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.25rem;
        }

        /* Badges */
        .badge {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.375rem 0.75rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .top-navbar {
                padding: 1rem;
            }
        }

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
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-200);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        /* Utility Classes */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, var(--success-color), #20c997);
        }

        .bg-gradient-warning {
            background: linear-gradient(135deg, var(--warning-color), #fd7e14);
        }

        .bg-gradient-danger {
            background: linear-gradient(135deg, var(--danger-color), #e74c3c);
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>
<body data-theme="<?php echo $theme_mode; ?>">
    <!-- Sidebar -->
    <nav class="sidebar <?php echo $sidebar_style; ?>" id="sidebar">
        <div class="sidebar-header">
            <?php if ($platform_logo): ?>
                <img src="/constract360/construction/<?php echo htmlspecialchars($platform_logo); ?>" alt="Logo" class="sidebar-logo">
            <?php endif; ?>
            <h1 class="sidebar-brand"><?php echo htmlspecialchars($platform_name); ?></h1>
        </div>
        
        <ul class="sidebar-nav">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo $is_super_admin ? '/constract360/construction/public/super-admin/' : '/constract360/construction/public/dashboard/'; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span><?php echo __('dashboard'); ?></span>
                </a>
            </li>
            
            <?php if ($is_super_admin): ?>
                <!-- Super Admin Menu -->
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'companies') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/super-admin/companies/">
                        <i class="fas fa-building"></i>
                        <span>Companies</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'languages') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/super-admin/languages/">
                        <i class="fas fa-language"></i>
                        <span>Languages</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'expenses') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/super-admin/expenses/">
                        <i class="fas fa-receipt"></i>
                        <span>Expenses</span>
                    </a>
                </li>
                                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payments') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/super-admin/payments/">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Payments</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'pricing') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/super-admin/pricing/">
                            <i class="fas fa-tags"></i>
                            <span>Pricing Plans</span>
                        </a>
                    </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/reports/">
                        <i class="fas fa-chart-bar"></i>
                        <span><?php echo __('reports'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/super-admin/settings/">
                        <i class="fas fa-cogs"></i>
                        <span>Platform Settings</span>
                    </a>
                </li>
                
            <?php elseif ($is_company_admin): ?>
                <!-- Company Admin Menu -->
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'employees') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/employees/">
                        <i class="fas fa-users"></i>
                        <span><?php echo __('employees'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'machines') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/machines/">
                        <i class="fas fa-truck"></i>
                        <span><?php echo __('machines'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'contracts') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/contracts/">
                        <i class="fas fa-file-contract"></i>
                        <span><?php echo __('contracts'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'parking') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/parking/">
                        <i class="fas fa-parking"></i>
                        <span><?php echo __('parking'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'area-rentals') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/area-rentals/">
                        <i class="fas fa-map-marked-alt"></i>
                        <span><?php echo __('area_rentals'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'expenses') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/expenses/">
                        <i class="fas fa-receipt"></i>
                        <span><?php echo __('expenses'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'salary-payments') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/salary-payments/">
                        <i class="fas fa-money-bill-wave"></i>
                        <span><?php echo __('salary_payments'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'attendance') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/admin/attendance/">
                        <i class="fas fa-clock"></i>
                        <span><?php echo __('attendance'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/reports/">
                        <i class="fas fa-chart-bar"></i>
                        <span><?php echo __('reports'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/users/">
                        <i class="fas fa-user-cog"></i>
                        <span><?php echo __('users'); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings') !== false ? 'active' : ''; ?>" href="/constract360/construction/public/settings/">
                        <i class="fas fa-cog"></i>
                        <span><?php echo __('settings'); ?></span>
                    </a>
                </li>
                
            <?php elseif ($is_employee): ?>
                <!-- Employee Menu -->
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'attendance') !== false ? 'active' : ''; ?>" href="../attendance/">
                        <i class="fas fa-clock"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'salary') !== false ? 'active' : ''; ?>" href="../salary/">
                        <i class="fas fa-money-bill"></i>
                        <span>Salary</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'leave') !== false ? 'active' : ''; ?>" href="../leave/">
                        <i class="fas fa-calendar-times"></i>
                        <span>Leave</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'profile') !== false ? 'active' : ''; ?>" href="../profile/">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
                
            <?php elseif ($is_renter): ?>
                <!-- Renter Menu -->
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'rentals') !== false ? 'active' : ''; ?>" href="../rentals/">
                        <i class="fas fa-list"></i>
                        <span>My Rentals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'payments') !== false ? 'active' : ''; ?>" href="../payments/">
                        <i class="fas fa-money-bill"></i>
                        <span>Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'profile') !== false ? 'active' : ''; ?>" href="../profile/">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
            <?php endif; ?>
            

            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-link d-md-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h4 class="mb-0 ms-3"><?php echo $page_title ?? 'Dashboard'; ?></h4>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <!-- Notifications -->
                        <div class="dropdown me-3">
                            <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown" id="notificationDropdown">
                                <i class="fas fa-bell"></i>
                                <span class="badge bg-danger rounded-pill" id="notificationBadge">0</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" id="notificationList">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <li><div class="dropdown-item text-center"><small class="text-muted">Loading...</small></div></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="#" onclick="markAllAsRead()">
                                    <small class="text-muted">Mark all as read</small>
                                </a></li>
                            </ul>
                        </div>
                        
                        <!-- User Dropdown -->
                        <div class="dropdown">
                            <a class="user-dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($current_user['first_name'], 0, 1) . substr($current_user['last_name'], 0, 1)); ?>
                                </div>
                                <span class="d-none d-md-inline"><?php echo htmlspecialchars($current_user['first_name'] . ' ' . $current_user['last_name']); ?></span>
                                <i class="fas fa-chevron-down ms-2"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile/"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="settings/"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><h6 class="dropdown-header"><?php echo __('language'); ?></h6></li>
                                <?php
                                $available_languages = getAvailableLanguages();
                                $current_language = getCompanyLanguage();
                                foreach ($available_languages as $lang):
                                    $is_active = ($current_language['id'] == $lang['id']);
                                ?>
                                <li>
                                    <a class="dropdown-item <?php echo $is_active ? 'active' : ''; ?>" 
                                       href="#" onclick="changeLanguage('<?php echo $lang['language_code']; ?>')">
                                        <i class="fas fa-language me-2"></i>
                                        <?php echo htmlspecialchars($lang['language_name_native']); ?>
                                        <?php if ($is_active): ?>
                                            <i class="fas fa-check ms-auto"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid p-4">
        
        <script>
        // Define API base URL
        const apiBaseUrl = '<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/../api/';
        
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

            // Clear existing notifications (keep header and footer)
            const header = list.querySelector('.dropdown-header').parentElement;
            const footer = list.querySelector('.dropdown-divider').parentElement;
            const markAllLink = list.querySelector('a[onclick="markAllAsRead()"]').parentElement;
            
            list.innerHTML = '';
            list.appendChild(header);
            
            if (notifications.length === 0) {
                const noNotifications = document.createElement('li');
                noNotifications.innerHTML = '<div class="dropdown-item text-center"><small class="text-muted">No notifications</small></div>';
                list.appendChild(noNotifications);
            } else {
                notifications.forEach(notification => {
                    const item = document.createElement('li');
                    const icon = getNotificationIcon(notification.type);
                    const timeAgo = getTimeAgo(notification.created_at);
                    
                    item.innerHTML = `
                        <a class="dropdown-item ${notification.is_read ? 'text-muted' : ''}" href="#" onclick="markNotificationRead(${notification.id})">
                            <i class="${icon} me-2"></i>
                            <div>
                                <div class="fw-bold">${notification.title}</div>
                                <small class="text-muted">${notification.message}</small>
                                <br><small class="text-muted">${timeAgo}</small>
                            </div>
                        </a>
                    `;
                    list.appendChild(item);
                });
            }
            
            list.appendChild(footer);
            list.appendChild(markAllLink);
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
                        All notifications marked as read
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
        function changeLanguage(languageCode) {
            // Show loading indicator
            const loadingToast = document.createElement('div');
            loadingToast.className = 'alert alert-info alert-dismissible fade show position-fixed';
            loadingToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            loadingToast.innerHTML = `
                <i class="fas fa-spinner fa-spin me-2"></i>
                Changing language...
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(loadingToast);

            // Make API call to change language
            fetch('/constract360/construction/api/change-language.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    language: languageCode
                })
            })
            .then(response => response.json())
            .then(data => {
                // Remove loading indicator
                loadingToast.remove();

                if (data.success) {
                    // Show success message
                    const successToast = document.createElement('div');
                    successToast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    successToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                    successToast.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(successToast);

                    // Reload page to apply language changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Show error message
                    const errorToast = document.createElement('div');
                    errorToast.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                    errorToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                    errorToast.innerHTML = `
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(errorToast);
                }
            })
            .catch(error => {
                // Remove loading indicator
                loadingToast.remove();

                // Show error message
                const errorToast = document.createElement('div');
                errorToast.className = 'alert alert-danger alert-dismissible fade show position-fixed';
                errorToast.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
                errorToast.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Failed to change language. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.body.appendChild(errorToast);
            });
        }
        </script>