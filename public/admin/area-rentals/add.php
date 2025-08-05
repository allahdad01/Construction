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

// Get available rental areas
$stmt = $conn->prepare("SELECT id, area_code, area_name, area_type FROM rental_areas WHERE company_id = ? AND status = 'available' ORDER BY area_name");
$stmt->execute([$company_id]);
$rental_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['rental_area_id', 'client_name', 'start_date', 'monthly_rate'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate monthly rate
        if (!is_numeric($_POST['monthly_rate']) || $_POST['monthly_rate'] <= 0) {
            throw new Exception("Monthly rate must be a positive number.");
        }

        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        
        if ($end_date && $start_date >= $end_date) {
            throw new Exception("End date must be after start date.");
        }

        // Generate area rental code
        $rental_code = generateAreaRentalCode($company_id);

        // Start transaction
        $conn->beginTransaction();

        // Calculate total days and amount if end date is provided
        $total_days = null;
        $total_amount = null;
        if ($end_date) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $total_days = $end->diff($start)->days + 1; // Include both start and end day
            $total_amount = $total_days * ($_POST['monthly_rate'] / 30); // Daily rate calculation
        }

        // Create area rental record
        $stmt = $conn->prepare("
            INSERT INTO area_rentals (
                company_id, rental_code, rental_area_id, client_name, client_contact,
                purpose, start_date, end_date, monthly_rate, total_days, total_amount,
                status, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
        ");

        $stmt->execute([
            $company_id,
            $rental_code,
            $_POST['rental_area_id'],
            $_POST['client_name'],
            $_POST['client_contact'] ?? '',
            $_POST['purpose'] ?? '',
            $start_date,
            $end_date,
            $_POST['monthly_rate'],
            $total_days,
            $total_amount,
            $_POST['notes'] ?? ''
        ]);

        $rental_id = $conn->lastInsertId();

        // Update rental area status to 'in_use'
        $stmt = $conn->prepare("UPDATE rental_areas SET status = 'in_use' WHERE id = ?");
        $stmt->execute([$_POST['rental_area_id']]);

        // Commit transaction
        $conn->commit();

        $success = "Area rental added successfully! Rental Code: $rental_code";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Helper function to generate area rental code
function generateAreaRentalCode($company_id) {
    global $conn;

    // Get company prefix
    $stmt = $conn->prepare("SELECT company_code FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_code = $stmt->fetch(PDO::FETCH_ASSOC)['company_code'];

    // Get next area rental number
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM area_rentals WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $next_number = $count + 1;
    return strtoupper($company_code) . 'ARL' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-map-marker-alt"></i> <?php echo __('add_area_rental'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_area_rentals'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Add Area Rental Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('area_rental_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="rental_area_id" class="form-label"><?php echo __('rental_area'); ?> *</label>
                            <div class="input-group">
                                <select class="form-control" id="rental_area_id" name="rental_area_id" required>
                                    <option value=""><?php echo __('select_rental_area'); ?></option>
                                    <?php foreach ($rental_areas as $area): ?>
                                    <option value="<?php echo $area['id']; ?>" <?php echo (isset($_POST['rental_area_id']) && $_POST['rental_area_id'] == $area['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($area['area_code'] . ' - ' . $area['area_name'] . ' (' . $area['area_type'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../rental-areas/add.php" class="btn btn-outline-primary" title="Add New Rental Area">
                                    <i class="fas fa-plus"></i> Add Area
                                </a>
                            </div>
                            <?php if (empty($rental_areas)): ?>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i> No rental areas available. <a href="../rental-areas/add.php">Create one first</a>.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_name" class="form-label"><?php echo __('client_name'); ?> *</label>
                            <input type="text" class="form-control" id="client_name" name="client_name" 
                                   value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_contact" class="form-label"><?php echo __('client_contact'); ?></label>
                            <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                   value="<?php echo htmlspecialchars($_POST['client_contact'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label"><?php echo __('monthly_rate'); ?> *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                   value="<?php echo htmlspecialchars($_POST['monthly_rate'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="purpose" class="form-label"><?php echo __('purpose'); ?></label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3"><?php echo htmlspecialchars($_POST['purpose'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label"><?php echo __('end_date'); ?></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('add_area_rental'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>