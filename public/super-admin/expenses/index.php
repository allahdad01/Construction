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
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query - only super admin expenses (company_id = 1)
$where_conditions = ["company_id = 1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(expense_code LIKE ? OR description LIKE ? OR notes LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($type_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "expense_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "expense_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM expenses WHERE $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get expenses
$sql = "SELECT * FROM expenses WHERE $where_clause ORDER BY expense_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get multi-currency statistics - only super admin expenses
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM expenses WHERE company_id = 1");
$stmt->execute();
$total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get expenses by currency
$stmt = $conn->prepare("
    SELECT currency, SUM(amount) as total_amount, COUNT(*) as count
    FROM expenses 
    WHERE company_id = 1 
    GROUP BY currency
");
$stmt->execute();
$expenses_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly expenses by currency
$stmt = $conn->prepare("
    SELECT currency, SUM(amount) as total_amount, COUNT(*) as count
    FROM expenses 
    WHERE company_id = 1 AND expense_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY currency
");
$stmt->execute();
$monthly_expenses_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals by currency (separate currencies)
$total_amount_usd = 0;
$total_amount_afn = 0;
$monthly_amount_usd = 0;
$monthly_amount_afn = 0;

foreach ($expenses_by_currency as $expense) {
    if ($expense['currency'] === 'USD') {
        $total_amount_usd += $expense['total_amount'];
    } elseif ($expense['currency'] === 'AFN') {
        $total_amount_afn += $expense['total_amount'];
    }
}

foreach ($monthly_expenses_by_currency as $expense) {
    if ($expense['currency'] === 'USD') {
        $monthly_amount_usd += $expense['total_amount'];
    } elseif ($expense['currency'] === 'AFN') {
        $monthly_amount_afn += $expense['total_amount'];
    }
}

$monthly_count = array_sum(array_column($monthly_expenses_by_currency, 'count'));
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-receipt"></i> <?php echo __('platform_expenses'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_expense'); ?>
        </a>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?php echo __('total_expenses'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_expenses; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-receipt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1"><?php echo __('total_usd'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($total_amount_usd, 'USD'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1"><?php echo __('total_afn'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($total_amount_afn, 'AFN'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1"><?php echo __('monthly_usd'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($monthly_amount_usd, 'USD'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1"><?php echo __('monthly_afn'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrencyAmount($monthly_amount_afn, 'AFN'); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1"><?php echo __('monthly_count'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $monthly_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Multi-Currency Breakdown -->
    <div class="row">
        <div class="col-xl-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('total_expenses_by_currency'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($expenses_by_currency)): ?>
                        <?php foreach ($expenses_by_currency as $expense): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary me-2"><?php echo $expense['currency']; ?></span>
                                <strong><?php echo formatCurrencyAmount($expense['total_amount'], $expense['currency']); ?></strong>
                                <small class="text-muted">(<?php echo $expense['count']; ?> <?php echo __('expenses'); ?>)</small>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong><?php echo __('total'); ?> (USD):</strong></span>
                            <strong class="text-danger"><?php echo formatCurrencyAmount($total_amount_usd, 'USD'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong><?php echo __('total'); ?> (AFN):</strong></span>
                            <strong class="text-primary"><?php echo formatCurrencyAmount($total_amount_afn, 'AFN'); ?></strong>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><?php echo __('no_expenses_found'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('this_month_by_currency'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthly_expenses_by_currency)): ?>
                        <?php foreach ($monthly_expenses_by_currency as $expense): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-warning me-2"><?php echo $expense['currency']; ?></span>
                                <strong><?php echo formatCurrencyAmount($expense['total_amount'], $expense['currency']); ?></strong>
                                <small class="text-muted">(<?php echo $expense['count']; ?> <?php echo __('expenses'); ?>)</small>
                            </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong><?php echo __('monthly_total'); ?> (USD):</strong></span>
                            <strong class="text-warning"><?php echo formatCurrencyAmount($monthly_amount_usd, 'USD'); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span><strong><?php echo __('monthly_total'); ?> (AFN):</strong></span>
                            <strong class="text-warning"><?php echo formatCurrencyAmount($monthly_amount_afn, 'AFN'); ?></strong>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><?php echo __('no_expenses_this_month'); ?></p>
                    <?php endif; ?>
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
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="<?php echo __('search_by_code_description_notes'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <option value="office_supplies" <?php echo $type_filter === 'office_supplies' ? 'selected' : ''; ?>><?php echo __('office_supplies'); ?></option>
                        <option value="utilities" <?php echo $type_filter === 'utilities' ? 'selected' : ''; ?>><?php echo __('utilities'); ?></option>
                        <option value="rent" <?php echo $type_filter === 'rent' ? 'selected' : ''; ?>><?php echo __('rent'); ?></option>
                        <option value="maintenance" <?php echo $type_filter === 'maintenance' ? 'selected' : ''; ?>><?php echo __('maintenance'); ?></option>
                        <option value="marketing" <?php echo $type_filter === 'marketing' ? 'selected' : ''; ?>><?php echo __('marketing'); ?></option>
                        <option value="software" <?php echo $type_filter === 'software' ? 'selected' : ''; ?>><?php echo __('software'); ?></option>
                        <option value="travel" <?php echo $type_filter === 'travel' ? 'selected' : ''; ?>><?php echo __('travel'); ?></option>
                        <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" 
                           placeholder="<?php echo __('from_date'); ?>" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" 
                           placeholder="<?php echo __('to_date'); ?>" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-3">
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

    <!-- Expenses Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expenses'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($expenses)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_expenses_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_expense_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_expense'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="expensesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('expense_code'); ?></th>
                                <th><?php echo __('type'); ?></th>
                                <th><?php echo __('description'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('currency'); ?></th>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('payment_method'); ?></th>
                                <th><?php echo __('receipt'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($expense['expense_code']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucwords(str_replace('_', ' ', $expense['category'] ?? 'other')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                    <td>
                                        <strong class="text-danger">
                                            <?php echo formatCurrencyAmount($expense['amount'], $expense['currency'] ?? 'USD'); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($expense['currency'] ?? 'USD'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($expense['expense_date']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucwords(str_replace('_', ' ', $expense['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($expense['reference_number']): ?>
                                            <span class="badge bg-success">
                                                <?php echo htmlspecialchars($expense['reference_number']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo __('na'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="delete.php" style="display: inline;" 
                                                  onsubmit="return confirm('<?php echo __('confirm_delete_expense'); ?>')">
                                                <input type="hidden" name="id" value="<?php echo $expense['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo __('previous'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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
    $('#expensesTable').DataTable({
        "pageLength": 25,
        "order": [[4, "desc"]]
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>