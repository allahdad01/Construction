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

// Get machine ID from URL
$machine_id = $_GET['id'] ?? null;

if (!$machine_id) {
    header('Location: index.php');
    exit;
}

// Get machine details
$stmt = $conn->prepare("
    SELECT * FROM machines 
    WHERE id = ? AND company_id = ?
");
$stmt->execute([$machine_id, $company_id]);
$machine = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$machine) {
    header('Location: index.php');
    exit;
}

// Calculate machine age
$machine_age = null;
if ($machine['year_manufactured']) {
    $machine_age = date('Y') - $machine['year_manufactured'];
}

// Get usage statistics (from contracts table)
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_contracts,
           SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_contracts
    FROM contracts 
    WHERE machine_id = ? AND company_id = ?
");
$stmt->execute([$machine_id, $company_id]);
$contract_stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-truck"></i> <?php echo __('machine_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $machine_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_machine'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_machines'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Machine Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('machine_code'); ?>:</strong> <?php echo htmlspecialchars($machine['machine_code']); ?></p>
                            <p><strong><?php echo __('name'); ?>:</strong> <?php echo htmlspecialchars($machine['name']); ?></p>
                            <p><strong><?php echo __('type'); ?>:</strong> 
                                <span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $machine['type'])); ?></span>
                            </p>
                            <p><strong><?php echo __('model'); ?>:</strong> <?php echo htmlspecialchars($machine['model'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo __('year_manufactured'); ?>:</strong> 
                                <?php echo $machine['year_manufactured'] ?? 'Unknown'; ?>
                                <?php if ($machine_age): ?>
                                    <small class="text-muted">(<?php echo $machine_age; ?> years old)</small>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('capacity'); ?>:</strong> <?php echo htmlspecialchars($machine['capacity'] ?? 'N/A'); ?></p>
                            <p><strong><?php echo __('fuel_type'); ?>:</strong> <?php echo ucfirst($machine['fuel_type'] ?? 'Unknown'); ?></p>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $machine['status'] === 'available' ? 'success' : ($machine['status'] === 'in_use' ? 'info' : ($machine['status'] === 'maintenance' ? 'warning' : 'secondary')); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $machine['status'])); ?>
                                </span>
                            </p>
                            <p><strong><?php echo __('purchase_date'); ?>:</strong> 
                                <?php echo $machine['purchase_date'] ? date('M j, Y', strtotime($machine['purchase_date'])) : 'N/A'; ?>
                            </p>
                            <p><strong><?php echo __('purchase_cost'); ?>:</strong> 
                                <?php echo $machine['purchase_cost'] ? formatCurrency($machine['purchase_cost'], $machine['purchase_currency'] ?? 'USD') : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($contract_stats && $contract_stats['total_contracts'] > 0): ?>
            <!-- Usage Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('usage_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('total_contracts'); ?>:</strong> <?php echo $contract_stats['total_contracts']; ?></p>
                            <p><strong><?php echo __('active_contracts'); ?>:</strong> <?php echo $contract_stats['active_contracts']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('availability'); ?>:</strong> 
                                <?php echo $contract_stats['active_contracts'] > 0 ? 'In Use' : 'Available'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Machine Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('machine_value'); ?></h6>
                        <h4 class="text-success"><?php echo $machine['purchase_cost'] ? formatCurrency($machine['purchase_cost'], $machine['purchase_currency'] ?? 'USD') : 'N/A'; ?></h4>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('machine_type'); ?></h6>
                        <span class="badge badge-info badge-lg">
                            <?php echo ucfirst(str_replace('_', ' ', $machine['type'])); ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('current_status'); ?></h6>
                        <span class="badge badge-<?php echo $machine['status'] === 'available' ? 'success' : ($machine['status'] === 'in_use' ? 'info' : ($machine['status'] === 'maintenance' ? 'warning' : 'secondary')); ?> badge-lg">
                            <?php echo ucfirst(str_replace('_', ' ', $machine['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Machine Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('timeline'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('machine_added'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($machine['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($machine['purchase_date']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('purchase_date'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y', strtotime($machine['purchase_date'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($machine['updated_at'] && $machine['updated_at'] != $machine['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('last_updated'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($machine['updated_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $machine_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> <?php echo __('edit_machine'); ?>
                        </a>
                        
                        <a href="../contracts/index.php?machine_id=<?php echo $machine_id; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-file-contract"></i> <?php echo __('view_contracts'); ?>
                        </a>
                        
                        <a href="../expenses/index.php?category=maintenance&search=<?php echo urlencode($machine['machine_code']); ?>" class="btn btn-warning btn-sm">
                            <i class="fas fa-tools"></i> <?php echo __('maintenance_expenses'); ?>
                        </a>
                        
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $machine_id; ?>, '<?php echo htmlspecialchars($machine['name']); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_machine'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Machine Type Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('machine_type_info'); ?></h6>
                </div>
                <div class="card-body">
                    <?php
                    $type_info = [
                        'excavator' => ['icon' => 'fas fa-truck', 'color' => 'warning', 'desc' => 'Heavy duty excavation equipment'],
                        'bulldozer' => ['icon' => 'fas fa-truck-monster', 'color' => 'danger', 'desc' => 'Earth moving and site preparation'],
                        'crane' => ['icon' => 'fas fa-truck-pickup', 'color' => 'info', 'desc' => 'Lifting and moving heavy materials'],
                        'loader' => ['icon' => 'fas fa-truck-moving', 'color' => 'success', 'desc' => 'Loading and moving materials'],
                        'truck' => ['icon' => 'fas fa-truck', 'color' => 'primary', 'desc' => 'Transportation and delivery'],
                        'compactor' => ['icon' => 'fas fa-truck', 'color' => 'secondary', 'desc' => 'Soil and asphalt compaction'],
                        'other' => ['icon' => 'fas fa-cogs', 'color' => 'dark', 'desc' => 'Specialized construction equipment']
                    ];
                    $current_type = $type_info[$machine['type']] ?? $type_info['other'];
                    ?>
                    
                    <div class="text-center">
                        <i class="<?php echo $current_type['icon']; ?> fa-3x text-<?php echo $current_type['color']; ?> mb-3"></i>
                        <h5><?php echo ucfirst(str_replace('_', ' ', $machine['type'])); ?></h5>
                        <p class="text-muted"><?php echo $current_type['desc']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.timeline-text {
    margin: 0;
    color: #6c757d;
    font-size: 0.9em;
}

.badge-lg {
    font-size: 1em;
    padding: 0.5em 1em;
}
</style>

<script>
function confirmDelete(machineId, machineName) {
    const message = `Are you sure you want to delete machine "${machineName}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${machineId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>