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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['client_name', 'start_date', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        
        if ($end_date && strtotime($end_date) <= strtotime($start_date)) {
            throw new Exception("End date must be after start date.");
        }

        // Validate monthly rate
        if (!is_numeric($_POST['monthly_rate']) || $_POST['monthly_rate'] <= 0) {
            throw new Exception("Monthly rate must be a positive number.");
        }

        // Calculate total days and amount if end date is provided
        $total_days = null;
        $total_amount = null;
        if ($end_date) {
            $total_days = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24));
            $daily_rate = $_POST['monthly_rate'] / 30;
            $total_amount = $total_days * $daily_rate;
        }

        // Update rental record
        $stmt = $conn->prepare("
            UPDATE parking_rentals SET 
                client_name = ?, client_contact = ?, vehicle_type = ?, 
                vehicle_registration = ?, start_date = ?, end_date = ?, 
                monthly_rate = ?, total_days = ?, total_amount = ?, 
                notes = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['client_name'],
            $_POST['client_contact'] ?? null,
            $_POST['vehicle_type'] ?? null,
            $_POST['vehicle_registration'] ?? null,
            $start_date,
            $end_date,
            $_POST['monthly_rate'],
            $total_days,
            $total_amount,
            $_POST['notes'] ?? null,
            $rental_id,
            $company_id
        ]);

        $success = "Rental updated successfully!";
        
        // Refresh rental data
        $stmt = $conn->prepare("
            SELECT pr.*, ps.space_code, ps.space_name, ps.vehicle_category
            FROM parking_rentals pr
            JOIN parking_spaces ps ON pr.parking_space_id = ps.id
            WHERE pr.id = ? AND pr.company_id = ?
        ");
        $stmt->execute([$rental_id, $company_id]);
        $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> Edit Parking Rental
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

    <!-- Edit Form -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Edit Rental Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_name" class="form-label">Client Name *</label>
                                    <input type="text" class="form-control" id="client_name" name="client_name" 
                                           value="<?php echo htmlspecialchars($rental['client_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="client_contact" class="form-label">Client Contact</label>
                                    <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                           value="<?php echo htmlspecialchars($rental['client_contact'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                    <input type="text" class="form-control" id="vehicle_type" name="vehicle_type" 
                                           value="<?php echo htmlspecialchars($rental['vehicle_type'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vehicle_registration" class="form-label">Vehicle Registration</label>
                                    <input type="text" class="form-control" id="vehicle_registration" name="vehicle_registration" 
                                           value="<?php echo htmlspecialchars($rental['vehicle_registration'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo $rental['start_date']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo $rental['end_date'] ?? ''; ?>">
                                    <small class="text-muted">Leave empty for ongoing rental</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="monthly_rate" class="form-label">Monthly Rate *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <?php 
                                            $currency = $rental['currency'] ?? 'USD';
                                            echo $currency === 'AFN' ? '؋' : ($currency === 'EUR' ? '€' : '$'); 
                                            ?>
                                        </span>
                                        <input type="number" step="0.01" class="form-control" id="monthly_rate" name="monthly_rate" 
                                               value="<?php echo $rental['monthly_rate']; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Currency</label>
                                    <input type="text" class="form-control" value="<?php echo strtoupper($rental['currency'] ?? 'USD'); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"><?php echo htmlspecialchars($rental['notes'] ?? ''); ?></textarea>
                            <small class="form-text text-muted">You can use spaces in notes and descriptions.</small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Rental
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
                        <span class="badge bg-<?php echo $rental['status'] == 'active' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst(htmlspecialchars($rental['status'])); ?>
                        </span>
                    </p>
                    <p><strong>Parking Space:</strong> <?php echo htmlspecialchars($space['space_name']); ?></p>
                    <p><strong>Created:</strong> <?php echo date('M j, Y', strtotime($rental['created_at'])); ?></p>
                    <?php if (!empty($rental['total_amount'])): ?>
                        <p><strong>Total Amount:</strong> <?php echo formatCurrencyAmount($rental['total_amount'], $rental['currency'] ?? 'USD'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notesTextarea = document.getElementById('notes');
    
    // Enable spaces in textarea
    if (notesTextarea) {
        // Remove any existing event listeners that might block spaces
        notesTextarea.removeEventListener('keydown', null);
        notesTextarea.removeEventListener('keypress', null);
        notesTextarea.removeEventListener('keyup', null);
        
        // Add space handling
        notesTextarea.addEventListener('keydown', function(e) {
            // Explicitly allow space key
            if (e.key === ' ' || e.keyCode === 32) {
                e.preventDefault();
                e.stopPropagation();
                
                // Manually insert space
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const value = this.value;
                this.value = value.substring(0, start) + ' ' + value.substring(end);
                this.selectionStart = this.selectionEnd = start + 1;
                
                return false;
            }
        });
        
        // Ensure the textarea is properly configured
        notesTextarea.style.textTransform = 'none';
        notesTextarea.style.letterSpacing = 'normal';
    }
});
</script>

<?php require_once '../../../includes/footer.php'; ?>