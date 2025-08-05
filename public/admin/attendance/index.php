<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $attendance_id = (int)$_GET['delete'];
    
    try {
        // Delete attendance record
        $stmt = $conn->prepare("DELETE FROM employee_attendance WHERE id = ? AND company_id = ?");
        $stmt->execute([$attendance_id, $company_id]);
        
        $success = __('attendance_record_deleted_successfully');
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$employee_filter = $_GET['employee'] ?? '';
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ["ea.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(e.name LIKE ? OR e.employee_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($employee_filter)) {
    $where_conditions[] = "ea.employee_id = ?";
    $params[] = $employee_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "ea.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(ea.date) = ?";
    $params[] = $date_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM employee_attendance ea
    LEFT JOIN employees e ON ea.employee_id = e.id
    WHERE $where_clause
");
$count_stmt->execute($params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Get attendance records with pagination
$stmt = $conn->prepare("
    SELECT ea.*, e.name, e.employee_code, e.position
    FROM employee_attendance ea
    LEFT JOIN employees e ON ea.employee_id = e.id
    WHERE $where_clause
    ORDER BY ea.date DESC, ea.check_in_time DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_records,
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
        COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_count
    FROM employee_attendance
    WHERE company_id = ?
");
$stats_stmt->execute([$company_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get employee list for filter
$stmt = $conn->prepare("SELECT id, name, employee_code FROM employees WHERE company_id = ? ORDER BY name");
$stmt->execute([$company_id]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-clock"></i> <?php echo __('employee_attendance'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_attendance'); ?>
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
                                <?php echo __('total_records'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_records']; ?></div>
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
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?php echo __('present'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['present_count']; ?></div>
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
                                <?php echo __('late'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['late_count']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
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
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                <?php echo __('absent'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['absent_count']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                    <label for="date" class="form-label"><?php echo __('date'); ?></label>
                    <input type="date" class="form-control" id="date" name="date" 
                           value="<?php echo htmlspecialchars($date_filter); ?>">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-control" id="status" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="present" <?php echo $status_filter === 'present' ? 'selected' : ''; ?>><?php echo __('present'); ?></option>
                        <option value="absent" <?php echo $status_filter === 'absent' ? 'selected' : ''; ?>><?php echo __('absent'); ?></option>
                        <option value="late" <?php echo $status_filter === 'late' ? 'selected' : ''; ?>><?php echo __('late'); ?></option>
                        <option value="leave" <?php echo $status_filter === 'leave' ? 'selected' : ''; ?>><?php echo __('leave'); ?></option>
                    </select>
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

    <!-- Attendance Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('attendance_records'); ?></h6>
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
            <?php if (empty($attendance_records)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clock fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-500"><?php echo __('no_attendance_records_found'); ?></h5>
                    <p class="text-gray-400"><?php echo __('add_first_attendance_record_to_get_started'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_attendance'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" id="attendanceTable">
                        <thead>
                            <tr>
                                <th><?php echo __('employee'); ?></th>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('check_in'); ?></th>
                                <th><?php echo __('check_out'); ?></th>
                                <th><?php echo __('hours'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('notes'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <?php 
                                            $name_parts = explode(' ', $record['name']);
                                            echo strtoupper(substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : ''));
                                            ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($record['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['employee_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($record['date'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo $record['check_in_time'] ? date('H:i', strtotime($record['check_in_time'])) : 'N/A'; ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo $record['check_out_time'] ? date('H:i', strtotime($record['check_out_time'])) : 'N/A'; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="text-center">
                                        <h6 class="mb-0"><?php echo $record['hours_worked']; ?></h6>
                                        <small class="text-muted"><?php echo __('hours'); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $record['status'] === 'present' ? 'success' : ($record['status'] === 'late' ? 'warning' : ($record['status'] === 'leave' ? 'info' : 'secondary')); ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($record['notes'] ?? '', 0, 50)); ?>
                                        <?php if (strlen($record['notes'] ?? '') > 50): ?>...<?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $record['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $record['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?php echo $record['id']; ?>, '<?php echo htmlspecialchars($record['name']); ?>')"
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
                <nav aria-label="Attendance pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&employee=<?php echo urlencode($employee_filter); ?>&date=<?php echo urlencode($date_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                                                            <?php echo __('previous'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&employee=<?php echo urlencode($employee_filter); ?>&date=<?php echo urlencode($date_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&employee=<?php echo urlencode($employee_filter); ?>&date=<?php echo urlencode($date_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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
        $('#attendanceTable').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[1, 'desc']],
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
function confirmDelete(attendanceId, employeeName) {
    if (confirm(`<?php echo __('confirm_delete_attendance_record'); ?> "${employeeName}"? <?php echo __('this_action_cannot_be_undone'); ?>`)) {
        window.location.href = `index.php?delete=${attendanceId}`;
    }
}

// Export functions
function exportToCSV() {
    const table = document.getElementById('attendanceTable');
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
    a.download = 'attendance_records.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

function exportToPDF() {
    alert('<?php echo __('pdf_export_feature_coming_soon'); ?>');
}
</script>

<?php require_once '../../includes/footer.php'; ?>