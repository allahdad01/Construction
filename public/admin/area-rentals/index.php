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
    $rental_id = (int)$_GET['delete'];
    
    try {
        // Get rental details first to update the area status later
        $stmt = $conn->prepare("SELECT rental_area_id, status FROM area_rentals WHERE id = ? AND company_id = ?");
        $stmt->execute([$rental_id, $company_id]);
        $rental = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rental) {
            throw new Exception(__('area_rental_not_found'));
        }
        
        // Check if rental has active contracts (if contracts table references area_rentals)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE area_rental_id = ? AND status = 'active'");
        $stmt->execute([$rental_id]);
        $active_contracts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($active_contracts > 0) {
            throw new Exception(__('cannot_delete_rental_has_active_contracts', ['count' => $active_contracts]));
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
        
        $success = __('area_rental_deleted_successfully');
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
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["ar.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(ar.rental_code LIKE ? OR ar.client_name LIKE ? OR ra.area_name LIKE ? OR ra.area_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "ar.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "ra.area_type = ?";
    $params[] = $type_filter;
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

// Get area rentals with pagination
$area_rentals_query = "
    SELECT ar.*, 
           ra.area_name,
           ra.area_code,
           ra.area_type,
           ra.size,
           CASE 
               WHEN ar.end_date IS NULL THEN 'Ongoing'
               WHEN ar.end_date > CURDATE() THEN 'Active'
               ELSE 'Expired'
           END as rental_status
    FROM area_rentals ar 
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    WHERE $where_clause
    ORDER BY ar.created_at DESC 
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($area_rentals_query);
$params_with_limits = array_merge($params, [$per_page, $offset]);
$stmt->execute($params_with_limits);
$area_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_rentals,
        COUNT(CASE WHEN ar.status = 'active' THEN 1 END) as active_rentals,
        COUNT(CASE WHEN ar.status = 'completed' THEN 1 END) as completed_rentals,
        AVG(ar.daily_rate) as avg_rate,
        SUM(ar.total_amount) as total_revenue
    FROM area_rentals ar
    WHERE ar.company_id = ?
");
$stats_stmt->execute([$company_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-map-marker-alt"></i> <?php echo __('area_rentals'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_area_rental'); ?>
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
                                <?php echo __('total_rentals'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_rentals']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
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
                                <?php echo __('available'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['available_rentals']; ?></div>
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
                                <?php echo __('rented'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['rented_rentals']; ?></div>
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
                                <?php echo __('total_value'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">$
    <?php echo number_format((float)($stats['total_value'] ?? 0), 2); ?>
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
                <div class="col-md-4">
                    <label for="search" class="form-label"><?php echo __('search'); ?></label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="<?php echo __('search_by_name_code_or_location'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" id="status" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>><?php echo __('available'); ?></option>
                        <option value="rented" <?php echo $status_filter === 'rented' ? 'selected' : ''; ?>><?php echo __('rented'); ?></option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>><?php echo __('maintenance'); ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label"><?php echo __('type'); ?></label>
                    <select class="form-control" id="type" name="type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <option value="warehouse" <?php echo $type_filter === 'warehouse' ? 'selected' : ''; ?>><?php echo __('warehouse'); ?></option>
                        <option value="office" <?php echo $type_filter === 'office' ? 'selected' : ''; ?>><?php echo __('office'); ?></option>
                        <option value="parking" <?php echo $type_filter === 'parking' ? 'selected' : ''; ?>><?php echo __('parking'); ?></option>
                        <option value="land" <?php echo $type_filter === 'land' ? 'selected' : ''; ?>><?php echo __('land'); ?></option>
                        <option value="other" <?php echo $type_filter === 'other' ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
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

    <!-- Area Rentals Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('area_rentals_list'); ?></h6>
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
            <?php if (empty($area_rentals)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-map-marker-alt fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_area_rentals_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_area_rental_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_area_rental'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" id="rentalsTable">
                        <thead>
                            <tr>
                                <th><?php echo __('rental_code'); ?></th>
                                <th><?php echo __('client_name'); ?></th>
                                <th><?php echo __('area'); ?></th>
                                <th><?php echo __('duration'); ?></th>
                                <th><?php echo __('daily_rate'); ?></th>
                                <th><?php echo __('total_amount'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($area_rentals as $rental): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="fas fa-file-contract text-white"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($rental['rental_code']); ?></h6>
                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($rental['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($rental['client_name']); ?></h6>
                                        <?php if ($rental['client_contact']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($rental['client_contact']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($rental['area_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($rental['area_code']) . ' - ' . ucfirst($rental['area_type']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <small class="text-muted"><?php echo __('start'); ?>:</small> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?><br>
                                        <?php if ($rental['end_date']): ?>
                                        <small class="text-muted"><?php echo __('end'); ?>:</small> <?php echo date('M j, Y', strtotime($rental['end_date'])); ?>
                                        <?php else: ?>
                                        <small class="text-muted"><?php echo __('ongoing'); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="text-success mb-0">$<?php echo number_format($rental['daily_rate'], 2); ?></h6>
                                        <small class="text-muted"><?php echo __('per_day'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="text-primary mb-0">$<?php echo number_format($rental['total_amount'] ?? 0, 2); ?></h6>
                                        <?php if ($rental['amount_paid'] > 0): ?>
                                        <small class="text-success">$<?php echo number_format($rental['amount_paid'], 2); ?> <?php echo __('paid'); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $rental['status'] === 'active' ? 'success' : ($rental['status'] === 'completed' ? 'primary' : 'secondary'); ?>">
                                        <?php echo ucfirst($rental['status']); ?>
                                    </span><br>
                                    <small class="badge bg-light text-dark"><?php echo $rental['rental_status']; ?></small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $rental['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $rental['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $rental['id']; ?>, '<?php echo htmlspecialchars($rental['rental_code']); ?>')"
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
                <nav aria-label="Area rentals pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                                                            <?php echo __('previous'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>">
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
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#rentalsTable').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[0, 'asc']],
            columnDefs: [
                {
                    targets: -1,
                    orderable: false,
                    searchable: false
                }
            ]
        });
    }
});

// Confirm delete function
function confirmDelete(rentalId, rentalName) {
    if (confirm(`<?php echo __('confirm_delete_area_rental'); ?> "${rentalName}"? <?php echo __('this_action_cannot_be_undone'); ?>`)) {
        window.location.href = `index.php?delete=${rentalId}`;
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('rentalsTable');
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
    a.download = 'area_rentals.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('<?php echo __('pdf_export_feature_coming_soon'); ?>');
}
</script>

<?php require_once '../../../includes/footer.php'; ?>