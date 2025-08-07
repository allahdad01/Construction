<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

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
    $payment_id = (int)$_GET['delete'];
    
    try {
        // Check if payment exists and belongs to company
        $stmt = $conn->prepare("
            SELECT sp.payment_code, e.name as employee_name 
            FROM salary_payments sp 
            LEFT JOIN employees e ON sp.employee_id = e.id 
            WHERE sp.id = ? AND sp.company_id = ?
        ");
        $stmt->execute([$payment_id, $company_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payment) {
            throw new Exception("Payment record not found or you don't have permission to delete it.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete salary payment
        $stmt = $conn->prepare("DELETE FROM salary_payments WHERE id = ? AND company_id = ?");
        $stmt->execute([$payment_id, $company_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No records were deleted. The payment may have already been removed.");
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = "Salary payment '{$payment['payment_code']}' for {$payment['employee_name']} deleted successfully!";
        
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
$employee_filter = $_GET['employee'] ?? '';
$month_filter = $_GET['month'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["sp.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(e.name LIKE ? OR e.employee_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($employee_filter)) {
    $where_conditions[] = "sp.employee_id = ?";
    $params[] = $employee_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sp.status = ?";
    $params[] = $status_filter;
}

if (!empty($month_filter)) {
    $where_conditions[] = "DATE_FORMAT(sp.payment_date, '%Y-%m') = ?";
    $params[] = $month_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM salary_payments sp
    LEFT JOIN employees e ON sp.employee_id = e.id
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get salary payments with pagination
$stmt = $conn->prepare("
    SELECT sp.*, e.name, e.employee_code, e.position,
           e.monthly_salary, e.daily_rate
    FROM salary_payments sp
    LEFT JOIN employees e ON sp.employee_id = e.id
    WHERE $where_clause
    ORDER BY sp.payment_date DESC 
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$salary_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as paid_payments,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
        SUM(amount_paid) as total_amount,
        AVG(amount_paid) as avg_amount
    FROM salary_payments
    WHERE company_id = ?
");
$stats_stmt->execute([$company_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get currency-specific totals
$currency_stmt = $conn->prepare("
    SELECT 
        COALESCE(currency, 'USD') as currency,
        SUM(amount_paid) as total,
        COUNT(*) as count
    FROM salary_payments
    WHERE company_id = ?
    GROUP BY COALESCE(currency, 'USD')
    ORDER BY total DESC
");
$currency_stmt->execute([$company_id]);
$currency_totals = $currency_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get employee list for filter
$stmt = $conn->prepare("SELECT id, name, employee_code FROM employees WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-money-bill-wave"></i> <?php echo __('salary_payments'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_payment'); ?>
        </a>
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
                                <?php echo __('total_payments'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_payments']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                                <?php echo __('paid'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['paid_payments']; ?></div>
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
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                <?php echo __('pending'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending_payments']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                <?php echo __('total_amount'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php if (count($currency_totals) > 1): ?>
                                    <?php foreach ($currency_totals as $index => $currency_total): ?>
                                        <div class="<?php echo $index > 0 ? 'small' : ''; ?>">
                                            <?php echo $currency_total['currency']; ?>: <?php echo number_format($currency_total['total'], 2); ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif (count($currency_totals) == 1): ?>
                                    <?php echo $currency_totals[0]['currency']; ?>: <?php echo number_format($currency_totals[0]['total'], 2); ?>
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

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('filters'); ?></h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label"><?php echo __('search'); ?></label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo __('search_by_employee_name_or_code'); ?>">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" id="status" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>><?php echo __('paid'); ?></option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="employee" class="form-label"><?php echo __('employee'); ?></label>
                    <select class="form-control" id="employee" name="employee">
                        <option value=""><?php echo __('all_employees'); ?></option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['id']; ?>" <?php echo $employee_filter == $employee['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="month" class="form-label"><?php echo __('month'); ?></label>
                    <input type="month" class="form-control" id="month" name="month" 
                           value="<?php echo htmlspecialchars($month_filter); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i> <?php echo __('search'); ?>
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?php echo __('clear'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Salary Payments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('salary_payments_list'); ?></h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                    <div class="dropdown-header"><?php echo __('export_options'); ?>:</div>
                    <a class="dropdown-item" href="#" onclick="exportToCSV()">
                        <i class="fas fa-file-csv me-2"></i><?php echo __('export_to_csv'); ?>
                    </a>
                    <a class="dropdown-item" href="#" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i><?php echo __('export_to_pdf'); ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($salary_payments)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-money-bill-wave fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_salary_payments_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_salary_payment_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_payment'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="paymentsTable">
                        <thead>
                            <tr>
                                <th><?php echo __('employee'); ?></th>
                                <th><?php echo __('payment_date'); ?></th>
                                <th><?php echo __('period'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('payment_method'); ?></th>
                                <th><?php echo __('notes'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salary_payments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <?php 
                                            $name_parts = explode(' ', $payment['name']);
                                            echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                            ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($payment['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($payment['employee_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        if ($payment['payment_month'] && $payment['payment_year']) {
                                            echo date('M Y', mktime(0, 0, 0, $payment['payment_month'], 1, $payment['payment_year']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="text-success mb-0">
                                            <?php 
                                            $currency = $payment['currency'] ?? 'USD';
                                            echo $currency . ' ' . number_format($payment['amount_paid'] ?? 0, 2); 
                                            ?>
                                        </h6>
                                        <small class="text-muted"><?php echo ($payment['working_days'] ?? 0); ?> <?php echo __('days'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $payment['status'] === 'completed' ? 'success' : ($payment['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($payment['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo ucfirst($payment['payment_method'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($payment['notes'] ?? '', 0, 50)); ?>
                                        <?php if (strlen($payment['notes'] ?? '') > 50): ?>...<?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $payment['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $payment['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Salary payments pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&employee=<?php echo urlencode($employee_filter); ?>&month=<?php echo urlencode($month_filter); ?>">
                                                                            <?php echo __('previous'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&employee=<?php echo urlencode($employee_filter); ?>&month=<?php echo urlencode($month_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&employee=<?php echo urlencode($employee_filter); ?>&month=<?php echo urlencode($month_filter); ?>">
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
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with proper destroy handling
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        const table = $('#paymentsTable');
        if (table.length > 0) {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable('#paymentsTable')) {
                table.DataTable().destroy();
            }
            
            // Initialize fresh DataTable
            table.DataTable({
                responsive: true,
                pageLength: 10,
                order: [[1, 'desc']],
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                        searchable: false
                    }
                ],
                destroy: true
            });
        }
    }
});

// Confirm delete function
function confirmDelete(paymentId, employeeName) {
    if (confirm(`<?php echo __('confirm_delete_salary_payment'); ?> "${employeeName}"? <?php echo __('this_action_cannot_be_undone'); ?>`)) {
        window.location.href = `index.php?delete=${paymentId}`;
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('paymentsTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => {
            let text = col.textContent.trim();
            text = text.replace(/"/g, '""');
            return `"${text}"`;
        });
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'salary_payments.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('<?php echo __('pdf_export_feature_coming_soon'); ?>');
}
</script>

<?php require_once '../../../includes/footer.php'; ?>