<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    try {
        // Check if user is trying to delete themselves
        if ($user_id == getCurrentUser()['id']) {
            throw new Exception(__('cannot_delete_own_account'));
        }
        
        // Check if user exists and belongs to current company
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND company_id = ?");
        $stmt->execute([$user_id, $company_id]);
        $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_to_delete) {
            throw new Exception(__('user_not_found_or_access_denied'));
        }
        
        // Check for related records that would prevent deletion
        $related_checks = [];
        
        // Check if user has associated employee record
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM employees WHERE user_id = ? AND company_id = ?");
        $stmt->execute([$user_id, $company_id]);
        $related_checks['employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Check parking rentals
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_rentals WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $related_checks['parking_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Check area rentals
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM area_rentals WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $related_checks['area_rentals'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Build error message for related records
        $blocking_records = [];
        if ($related_checks['employees'] > 0) {
            $blocking_records[] = $related_checks['employees'] . ' employee record(s)';
        }
        if ($related_checks['parking_rentals'] > 0) {
            $blocking_records[] = $related_checks['parking_rentals'] . ' parking rental(s)';
        }
        if ($related_checks['area_rentals'] > 0) {
            $blocking_records[] = $related_checks['area_rentals'] . ' area rental(s)';
        }
        
        if (!empty($blocking_records)) {
            throw new Exception("Cannot delete user. They have the following related records: " . implode(', ', $blocking_records) . ". Please remove these records first.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
        $stmt->execute([$user_id, $company_id]);
        
        // Commit transaction
        $conn->commit();
        
        $success = "User '{$user_to_delete['first_name']} {$user_to_delete['last_name']}' deleted successfully!";
        
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
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["u.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM users u
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get users with pagination
$stmt = $conn->prepare("
    SELECT u.*, 
           e.employee_code, e.position
    FROM users u
    LEFT JOIN employees e ON u.id = e.user_id AND e.company_id = u.company_id
    WHERE $where_clause
    ORDER BY u.created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_users,
        COUNT(CASE WHEN role = 'company_admin' THEN 1 END) as admin_users,
        COUNT(CASE WHEN role = 'driver' THEN 1 END) as driver_users,
        COUNT(CASE WHEN role = 'driver_assistant' THEN 1 END) as assistant_users
    FROM users
    WHERE company_id = ?
");
$stats_stmt->execute([$company_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users"></i> <?php echo __('user_management'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_user'); ?>
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
                                <?php echo __('total_users'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                <?php echo __('active_users'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_users']; ?></div>
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
                                <?php echo __('admins'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['admin_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-shield fa-2x text-gray-300"></i>
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
                                <?php echo __('drivers'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['driver_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                           placeholder="<?php echo __('search_by_name_or_email'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="role" class="form-label"><?php echo __('role'); ?></label>
                    <select class="form-control" id="role" name="role">
                        <option value=""><?php echo __('all_roles'); ?></option>
                        <option value="company_admin" <?php echo $role_filter === 'company_admin' ? 'selected' : ''; ?>><?php echo __('company_admin'); ?></option>
                        <option value="driver" <?php echo $role_filter === 'driver' ? 'selected' : ''; ?>><?php echo __('driver'); ?></option>
                        <option value="driver_assistant" <?php echo $role_filter === 'driver_assistant' ? 'selected' : ''; ?>><?php echo __('driver_assistant'); ?></option>
                        <option value="parking_user" <?php echo $role_filter === 'parking_user' ? 'selected' : ''; ?>><?php echo __('parking_user'); ?></option>
                        <option value="area_renter" <?php echo $role_filter === 'area_renter' ? 'selected' : ''; ?>><?php echo __('area_renter'); ?></option>
                        <option value="container_renter" <?php echo $role_filter === 'container_renter' ? 'selected' : ''; ?>><?php echo __('container_renter'); ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" id="status" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>><?php echo __('inactive'); ?></option>
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

    <!-- Users Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('users_list'); ?></h6>
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
            <?php if (empty($users)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_users_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_user_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_user'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" id="usersTable">
                        <thead>
                            <tr>
                                <th><?php echo __('user'); ?></th>
                                <th><?php echo __('email'); ?></th>
                                <th><?php echo __('role'); ?></th>
                                <th><?php echo __('employee_info'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('last_login'); ?></th>
                                <th><?php echo __('created'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <span class="text-white font-weight-bold">
                                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                                            <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role'] === 'company_admin' ? 'danger' : ($user['role'] === 'driver' ? 'primary' : 'info'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['employee_code']): ?>
                                        <small class="text-muted">
                                            <strong>Code:</strong> <?php echo htmlspecialchars($user['employee_code']); ?><br>
                                            <strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $user['employee_type'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted"><?php echo __('no_employee_record'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : __('never'); ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != getCurrentUser()['id']): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                data-user-id="<?php echo $user['id']; ?>"
                                                data-user-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                onclick="confirmDelete(this.dataset.userId, this.dataset.userName)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                <nav aria-label="Users pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                                            <?php echo __('previous'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
        // Check if DataTable is already initialized
        if (!$.fn.DataTable.isDataTable('#usersTable')) {
            $('#usersTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[6, 'desc']], // Column 6 is 'created' (0-indexed)
                columnDefs: [
                    {
                        targets: -1, // Last column (actions)
                        orderable: false,
                        searchable: false
                    }
                ],
                destroy: true // Allow reinitializing if needed
            });
        }
    }
});

// Confirm delete function
function confirmDelete(userId, userName) {
    const message = `Are you sure you want to delete user "${userName}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${userId}`;
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('usersTable');
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
    a.download = 'users.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('<?php echo __('pdf_export_feature_coming_soon'); ?>');
}
</script>

<?php require_once '../../includes/footer.php'; ?>