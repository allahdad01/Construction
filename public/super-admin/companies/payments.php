<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

$company_id = (int)($_GET['company_id'] ?? 0);

if (!$company_id) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Get company details
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    header('Location: /constract360/construction/public/super-admin/companies/');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$method_filter = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = ["company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(payment_code LIKE ? OR transaction_id LIKE ? OR notes LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "payment_status = ?";
    $params[] = $status_filter;
}

if (!empty($method_filter)) {
    $where_conditions[] = "payment_method = ?";
    $params[] = $method_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "payment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "payment_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM company_payments WHERE $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get payments
$sql = "SELECT * FROM company_payments WHERE $where_clause ORDER BY payment_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment statistics
$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE company_id = ? AND payment_status = 'completed'");
$stmt->execute([$company_id]);
$total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("SELECT SUM(amount) as total FROM company_payments WHERE company_id = ? AND payment_status = 'pending'");
$stmt->execute([$company_id]);
$total_pending = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM company_payments WHERE company_id = ? AND payment_status = 'completed'");
$stmt->execute([$company_id]);
$completed_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM company_payments WHERE company_id = ? AND payment_status = 'pending'");
$stmt->execute([$company_id]);
$pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill"></i> Company Payments - <?php echo htmlspecialchars($company['company_name']); ?>
        </h1>
        <div>
            <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Companies
            </a>
            <a href="/constract360/construction/public/super-admin/companies/view.php?id=<?php echo $company['id']; ?>" class="btn btn-info btn-sm">
                <i class="fas fa-eye"></i> View Company
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Paid</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_paid); ?></div>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Amount</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_pending); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed Payments</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completed_count; ?></div>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pending Payments</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search & Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by payment code, transaction ID, or notes" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value="">All Status</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="method">
                        <option value="">All Methods</option>
                        <option value="credit_card" <?php echo $method_filter === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="bank_transfer" <?php echo $method_filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="paypal" <?php echo $method_filter === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                        <option value="cash" <?php echo $method_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" 
                           placeholder="From Date" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" 
                           placeholder="To Date" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
            <div class="row mt-3">
                <div class="col-12">
                    <a href="?company_id=<?php echo $company_id; ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Payments List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($payments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-money-bill fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No payments found for this company.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Payment Code</th>
                                <th>Amount</th>
                                <th>Currency</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Billing Period</th>
                                <th>Plan</th>
                                <th>Transaction ID</th>
                                <th>Payment Date</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['payment_code']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-success font-weight-bold">
                                            <?php echo formatCurrency($payment['amount']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['currency']); ?></td>
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
                                    <td>
                                        <?php if ($payment['billing_period_start'] && $payment['billing_period_end']): ?>
                                            <?php echo formatDate($payment['billing_period_start']); ?> - 
                                            <?php echo formatDate($payment['billing_period_end']); ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst($payment['subscription_plan'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?>
                                        </small>
                                    </td>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($payment['notes'] ?? ''); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/constract360/construction/public/super-admin/companies/payment-view.php?id=<?php echo $payment['id']; ?>&company_id=<?php echo $company_id; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($payment['payment_status'] === 'pending'): ?>
                                                <a href="/constract360/construction/public/super-admin/companies/payment-approve.php?id=<?php echo $payment['id']; ?>&company_id=<?php echo $company_id; ?>" 
                                                   class="btn btn-sm btn-success" title="Approve"
                                                   onclick="return confirmDelete('Are you sure you want to approve this payment?')">
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
                                    <a class="page-link" href="?company_id=<?php echo $company_id; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&method=<?php echo urlencode($method_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?company_id=<?php echo $company_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&method=<?php echo urlencode($method_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?company_id=<?php echo $company_id; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&method=<?php echo urlencode($method_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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
function confirmDelete(message) {
    return confirm(message);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>