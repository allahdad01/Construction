<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['super_admin', 'company_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $expense_id = (int)$_GET['delete'];
    
    try {
        // Check if expense exists and belongs to company
        $stmt = $conn->prepare("
            SELECT expense_code, description 
            FROM expenses 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$expense_id, $company_id]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            throw new Exception("Expense record not found or you don't have permission to delete it.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete expense
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND company_id = ?");
        $stmt->execute([$expense_id, $company_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No records were deleted. The expense may have already been removed.");
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = "Expense '{$expense['expense_code']}' deleted successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

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
$where_conditions = ['company_id = ?'];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(description LIKE ? OR expense_code LIKE ? OR reference_number LIKE ?)";
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

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM expenses $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get expenses
$sql = "SELECT * FROM expenses $where_clause ORDER BY expense_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM expenses WHERE company_id = ?");
$stmt->execute([$company_id]);
$total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM expenses 
    WHERE company_id = ? AND expense_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
");
$stmt->execute([$company_id]);
$monthly_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$stmt = $conn->prepare("
    SELECT category, SUM(amount) as total 
    FROM expenses 
    WHERE company_id = ? 
    GROUP BY category 
    ORDER BY total DESC 
    LIMIT 1
");
$stmt->execute([$company_id]);
$top_category = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("
    SELECT SUM(amount) as total 
    FROM expenses 
    WHERE company_id = ? AND expense_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
");
$stmt->execute([$company_id]);
$recent_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get category breakdown by currency
$stmt = $conn->prepare("
    SELECT category, COALESCE(currency, 'USD') as currency, COUNT(*) as count, SUM(amount) as total 
    FROM expenses 
    WHERE company_id = ? 
    GROUP BY category, COALESCE(currency, 'USD') 
    ORDER BY total DESC
");
$stmt->execute([getCurrentCompanyId()]);
$category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals by currency for percentage calculation
$stmt = $conn->prepare("SELECT COALESCE(currency, 'USD') as currency, SUM(amount) as total FROM expenses WHERE company_id = ? GROUP BY COALESCE(currency, 'USD')");
$stmt->execute([getCurrentCompanyId()]);
$totals_by_currency_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $totals_by_currency_map[$row['currency']] = $row['total'];
}

// Get expense categories for filter
$stmt = $conn->prepare("SELECT DISTINCT category FROM expenses WHERE company_id = ? ORDER BY category");
$stmt->execute([getCurrentCompanyId()]);
$expense_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('expense_management'); ?></h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_expense'); ?>
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
                                <?php echo __('total_expenses'); ?></div>
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
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?php echo __('monthly_expenses'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $stmt = $conn->prepare("SELECT COALESCE(currency, 'USD') as currency, SUM(amount) as total FROM expenses WHERE company_id = ? AND expense_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY) GROUP BY COALESCE(currency, 'USD') ORDER BY total DESC");
                                $stmt->execute([$company_id]);
                                $monthly_expenses_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if (!empty($monthly_expenses_by_currency)) {
                                    foreach ($monthly_expenses_by_currency as $idx => $row) {
                                        echo ($idx > 0 ? '<div class="small">' : '<div>') . formatCurrencyAmount($row['total'], $row['currency']) . '</div>';
                                    }
                                } else {
                                    echo '$0.00';
                                }
                                ?>
                            </div>
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
                                <?php echo __('top_category'); ?></div>
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
                                <?php echo __('recent_7_days'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $stmt = $conn->prepare("SELECT COALESCE(currency, 'USD') as currency, SUM(amount) as total FROM expenses WHERE company_id = ? AND expense_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY) GROUP BY COALESCE(currency, 'USD') ORDER BY total DESC");
                                $stmt->execute([$company_id]);
                                $recent_expenses_by_currency = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if (!empty($recent_expenses_by_currency)) {
                                    foreach ($recent_expenses_by_currency as $idx => $row) {
                                        echo ($idx > 0 ? '<div class="small">' : '<div>') . formatCurrencyAmount($row['total'], $row['currency']) . '</div>';
                                    }
                                } else {
                                    echo '$0.00';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                           placeholder="<?php echo __('search_by_description_code_reference'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="category">
                        <option value=""><?php echo __('all_categories'); ?></option>
                        <?php foreach ($expense_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                    <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($category)); ?>
                            </option>
                        <?php endforeach; ?>
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
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-times"></i> <?php echo __('clear'); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expense_list'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($expenses)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_expenses_found'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_expense'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('expense_code'); ?></th>
                                <th><?php echo __('description'); ?></th>
                                <th><?php echo __('category'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('payment_method'); ?></th>
                                <th><?php echo __('reference'); ?></th>
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
                                        <div>
                                            <strong><?php echo htmlspecialchars($expense['description']); ?></strong>
                                            <?php if (!empty($expense['notes'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($expense['notes']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $expense['category'] === 'fuel' ? 'bg-primary' : 
                                                ($expense['category'] === 'maintenance' ? 'bg-warning' : 
                                                ($expense['category'] === 'salary' ? 'bg-success' : 
                                                ($expense['category'] === 'rent' ? 'bg-info' : 'bg-secondary'))); 
                                        ?>">
                                            <?php echo ucfirst($expense['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                                            <div>
                        <strong class="text-danger"><?php echo formatCurrencyAmount($expense['amount'], $expense['currency'] ?? 'USD'); ?></strong>
                    </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo formatDate($expense['expense_date']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="badge <?php 
                                                echo $expense['payment_method'] === 'credit_card' ? 'bg-primary' : 'bg-success'; 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small class="text-muted"><?php echo htmlspecialchars($expense['reference_number']); ?></small>
                                        </div>
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
                                            <a href="delete.php?id=<?php echo $expense['id']; ?>" 
                                               class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirmDelete('<?php echo __('confirm_delete_expense'); ?>')">
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
                                        <?php echo __('previous'); ?>
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

    <!-- Category Breakdown -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('category_breakdown'); ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th><?php echo __('category'); ?></th>
                            <th><?php echo __('count'); ?></th>
                            <th><?php echo __('total_amount'); ?></th>
                            <th><?php echo __('percentage'); ?></th>
                            <th><?php echo __('progress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_breakdown as $category): ?>
                            <tr>
                                <td>
                                    <span class="badge <?php 
                                        echo $category['category'] === 'fuel' ? 'bg-primary' : 
                                            ($category['category'] === 'maintenance' ? 'bg-warning' : 
                                            ($category['category'] === 'salary' ? 'bg-success' : 
                                            ($category['category'] === 'rent' ? 'bg-info' : 'bg-secondary'))); 
                                    ?>">
                                        <?php echo ucfirst($category['category']); ?>
                                    </span>
                                </td>
                                <td><?php echo $category['count']; ?></td>
                                <td><strong><?php echo formatCurrencyAmount($category['total'], $category['currency'] ?? 'USD'); ?></strong> <small class="text-muted ms-1"><?php echo htmlspecialchars($category['currency'] ?? 'USD'); ?></small></td>
                                <td>
                                    <?php 
                                    $currency_code = $category['currency'] ?? 'USD';
                                    $total_amount_for_currency = $totals_by_currency_map[$currency_code] ?? 0;
                                    $percentage = $total_amount_for_currency > 0 ? ($category['total'] / $total_amount_for_currency) * 100 : 0;
                                    echo round($percentage, 1) . '%';
                                    ?>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar <?php 
                                            echo $category['category'] === 'fuel' ? 'bg-primary' : 
                                                ($category['category'] === 'maintenance' ? 'bg-warning' : 
                                                ($category['category'] === 'salary' ? 'bg-success' : 
                                                ($category['category'] === 'rent' ? 'bg-info' : 'bg-secondary'))); 
                                        ?>" 
                                        style="width: <?php echo $percentage; ?>%">
                                            <?php echo round($percentage, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(message) {
    return confirm(message);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>