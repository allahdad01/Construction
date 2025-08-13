<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);
require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get area ID from URL
$area_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$area_id) {
    header('Location: index.php');
    exit;
}

// Get rental area details with enhanced information
$stmt = $conn->prepare("
    SELECT 
        ra.*,
        COUNT(ar.id) as total_rentals,
        COUNT(CASE WHEN ar.status = 'active' THEN 1 END) as active_rentals,
        COUNT(CASE WHEN ar.status = 'ended' THEN 1 END) as ended_rentals,
        COALESCE(SUM(arp.amount), 0) as total_earnings,
        COUNT(arp.id) as payment_count
    FROM rental_areas ra
    LEFT JOIN area_rentals ar ON ra.id = ar.rental_area_id
    LEFT JOIN area_rental_payments arp ON ar.id = arp.area_rental_id
    WHERE ra.id = ? AND ra.company_id = ?
    GROUP BY ra.id
");
$stmt->execute([$area_id, $company_id]);
$area = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$area) {
    header('Location: index.php');
    exit;
}

// Get recent rentals for this area
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        COALESCE(SUM(arp.amount), 0) as total_paid,
        COUNT(arp.id) as payment_count
    FROM area_rentals ar
    LEFT JOIN area_rental_payments arp ON ar.id = arp.area_rental_id
    WHERE ar.rental_area_id = ? AND ar.company_id = ?
    GROUP BY ar.id
    ORDER BY ar.created_at DESC
    LIMIT 10
");
$stmt->execute([$area_id, $company_id]);
$recent_rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get area statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_rentals,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_rentals,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended_rentals,
        AVG(monthly_rate) as avg_monthly_rate,
        SUM(monthly_rate) as total_monthly_revenue
    FROM area_rentals 
    WHERE rental_area_id = ? AND company_id = ?
