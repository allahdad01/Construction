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

// Search and filter for rentals (parked machines)
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';// active, ended
$vehicle_type_filter = $_GET['vehicle_type'] ?? '';

// Build query for rentals
$where_conditions = ['pr.company_id = ?'];
$params = [getCurrentCompanyId()];

if (!empty($search)) {
    $where_conditions[] = "(pr.client_name LIKE ? OR pr.rental_code LIKE ? OR pr.vehicle_registration LIKE ? OR pr.vehicle_type LIKE ? OR ps.space_name LIKE ? OR ps.space_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "pr.status = ?";
    $params[] = $status_filter;
}

if (!empty($vehicle_type_filter)) {
    $where_conditions[] = "pr.vehicle_type = ?";
    $params[] = $vehicle_type_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Total count of rentals
$count_sql = "SELECT COUNT(*) as total FROM parking_rentals pr LEFT JOIN parking_spaces ps ON pr.parking_space_id = ps.id $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Fetch rentals (parked machines)
$sql = "
    SELECT pr.*
    FROM parking_rentals pr

    $where_clause
    ORDER BY pr.start_date DESC, pr.id DESC
    LIMIT ? OFFSET ?
";
$params_with_pagination = array_merge($params, [$limit, $offset]);
$stmt = $conn->prepare($sql);
$stmt->execute($params_with_pagination);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
// Total parked machines (active rentals)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM parking_rentals WHERE company_id = ? AND status = 'active'");
$stmt->execute([getCurrentCompanyId()]);
$total_parked_active = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total ended rentals
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM parking_rentals WHERE company_id = ? AND status != 'active'");
$stmt->execute([getCurrentCompanyId()]);
$total_ended = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue from payments by currency (unchanged computation)
$stmt = $conn->prepare("
    SELECT 
        pp.currency,
        SUM(pp.amount) as total_payments
    FROM parking_payments pp
    JOIN parking_rentals pr ON pp.rental_id = pr.id
    WHERE pr.company_id = ? 
    GROUP BY pp.currency
    ORDER BY total_payments DESC
");
$stmt->execute([getCurrentCompanyId()]);
$currency_revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall total (for backward compatibility)
$total_revenue = 0;
foreach ($currency_revenues as $currency_revenue) {
    $total_revenue += $currency_revenue['total_payments'];
}

// Monthly revenue by currency (last 30 days payments)
$ppCols = [];
try {
    $ppCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM parking_payments")->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {}
$dateCol = in_array('payment_date', $ppCols, true) ? 'payment_date' : (in_array('created_at', $ppCols, true) ? 'created_at' : null);

$monthly_revenue_by_currency = [];
if ($dateCol) {
    $sql = "
        SELECT COALESCE(pp.currency, 'USD') AS currency, SUM(pp.amount) AS total
        FROM parking_payments pp
        JOIN parking_rentals pr ON pp.rental_id = pr.id
        WHERE pr.company_id = ? AND pp.$dateCol >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
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
            <i class="fas fa-car"></i> <?php echo __('parked_machines'); ?>
        </h1>
        <a href="add-rental.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('park_new_machine'); ?>
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
                                <?php echo __('active_parked_machines'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_parked_active; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck-pickup fa-2x text-gray-300"></i>
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
                                <?php echo __('ended_rentals'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_ended; ?></div>
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                <?php echo __('monthly_revenue_30_days'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if (!empty($monthly_revenue_by_currency)): ?>
                                    <?php foreach ($monthly_revenue_by_currency as $idx => $row): ?>
                                        <div class="<?php echo $idx > 0 ? 'small' : ''; ?>">
                                            <?php echo formatCurrencyAmount($row['total'], $row['currency']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    $0.00
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
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
                           placeholder="<?php echo __('search_by_client_vehicle_code_or_space'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>✅ <?php echo __('active'); ?></option>
                        <option value="ended" <?php echo $status_filter === 'ended' ? 'selected' : ''; ?>>⛔ <?php echo __('ended'); ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo __('vehicle_type'); ?></label>
                    <select class="form-control" name="vehicle_type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <?php 
                        $vehicle_types = ['Excavator','Bulldozer','Crane','Dump Truck','Pickup Truck','Van','Car','Motorcycle','Trailer','Other'];
                        foreach ($vehicle_types as $vt): ?>
                            <option value="<?php echo $vt; ?>" <?php echo $vehicle_type_filter === $vt ? 'selected' : ''; ?>><?php echo __(strtolower(str_replace(' ', '_', $vt))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> <?php echo __('search'); ?>
                        </button>
                    </div>
                </div>
            </form>
            <?php if (!empty($search) || !empty($vehicle_type_filter) || !empty($status_filter)): ?>
            <div class="mt-3">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-times"></i> <?php echo __('clear_all_filters'); ?>
                </a>
                <small class="text-muted ms-2">
                    <?php echo __('showing'); ?> <?php echo count($rentals); ?> <?php echo __('of'); ?> <?php echo $total_records; ?> <?php echo __('records'); ?>
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Parked Machines Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parked_machines'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($rentals)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-car fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_parked_machines_found'); ?></p>
                    <a href="add-rental.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('park_new_machine'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('rental_code'); ?></th>
                                <th><?php echo __('client_vehicle'); ?></th>
                                <th><?php echo __('period'); ?></th>
                                <th><?php echo __('rate'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentals as $r): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($r['rental_code']); ?></strong></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($r['client_name'] ?? ''); ?></strong>
                                            <?php if (!empty($r['vehicle_type']) || !empty($r['vehicle_registration'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(trim(($r['vehicle_type'] ?? '') . (empty($r['vehicle_registration']) ? '' : ' - ' . $r['vehicle_registration']))); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo __('start'); ?>:</strong> <?php echo date('M j, Y', strtotime($r['start_date'] ?? 'now')); ?>
                                            <?php if (!empty($r['end_date'])): ?>
                                                <br><strong><?php echo __('end'); ?>:</strong> <?php echo date('M j, Y', strtotime($r['end_date'])); ?>
                                            <?php else: ?>
                                                <br><small class="text-info"><?php echo __('ongoing_rental'); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>
                                                <?php echo formatCurrencyAmount($r['monthly_rate'] ?? 0, $r['currency'] ?? ($r['space_currency'] ?? 'USD')); ?>/<?php echo __('month'); ?>
                                            </strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $r['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($r['status']); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="view-rental.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($r['status'] === 'active'): ?>
                                                <a href="payment.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-success" title="Payment">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                                <a href="edit-rental.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="end-rental.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-danger" title="End">
                                                    <i class="fas fa-stop"></i>
                                                </a>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&vehicle_type=<?php echo urlencode($vehicle_type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo __('previous'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&vehicle_type=<?php echo urlencode($vehicle_type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&vehicle_type=<?php echo urlencode($vehicle_type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
                        <a href="add-rental.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-key"></i> <?php echo __('create_new_rental'); ?>
                        </a>
                        <a href="payment.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-credit-card"></i> <?php echo __('payment_history'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('rental_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6><?php echo __('active'); ?></h6>
                            <p><strong><?php echo $total_parked_active; ?></strong></p>
                            <h6><?php echo __('ended'); ?></h6>
                            <p><strong><?php echo $total_ended; ?></strong></p>
                        </div>
                        <div class="col-6">
                            <h6><?php echo __('monthly_revenue'); ?></h6>
                            <p>
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
                            <h6><?php echo __('total_revenue'); ?></h6>
                            <p>
                                <?php if (!empty($currency_revenues)): ?>
                                    <?php foreach ($currency_revenues as $idx => $row): ?>
                                        <div class="<?php echo $idx > 0 ? 'small' : ''; ?>">
                                            <?php echo formatCurrencyAmount($row['total_payments'], $row['currency']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    $0.00
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>