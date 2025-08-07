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

// Get rental ID from URL
$rental_id = $_GET['id'] ?? null;

if (!$rental_id) {
    header('Location: index.php');
    exit;
}

// Get rental details
$stmt = $conn->prepare("
    SELECT pr.*, ps.space_code, ps.space_name, ps.vehicle_category
    FROM parking_rentals pr
    JOIN parking_spaces ps ON pr.parking_space_id = ps.id
    WHERE pr.id = ? AND pr.company_id = ?
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    header('Location: index.php');
    exit;
}

// Get parking space details
$stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
$stmt->execute([$rental['parking_space_id'], $company_id]);
$space = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-car"></i> Parking Rental Details
        </h1>
        <div>
            <?php if ($rental['status'] === 'active'): ?>
                <a href="edit-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Rental
                </a>
                <a href="end-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-danger">
                    <i class="fas fa-stop"></i> End Rental
                </a>
            <?php endif; ?>
            <a href="view.php?id=<?php echo $rental['parking_space_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Space
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Rental Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Rental Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Rental Code:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($rental['rental_code']); ?></span></p>
                            <p><strong>Client Name:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                            <?php if (!empty($rental['client_contact'])): ?>
                                <p><strong>Client Contact:</strong> <?php echo htmlspecialchars($rental['client_contact']); ?></p>
                            <?php endif; ?>
                            <p><strong>Status:</strong> 
                                <span class="badge bg-<?php echo $rental['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($rental['status'])); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                            <?php if (!empty($rental['end_date'])): ?>
                                <p><strong>End Date:</strong> <?php echo date('M j, Y', strtotime($rental['end_date'])); ?></p>
                                <p><strong>Total Days:</strong> <?php echo $rental['total_days']; ?> days</p>
                            <?php else: ?>
                                <p><strong>End Date:</strong> <span class="text-info">Ongoing rental</span></p>
                            <?php endif; ?>
                            <p><strong>Monthly Rate:</strong> <?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></p>
                            <?php if (!empty($rental['total_amount'])): ?>
                                <p><strong>Total Amount:</strong> <?php echo formatCurrencyAmount($rental['total_amount'], $rental['currency'] ?? 'USD'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($rental['vehicle_type']) || !empty($rental['vehicle_registration'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <p><strong>Vehicle Information:</strong></p>
                            <div class="row">
                                <?php if (!empty($rental['vehicle_type'])): ?>
                                    <div class="col-md-6">
                                        <p><strong>Vehicle Type:</strong> <?php echo htmlspecialchars($rental['vehicle_type']); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($rental['vehicle_registration'])): ?>
                                    <div class="col-md-6">
                                        <p><strong>Registration:</strong> <?php echo htmlspecialchars($rental['vehicle_registration']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($rental['notes'])): ?>
                    <div class="row">
                        <div class="col-12">
                            <hr>
                            <p><strong>Notes:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($rental['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Parking Space</h6>
                </div>
                <div class="card-body">
                    <p><strong>Space Code:</strong> <?php echo htmlspecialchars($space['space_code']); ?></p>
                    <p><strong>Space Name:</strong> <?php echo htmlspecialchars($space['space_name']); ?></p>
                    <p><strong>Category:</strong> 
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
                    <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($rental['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>