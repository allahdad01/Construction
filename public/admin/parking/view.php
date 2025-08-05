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

// Calculate total earnings
$total_earnings = array_sum(array_column($rentals, 'total_amount'));
$total_rentals = count($rentals);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-parking"></i> <?php echo __('parking_space_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $space_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_parking_space'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_parking_spaces'); ?>
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
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_space_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('space_code'); ?>:</strong> <?php echo htmlspecialchars($space['space_code']); ?></p>
                            <p><strong><?php echo __('space_number'); ?>:</strong> <?php echo htmlspecialchars($space['space_number']); ?></p>
                            <p><strong><?php echo __('space_type'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($space['space_type'])); ?></p>
                            <p><strong><?php echo __('daily_rate'); ?>:</strong> <?php echo formatCurrency($space['daily_rate'], $space['currency']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('currency'); ?>:</strong> <?php echo htmlspecialchars($space['currency']); ?></p>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $space['status'] == 'available' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($space['status'])); ?>
                                </span>
                            </p>
                            <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($space['created_at']); ?></p>
                            <p><strong><?php echo __('updated_at'); ?>:</strong> <?php echo formatDateTime($space['updated_at']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($space['description']): ?>
                    <div class="row">
                        <div class="col-12">
                            <p><strong><?php echo __('description'); ?>:</strong> <?php echo htmlspecialchars($space['description']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_space_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo $total_rentals; ?></h4>
                                <small class="text-muted"><?php echo __('total_rentals'); ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-success"><?php echo formatCurrency($total_earnings, $space['currency']); ?></h4>
                                <small class="text-muted"><?php echo __('total_earnings'); ?></small>
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
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('parking_rentals'); ?></h6>
            <a href="add-rental.php?space_id=<?php echo $space_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('add_rental'); ?>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($rentals)): ?>
                <p class="text-muted text-center"><?php echo __('no_parking_rentals_found'); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="rentalsTable">
                        <thead>
                            <tr>
                                <th><?php echo __('start_date'); ?></th>
                                <th><?php echo __('end_date'); ?></th>
                                <th><?php echo __('employee'); ?></th>
                                <th><?php echo __('days_rented'); ?></th>
                                <th><?php echo __('total_amount'); ?></th>
                                <th><?php echo __('status'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rentals as $rental): ?>
                            <tr>
                                <td><?php echo formatDate($rental['start_date']); ?></td>
                                <td><?php echo formatDate($rental['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($rental['client_name'] . ' (' . ($rental['first_name'] ? $rental['first_name'] . ' ' . $rental['last_name'] : 'No user') . ')'); ?></td>
                                <td><?php echo $rental['days_rented']; ?></td>
                                <td><?php echo formatCurrency($rental['total_amount'], $rental['currency']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $rental['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($rental['status'])); ?>
                                    </span>
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