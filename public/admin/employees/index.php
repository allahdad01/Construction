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

// Debug: Check URL parameters
error_log("Current URL parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $employee_id = (int)$_GET['delete'];
    
    // Debug: Log delete request
    error_log("Delete request received for employee ID: " . $employee_id);
    error_log("GET parameters: " . print_r($_GET, true));
    
    try {
        error_log("Starting delete process for employee ID: " . $employee_id);
        error_log("Company ID: " . $company_id);
        
        // Check if employee exists and belongs to company
        $stmt = $conn->prepare("
            SELECT e.employee_code, e.name 
            FROM employees e 
            WHERE e.id = ? AND e.company_id = ?
        ");
        $stmt->execute([$employee_id, $company_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Employee found: " . ($employee ? "YES - " . print_r($employee, true) : "NO"));
        
        if (!$employee) {
            throw new Exception("Employee not found or you don't have permission to delete it.");
        }
        
        // Check for related records with more detailed information
        $related_checks = [
            'salary_payments' => "SELECT COUNT(*) FROM salary_payments WHERE employee_id = ?",
            'employee_attendance' => "SELECT COUNT(*) FROM employee_attendance WHERE employee_id = ?",
            'working_hours' => "SELECT COUNT(*) FROM working_hours WHERE employee_id = ?"
        ];
        
        $related_records = [];
        foreach ($related_checks as $table => $query) {
            $stmt = $conn->prepare($query);
            $stmt->execute([$employee_id]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $related_records[] = "$count $table record(s)";
            }
        }
        
        if (!empty($related_records)) {
            throw new Exception("Cannot delete employee '{$employee['employee_code']}' - {$employee['name']} because it has related records: " . implode(', ', $related_records) . ". Please remove these records first or contact system administrator.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete associated user account if exists
        $stmt = $conn->prepare("SELECT user_id FROM employees WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        $user_id = $stmt->fetchColumn();
        
        if ($user_id) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        
        // Delete employee
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ? AND company_id = ?");
        $stmt->execute([$employee_id, $company_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No records were deleted. The employee may have already been removed.");
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = "Employee '{$employee['employee_code']}' - {$employee['name']} deleted successfully!" . ($user_id ? " (Associated user account also removed)" : "");
        
        error_log("Delete successful: " . $success);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = "Delete failed: " . $e->getMessage();
        error_log("Delete failed: " . $e->getMessage());
        error_log("Exception details: " . print_r($e, true));
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["e.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(e.name LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($position_filter)) {
    $where_conditions[] = "e.position = ?";
    $params[] = $position_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM employees e
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get employees with pagination
$stmt = $conn->prepare("
    SELECT e.*, u.email as user_email, u.status as user_status
    FROM employees e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE $where_clause
    ORDER BY e.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT
        COUNT(*) as total_employees,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_employees,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_employees,
        COUNT(CASE WHEN position = 'driver' THEN 1 END) as drivers,
        COUNT(CASE WHEN position = 'driver_assistant' THEN 1 END) as assistants,
        AVG(monthly_salary) as avg_salary,
        SUM(monthly_salary) as total_salary
    FROM employees
    WHERE company_id = ?
");
$stats_stmt->execute([$company_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-users"></i> <?php echo __('employees'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> <?php echo __('add_employee'); ?>
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
                                <?php echo __('total_employees'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_employees']; ?></div>
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
                                <?php echo __('active_employees'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['active_employees']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                <?php echo __('drivers'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['drivers']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                                <?php echo __('assistants'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['assistants']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                           placeholder="<?php echo __('search_by_name_code_email'); ?>">
                </div>
                <div class="col-md-3">
                    <label for="position" class="form-label"><?php echo __('position'); ?></label>
                    <select class="form-control" id="position" name="position">
                        <option value=""><?php echo __('all_positions'); ?></option>
                        <option value="driver" <?php echo $position_filter === 'driver' ? 'selected' : ''; ?>><?php echo __('driver'); ?></option>
                        <option value="driver_assistant" <?php echo $position_filter === 'driver_assistant' ? 'selected' : ''; ?>><?php echo __('driver_assistant'); ?></option>
                        <option value="operator" <?php echo $position_filter === 'operator' ? 'selected' : ''; ?>><?php echo __('machine_operator'); ?></option>
                        <option value="supervisor" <?php echo $position_filter === 'supervisor' ? 'selected' : ''; ?>><?php echo __('supervisor'); ?></option>
                        <option value="technician" <?php echo $position_filter === 'technician' ? 'selected' : ''; ?>><?php echo __('technician'); ?></option>
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

    <!-- Employees Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('employees_list'); ?></h6>
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
            <?php if (empty($employees)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_employees_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_employee_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?php echo __('add_employee'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="employeesTable">
                        <thead>
                            <tr>
                                <th><?php echo __('employee_code'); ?></th>
                                <th><?php echo __('name'); ?></th>
                                <th><?php echo __('position'); ?></th>
                                <th><?php echo __('email'); ?></th>
                                <th><?php echo __('phone'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('created'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <?php 
                                            $name_parts = explode(' ', $employee['name']);
                                            echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                            ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($employee['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($employee['employee_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $employee['position'] === 'driver' ? 'info' : 'warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $employee['position'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($employee['user_email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($employee['user_email']); ?>">
                                            <?php echo htmlspecialchars($employee['user_email']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo __('no_email'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($employee['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($employee['phone']); ?>">
                                            <?php echo htmlspecialchars($employee['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo __('no_phone'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($employee['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($employee['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $employee['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $employee['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger delete-employee-btn" 
                                                data-employee-id="<?php echo $employee['id']; ?>"
                                                data-employee-name="<?php echo htmlspecialchars($employee['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Delete Employee">
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
                <nav aria-label="Employees pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                                            <?php echo __('previous'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo urlencode($position_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
    console.log('Page loaded, initializing employees index functionality');
    
    // Initialize delete button event listeners
    const deleteButtons = document.querySelectorAll('.delete-employee-btn');
    console.log(`Found ${deleteButtons.length} delete buttons`);
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const employeeId = this.dataset.employeeId;
            const employeeName = this.dataset.employeeName;
            
            console.log('Delete button clicked:', { employeeId, employeeName });
            confirmDelete(employeeId, employeeName);
        });
    });
    
    // Initialize DataTable with proper destroy handling
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        const table = $('#employeesTable');
        if (table.length > 0) {
            // Destroy existing DataTable if it exists
            if ($.fn.DataTable.isDataTable('#employeesTable')) {
                table.DataTable().destroy();
            }
            
            // Initialize fresh DataTable
            table.DataTable({
                responsive: true,
                pageLength: 10,
                order: [[1, 'asc']],
                columnDefs: [
                    {
                        targets: -1,
                        orderable: false,
                        searchable: false
                    }
                ],
                destroy: true
            });
            
            console.log('DataTable initialized successfully');
        }
    }
});

// Confirm delete function
function confirmDelete(employeeId, employeeName) {
    console.log('confirmDelete function called');
    console.log('Delete button clicked for employee:', employeeName, 'ID:', employeeId);
    console.log('Employee ID type:', typeof employeeId);
    console.log('Employee Name type:', typeof employeeName);
    
    const message = `Are you sure you want to delete employee "${employeeName}"?\n\nThis action cannot be undone and will:\n- Remove the employee record\n- Delete associated user account (if exists)\n- Require manual removal of related records (attendance, payments, etc.)\n\nContinue with deletion?`;
    
    console.log('About to show confirmation dialog');
    const userConfirmed = confirm(message);
    console.log('User confirmation result:', userConfirmed);
    
    if (userConfirmed) {
        const redirectUrl = `index.php?delete=${employeeId}`;
        console.log('User confirmed deletion, redirecting to:', redirectUrl);
        console.log('Current URL before redirect:', window.location.href);
        
        // Add a small delay to ensure console logs are visible
        setTimeout(() => {
            window.location.href = redirectUrl;
        }, 100);
    } else {
        console.log('User cancelled deletion');
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('employeesTable');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const rowData = Array.from(cols).map(col => {
            // Remove HTML tags and get text content
            let text = col.textContent.trim();
            // Escape quotes
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
    a.download = 'employees.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('<?php echo __('pdf_export_feature_coming_soon'); ?>');
}
</script>

<?php require_once '../../../includes/footer.php'; ?>