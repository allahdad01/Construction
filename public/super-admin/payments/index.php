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
$method_filter = $_GET['method'] ?? '';
$company_filter = $_GET['company'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(cp.payment_code LIKE ? OR cp.transaction_id LIKE ? OR cp.notes LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "cp.payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($method_filter)) {
    $where_conditions[] = "cp.payment_method = ?";
    $params[] = $method_filter;
}

if (!empty($company_filter)) {
    $where_conditions[] = "cp.company_id = ?";
    $params[] = $company_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "cp.payment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "cp.payment_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "
    SELECT COUNT(*) as total 
    FROM company_payments cp 
    LEFT JOIN companies c ON cp.company_id = c.id 
    WHERE $where_clause
";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get payments
$sql = "
    SELECT cp.*, c.company_name 
    FROM company_payments cp 
    LEFT JOIN companies c ON cp.company_id = c.id 
    WHERE $where_clause 
    ORDER BY cp.payment_date DESC 
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get companies for filter
$stmt = $conn->prepare("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name");
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get multi-currency statistics
$stmt = $conn->prepare("
    SELECT 
        currency,
        COUNT(*) as count,
        SUM(amount) as total_amount,
        SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as completed_amount,
        SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM company_payments 
    GROUP BY currency
");
$stmt->execute();
$currency_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals in USD
$total_received_usd = 0;
$total_pending_usd = 0;
$total_received_afn = 0;
$total_pending_afn = 0;

foreach ($currency_stats as $stat) {
    if ($stat['currency'] === 'USD') {
        $total_received_usd = $stat['completed_amount'];
        $total_pending_usd = $stat['pending_amount'];
    } elseif ($stat['currency'] === 'AFN') {
        $total_received_afn = $stat['completed_amount'];
        $total_pending_afn = $stat['pending_amount'];
    }
}

$total_payments = array_sum(array_column($currency_stats, 'count'));

// Get USD statistics
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE payment_status = 'completed' AND currency = 'USD'");
$stmt->execute();
$total_received_usd = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get AFN statistics
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE payment_status = 'completed' AND currency = 'AFN'");
$stmt->execute();
$total_received_afn = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get pending amounts by currency
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE payment_status = 'pending' AND currency = 'USD'");
$stmt->execute();
$total_pending_usd = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE payment_status = 'pending' AND currency = 'AFN'");
$stmt->execute();
$total_pending_afn = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM company_payments WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute();
$monthly_payments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill-wave"></i> <?php echo __('platform_payments'); ?>
        </h1>
        <div class="d-flex">
            <a href="add.php" class="btn btn-primary me-2">
                <i class="fas fa-plus"></i> <?php echo __('add_payment'); ?>
            </a>
            <a href="export.php" class="btn btn-success">
                <i class="fas fa-download"></i> <?php echo __('export'); ?>
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?php echo __('total_payments'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_payments; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1"><?php echo __('usd_received'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_received_usd, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1"><?php echo __('afn_received'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_received_afn, 2); ?> AFN</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1"><?php echo __('pending_usd'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($total_pending_usd, 2); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1"><?php echo __('pending_afn'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_pending_afn, 2); ?> AFN</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-6 mb-4">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1"><?php echo __('this_month'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $monthly_payments; ?></div>
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
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('search_filter'); ?></h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="<?php echo __('search_by_payment_code_transaction_id_notes'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>><?php echo __('failed'); ?></option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="method">
                        <option value=""><?php echo __('all_methods'); ?></option>
                        <option value="credit_card" <?php echo $method_filter === 'credit_card' ? 'selected' : ''; ?>><?php echo __('credit_card'); ?></option>
                        <option value="bank_transfer" <?php echo $method_filter === 'bank_transfer' ? 'selected' : ''; ?>><?php echo __('bank_transfer'); ?></option>
                        <option value="cash" <?php echo $method_filter === 'cash' ? 'selected' : ''; ?>><?php echo __('cash'); ?></option>
                        <option value="check" <?php echo $method_filter === 'check' ? 'selected' : ''; ?>><?php echo __('check'); ?></option>
                        <option value="paypal" <?php echo $method_filter === 'paypal' ? 'selected' : ''; ?>><?php echo __('paypal'); ?></option>
                        <option value="other" <?php echo $method_filter === 'other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="company">
                        <option value=""><?php echo __('all_companies'); ?></option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="row">
                        <div class="col-6">
                            <input type="date" class="form-control" name="date_from" 
                                   placeholder="<?php echo __('from_date'); ?>" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        <div class="col-6">
                            <input type="date" class="form-control" name="date_to" 
                                   placeholder="<?php echo __('to_date'); ?>" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                    </div>
                </div>
                <div class="col-md-12 mt-3">
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

    <!-- Payments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('payments'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-money-bill-wave fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_payments_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('payments_from_companies_will_appear_here'); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="paymentsTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('payment_code'); ?></th>
                                <th><?php echo __('company'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('currency'); ?></th>
                                <th><?php echo __('method'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['payment_code']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($payment['company_name'] ?? __('na')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-success">
                                            <?php echo formatCurrency($payment['amount']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($payment['currency']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $payment['payment_status'] === 'completed' ? 'bg-success' : 
                                                ($payment['payment_status'] === 'pending' ? 'bg-warning' : 
                                                ($payment['payment_status'] === 'failed' ? 'bg-danger' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $payment['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($payment['payment_status'] === 'pending'): ?>
                                                <a href="approve.php?id=<?php echo $payment['id']; ?>" 
                                                   class="btn btn-sm btn-success" title="Approve"
                                                   onclick="return confirm('<?php echo __('confirm_approve_payment'); ?>')">
                                                    <i class="fas fa-check"></i>
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&method=<?php echo urlencode($method_filter); ?>&company=<?php echo urlencode($company_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo __('previous'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&method=<?php echo urlencode($method_filter); ?>&company=<?php echo urlencode($company_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&method=<?php echo urlencode($method_filter); ?>&company=<?php echo urlencode($company_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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
    $('#paymentsTable').DataTable({
        "pageLength": 25,
        "order": [[6, "desc"]]
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>