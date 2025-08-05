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

// Get area rental details
$stmt = $conn->prepare("
    SELECT ar.*, ra.area_name
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

// Get available rental areas
$stmt = $conn->prepare("SELECT id, area_code, area_name, area_type FROM rental_areas WHERE company_id = ? AND (status = 'available' OR id = ?) ORDER BY area_name");
$stmt->execute([$company_id, $rental['rental_area_id']]);
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

        // Calculate total days and amount if end date is provided
        $total_days = $rental['total_days']; // Keep existing if no end date change
        $total_amount = $rental['total_amount']; // Keep existing if no end date change
        
        if ($end_date) {
            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $total_days = $end->diff($start)->days + 1;
            $total_amount = $total_days * ($_POST['monthly_rate'] / 30);
        } elseif (!$rental['end_date'] && $end_date === '') {
            // If changing from fixed term to ongoing
            $total_days = null;
            $total_amount = null;
        }

        // Start transaction
        $conn->beginTransaction();

        // Update area rental record
        $stmt = $conn->prepare("
            UPDATE area_rentals SET
                rental_area_id = ?, client_name = ?, client_contact = ?,
                purpose = ?, start_date = ?, end_date = ?, monthly_rate = ?,
                total_days = ?, total_amount = ?, amount_paid = ?, status = ?,
                notes = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['rental_area_id'],
            $_POST['client_name'],
            $_POST['client_contact'] ?? '',
            $_POST['purpose'] ?? '',
            $start_date,
            $end_date ?: null,
            $_POST['monthly_rate'],
            $total_days,
            $total_amount,
            $_POST['amount_paid'] ?? 0,
            $_POST['status'] ?? 'active',
            $_POST['notes'] ?? '',
            $rental_id,
            $company_id
        ]);

        // If rental area changed, update the previous area status
        if ($_POST['rental_area_id'] != $rental['rental_area_id']) {
            // Set previous area to available
            $stmt = $conn->prepare("UPDATE rental_areas SET status = 'available' WHERE id = ?");
            $stmt->execute([$rental['rental_area_id']]);
            
            // Set new area to in_use
            $stmt = $conn->prepare("UPDATE rental_areas SET status = 'in_use' WHERE id = ?");
            $stmt->execute([$_POST['rental_area_id']]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Area rental updated successfully!";

        // Refresh rental data
        $stmt = $conn->prepare("
            SELECT ar.*, ra.area_name
            FROM area_rentals ar 
            LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
            WHERE ar.id = ? AND ar.company_id = ?
        ");
        $stmt->execute([$rental_id, $company_id]);
        $rental = $stmt->fetch(PDO::FETCH_ASSOC);

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$rental_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_area_rental'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $rental_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_rental'); ?>
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

    <!-- Edit Area Rental Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <?php echo __('edit_area_rental_details'); ?> - <?php echo htmlspecialchars($rental['rental_code']); ?>
            </h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="rental_area_id" class="form-label"><?php echo __('rental_area'); ?> *</label>
                            <select class="form-control" id="rental_area_id" name="rental_area_id" required>
                                <option value=""><?php echo __('select_rental_area'); ?></option>
                                <?php foreach ($rental_areas as $area): ?>
                                <option value="<?php echo $area['id']; ?>" <?php echo ($rental['rental_area_id'] == $area['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['area_code'] . ' - ' . $area['area_name'] . ' (' . $area['area_type'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_name" class="form-label"><?php echo __('client_name'); ?> *</label>
                            <input type="text" class="form-control" id="client_name" name="client_name" 
                                   value="<?php echo htmlspecialchars($rental['client_name']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_contact" class="form-label"><?php echo __('client_contact'); ?></label>
                            <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                   value="<?php echo htmlspecialchars($rental['client_contact'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monthly_rate" class="form-label"><?php echo __('monthly_rate'); ?> *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_rate" name="monthly_rate" 
                                   value="<?php echo htmlspecialchars($rental['monthly_rate']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="purpose" class="form-label"><?php echo __('purpose'); ?></label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3"><?php echo htmlspecialchars($rental['purpose'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($rental['start_date']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label"><?php echo __('end_date'); ?></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($rental['end_date'] ?? ''); ?>">
                            <small class="form-text text-muted"><?php echo __('leave_empty_for_ongoing_rental'); ?></small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="amount_paid" class="form-label"><?php echo __('amount_paid'); ?></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="amount_paid" name="amount_paid" 
                                   value="<?php echo htmlspecialchars($rental['amount_paid'] ?? 0); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="active" <?php echo ($rental['status'] == 'active') ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                <option value="completed" <?php echo ($rental['status'] == 'completed') ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                <option value="cancelled" <?php echo ($rental['status'] == 'cancelled') ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                                <option value="suspended" <?php echo ($rental['status'] == 'suspended') ? 'selected' : ''; ?>><?php echo __('suspended'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($rental['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <a href="view.php?id=<?php echo $rental_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_area_rental'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Calculate total amount when dates or rate change
function calculateTotal() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const monthlyRate = parseFloat(document.getElementById('monthly_rate').value) || 0;
    
    if (startDate && endDate && monthlyRate > 0) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const timeDiff = end.getTime() - start.getTime();
        const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        const dailyRate = monthlyRate / 30;
        const totalAmount = daysDiff * dailyRate;
        
        // You could display this calculation somewhere if needed
        console.log(`Days: ${daysDiff}, Daily Rate: ${dailyRate.toFixed(2)}, Total: ${totalAmount.toFixed(2)}`);
    }
}

// Add event listeners
document.getElementById('start_date').addEventListener('change', calculateTotal);
document.getElementById('end_date').addEventListener('change', calculateTotal);
document.getElementById('monthly_rate').addEventListener('input', calculateTotal);
</script>

<?php require_once '../../../includes/footer.php'; ?>