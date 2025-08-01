<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Construction Company SaaS Platform">
    <meta name="author" content="Construction Company">

    <title><?php echo APP_NAME; ?></title>

    <!-- Custom fonts for this template-->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            border-radius: 0.35rem;
            margin: 0.2rem 0;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
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
        .topbar {
            height: 4.375rem;
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        .topbar .navbar-search {
            width: 25rem;
        }
        .topbar .navbar-search input {
            font-size: 0.85rem;
            height: auto;
        }
        .topbar .topbar-divider {
            width: 0;
            border-right: 1px solid #e3e6f0;
            height: calc(4.375rem - 2rem);
            margin: auto 1rem;
        }
        .topbar .nav-item .nav-link {
            height: 4.375rem;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
        }
        .topbar .nav-item .nav-link .badge-counter {
            position: absolute;
            transform: scale(0.7);
            transform-origin: top right;
            right: 0.25rem;
            margin-top: -0.25rem;
        }
        .topbar .dropdown-list {
            padding: 0;
            border: none;
            overflow: hidden;
            width: 20rem !important;
        }
        .topbar .dropdown-list .dropdown-header {
            background-color: #4e73df;
            border: 1px solid #4e73df;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
            color: #fff;
        }
        .topbar .dropdown-list .dropdown-item {
            white-space: normal;
            border-top: 1px solid #e3e6f0;
            color: #3a3b45;
            font-size: 0.85rem;
            line-height: 1.3rem;
        }
        .topbar .dropdown-list .dropdown-item:hover {
            background-color: #eaecf4;
            color: #3a3b45;
        }
        .topbar .dropdown-list .dropdown-item .icon-circle {
            height: 2rem;
            width: 2rem;
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .topbar .dropdown-list .dropdown-item .icon-circle.bg-primary {
            background-color: #4e73df;
        }
        .topbar .dropdown-list .dropdown-item .icon-circle.bg-success {
            background-color: #1cc88a;
        }
        .topbar .dropdown-list .dropdown-item .icon-circle.bg-warning {
            background-color: #f6c23e;
        }
        .topbar .dropdown-list .dropdown-item .icon-circle.bg-danger {
            background-color: #e74a3b;
        }
        .topbar .dropdown-list .dropdown-item .text-truncate {
            max-width: 13.375rem;
        }
        .topbar .dropdown-list .dropdown-item:active {
            background-color: #eaecf4;
            color: #3a3b45;
        }
        .dropdown-menu {
            font-size: 0.85rem;
        }
        .dropdown-menu .dropdown-header {
            background-color: #4e73df;
            border: 1px solid #4e73df;
            margin-top: 0;
            font-size: 0.65rem;
            font-weight: bold;
            color: #fff;
            text-transform: uppercase;
        }
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid #e3e6f0;
            border-radius: 0.35rem;
        }
        .card-header {
            padding: 0.75rem 1.25rem;
            margin-bottom: 0;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .card-body {
            flex: 1 1 auto;
            min-height: 1px;
            padding: 1.25rem;
        }
        .shadow {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }
        .text-xs {
            font-size: 0.7rem;
        }
        .font-weight-bold {
            font-weight: 700 !important;
        }
        .text-uppercase {
            text-transform: uppercase !important;
        }
        .h-100 {
            height: 100% !important;
        }
        .py-2 {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
        .mb-4 {
            margin-bottom: 1.5rem !important;
        }
        .mb-0 {
            margin-bottom: 0 !important;
        }
        .mr-2 {
            margin-right: 0.5rem !important;
        }
        .col-auto {
            flex: 0 0 auto;
            width: auto;
            max-width: 100%;
        }
        .no-gutters {
            margin-right: 0;
            margin-left: 0;
        }
        .no-gutters > .col,
        .no-gutters > [class*="col-"] {
            padding-right: 0;
            padding-left: 0;
        }
        .align-items-center {
            align-items: center !important;
        }
        .fa-2x {
            font-size: 2em;
        }
    </style>
</head>

<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="sidebar-brand-text mx-3"><?php echo APP_NAME; ?></div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Management
            </div>

            <!-- Nav Item - Employees -->
            <li class="nav-item">
                <a class="nav-link" href="employees/">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Employees</span>
                </a>
            </li>

            <!-- Nav Item - Machines -->
            <li class="nav-item">
                <a class="nav-link" href="machines/">
                    <i class="fas fa-fw fa-truck"></i>
                    <span>Machines</span>
                </a>
            </li>

            <!-- Nav Item - Projects -->
            <li class="nav-item">
                <a class="nav-link" href="projects/">
                    <i class="fas fa-fw fa-project-diagram"></i>
                    <span>Projects</span>
                </a>
            </li>

            <!-- Nav Item - Contracts -->
            <li class="nav-item">
                <a class="nav-link" href="contracts/">
                    <i class="fas fa-fw fa-file-contract"></i>
                    <span>Contracts</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Rental & Parking
            </div>

            <!-- Nav Item - Parking -->
            <li class="nav-item">
                <a class="nav-link" href="parking/">
                    <i class="fas fa-fw fa-parking"></i>
                    <span>Parking Spaces</span>
                </a>
            </li>

            <!-- Nav Item - Area Rental -->
            <li class="nav-item">
                <a class="nav-link" href="rental-areas/">
                    <i class="fas fa-fw fa-map-marked-alt"></i>
                    <span>Area Rental</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Financial
            </div>

            <!-- Nav Item - Expenses -->
            <li class="nav-item">
                <a class="nav-link" href="expenses/">
                    <i class="fas fa-fw fa-dollar-sign"></i>
                    <span>Expenses</span>
                </a>
            </li>

            <!-- Nav Item - Salary Payments -->
            <li class="nav-item">
                <a class="nav-link" href="salary-payments/">
                    <i class="fas fa-fw fa-money-bill-wave"></i>
                    <span>Salary Payments</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Reports
            </div>

            <!-- Nav Item - Reports -->
            <li class="nav-item">
                <a class="nav-link" href="reports/">
                    <i class="fas fa-fw fa-chart-area"></i>
                    <span>Reports</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Search -->
                    <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
                        <div class="input-group">
                            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search" aria-describedby="basic-addon2">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button">
                                    <i class="fas fa-search fa-sm"></i>
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?>
                                </span>
                                <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <a class="dropdown-item" href="settings.php">
                                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->