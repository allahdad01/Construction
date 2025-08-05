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

// Get all rental areas for this company
$stmt = $conn->prepare("
    SELECT ra.*, 
           COUNT(ar.id) as active_rentals
    FROM rental_areas ra
    LEFT JOIN area_rentals ar ON ra.id = ar.rental_area_id AND ar.status = 'active'
    WHERE ra.company_id = ?
    GROUP BY ra.id
    ORDER BY ra.created_at DESC
");
$stmt->execute([$company_id]);
$rental_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rental_areas WHERE company_id = ?");
$stmt->execute([$company_id]);
$total_areas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rental_areas WHERE company_id = ? AND status = 'available'");
$stmt->execute([$company_id]);
$available_areas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM rental_areas WHERE company_id = ? AND status = 'in_use'");
$stmt->execute([$company_id]);
$rented_areas = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-map-marked-alt"></i> <?php echo __('rental_areas'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_rental_area'); ?>
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_areas'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_areas; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map-marked-alt fa-2x text-gray-300"></i>
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
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $available_areas; ?></div>
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
                                <?php echo __('rented'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rented_areas; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rental Areas List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('rental_areas_list'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($rental_areas)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-map-marked-alt fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-600"><?php echo __('no_rental_areas_found'); ?></h5>
                    <p class="text-gray-500"><?php echo __('add_your_first_rental_area'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_rental_area'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="areasTable">
                        <thead>
                            <tr>
                                <th><?php echo __('area_info'); ?></th>
                                <th><?php echo __('type_location'); ?></th>
                                <th><?php echo __('size_capacity'); ?></th>
                                <th><?php echo __('monthly_rate'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rental_areas as $area): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            <div class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($area['area_name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($area['area_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo ucfirst($area['area_type']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($area['location']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($area['size'] ?? 'N/A'); ?></strong><br>
                                    <small class="text-muted">
                                        <?php if ($area['capacity']): ?>
                                            Max: <?php echo $area['capacity']; ?> people
                                        <?php else: ?>
                                            No limit
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($area['monthly_rate'] ?? 0); ?></strong><br>
                                    <small class="text-muted">per month</small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $area['status'] === 'available' ? 'success' : ($area['status'] === 'in_use' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($area['status']); ?>
                                    </span>
                                    <?php if ($area['active_rentals'] > 0): ?>
                                        <br><small class="text-info"><?php echo $area['active_rentals']; ?> active rental(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $area['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $area['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($area['active_rentals'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete(<?php echo $area['id']; ?>, '<?php echo htmlspecialchars($area['area_name']); ?>')"
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
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(areaId, areaName) {
    const message = `Are you sure you want to delete rental area "${areaName}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${areaId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>