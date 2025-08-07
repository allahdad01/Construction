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

// Check if rental is already ended
if ($rental['status'] !== 'active') {
    header('Location: view-rental.php?id=' . $rental_id);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate end date
        $end_date = $_POST['end_date'] ?? null;
        if (!$end_date) {
            throw new Exception("End date is required to end the rental.");
        }

        if (strtotime($end_date) < strtotime($rental['start_date'])) {
            throw new Exception("End date cannot be before start date.");
        }

        // Calculate total days and amount
        $total_days = ceil((strtotime($end_date) - strtotime($rental['start_date'])) / (60 * 60 * 24));
        $daily_rate = $rental['monthly_rate'] / 30;
        $total_amount = $total_days * $daily_rate;

        // Start transaction
        $conn->beginTransaction();

        // Update rental record
        $stmt = $conn->prepare("
            UPDATE parking_rentals SET 
                end_date = ?, total_days = ?, total_amount = ?, 
                status = 'ended', updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $end_date,
            $total_days,
            $total_amount,
            $rental_id,
            $company_id
        ]);

        // Update parking space status to available
        $stmt = $conn->prepare("
            UPDATE parking_spaces SET status = 'available' 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$rental['parking_space_id'], $company_id]);

        $conn->commit();
        
        $success = "Rental ended successfully! The parking space is now available.";
        
        // Redirect to view page after a short delay
        header("Refresh: 2; URL=view-rental.php?id=" . $rental_id);

    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-stop"></i> End Parking Rental
        </h1>
        <div>
            <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Rental
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- End Rental Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">End Rental</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will end the rental and make the parking space available for new rentals.
                    </div>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date *</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                    <small class="text-muted">Select the date when the rental should end</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="text" class="form-control" value="<?php echo date('M j, Y', strtotime($rental['start_date'])); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Monthly Rate</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Client</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($rental['client_name']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to end this rental?')">
                                <i class="fas fa-stop"></i> End Rental
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Rental Summary</h6>
                </div>
                <div class="card-body">
                    <p><strong>Rental Code:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-success">Active</span>
                    </p>
                    <p><strong>Parking Space:</strong> <?php echo htmlspecialchars($space['space_name']); ?></p>
                    <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></p>
                    <p><strong>Duration:</strong> 
                        <?php 
                        $days = ceil((time() - strtotime($rental['start_date'])) / (60 * 60 * 24));
                        echo $days . ' days so far';
                        ?>
                    </p>
                    <?php if (!empty($rental['vehicle_type']) || !empty($rental['vehicle_registration'])): ?>
                        <p><strong>Vehicle:</strong> 
                            <?php 
                            $vehicle_info = [];
                            if (!empty($rental['vehicle_type'])) $vehicle_info[] = $rental['vehicle_type'];
                            if (!empty($rental['vehicle_registration'])) $vehicle_info[] = $rental['vehicle_registration'];
                            echo htmlspecialchars(implode(' - ', $vehicle_info));
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('end_date').addEventListener('change', function() {
    const startDate = new Date('<?php echo $rental['start_date']; ?>');
    const endDate = new Date(this.value);
    
    if (endDate < startDate) {
        alert('End date cannot be before start date!');
        this.value = '';
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>