<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = ['company_id = ?'];
$params = [getCurrentCompanyId()];

if (!empty($search)) {
    $where_conditions[] = "(space_name LIKE ? OR space_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "space_type = ?";
    $params[] = $type_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "vehicle_category = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM parking_spaces $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get parking spaces
$sql = "SELECT * FROM parking_spaces $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$parking_spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active rentals for each parking space
foreach ($parking_spaces as &$space) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as active_rentals 
        FROM parking_rentals 
        WHERE parking_space_id = ? AND status = 'active'
    ");
    $stmt->execute([$space['id']]);
    $space['active_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_rentals'];
}

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM parking_spaces WHERE company_id = ?");
$stmt->execute([getCurrentCompanyId()]);
$total_spaces = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM parking_spaces WHERE company_id = ? AND status = 'available'");
$stmt->execute([getCurrentCompanyId()]);
$available_spaces = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM parking_spaces WHERE company_id = ? AND status = 'occupied'");
$stmt->execute([getCurrentCompanyId()]);
$occupied_spaces = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM parking_rentals pr 
    JOIN parking_spaces ps ON pr.parking_space_id = ps.id 
    WHERE ps.company_id = ? AND pr.status = 'active'
");
$stmt->execute([getCurrentCompanyId()]);
$active_rentals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total revenue from actual payments by currency
$stmt = $conn->prepare("
    SELECT 
        pp.currency,
        SUM(pp.amount) as total_payments
    FROM parking_payments pp
    JOIN parking_rentals pr ON pp.rental_id = pr.id
    JOIN parking_spaces ps ON pr.parking_space_id = ps.id
    WHERE ps.company_id = ? 
    GROUP BY pp.currency
    ORDER BY total_payments DESC
");
$stmt->execute([getCurrentCompanyId()]);
$currency_revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall total (for backward compatibility)
$total_revenue = 0;
foreach ($currency_revenues as $currency_revenue) {
    $total_revenue += $currency_revenue['total_payments'];
}

// Get monthly potential revenue from active rentals
$stmt = $conn->prepare("
    SELECT 
        COALESCE(ps.currency, 'USD') as currency,
        SUM(ps.monthly_rate) as total_monthly 
    FROM parking_rentals pr 
    JOIN parking_spaces ps ON pr.parking_space_id = ps.id 
    WHERE ps.company_id = ? AND pr.status = 'active'
    GROUP BY COALESCE(ps.currency, 'USD')
    ORDER BY total_monthly DESC
");
$stmt->execute([getCurrentCompanyId()]);
$monthly_potential = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine date column for payments
$ppCols = [];
try {
    $ppCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM parking_payments")->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {}
$dateCol = in_array('payment_date', $ppCols, true) ? 'payment_date' : (in_array('created_at', $ppCols, true) ? 'created_at' : null);

// Monthly revenue by currency (last 30 days payments)
$monthly_revenue_by_currency = [];
if ($dateCol) {
    $sql = "
        SELECT COALESCE(pp.currency, 'USD') AS currency, SUM(pp.amount) AS total
        FROM parking_payments pp
        JOIN parking_rentals pr ON pp.rental_id = pr.id
        JOIN parking_spaces ps ON pr.parking_space_id = ps.id
        WHERE ps.company_id = ? AND pp.$dateCol >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
        GROUP BY COALESCE(pp.currency, 'USD')
        ORDER BY total DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([getCurrentCompanyId()]);
    $monthly_revenue_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-parking"></i> <?php echo __('parking_management'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_parking_space'); ?>
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_parking_spaces'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_spaces; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-parking fa-2x text-gray-300"></i>
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
                                <?php echo __('available_spaces'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $available_spaces; ?></div>
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
                                <?php echo __('active_rentals'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_rentals; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-key fa-2x text-gray-300"></i>
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
                                <?php echo __('total_revenue'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if (count($currency_revenues) > 1): ?>
                                    <?php foreach ($currency_revenues as $index => $currency_revenue): ?>
                                        <div class="<?php echo $index > 0 ? 'small' : ''; ?>">
                                            <?php echo formatCurrencyAmount($currency_revenue['total_payments'], $currency_revenue['currency']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif (count($currency_revenues) == 1): ?>
                                    <?php echo formatCurrencyAmount($currency_revenues[0]['total_payments'], $currency_revenues[0]['currency']); ?>
                                <?php else: ?>
                                    $0.00
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
    </div>

    <!-- Revenue Details -->
    <?php if (!empty($currency_revenues) || !empty($monthly_potential)): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">
                        <i class="fas fa-chart-line"></i> <?php echo __('actual_revenue'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($currency_revenues)): ?>
                        <?php foreach ($currency_revenues as $currency_revenue): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">
                                    <?php echo formatCurrencyAmount($currency_revenue['total_payments'], $currency_revenue['currency']); ?>
                                </span>
                                <span class="badge bg-success"><?php echo $currency_revenue['currency']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0"><?php echo __('no_revenue_recorded_yet'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">
                        <i class="fas fa-calendar"></i> <?php echo __('monthly_potential'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthly_potential)): ?>
                        <?php foreach ($monthly_potential as $potential): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold">
                                    <?php echo formatCurrencyAmount($potential['total_monthly'], $potential['currency']); ?>
                                </span>
                                <span class="badge bg-info"><?php echo $potential['currency']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0"><?php echo __('no_active_rentals'); ?></p>
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
                <a href="index.php" class="btn btn-outline-primary <?php echo empty($status_filter) ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> <?php echo __('all_spaces'); ?>
                </a>
                <a href="index.php?status=available" class="btn btn-outline-success <?php echo $status_filter === 'available' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i> <?php echo __('available'); ?>
                </a>
                <a href="index.php?status=occupied" class="btn btn-outline-warning <?php echo $status_filter === 'occupied' ? 'active' : ''; ?>">
                    <i class="fas fa-car"></i> <?php echo __('occupied'); ?>
                </a>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> <?php echo __('add_new_space'); ?>
            </a>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-search"></i> <?php echo __('search_filter'); ?>
            </h6>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#searchForm">
                <i class="fas fa-filter"></i> <?php echo __('toggle_filters'); ?>
            </button>
        </div>
        <div class="card-body collapse show" id="searchForm">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('search'); ?></label>
                    <input type="text" class="form-control" name="search" 
                           placeholder="<?php echo __('search_by_space_name_code_or_description'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?php echo __('category'); ?></label>
                    <select class="form-control" name="category">
                        <option value=""><?php echo __('all_categories'); ?></option>
                        <option value="machines" <?php echo $category_filter === 'machines' ? 'selected' : ''; ?>>🏗️ <?php echo __('construction_machines'); ?></option>
                        <option value="cars" <?php echo $category_filter === 'cars' ? 'selected' : ''; ?>>🚗 <?php echo __('cars'); ?></option>
                        <option value="trucks" <?php echo $category_filter === 'trucks' ? 'selected' : ''; ?>>🚛 <?php echo __('trucks'); ?></option>
                        <option value="vans" <?php echo $category_filter === 'vans' ? 'selected' : ''; ?>>🚐 <?php echo __('vans'); ?></option>
                        <option value="motorcycles" <?php echo $category_filter === 'motorcycles' ? 'selected' : ''; ?>>🏍️ <?php echo __('motorcycles'); ?></option>
                        <option value="trailers" <?php echo $category_filter === 'trailers' ? 'selected' : ''; ?>>🚛 <?php echo __('trailers'); ?></option>
                        <option value="general" <?php echo $category_filter === 'general' ? 'selected' : ''; ?>>🅿️ <?php echo __('general'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?php echo __('type'); ?></label>
                    <select class="form-control" name="type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <option value="covered" <?php echo $type_filter === 'covered' ? 'selected' : ''; ?>>🏠 <?php echo __('covered'); ?></option>
                        <option value="uncovered" <?php echo $type_filter === 'uncovered' ? 'selected' : ''; ?>>🌤️ <?php echo __('uncovered'); ?></option>
                        <option value="indoor" <?php echo $type_filter === 'indoor' ? 'selected' : ''; ?>>🏢 <?php echo __('indoor'); ?></option>
                        <option value="outdoor" <?php echo $type_filter === 'outdoor' ? 'selected' : ''; ?>>🌳 <?php echo __('outdoor'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>✅ <?php echo __('available'); ?></option>
                        <option value="occupied" <?php echo $status_filter === 'occupied' ? 'selected' : ''; ?>>🚗 <?php echo __('occupied'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> <?php echo __('search'); ?>
                        </button>
                    </div>
                </div>
            </form>
            <?php if (!empty($search) || !empty($category_filter) || !empty($type_filter) || !empty($status_filter)): ?>
            <div class="mt-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times"></i> <?php echo __('clear_all_filters'); ?>
                </a>
                <small class="text-muted ms-2">
                    <?php echo __('showing'); ?> <?php echo count($parking_spaces); ?> <?php echo __('of'); ?> <?php echo $total_records; ?> <?php echo __('spaces'); ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Parking Spaces Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_spaces'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($parking_spaces)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-parking fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_parking_spaces_found'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_parking_space'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('space_code'); ?></th>
                                <th><?php echo __('space_name'); ?></th>
                                <th><?php echo __('vehicle_category_type'); ?></th>
                                <th><?php echo __('size_rate'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('active_rentals'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($parking_spaces as $space): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($space['space_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($space['space_name']); ?></strong>
                                            <?php if (!empty($space['description'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($space['description'], 0, 50)); ?><?php echo strlen($space['description']) > 50 ? '...' : ''; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge <?php 
                                                $category = $space['vehicle_category'] ?? 'general';
                                                echo $category === 'machines' ? 'bg-primary' : 
                                                    ($category === 'cars' ? 'bg-success' : 
                                                    ($category === 'trucks' ? 'bg-warning' : 
                                                    ($category === 'motorcycles' ? 'bg-info' : 'bg-secondary'))); 
                                            ?>">
                                                <?php 
                                                $category_display = [
                                                    'machines' => '🏗️ ' . __('machines'),
                                                    'cars' => '🚗 ' . __('cars'), 
                                                    'trucks' => '🚛 ' . __('trucks'),
                                                    'vans' => '🚐 ' . __('vans'),
                                                    'motorcycles' => '🏍️ ' . __('motorcycles'),
                                                    'trailers' => '🚛 ' . __('trailers'),
                                                    'general' => '🅿️ ' . __('general')
                                                ];
                                                echo $category_display[$category] ?? ucfirst($category); 
                                                ?>
                                            </span>
                                            <br><small class="text-muted">
                                                <?php echo ucfirst($space['space_type'] ?? 'standard'); ?> • <?php echo ucfirst($space['size'] ?? 'medium'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>
                                                <?php 
                                                $currency = $space['currency'] ?? 'USD';
                                                $symbol = $currency === 'AFN' ? '؋' : ($currency === 'EUR' ? '€' : '$');
                                                echo $symbol . number_format($space['monthly_rate'], 2); 
                                                ?>
                                            </strong>
                                            <br><small class="text-muted"><?php echo __('per_month'); ?></small>
                                            <?php if (isset($space['capacity']) && $space['capacity'] > 1): ?>
                                                <br><small class="text-info"><?php echo $space['capacity']; ?> <?php echo __('vehicles'); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $space['status'] === 'available' ? 'bg-success' : 'bg-warning'; 
                                        ?>">
                                            <?php echo ucfirst($space['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <span class="badge <?php echo $space['active_rentals'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $space['active_rentals']; ?> <?php echo __('active'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view.php?id=<?php echo $space['id']; ?>" 
                                                   class="btn btn-outline-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $space['id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit Space">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                            <?php if ($space['status'] === 'available'): ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="add-rental.php?space_id=<?php echo $space['id']; ?>" 
                                                   class="btn btn-outline-success" title="Add Rental">
                                                    <i class="fas fa-plus"></i> Rent
                                                </a>
                                            </div>
                                            <?php else: ?>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view.php?id=<?php echo $space['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Rentals">
                                                    <i class="fas fa-list"></i> Rentals
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
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo __('previous'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo __('next'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions and Statistics -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="add.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus"></i> <?php echo __('add_new_parking_space'); ?>
                        </a>
                        <a href="rentals/" class="list-group-item list-group-item-action">
                            <i class="fas fa-list"></i> <?php echo __('manage_all_rentals'); ?>
                        </a>
                        <a href="add-rental.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-key"></i> <?php echo __('create_new_rental'); ?>
                        </a>
                        <a href="reports/" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar"></i> <?php echo __('parking_reports'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6><?php echo __('space_breakdown'); ?></h6>
                            <p><strong><?php echo __('available'); ?>:</strong> <?php echo $available_spaces; ?></p>
                            <p><strong><?php echo __('occupied'); ?>:</strong> <?php echo $occupied_spaces; ?></p>
                            <p><strong><?php echo __('total'); ?>:</strong> <?php echo $total_spaces; ?></p>
                        </div>
                        <div class="col-6">
                            <h6><?php echo __('revenue_overview'); ?></h6>
                            <p><strong><?php echo __('monthly_revenue'); ?>:</strong>
                                <?php if (!empty($monthly_revenue_by_currency)): ?>
                                    <?php foreach ($monthly_revenue_by_currency as $idx => $row): ?>
                                        <div class="<?php echo $idx > 0 ? 'small' : ''; ?>">
                                            <?php echo formatCurrencyAmount($row['total'], $row['currency']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    $0.00
                                <?php endif; ?>
                            </p>
                            <p><strong><?php echo __('active_rentals'); ?>:</strong> <?php echo $active_rentals; ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6><?php echo __('occupancy_rate'); ?></h6>
                        <h4 class="text-info">
                            <?php echo $total_spaces > 0 ? round(($occupied_spaces / $total_spaces) * 100, 1) : 0; ?>%
                        </h4>
                        <small class="text-muted"><?php echo __('spaces_currently_occupied'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>