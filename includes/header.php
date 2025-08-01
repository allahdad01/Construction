<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin: 0.25rem 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .topbar {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .main-content {
            background: #f8f9fc;
            min-height: 100vh;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        
        .text-gray-300 {
            color: #dddfeb !important;
        }
        
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        
        .btn-group .btn {
            margin-right: 2px;
        }
        
        .table th {
            background-color: #f8f9fc;
            border-top: none;
        }
        
        .badge {
            font-size: 0.75em;
        }
        
        .bg-success {
            background-color: #1cc88a !important;
        }
        
        .bg-warning {
            background-color: #f6c23e !important;
        }
        
        .bg-danger {
            background-color: #e74a3b !important;
        }
        
        .bg-info {
            background-color: #36b9cc !important;
        }
        
        .bg-secondary {
            background-color: #858796 !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4 class="text-white"><?php echo APP_NAME; ?></h4>
                        <?php if (isAuthenticated()): ?>
                            <small class="text-white-50">
                                <?php 
                                $user = getCurrentUser();
                                $company = getCurrentCompany();
                                echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                if ($company) {
                                    echo '<br>' . htmlspecialchars($company['company_name']);
                                }
                                ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        
                        <?php if (isSuperAdmin()): ?>
                            <!-- Super Admin Menu -->
                            <li class="nav-item">
                                <a class="nav-link" href="../super-admin/">
                                    <i class="fas fa-crown"></i> Super Admin
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../super-admin/companies/">
                                    <i class="fas fa-building"></i> Companies
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../super-admin/subscription-plans/">
                                    <i class="fas fa-list"></i> Subscription Plans
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../super-admin/payments/">
                                    <i class="fas fa-money-bill"></i> Payments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../super-admin/settings/">
                                    <i class="fas fa-cogs"></i> System Settings
                                </a>
                            </li>
                            
                        <?php elseif (isCompanyAdmin()): ?>
                            <!-- Company Admin Menu -->
                            <li class="nav-item">
                                <a class="nav-link" href="../employees/">
                                    <i class="fas fa-users"></i> Employees
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../machines/">
                                    <i class="fas fa-truck"></i> Machines
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../projects/">
                                    <i class="fas fa-project-diagram"></i> Projects
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../contracts/">
                                    <i class="fas fa-file-contract"></i> Contracts
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../parking/">
                                    <i class="fas fa-parking"></i> Parking
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../area-rentals/">
                                    <i class="fas fa-map-marked-alt"></i> Area Rentals
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../expenses/">
                                    <i class="fas fa-dollar-sign"></i> Expenses
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../salary-payments/">
                                    <i class="fas fa-money-bill-wave"></i> Salary Payments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../reports/">
                                    <i class="fas fa-chart-bar"></i> Reports
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../users/">
                                    <i class="fas fa-user-cog"></i> Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../settings.php">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                            </li>
                            
                        <?php elseif (isEmployee()): ?>
                            <!-- Employee Menu -->
                            <li class="nav-item">
                                <a class="nav-link" href="../attendance/">
                                    <i class="fas fa-clock"></i> Attendance
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../salary/">
                                    <i class="fas fa-money-bill"></i> Salary
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../leave/">
                                    <i class="fas fa-calendar-times"></i> Leave
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../profile/">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                            </li>
                            
                        <?php elseif (isRenter()): ?>
                            <!-- Renter Menu -->
                            <li class="nav-item">
                                <a class="nav-link" href="../rentals/">
                                    <i class="fas fa-list"></i> My Rentals
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../payments/">
                                    <i class="fas fa-money-bill"></i> Payments
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../profile/">
                                    <i class="fas fa-user"></i> Profile
                                </a>
                            </li>
                            
                        <?php endif; ?>
                        
                        <!-- Common Menu Items -->
                        <li class="nav-item">
                            <a class="nav-link" href="../profile/">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand-lg navbar-light topbar mb-4">
                    <div class="container-fluid">
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        
                        <div class="navbar-nav ms-auto">
                            <div class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle"></i>
                                    <?php 
                                    if (isAuthenticated()) {
                                        $user = getCurrentUser();
                                        echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
                                    }
                                    ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="../profile/"><i class="fas fa-user"></i> Profile</a></li>
                                    <li><a class="dropdown-item" href="../settings/"><i class="fas fa-cog"></i> Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Page content -->
                <div class="container-fluid">