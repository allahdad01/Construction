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

// Handle machine deletion
if ($_GET['delete'] ?? false) {
    $machine_id = (int)$_GET['delete'];
    
    try {
        // Check if machine exists and belongs to company
        $stmt = $conn->prepare("SELECT machine_code, name FROM machines WHERE id = ? AND company_id = ?");
        $stmt->execute([$machine_id, $company_id]);
        $machine = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$machine) {
            throw new Exception("Machine not found.");
        }
        
        // Check for related records
        $related_records = [];
        
        // Check contracts
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE machine_id = ?");
        $stmt->execute([$machine_id]);
        $contract_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($contract_count > 0) {
            $related_records[] = "$contract_count contract(s)";
        }
        
        if (!empty($related_records)) {
            throw new Exception("Cannot delete machine '{$machine['name']}' because it has related records: " . implode(', ', $related_records) . ". Please remove these records first.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete machine
        $stmt = $conn->prepare("DELETE FROM machines WHERE id = ? AND company_id = ?");
        $stmt->execute([$machine_id, $company_id]);
        
        // Commit transaction
        $conn->commit();
        
        $success = "Machine '{$machine['name']}' deleted successfully!";
        
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
$type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = ['company_id = ?'];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR machine_code LIKE ? OR model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM machines $where_clause";
$stmt = $conn->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $limit);

// Get machines
$sql = "SELECT * FROM machines $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ?");
$stmt->execute([$company_id]);
$total_machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ? AND status = 'available'");
$stmt->execute([$company_id]);
$available_machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ? AND status = 'in_use'");
$stmt->execute([$company_id]);
$in_use_machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM machines WHERE company_id = ? AND status = 'maintenance'");
$stmt->execute([$company_id]);
$maintenance_machines = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get total value by currency
$stmt = $conn->prepare("
    SELECT 
        COALESCE(purchase_currency, 'USD') as currency,
        SUM(purchase_cost) as total 
    FROM machines 
    WHERE company_id = ? AND status != 'retired' AND purchase_cost > 0
    GROUP BY COALESCE(purchase_currency, 'USD')
    ORDER BY total DESC
");
$stmt->execute([$company_id]);
$currency_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall total in USD (for backward compatibility)
$total_value = 0;
foreach ($currency_totals as $currency_total) {
    $total_value += $currency_total['total']; // This is simplified - you may want currency conversion
}

// Get machine types for filter
$stmt = $conn->prepare("SELECT DISTINCT type FROM machines WHERE company_id = ? ORDER BY type");
$stmt->execute([$company_id]);
$machine_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?php echo __('machine_management'); ?></h1>
        <a href="add.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_machine'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_machines'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_machines; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
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
                                <?php echo __('available'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $available_machines; ?></div>
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
                                <?php echo __('in_use'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $in_use_machines; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-cogs fa-2x text-gray-300"></i>
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
                                <?php echo __('total_value'); ?></div>
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

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('search_filter'); ?></h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="<?php echo __('search_by_name_code_model'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="type">
                        <option value=""><?php echo __('all_types'); ?></option>
                        <?php foreach ($machine_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-control" name="status">
                        <option value=""><?php echo __('all_status'); ?></option>
                        <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>><?php echo __('available'); ?></option>
                        <option value="in_use" <?php echo $status_filter === 'in_use' ? 'selected' : ''; ?>><?php echo __('in_use'); ?></option>
                        <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>><?php echo __('maintenance'); ?></option>
                        <option value="retired" <?php echo $status_filter === 'retired' ? 'selected' : ''; ?>><?php echo __('retired'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> <?php echo __('search'); ?>
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

    <!-- Machines Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_inventory'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($machines)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-truck fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500"><?php echo __('no_machines_found'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_first_machine'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th><?php echo __('machine_code'); ?></th>
                                <th><?php echo __('name_model'); ?></th>
                                <th><?php echo __('type'); ?></th>
                                <th><?php echo __('specifications'); ?></th>
                                <th><?php echo __('value'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $machine): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($machine['machine_code']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($machine['name']); ?></strong>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars($machine['model']); ?>
                                                <?php if ($machine['year_manufactured']): ?>
                                                    (<?php echo $machine['year_manufactured']; ?>)
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($machine['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if ($machine['capacity']): ?>
                                                <strong><?php echo __('capacity'); ?>:</strong> <?php echo htmlspecialchars($machine['capacity']); ?><br>
                                            <?php endif; ?>
                                            <?php if ($machine['fuel_type']): ?>
                                                <small class="text-muted">
                                                    <?php echo __('fuel'); ?>: <?php echo ucfirst($machine['fuel_type']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo formatCurrency($machine['purchase_cost'] ?? 0); ?></strong>
                                            <?php if ($machine['purchase_date']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo __('purchased'); ?>: <?php echo date('M Y', strtotime($machine['purchase_date'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php 
                                            echo $machine['status'] === 'available' ? 'bg-success' : 
                                                ($machine['status'] === 'in_use' ? 'bg-info' : 
                                                ($machine['status'] === 'maintenance' ? 'bg-warning' : 'bg-secondary')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $machine['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $machine['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $machine['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../contracts/index.php?machine_id=<?php echo $machine['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Contracts">
                                                <i class="fas fa-file-contract"></i>
                                            </a>
                                            <a href="../expenses/index.php?category=maintenance&search=<?php echo urlencode($machine['machine_code']); ?>" 
                                               class="btn btn-sm btn-primary" title="Maintenance">
                                                <i class="fas fa-tools"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $machine['id']; ?>, '<?php echo htmlspecialchars($machine['name']); ?>')"
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
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo __('previous'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
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

    <!-- Quick Actions and Statistics -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="add.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus"></i> <?php echo __('add_new_machine'); ?>
                        </a>
                        <a href="contracts/" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-contract"></i> <?php echo __('manage_contracts'); ?>
                        </a>
                        <a href="maintenance/" class="list-group-item list-group-item-action">
                            <i class="fas fa-tools"></i> <?php echo __('maintenance_schedule'); ?>
                        </a>
                        <a href="reports/" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar"></i> <?php echo __('machine_reports'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <h6><?php echo __('status_breakdown'); ?></h6>
                            <p><strong><?php echo __('available'); ?>:</strong> <?php echo $available_machines; ?></p>
                            <p><strong><?php echo __('in_use'); ?>:</strong> <?php echo $in_use_machines; ?></p>
                            <p><strong><?php echo __('maintenance'); ?>:</strong> <?php echo $maintenance_machines; ?></p>
                        </div>
                        <div class="col-6">
                            <h6><?php echo __('value_overview'); ?></h6>
                            <p><strong><?php echo __('total_value'); ?>:</strong> <?php echo formatCurrency($total_value); ?></p>
                            <p><strong><?php echo __('average_value'); ?>:</strong> 
                                <?php echo $total_machines > 0 ? formatCurrency($total_value / $total_machines) : '$0.00'; ?>
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6><?php echo __('utilization_rate'); ?></h6>
                        <h4 class="text-info">
                            <?php echo $total_machines > 0 ? round(($in_use_machines / $total_machines) * 100, 1) : 0; ?>%
                        </h4>
                        <small class="text-muted"><?php echo __('machines_currently_in_use'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(machineId, machineName) {
    const message = `Are you sure you want to delete machine "${machineName}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${machineId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>