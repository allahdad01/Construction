<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated
requireAuth();

$db = new Database();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Search functionality
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(expense_code LIKE ? OR description LIKE ? OR reference_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "expense_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "expense_date <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM expenses $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get expenses
$sql = "SELECT * FROM expenses $where_clause ORDER BY expense_date DESC, created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Expenses</h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add Expense
        </a>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Search & Filter</h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search by code, description, or reference" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="category">
                        <option value="">All Categories</option>
                        <option value="fuel" <?php echo $category_filter === 'fuel' ? 'selected' : ''; ?>>Fuel</option>
                        <option value="maintenance" <?php echo $category_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="salary" <?php echo $category_filter === 'salary' ? 'selected' : ''; ?>>Salary</option>
                        <option value="rent" <?php echo $category_filter === 'rent' ? 'selected' : ''; ?>>Rent</option>
                        <option value="utilities" <?php echo $category_filter === 'utilities' ? 'selected' : ''; ?>>Utilities</option>
                        <option value="insurance" <?php echo $category_filter === 'insurance' ? 'selected' : ''; ?>>Insurance</option>
                        <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" 
                           placeholder="From Date" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" 
                           placeholder="To Date" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Expense List</h6>
        </div>
        <div class="card-body">
            <?php if (empty($expenses)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-dollar-sign fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No expenses found.</p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Expense
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Expense Code</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Payment Method</th>
                                <th>Reference</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($expense['expense_code']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $expense['category'] === 'fuel' ? 'bg-danger' : 
                                                ($expense['category'] === 'maintenance' ? 'bg-warning' : 
                                                ($expense['category'] === 'salary' ? 'bg-primary' : 
                                                ($expense['category'] === 'rent' ? 'bg-info' : 
                                                ($expense['category'] === 'utilities' ? 'bg-success' : 
                                                ($expense['category'] === 'insurance' ? 'bg-secondary' : 'bg-dark'))))); 
                                        ?>">
                                            <?php echo ucfirst($expense['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-danger">
                                            <?php echo formatCurrency($expense['amount']); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo formatDate($expense['expense_date']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($expense['reference_number'] ?? 'N/A'); ?></td>
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
                                            <a href="delete.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirmDelete('Are you sure you want to delete this expense?')">
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
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
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

    <!-- Statistics -->
    <div class="row">
        <?php
        // Get statistics
        $stmt = $conn->prepare("SELECT SUM(amount) as total_expenses FROM expenses");
        $stmt->execute();
        $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;

        $stmt = $conn->prepare("SELECT SUM(amount) as monthly_expenses FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())");
        $stmt->execute();
        $monthly_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_expenses'] ?? 0;

        $stmt = $conn->prepare("SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC LIMIT 1");
        $stmt->execute();
        $top_category = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM expenses WHERE expense_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)");
        $stmt->execute();
        $recent_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        ?>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Expenses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_expenses); ?></div>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                This Month</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($monthly_expenses); ?></div>
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
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Top Category</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $top_category ? ucfirst($top_category['category']) : 'N/A'; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
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
                                Last 30 Days</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $recent_expenses; ?> expenses</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Expense Breakdown by Category</h6>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $conn->prepare("SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC");
                    $stmt->execute();
                    $category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <?php if (empty($category_breakdown)): ?>
                        <p class="text-muted">No expense data available.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total Amount</th>
                                        <th>Percentage</th>
                                        <th>Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_breakdown as $category): ?>
                                        <?php $percentage = $total_expenses > 0 ? ($category['total'] / $total_expenses) * 100 : 0; ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $category['category'] === 'fuel' ? 'bg-danger' : 
                                                        ($category['category'] === 'maintenance' ? 'bg-warning' : 
                                                        ($category['category'] === 'salary' ? 'bg-primary' : 
                                                        ($category['category'] === 'rent' ? 'bg-info' : 
                                                        ($category['category'] === 'utilities' ? 'bg-success' : 
                                                        ($category['category'] === 'insurance' ? 'bg-secondary' : 'bg-dark'))))); 
                                                ?>">
                                                    <?php echo ucfirst($category['category']); ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo formatCurrency($category['total']); ?></strong></td>
                                            <td><?php echo number_format($percentage, 1); ?>%</td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" 
                                                         style="width: <?php echo $percentage; ?>%">
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>