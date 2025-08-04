<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(plan_name LIKE ? OR plan_code LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "is_active = ?";
    $params[] = ($status_filter === 'active' ? 1 : 0);
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM pricing_plans WHERE $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get pricing plans
$sql = "SELECT * FROM pricing_plans WHERE $where_clause ORDER BY price ASC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$pricing_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pricing_plans");
$stmt->execute();
$total_plans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pricing_plans WHERE is_active = 1");
$stmt->execute();
$active_plans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pricing_plans WHERE is_popular = 1");
$stmt->execute();
$popular_plans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT AVG(price) as avg_price FROM pricing_plans WHERE is_active = 1");
$stmt->execute();
$avg_price = $stmt->fetch(PDO::FETCH_ASSOC)['avg_price'] ?? 0;
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tags"></i> <?php echo __('pricing_plans_management'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_pricing_plan'); ?>
        </a>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?php echo __('total_plans'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_plans; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tags fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1"><?php echo __('active_plans'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_plans; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1"><?php echo __('popular_plans'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $popular_plans; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1"><?php echo __('average_price'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($avg_price, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('search_filter'); ?></h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label"><?php echo __('search'); ?></label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo __('search_by_plan_name_code_or_description'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" id="status" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>><?php echo __('inactive'); ?></option>
                    </select>
                </div>
                <div class="col-md-5 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> <?php echo __('search'); ?>
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('clear'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Pricing Plans Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('pricing_plans'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($pricing_plans)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-tags fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_pricing_plans_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_pricing_plan_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_pricing_plan'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="pricingTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('plan_name'); ?></th>
                                <th><?php echo __('code'); ?></th>
                                <th><?php echo __('price'); ?></th>
                                <th><?php echo __('billing_cycle'); ?></th>
                                <th><?php echo __('limits'); ?></th>
                                <th><?php echo __('features'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('popular'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pricing_plans as $plan): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($plan['plan_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($plan['description']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                                    </td>
                                    <td>
                                        <strong class="text-success">
                                            $<?php echo number_format($plan['price'], 2); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted"><?php echo ucfirst($plan['billing_cycle']); ?></small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($plan['max_employees'] > 0): ?>
                                                <div><?php echo __('employees'); ?>: <?php echo $plan['max_employees']; ?></div>
                                            <?php else: ?>
                                                <div><?php echo __('employees'); ?>: <?php echo __('unlimited'); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if ($plan['max_machines'] > 0): ?>
                                                <div><?php echo __('machines'); ?>: <?php echo $plan['max_machines']; ?></div>
                                            <?php else: ?>
                                                <div><?php echo __('machines'); ?>: <?php echo __('unlimited'); ?></div>
                                            <?php endif; ?>
                                            
                                            <?php if ($plan['max_projects'] > 0): ?>
                                                <div><?php echo __('projects'); ?>: <?php echo $plan['max_projects']; ?></div>
                                            <?php else: ?>
                                                <div><?php echo __('projects'); ?>: <?php echo __('unlimited'); ?></div>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php 
                                        $features = json_decode($plan['features'], true);
                                        if ($features && count($features) > 0):
                                            echo '<ul class="list-unstyled mb-0">';
                                            foreach (array_slice($features, 0, 3) as $feature):
                                                echo '<li><i class="fas fa-check text-success me-1"></i>' . htmlspecialchars($feature) . '</li>';
                                            endforeach;
                                            if (count($features) > 3):
                                                echo '<li><small class="text-muted">+' . (count($features) - 3) . ' ' . __('more') . '</small></li>';
                                            endif;
                                            echo '</ul>';
                                        else:
                                            echo '<span class="text-muted">' . __('no_features_listed') . '</span>';
                                        endif;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $plan['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $plan['is_active'] ? __('active') : __('inactive'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($plan['is_popular']): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-star"></i> <?php echo __('popular'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $plan['id']; ?>" 
                                               class="btn btn-sm btn-info" title="<?php echo __('view'); ?>">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $plan['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="<?php echo __('edit'); ?>">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $plan['id']; ?>" 
                                               class="btn btn-sm btn-danger" title="<?php echo __('delete'); ?>"
                                               onclick="return confirm('<?php echo __('confirm_delete_pricing_plan'); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo __('previous'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
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
</div>

<script>
$(document).ready(function() {
    $('#pricingTable').DataTable({
        "pageLength": 25,
        "order": [[2, "asc"]]
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>