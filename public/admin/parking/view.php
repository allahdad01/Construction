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

// Get parking space ID from URL
$space_id = $_GET['id'] ?? null;

if (!$space_id) {
    header('Location: index.php');
    exit;
}

// Get parking space details
$stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
$stmt->execute([$space_id, $company_id]);
$space = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$space) {
    header('Location: index.php');
    exit;
}

// Get parking rentals for this space
$stmt = $conn->prepare("
    SELECT pr.*, u.first_name, u.last_name
    FROM parking_rentals pr
    LEFT JOIN users u ON pr.user_id = u.id
    WHERE pr.parking_space_id = ? AND pr.company_id = ?
    ORDER BY pr.start_date DESC
");
$stmt->execute([$space_id, $company_id]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total earnings from actual payments
$total_earnings = 0;
$total_rentals = count($rentals);

// Get all payments for this parking space's rentals
if (!empty($rentals)) {
    $rental_ids = array_column($rentals, 'id');
    $placeholders = str_repeat('?,', count($rental_ids) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total_payments 
        FROM parking_payments 
        WHERE rental_id IN ($placeholders) AND company_id = ?
    ");
    $params = array_merge($rental_ids, [$company_id]);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_earnings = $result['total_payments'] ?? 0;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-parking"></i> <?php echo htmlspecialchars($space['space_name'] ?? 'Parking Space'); ?>
            </h1>
            <p class="text-muted mb-0">
                <span class="badge bg-primary"><?php echo htmlspecialchars($space['space_code']); ?></span>
                <span class="badge bg-<?php echo $space['status'] == 'available' ? 'success' : 'warning'; ?>">
                    <?php echo ucfirst($space['status']); ?>
                </span>
            </p>
        </div>
        <div class="btn-group" role="group">
            <?php if ($space['status'] === 'available'): ?>
                <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-success">
                    <i class="fas fa-plus"></i> <?php echo __('add_rental'); ?>
                </a>
            <?php endif; ?>
            <a href="edit.php?id=<?php echo $space_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
            </a>
            <a href="print.php?id=<?php echo $space_id; ?>" target="_blank" class="btn btn-outline-dark">
                <i class="fas fa-print"></i> <?php echo __('print'); ?>
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_rentals'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_rentals; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?php echo __('total_earnings'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrencyAmount($total_earnings ?? 0, $space['currency'] ?? 'USD'); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                <?php echo __('monthly_rate'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatCurrencyAmount($space['monthly_rate'], $space['currency'] ?? 'USD'); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-left-<?php echo $space['status'] == 'available' ? 'success' : 'warning'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo $space['status'] == 'available' ? 'success' : 'warning'; ?> text-uppercase mb-1">
                                <?php echo __('status'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo ucfirst($space['status']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-<?php echo $space['status'] == 'available' ? 'check-circle' : 'car'; ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parking Space Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> <?php echo __('space_information'); ?>
                    </h6>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="edit.php?id=<?php echo $space_id; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
                        </a>
                        <?php if ($space['status'] === 'available'): ?>
                            <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-outline-success">
                                <i class="fas fa-plus"></i> <?php echo __('add_rental'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('space_code'); ?></label>
                                <div><span class="badge bg-primary fs-6"><?php echo htmlspecialchars($space['space_code']); ?></span></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('space_name'); ?></label>
                                <div class="fw-bold"><?php echo htmlspecialchars($space['space_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('vehicle_category'); ?></label>
                                <div>
                                    <?php 
                                    $category_display = [
                                        'machines' => '🏗️ Construction Machines',
                                        'cars' => '🚗 Cars', 
                                        'trucks' => '🚛 Trucks',
                                        'vans' => '🚐 Vans',
                                        'motorcycles' => '🏍️ Motorcycles',
                                        'trailers' => '🚛 Trailers',
                                        'general' => '🅿️ General'
                                    ];
                                    $category = $space['vehicle_category'] ?? 'general';
                                    echo $category_display[$category] ?? ucfirst($category);
                                    ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('space_type'); ?></label>
                                <div class="fw-bold">
                                    <?php 
                                    $type_display = [
                                        'covered' => '🏠 Covered',
                                        'uncovered' => '🌤️ Uncovered',
                                        'indoor' => '🏢 Indoor',
                                        'outdoor' => '🌳 Outdoor'
                                    ];
                                    $type = $space['space_type'] ?? 'standard';
                                    echo $type_display[$type] ?? ucfirst($type);
                                    ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('size'); ?></label>
                                <div class="fw-bold"><?php echo ucfirst(htmlspecialchars($space['size'] ?? 'medium')); ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('monthly_rate'); ?></label>
                                <div class="fw-bold fs-5 text-primary">
                                    <?php 
                                    require_once '../../../config/currency_helper.php';
                                    echo formatCurrencyAmount($space['monthly_rate'], $space['currency'] ?? 'USD'); 
                                    ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('daily_rate'); ?></label>
                                <div class="fw-bold text-info">
                                    <?php echo formatCurrencyAmount($space['monthly_rate'] / 30, $space['currency'] ?? 'USD'); ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('status'); ?></label>
                                <div>
                                    <span class="badge bg-<?php echo $space['status'] == 'available' ? 'success' : 'warning'; ?> fs-6">
                                        <?php echo ucfirst(htmlspecialchars($space['status'] ?? 'available')); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('created'); ?></label>
                                <div class="fw-bold"><?php echo date('M j, Y', strtotime($space['created_at'] ?? 'now')); ?></div>
                            </div>
                            <?php if (isset($space['capacity']) && $space['capacity'] > 1): ?>
                                <div class="mb-3">
                                    <label class="form-label text-muted small"><?php echo __('vehicle_capacity'); ?></label>
                                    <div class="fw-bold"><?php echo $space['capacity']; ?> <?php echo __('vehicles'); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($space['description'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <div class="mb-3">
                                <label class="form-label text-muted small"><?php echo __('features_description'); ?></label>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($space['description'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('space_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo $total_rentals; ?></h4>
                                <small class="text-muted"><?php echo __('total_rentals'); ?></small>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="text-center">
                                <h4 class="text-success">
                                    <?php echo formatCurrencyAmount($total_earnings ?? 0, $space['currency'] ?? 'USD'); ?>
                                </h4>
                                <small class="text-muted"><?php echo __('total_payments_received'); ?></small>
                            </div>
                        </div>
                        <?php if (!empty($rentals)): ?>
                        <div class="col-12 mb-3">
                            <div class="text-center">
                                <?php 
                                // Calculate total expected amount from all rentals
                                $total_expected = 0;
                                foreach ($rentals as $rental) {
                                    if (!empty($rental['total_amount'])) {
                                        $total_expected += $rental['total_amount'];
                                    } else {
                                        // For ongoing rentals, calculate current amount
                                        $start_date = new DateTime($rental['start_date']);
                                        $current_date = new DateTime();
                                        $current_days = $start_date->diff($current_date)->days;
                                        $daily_rate = $rental['monthly_rate'] / 30;
                                        $total_expected += $current_days * $daily_rate;
                                    }
                                }
                                ?>
                                <h4 class="text-info">
                                    <?php echo formatCurrencyAmount($total_expected, $space['currency'] ?? 'USD'); ?>
                                </h4>
                                <small class="text-muted"><?php echo __('total_expected'); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="text-center">
                                <span class="badge bg-<?php echo ($space['status'] == 'available') ? 'success' : 'warning'; ?> p-2">
                                    <?php 
                                    if ($space['status'] == 'available') {
                                        echo '✅ ' . __('available_for_rent');
                                    } else {
                                        echo '🚗 ' . __('currently_occupied');
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parking Rentals -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-history"></i> <?php echo __('rental_history'); ?>
            </h6>
            <div class="btn-group btn-group-sm" role="group">
                <?php if ($space['status'] === 'available'): ?>
                    <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> <?php echo __('add_rental'); ?>
                    </a>
                <?php endif; ?>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rentalFilters">
                    <i class="fas fa-filter"></i> <?php echo __('filter'); ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($rentals)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                    <p class="text-muted"><?php echo __('no_parking_rentals_found_for_this_space'); ?></p>
                    <?php if ($space['status'] === 'available'): ?>
                        <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> <?php echo __('add_first_rental'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="rentalsTable">
                        <thead>
                            <tr>
                                <th><?php echo __('rental_code'); ?></th>
                                <th><?php echo __('client_vehicle'); ?></th>
                                <th><?php echo __('rental_period'); ?></th>
                                <th><?php echo __('rate_amount'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentals as $rental): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($rental['rental_code'] ?? 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($rental['client_name'] ?? 'N/A'); ?></strong>
                                        <?php if (!empty($rental['client_contact'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($rental['client_contact']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($rental['vehicle_type']) || !empty($rental['vehicle_registration'])): ?>
                                            <br>
                                            <span class="badge bg-info">
                                                <?php 
                                                $vehicle_info = [];
                                                if (!empty($rental['vehicle_type'])) {
                                                    $vehicle_info[] = $rental['vehicle_type'];
                                                }
                                                if (!empty($rental['vehicle_registration'])) {
                                                    $vehicle_info[] = $rental['vehicle_registration'];
                                                }
                                                echo htmlspecialchars(implode(' - ', $vehicle_info));
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo __('start'); ?>:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'] ?? 'now')); ?>
                                        <?php 
                                        // Calculate days properly
                                        $current_date = new DateTime();
                                        $start_date = new DateTime($rental['start_date']);
                                        $end_date = !empty($rental['end_date']) ? new DateTime($rental['end_date']) : null;
                                        
                                        if ($end_date && $end_date > $start_date) {
                                            $total_days = $start_date->diff($end_date)->days;
                                            echo '<br><strong>' . __('end') . ':</strong> ' . date('M j, Y', strtotime($rental['end_date']));
                                            echo '<br><small class="text-muted">' . $total_days . ' ' . __('days') . '</small>';
                                        } else {
                                            $current_days = $start_date->diff($current_date)->days;
                                            echo '<br><small class="text-info">' . __('ongoing_rental') . ' (' . $current_days . ' ' . __('days') . ')</small>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo formatCurrencyAmount($rental['monthly_rate'] ?? 0, $rental['currency'] ?? 'USD'); ?>/<?php echo __('month'); ?></strong>
                                        <?php 
                                        // Get payments for this rental
                                        $stmt = $conn->prepare("
                                            SELECT SUM(amount) as total_paid 
                                            FROM parking_payments 
                                            WHERE rental_id = ? AND company_id = ?
                                        ");
                                        $stmt->execute([$rental['id'], $company_id]);
                                        $payment_result = $stmt->fetch(PDO::FETCH_ASSOC);
                                        $total_paid = $payment_result['total_paid'] ?? 0;
                                        
                                        // Calculate expected amount
                                        if (!empty($rental['total_amount'])) {
                                            $expected_amount = $rental['total_amount'];
                                        } else {
                                            // For ongoing rentals, calculate current amount
                                            $start_date = new DateTime($rental['start_date']);
                                            $current_date = new DateTime();
                                            $current_days = $start_date->diff($current_date)->days;
                                            $daily_rate = $rental['monthly_rate'] / 30;
                                            $expected_amount = $current_days * $daily_rate;
                                        }
                                        ?>
                                        <?php if (!empty($rental['total_amount'])): ?>
                                            <br><strong><?php echo __('total'); ?>:</strong> <?php echo formatCurrencyAmount($rental['total_amount'], $rental['currency'] ?? 'USD'); ?>
                                        <?php else: ?>
                                            <br><strong><?php echo __('current'); ?>:</strong> <?php echo formatCurrencyAmount($expected_amount, $rental['currency'] ?? 'USD'); ?>
                                        <?php endif; ?>
                                        <br><small class="text-success"><?php echo __('paid'); ?>: <?php echo formatCurrencyAmount($total_paid, $rental['currency'] ?? 'USD'); ?></small>
                                        <?php if ($total_paid < $expected_amount): ?>
                                            <br><small class="text-warning"><?php echo __('due'); ?>: <?php echo formatCurrencyAmount($expected_amount - $total_paid, $rental['currency'] ?? 'USD'); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $rental['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($rental['status'] ?? 'unknown')); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="view-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($rental['status'] == 'active'): ?>
                                                <a href="payment.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-success" title="Payment">
                                                    <i class="fas fa-credit-card"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($rental['status'] == 'active'): ?>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="edit-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-warning" title="Edit Rental">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="end-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-danger" title="End Rental">
                                                <i class="fas fa-stop"></i>
                                            </a>
                                        </div>
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

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-bolt"></i> <?php echo __('quick_actions'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="edit.php?id=<?php echo $space_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-edit"></i><br>
                                <small><?php echo __('edit_space'); ?></small>
                            </a>
                        </div>
                        <?php if ($space['status'] === 'available'): ?>
                        <div class="col-md-6 mb-3">
                            <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-outline-success w-100">
                                <i class="fas fa-plus"></i><br>
                                <small><?php echo __('add_rental'); ?></small>
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6 mb-3">
                            <a href="index.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-arrow-left"></i><br>
                                <small><?php echo __('back_to_list'); ?></small>
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="payment.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-credit-card"></i><br>
                                <small><?php echo __('payment_history'); ?></small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> <?php echo __('space_statistics'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $total_rentals; ?></h4>
                            <small class="text-muted"><?php echo __('total_rentals'); ?></small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success">
                                <?php echo formatCurrencyAmount($total_earnings ?? 0, $space['currency'] ?? 'USD'); ?>
                            </h4>
                            <small class="text-muted"><?php echo __('total_earnings'); ?></small>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h5 class="text-info">
                                <?php echo formatCurrencyAmount($space['monthly_rate'], $space['currency'] ?? 'USD'); ?>
                            </h5>
                            <small class="text-muted"><?php echo __('monthly_rate'); ?></small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-warning">
                                <?php echo formatCurrencyAmount($space['monthly_rate'] / 30, $space['currency'] ?? 'USD'); ?>
                            </h5>
                            <small class="text-muted"><?php echo __('daily_rate'); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#rentalsTable').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 10,
        "language": {
            "search": "<?php echo __('search'); ?>:",
            "lengthMenu": "<?php echo __('show'); ?> _MENU_ <?php echo __('entries'); ?>",
            "info": "<?php echo __('showing'); ?> _START_ <?php echo __('to'); ?> _END_ <?php echo __('of'); ?> _TOTAL_ <?php echo __('entries'); ?>",
            "infoEmpty": "<?php echo __('showing'); ?> 0 <?php echo __('to'); ?> 0 <?php echo __('of'); ?> 0 <?php echo __('entries'); ?>",
            "infoFiltered": "(<?php echo __('filtered_from'); ?> _MAX_ <?php echo __('total_entries'); ?>)",
            "paginate": {
                "first": "<?php echo __('first'); ?>",
                "last": "<?php echo __('last'); ?>",
                "next": "<?php echo __('next'); ?>",
                "previous": "<?php echo __('previous'); ?>"
            }
        }
    });
});
</script>

<?php require_once '../../../includes/footer.php'; ?>