");
$stmt->execute([$area_id, $company_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-map-marked-alt"></i> <?php echo __('rental_area_details'); ?>
            </h1>
            <p class="text-muted mb-0"><?php echo __('view_detailed_information_for'); ?> <?php echo htmlspecialchars($area['area_name']); ?></p>
        </div>
        <div class="btn-group" role="group">
            <a href="edit.php?id=<?php echo $area_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> <?php echo __('edit_area'); ?>
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_areas'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Area Information -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> <?php echo __('area_information'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('area_name'); ?></label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($area['area_name']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('area_code'); ?></label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($area['area_code']); ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('area_type'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-<?php echo $area['area_type'] === 'commercial' ? 'primary' : ($area['area_type'] === 'industrial' ? 'warning' : 'success'); ?>">
                                        <?php echo ucfirst($area['area_type']); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('status'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-<?php echo $area['status'] === 'available' ? 'success' : ($area['status'] === 'in_use' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($area['status']); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('monthly_rate'); ?></label>
                                <p class="form-control-plaintext">
                                    <strong class="text-success">
                                        <?php echo formatCurrencyAmount($area['monthly_rate'], $area['currency'] ?? 'USD'); ?>
                                    </strong>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('daily_rate'); ?></label>
                                <p class="form-control-plaintext">
                                    <strong class="text-info">
                                        <?php echo formatCurrencyAmount($area['daily_rate'], $area['currency'] ?? 'USD'); ?>
                                    </strong>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('currency'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-light text-dark">
                                        <?php echo $area['currency'] ?? 'USD'; ?>
                                    </span>
                                </p>
                            </div>
                            <?php if (!empty($area['area_size_sqm'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('area_size'); ?></label>
                                <p class="form-control-plaintext">
                                    <?php echo number_format($area['area_size_sqm'], 1); ?> sqm
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Details -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-cogs"></i> <?php echo __('area_details'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-secondary mb-3"><?php echo __('amenities'); ?></h6>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <i class="fas fa-bolt <?php echo $area['has_electricity'] ? 'text-success' : 'text-muted'; ?>"></i>
                                    <span class="ms-2"><?php echo __('electricity'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <i class="fas fa-tint <?php echo $area['has_water'] ? 'text-info' : 'text-muted'; ?>"></i>
                                    <span class="ms-2"><?php echo __('water'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <i class="fas fa-shield-alt <?php echo $area['has_security'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <span class="ms-2"><?php echo __('security'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <i class="fas fa-car <?php echo $area['has_parking'] ? 'text-primary' : 'text-muted'; ?>"></i>
                                    <span class="ms-2"><?php echo __('parking'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <i class="fas fa-truck <?php echo $area['has_loading_dock'] ? 'text-success' : 'text-muted'; ?>"></i>
                                    <span class="ms-2"><?php echo __('loading_dock'); ?></span>
                                </div>
                                <div class="col-6 mb-2">
                                    <i class="fas fa-home <?php echo $area['is_covered'] ? 'text-secondary' : 'text-muted'; ?>"></i>
                                    <span class="ms-2"><?php echo __('covered'); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-secondary mb-3"><?php echo __('additional_information'); ?></h6>
                            <?php if (!empty($area['description'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('description'); ?></label>
                                <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($area['description'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($area['location_details'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('location_details'); ?></label>
                                <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($area['location_details'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($area['amenities'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('amenities'); ?></label>
                                <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($area['amenities'])); ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($area['restrictions'])): ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('restrictions'); ?></label>
                                <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($area['restrictions'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Rentals -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> <?php echo __('recent_rentals'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_rentals)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo __('no_rentals_found_for_this_area'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo __('rental_code'); ?></th>
                                        <th><?php echo __('client'); ?></th>
                                        <th><?php echo __('period'); ?></th>
                                        <th><?php echo __('amount'); ?></th>
                                        <th><?php echo __('status'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_rentals as $rental): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rental['rental_code']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($rental['client_name']); ?></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($rental['start_date'])); ?>
                                            <?php if (!empty($rental['end_date'])): ?>
                                                - <?php echo date('M j, Y', strtotime($rental['end_date'])); ?>
                                            <?php else: ?>
                                                - <?php echo __('ongoing'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></strong>
                                            <?php if ($rental['total_paid'] > 0): ?>
                                                <br><small class="text-success"><?php echo __('paid'); ?>: <?php echo formatCurrencyAmount($rental['total_paid'], $rental['currency'] ?? 'USD'); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $rental['status'] === 'active' ? 'success' : ($rental['status'] === 'ended' ? 'secondary' : 'warning'); ?>">
                                                <?php echo ucfirst($rental['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../area-rentals/view.php?id=<?php echo $rental['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
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

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> <?php echo __('area_statistics'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h6 class="text-primary"><?php echo $area['total_rentals']; ?></h6>
                            <small class="text-muted"><?php echo __('total_rentals'); ?></small>
                        </div>
                        <div class="col-6 mb-3">
                            <h6 class="text-success"><?php echo $area['active_rentals']; ?></h6>
                            <small class="text-muted"><?php echo __('active_rentals'); ?></small>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h6 class="text-info"><?php echo $area['ended_rentals']; ?></h6>
                            <small class="text-muted"><?php echo __('ended_rentals'); ?></small>
                        </div>
                        <div class="col-6 mb-3">
                            <h6 class="text-warning"><?php echo $area['payment_count']; ?></h6>
                            <small class="text-muted"><?php echo __('payments'); ?></small>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6 class="text-success"><?php echo formatCurrencyAmount($area['total_earnings'], $area['currency'] ?? 'USD'); ?></h6>
                        <small class="text-muted"><?php echo __('total_earnings'); ?></small>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-bolt"></i> <?php echo __('quick_actions'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $area_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> <?php echo __('edit_area'); ?>
                        </a>
                        <a href="../area-rentals/add.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> <?php echo __('create_rental'); ?>
                        </a>
                        <?php if ($area['active_rentals'] == 0): ?>
                        <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $area_id; ?>, '<?php echo htmlspecialchars($area['area_name']); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_area'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Area Details -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> <?php echo __('additional_details'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($area['capacity'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('capacity'); ?></label>
                        <p class="form-control-plaintext"><?php echo $area['capacity']; ?> people</p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($area['max_vehicle_size'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('max_vehicle_size'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($area['max_vehicle_size']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($area['operating_hours'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('operating_hours'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($area['operating_hours']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($area['contact_person'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('contact_person'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($area['contact_person']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($area['contact_phone'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('contact_phone'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($area['contact_phone']); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($area['contact_email'])): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('contact_email'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($area['contact_email']); ?></p>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('created'); ?></label>
                        <p class="form-control-plaintext"><?php echo date('M j, Y', strtotime($area['created_at'])); ?></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('last_updated'); ?></label>
                        <p class="form-control-plaintext"><?php echo date('M j, Y', strtotime($area['updated_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(areaId, areaName) {
    const message = `<?php echo __('are_you_sure_you_want_to_delete_rental_area'); ?> "${areaName}"? <?php echo __('this_action_cannot_be_undone'); ?>.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${areaId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>