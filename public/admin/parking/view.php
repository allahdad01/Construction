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
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-parking"></i> Parking Space Details
        </h1>
        <div>
            <?php if ($space['status'] === 'available'): ?>
                <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-success">
                    <i class="fas fa-plus"></i> Add Rental
                </a>
            <?php endif; ?>
            <a href="edit.php?id=<?php echo $space_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Space
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Parking
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Parking Space Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Parking Space Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Space Code:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($space['space_code']); ?></span></p>
                            <p><strong>Space Name:</strong> <?php echo htmlspecialchars($space['space_name'] ?? 'N/A'); ?></p>
                            <p><strong>Vehicle Category:</strong> 
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
                            </p>
                            <p><strong>Space Type:</strong> <?php echo ucfirst(htmlspecialchars($space['space_type'] ?? 'standard')); ?></p>
                            <p><strong>Size:</strong> <?php echo ucfirst(htmlspecialchars($space['size'] ?? 'medium')); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Monthly Rate:</strong> 
                                <?php 
                                require_once '../../../config/currency_helper.php';
                                echo formatCurrencyAmount($space['monthly_rate'], $space['currency'] ?? 'USD'); 
                                ?>
                            </p>
                            <p><strong>Daily Rate:</strong> 
                                <?php echo formatCurrencyAmount($space['monthly_rate'] / 30, $space['currency'] ?? 'USD'); ?>
                            </p>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $space['status'] == 'available' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($space['status'] ?? 'available')); ?>
                                </span>
                            </p>
                            <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($space['created_at'] ?? 'now')); ?></p>
                            <?php if (isset($space['capacity']) && $space['capacity'] > 1): ?>
                                <p><strong>Vehicle Capacity:</strong> <?php echo $space['capacity']; ?> vehicles</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($space['description'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <p><strong>Features & Description:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($space['description'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Space Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 mb-3">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo $total_rentals; ?></h4>
                                <small class="text-muted">Total Rentals</small>
                            </div>
                        </div>
                        <div class="col-12 mb-3">
                            <div class="text-center">
                                <h4 class="text-success">
                                    <?php echo formatCurrencyAmount($total_earnings ?? 0, $space['currency'] ?? 'USD'); ?>
                                </h4>
                                <small class="text-muted">Total Payments Received</small>
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
                                <small class="text-muted">Total Expected</small>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <div class="text-center">
                                <span class="badge bg-<?php echo ($space['status'] == 'available') ? 'success' : 'warning'; ?> p-2">
                                    <?php 
                                    if ($space['status'] == 'available') {
                                        echo '✅ Available for Rent';
                                    } else {
                                        echo '🚗 Currently Occupied';
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
            <h6 class="m-0 font-weight-bold text-primary">Parking Rentals History</h6>
            <?php if ($space['status'] === 'available'): ?>
                <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Add New Rental
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($rentals)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No parking rentals found for this space.</p>
                    <?php if ($space['status'] === 'available'): ?>
                        <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add First Rental
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="rentalsTable">
                        <thead>
                            <tr>
                                <th>Rental Code</th>
                                <th>Client & Vehicle</th>
                                <th>Rental Period</th>
                                <th>Rate & Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                        <strong>Start:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'] ?? 'now')); ?>
                                        <?php 
                                        // Calculate days properly
                                        $current_date = new DateTime();
                                        $start_date = new DateTime($rental['start_date']);
                                        $end_date = !empty($rental['end_date']) ? new DateTime($rental['end_date']) : null;
                                        
                                        if ($end_date && $end_date > $start_date) {
                                            $total_days = $start_date->diff($end_date)->days;
                                            echo '<br><strong>End:</strong> ' . date('M j, Y', strtotime($rental['end_date']));
                                            echo '<br><small class="text-muted">' . $total_days . ' days</small>';
                                        } else {
                                            $current_days = $start_date->diff($current_date)->days;
                                            echo '<br><small class="text-info">Ongoing rental (' . $current_days . ' days)</small>';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo formatCurrencyAmount($rental['monthly_rate'] ?? 0, $rental['currency'] ?? 'USD'); ?>/month</strong>
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
                                            <br><strong>Total:</strong> <?php echo formatCurrencyAmount($rental['total_amount'], $rental['currency'] ?? 'USD'); ?>
                                        <?php else: ?>
                                            <br><strong>Current:</strong> <?php echo formatCurrencyAmount($expected_amount, $rental['currency'] ?? 'USD'); ?>
                                        <?php endif; ?>
                                        <br><small class="text-success">Paid: <?php echo formatCurrencyAmount($total_paid, $rental['currency'] ?? 'USD'); ?></small>
                                        <?php if ($total_paid < $expected_amount): ?>
                                            <br><small class="text-warning">Due: <?php echo formatCurrencyAmount($expected_amount - $total_paid, $rental['currency'] ?? 'USD'); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $rental['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($rental['status'] ?? 'unknown')); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="view-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-primary" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($rental['status'] == 'active'): ?>
                                            <a href="payment.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-success" title="Payment">
                                                <i class="fas fa-credit-card"></i>
                                            </a>
                                            <a href="edit-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-warning" title="Edit Rental">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="end-rental.php?id=<?php echo $rental['id']; ?>" class="btn btn-outline-danger" title="End Rental">
                                                <i class="fas fa-stop"></i>
                                            </a>
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