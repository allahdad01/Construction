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

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $rental_id = (int)$_GET['delete'];
    
    try {
        // Get rental details first to update the area status later
        $stmt = $conn->prepare("SELECT rental_area_id, status FROM area_rentals WHERE id = ? AND company_id = ?");
        $stmt->execute([$rental_id, $company_id]);
        $rental = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rental) {
            throw new Exception("Area rental not found.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete area rental
        $stmt = $conn->prepare("DELETE FROM area_rentals WHERE id = ? AND company_id = ?");
        $stmt->execute([$rental_id, $company_id]);
        
        // Update rental area status back to available if rental was active
        if ($rental['status'] === 'active') {
            $stmt = $conn->prepare("UPDATE rental_areas SET status = 'available' WHERE id = ?");
            $stmt->execute([$rental['rental_area_id']]);
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = "Area rental deleted successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$area_type_filter = $_GET['area_type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["ar.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(ar.rental_code LIKE ? OR ar.client_name LIKE ? OR ra.area_name LIKE ? OR ra.area_code LIKE ? OR ar.business_type LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "ar.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "ar.rental_type = ?";
    $params[] = $type_filter;
}

if (!empty($area_type_filter)) {
    $where_conditions[] = "ra.area_type = ?";
    $params[] = $area_type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM area_rentals ar
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get area rentals with enhanced data
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        ra.area_name,
        ra.area_code,
        ra.area_type,
        ra.area_size_sqm,
        ra.has_electricity,
        ra.has_water,
        ra.has_security,
        ra.has_parking,
        ra.has_loading_dock,
        ra.is_covered,
        ra.currency as area_currency,
        ra.monthly_rate as area_monthly_rate,
        COALESCE(SUM(arp.amount), 0) as total_paid,
        COUNT(arp.id) as payment_count,
        COUNT(arm.id) as maintenance_count,
        COUNT(arv.id) as visit_count
    FROM area_rentals ar
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    LEFT JOIN area_rental_payments arp ON ar.id = arp.area_rental_id
    LEFT JOIN area_rental_maintenance arm ON ar.id = arm.area_rental_id
    LEFT JOIN area_rental_visits arv ON ar.id = arv.area_rental_id
    WHERE $where_clause
    GROUP BY ar.id
    ORDER BY ar.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$area_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics by currency
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_rentals,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_rentals,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_rentals,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_rentals,
        currency,
        SUM(monthly_rate) as total_monthly_revenue,
        AVG(monthly_rate) as avg_monthly_rate
    FROM area_rentals 
    WHERE company_id = ?
    GROUP BY currency
    ORDER BY total_monthly_revenue DESC
");
$stmt->execute([$company_id]);
$stats_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall statistics (without currency grouping)
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_rentals,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_rentals,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_rentals,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_rentals
    FROM area_rentals 
    WHERE company_id = ?
");
$stmt->execute([$company_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total payments received
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(arp.amount), 0) as total_payments,
        arp.currency
    FROM area_rental_payments arp
    JOIN area_rentals ar ON arp.area_rental_id = ar.id
    WHERE ar.company_id = ?
    GROUP BY arp.currency
    ORDER BY total_payments DESC
");
$stmt->execute([$company_id]);
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get area type distribution by currency
$stmt = $conn->prepare("
    SELECT 
        ra.area_type,
        ar.currency,
        COUNT(*) as count,
        SUM(ar.monthly_rate) as total_revenue
    FROM area_rentals ar
    JOIN rental_areas ra ON ar.rental_area_id = ra.id
    WHERE ar.company_id = ? AND ar.status = 'active'
    GROUP BY ra.area_type, ar.currency
    ORDER BY ra.area_type, total_revenue DESC
");
$stmt->execute([$company_id]);
$area_type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-map-marked-alt"></i> Area Rentals Management
            </h1>
            <p class="text-muted mb-0">Manage land rentals for commercial, residential, and industrial use</p>
        </div>
        <div class="btn-group" role="group">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Rental
            </a>
            <a href="../rental-areas/" class="btn btn-primary">
                <i class="fas fa-map"></i> Manage Areas
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Rentals
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_rentals']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Rentals
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_rentals']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Monthly Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if (!empty($stats_by_currency)): ?>
                                    <?php foreach ($stats_by_currency as $index => $stat): ?>
                                        <?php if ($index > 0): ?><br><?php endif; ?>
                                        <span class="text-<?php echo $stat['currency'] === 'USD' ? 'success' : ($stat['currency'] === 'AFN' ? 'warning' : 'info'); ?>">
                                            <?php echo formatCurrencyAmount($stat['total_monthly_revenue'], $stat['currency']); ?>
                                        </span>
                                        <small class="text-muted"><?php echo $stat['currency']; ?></small>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No revenue data</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Avg Monthly Rate
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if (!empty($stats_by_currency)): ?>
                                    <?php foreach ($stats_by_currency as $index => $stat): ?>
                                        <?php if ($index > 0): ?><br><?php endif; ?>
                                        <span class="text-<?php echo $stat['currency'] === 'USD' ? 'success' : ($stat['currency'] === 'AFN' ? 'warning' : 'info'); ?>">
                                            <?php echo formatCurrencyAmount($stat['avg_monthly_rate'], $stat['currency']); ?>
                                        </span>
                                        <small class="text-muted"><?php echo $stat['currency']; ?></small>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">No rate data</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Details -->
    <?php if (!empty($payment_stats)): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-chart-line"></i> Actual Revenue (All Time)
                    </h6>
                </div>
                <div class="card-body">
                    <?php foreach ($payment_stats as $payment_stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">
                                <?php echo formatCurrencyAmount($payment_stat['total_payments'], $payment_stat['currency']); ?>
                            </span>
                            <span class="badge bg-success"><?php echo $payment_stat['currency']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="fas fa-chart-pie"></i> Area Type Distribution
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($area_type_stats)): ?>
                        <?php 
                        $grouped_area_stats = [];
                        foreach ($area_type_stats as $area_stat) {
                            $area_type = $area_stat['area_type'];
                            if (!isset($grouped_area_stats[$area_type])) {
                                $grouped_area_stats[$area_type] = [];
                            }
                            $grouped_area_stats[$area_type][] = $area_stat;
                        }
                        ?>
                        <?php foreach ($grouped_area_stats as $area_type => $stats): ?>
                            <div class="mb-3">
                                <h6 class="text-primary mb-2">
                                    <?php echo ucfirst($area_type); ?>
                                </h6>
                                <?php foreach ($stats as $stat): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-muted">
                                            <?php echo $stat['count']; ?> rental(s)
                                        </span>
                                        <span class="badge bg-<?php echo $stat['currency'] === 'USD' ? 'success' : ($stat['currency'] === 'AFN' ? 'warning' : 'info'); ?>">
                                            <?php echo formatCurrencyAmount($stat['total_revenue'], $stat['currency']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">No active rentals by area type.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions Bar -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="btn-group" role="group">
                <a href="?status=" class="btn btn-outline-primary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                    All Rentals
                </a>
                <a href="?status=active" class="btn btn-outline-success <?php echo $status_filter === 'active' ? 'active' : ''; ?>">
                    Active
                </a>
                <a href="?status=pending" class="btn btn-outline-warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=ended" class="btn btn-outline-secondary <?php echo $status_filter === 'ended' ? 'active' : ''; ?>">
                    Ended
                </a>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <a href="add.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Rental
            </a>
        </div>
    </div>

    <!-- Search & Filter Section -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-search"></i> Search & Filter
            </h6>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#searchFilters">
                <i class="fas fa-filter"></i> Toggle Filters
            </button>
        </div>
        <div class="collapse show" id="searchFilters">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by rental code, client name, area name...">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>Ended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Rental Type</label>
                        <select class="form-control" id="type" name="type">
                            <option value="">All Types</option>
                            <option value="commercial" <?php echo $type_filter === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                            <option value="residential" <?php echo $type_filter === 'residential' ? 'selected' : ''; ?>>Residential</option>
                            <option value="industrial" <?php echo $type_filter === 'industrial' ? 'selected' : ''; ?>>Industrial</option>
                            <option value="container" <?php echo $type_filter === 'container' ? 'selected' : ''; ?>>Container</option>
                            <option value="event" <?php echo $type_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="area_type" class="form-label">Area Type</label>
                        <select class="form-control" id="area_type" name="area_type">
                            <option value="">All Areas</option>
                            <option value="commercial" <?php echo $area_type_filter === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                            <option value="industrial" <?php echo $area_type_filter === 'industrial' ? 'selected' : ''; ?>>Industrial</option>
                            <option value="residential" <?php echo $area_type_filter === 'residential' ? 'selected' : ''; ?>>Residential</option>
                            <option value="container" <?php echo $area_type_filter === 'container' ? 'selected' : ''; ?>>Container</option>
                            <option value="event" <?php echo $area_type_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
                <?php if (!empty($search) || !empty($status_filter) || !empty($type_filter) || !empty($area_type_filter)): ?>
                    <div class="mt-3">
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times"></i> Clear All Filters
                        </a>
                        <span class="text-muted ms-2">Showing <?php echo count($area_rentals); ?> of <?php echo $total_records; ?> rentals</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Area Rentals Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list"></i> Area Rentals (<?php echo $total_records; ?> total)
            </h6>
        </div>
        <div class="card-body">
            <?php if (empty($area_rentals)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-map-marked-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Area Rentals Found</h5>
                    <p class="text-muted">Get started by adding your first area rental.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Rental
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="areaRentalsTable">
                        <thead>
                            <tr>
                                <th>Rental Details</th>
                                <th>Area Information</th>
                                <th>Financial</th>
                                <th>Status & Dates</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($area_rentals as $rental): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong class="text-primary"><?php echo htmlspecialchars($rental['rental_code']); ?></strong>
                                            <span class="text-dark"><?php echo htmlspecialchars($rental['client_name']); ?></span>
                                            <?php if (!empty($rental['business_type'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($rental['business_type']); ?></small>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <span class="badge bg-<?php echo $rental['rental_type'] === 'commercial' ? 'primary' : ($rental['rental_type'] === 'residential' ? 'success' : 'warning'); ?>">
                                                    <?php echo ucfirst($rental['rental_type']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong><?php echo htmlspecialchars($rental['area_name']); ?></strong>
                                            <small class="text-muted"><?php echo htmlspecialchars($rental['area_code']); ?></small>
                                            <span class="badge bg-info"><?php echo ucfirst($rental['area_type']); ?></span>
                                            <?php if (!empty($rental['area_size_sqm'])): ?>
                                                <small class="text-muted"><?php echo number_format($rental['area_size_sqm'], 1); ?> sqm</small>
                                            <?php endif; ?>
                                            <div class="mt-1">
                                                <?php if ($rental['has_electricity']): ?>
                                                    <i class="fas fa-bolt text-success" title="Electricity"></i>
                                                <?php endif; ?>
                                                <?php if ($rental['has_water']): ?>
                                                    <i class="fas fa-tint text-info" title="Water"></i>
                                                <?php endif; ?>
                                                <?php if ($rental['has_security']): ?>
                                                    <i class="fas fa-shield-alt text-warning" title="Security"></i>
                                                <?php endif; ?>
                                                <?php if ($rental['has_parking']): ?>
                                                    <i class="fas fa-car text-primary" title="Parking"></i>
                                                <?php endif; ?>
                                                <?php if ($rental['is_covered']): ?>
                                                    <i class="fas fa-home text-secondary" title="Covered"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong class="text-success">
                                                <?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?>
                                            </strong>
                                            <small class="text-muted">Monthly Rate</small>
                                            <?php if ($rental['total_paid'] > 0): ?>
                                                <div class="mt-1">
                                                    <small class="text-success">
                                                        Paid: <?php echo formatCurrencyAmount($rental['total_paid'], $rental['currency'] ?? 'USD'); ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo $rental['payment_count']; ?> payments
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-<?php echo $rental['status'] === 'active' ? 'success' : ($rental['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($rental['status']); ?>
                                            </span>
                                            <small class="text-muted">
                                                Start: <?php echo date('M j, Y', strtotime($rental['start_date'])); ?>
                                            </small>
                                            <?php if (!empty($rental['end_date'])): ?>
                                                <small class="text-muted">
                                                    End: <?php echo date('M j, Y', strtotime($rental['end_date'])); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-info">Ongoing</small>
                                            <?php endif; ?>
                                            <?php if ($rental['maintenance_count'] > 0): ?>
                                                <small class="text-warning">
                                                    <i class="fas fa-tools"></i> <?php echo $rental['maintenance_count']; ?> maintenance
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-warning" title="Edit Rental">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($rental['status'] === 'active'): ?>
                                                    <a href="payment.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-success" title="Payment">
                                                        <i class="fas fa-credit-card"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($rental['status'] === 'active'): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="maintenance.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-info" title="Maintenance">
                                                    <i class="fas fa-tools"></i>
                                                </a>
                                                <a href="visits.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-secondary" title="Visits">
                                                    <i class="fas fa-calendar-check"></i>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Area rentals pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&area_type=<?php echo urlencode($area_type_filter); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&area_type=<?php echo urlencode($area_type_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&area_type=<?php echo urlencode($area_type_filter); ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#areaRentalsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 15,
        "responsive": true,
        "language": {
            "search": "Search rentals:",
            "lengthMenu": "Show _MENU_ rentals per page",
            "info": "Showing _START_ to _END_ of _TOTAL_ rentals",
            "infoEmpty": "Showing 0 to 0 of 0 rentals",
            "infoFiltered": "(filtered from _MAX_ total rentals)"
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>