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

// Get area rental ID from URL
$rental_id = $_GET['id'] ?? null;

if (!$rental_id) {
    header('Location: index.php');
    exit;
}

// Get area rental details with area information
$stmt = $conn->prepare("
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
    WHERE ar.id = ? AND ar.company_id = ?
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    header('Location: index.php');
    exit;
}

// Calculate some additional metrics
$days_rented = 0;
$remaining_balance = 0;

if ($rental['end_date']) {
    $start = new DateTime($rental['start_date']);
    $end = new DateTime($rental['end_date']);
    $days_rented = $end->diff($start)->days + 1;
} else {
    $start = new DateTime($rental['start_date']);
    $now = new DateTime();
    $days_rented = $now->diff($start)->days;
}

if ($rental['total_amount']) {
    $remaining_balance = $rental['total_amount'] - ($rental['amount_paid'] ?? 0);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-contract"></i> <?php echo __('area_rental_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $rental_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_area_rental'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_area_rentals'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Area Rental Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('rental_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('rental_code'); ?>:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                            <p><strong><?php echo __('client_name'); ?>:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                            <?php if ($rental['client_contact']): ?>
                            <p><strong><?php echo __('client_contact'); ?>:</strong> <?php echo htmlspecialchars($rental['client_contact']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php echo __('start_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                            <?php if ($rental['end_date']): ?>
                            <p><strong><?php echo __('end_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($rental['end_date'])); ?></p>
                            <?php else: ?>
                            <p><strong><?php echo __('end_date'); ?>:</strong> <span class="text-muted"><?php echo __('ongoing'); ?></span></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('area_name'); ?>:</strong> <?php echo htmlspecialchars($rental['area_name']); ?></p>
                            <p><strong><?php echo __('area_code'); ?>:</strong> <?php echo htmlspecialchars($rental['area_code']); ?></p>
                            <p><strong><?php echo __('area_type'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($rental['area_type'])); ?></p>
                            <?php if ($rental['size']): ?>
                            <p><strong><?php echo __('area_size'); ?>:</strong> <?php echo htmlspecialchars($rental['size']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $rental['status'] == 'active' ? 'success' : ($rental['status'] == 'completed' ? 'primary' : 'secondary'); ?>">
                                    <?php echo ucfirst(htmlspecialchars($rental['status'])); ?>
                                </span>
                                <span class="badge badge-light ml-2"><?php echo $rental['rental_status']; ?></span>
                            </p>
                        </div>
                    </div>

                    <?php if ($rental['purpose']): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong><?php echo __('purpose'); ?>:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($rental['purpose'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($rental['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong><?php echo __('notes'); ?>:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($rental['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Financial Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('financial_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('monthly_rate'); ?></h6>
                        <h4 class="text-success">$<?php echo number_format($rental['monthly_rate'], 2); ?></h4>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('daily_rate'); ?></h6>
                        <h5 class="text-info">$<?php echo number_format($rental['daily_rate'], 2); ?></h5>
                    </div>

                    <?php if ($rental['total_amount']): ?>
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('total_amount'); ?></h6>
                        <h4 class="text-primary">$<?php echo number_format($rental['total_amount'], 2); ?></h4>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('amount_paid'); ?></h6>
                        <h5 class="text-success">$<?php echo number_format($rental['amount_paid'] ?? 0, 2); ?></h5>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('remaining_balance'); ?></h6>
                        <h5 class="<?php echo $remaining_balance > 0 ? 'text-warning' : 'text-success'; ?>">
                            $<?php echo number_format($remaining_balance, 2); ?>
                        </h5>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('days_rented'); ?></h6>
                        <h5 class="text-dark"><?php echo $days_rented; ?> <?php echo __('days'); ?></h5>
                    </div>
                </div>
            </div>

            <!-- Rental Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('rental_timeline'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('rental_created'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($rental['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('rental_started'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                            </div>
                        </div>

                        <?php if ($rental['end_date']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-<?php echo strtotime($rental['end_date']) > time() ? 'warning' : 'primary'; ?>"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo strtotime($rental['end_date']) > time() ? __('rental_ends') : __('rental_ended'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y', strtotime($rental['end_date'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($rental['updated_at'] && $rental['updated_at'] != $rental['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('last_updated'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($rental['updated_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
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
</style>

<?php require_once '../../../includes/footer.php'; ?>