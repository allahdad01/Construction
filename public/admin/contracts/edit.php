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

// Get contract ID from URL
$contract_id = $_GET['id'] ?? null;

if (!$contract_id) {
    header('Location: index.php');
    exit;
}

// Get contract details
$stmt = $conn->prepare("SELECT * FROM contracts WHERE id = ? AND company_id = ?");
$stmt->execute([$contract_id, $company_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: index.php');
    exit;
}

// Get available projects
$stmt = $conn->prepare("SELECT id, project_code, name FROM projects WHERE company_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$company_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available machines
$stmt = $conn->prepare("SELECT id, machine_code, name, type FROM machines WHERE company_id = ? AND (status = 'available' OR id = ?) ORDER BY name");
$stmt->execute([$company_id, $contract['machine_id']]);
$machines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['project_id', 'machine_id', 'contract_type', 'rate_amount', 'start_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate rate amount
        if (!is_numeric($_POST['rate_amount']) || $_POST['rate_amount'] <= 0) {
            throw new Exception("Rate amount must be a positive number.");
        }

        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        
        if ($end_date && $start_date >= $end_date) {
            throw new Exception("End date must be after start date.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update contract record
        $stmt = $conn->prepare("
            UPDATE contracts SET
                project_id = ?, machine_id = ?, contract_type = ?, rate_amount = ?,
                currency = ?, total_hours_required = ?, total_days_required = ?,
                working_hours_per_day = ?, start_date = ?, end_date = ?, status = ?,
                updated_at = NOW()
            WHERE id = ? AND company_id = ?
        ");

        $stmt->execute([
            $_POST['project_id'],
            $_POST['machine_id'],
            $_POST['contract_type'],
            $_POST['rate_amount'],
            $_POST['currency'] ?? 'USD',
            $_POST['total_hours_required'] ?: null,
            $_POST['total_days_required'] ?: null,
            $_POST['working_hours_per_day'] ?? 8,
            $start_date,
            $end_date,
            $_POST['status'] ?? 'active',
            $contract_id,
            $company_id
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Contract updated successfully!";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$contract_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit"></i> <?php echo __('edit_contract'); ?>
        </h1>
        <div>
            <a href="view.php?id=<?php echo $contract_id; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> <?php echo __('view_contract'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_contracts'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Edit Contract Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_details'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="project_id" class="form-label"><?php echo __('project'); ?> *</label>
                            <select class="form-control" id="project_id" name="project_id" required>
                                <option value=""><?php echo __('select_project'); ?></option>
                                <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo ($contract['project_id'] == $project['id'] || (isset($_POST['project_id']) && $_POST['project_id'] == $project['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['project_code'] . ' - ' . $project['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="machine_id" class="form-label"><?php echo __('machine'); ?> *</label>
                            <select class="form-control" id="machine_id" name="machine_id" required>
                                <option value=""><?php echo __('select_machine'); ?></option>
                                <?php foreach ($machines as $machine): ?>
                                <option value="<?php echo $machine['id']; ?>" <?php echo ($contract['machine_id'] == $machine['id'] || (isset($_POST['machine_id']) && $_POST['machine_id'] == $machine['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($machine['machine_code'] . ' - ' . $machine['name'] . ' (' . $machine['type'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="contract_type" class="form-label"><?php echo __('contract_type'); ?> *</label>
                            <select class="form-control" id="contract_type" name="contract_type" required>
                                <option value=""><?php echo __('select_contract_type'); ?></option>
                                <option value="hourly" <?php echo ($contract['contract_type'] == 'hourly' || (isset($_POST['contract_type']) && $_POST['contract_type'] == 'hourly')) ? 'selected' : ''; ?>><?php echo __('hourly'); ?></option>
                                <option value="daily" <?php echo ($contract['contract_type'] == 'daily' || (isset($_POST['contract_type']) && $_POST['contract_type'] == 'daily')) ? 'selected' : ''; ?>><?php echo __('daily'); ?></option>
                                <option value="weekly" <?php echo ($contract['contract_type'] == 'weekly' || (isset($_POST['contract_type']) && $_POST['contract_type'] == 'weekly')) ? 'selected' : ''; ?>><?php echo __('weekly'); ?></option>
                                <option value="monthly" <?php echo ($contract['contract_type'] == 'monthly' || (isset($_POST['contract_type']) && $_POST['contract_type'] == 'monthly')) ? 'selected' : ''; ?>><?php echo __('monthly'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="rate_amount" class="form-label"><?php echo __('rate_amount'); ?> *</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="rate_amount" name="rate_amount" 
                                   value="<?php echo htmlspecialchars($_POST['rate_amount'] ?? $contract['rate_amount']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="currency" class="form-label"><?php echo __('currency'); ?></label>
                            <select class="form-control" id="currency" name="currency">
                                <option value="USD" <?php echo (($_POST['currency'] ?? $contract['currency']) == 'USD') ? 'selected' : ''; ?>>USD</option>
                                <option value="AFN" <?php echo (($_POST['currency'] ?? $contract['currency']) == 'AFN') ? 'selected' : ''; ?>>AFN</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? $contract['start_date']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="end_date" class="form-label"><?php echo __('end_date'); ?></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? $contract['end_date']); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-control" id="status" name="status">
                                <option value="active" <?php echo (($_POST['status'] ?? $contract['status']) == 'active') ? 'selected' : ''; ?>><?php echo __('active'); ?></option>
                                <option value="completed" <?php echo (($_POST['status'] ?? $contract['status']) == 'completed') ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                <option value="cancelled" <?php echo (($_POST['status'] ?? $contract['status']) == 'cancelled') ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="total_hours_required" class="form-label"><?php echo __('total_hours_required'); ?></label>
                            <input type="number" min="0" class="form-control" id="total_hours_required" name="total_hours_required" 
                                   value="<?php echo htmlspecialchars($_POST['total_hours_required'] ?? $contract['total_hours_required']); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="total_days_required" class="form-label"><?php echo __('total_days_required'); ?></label>
                            <input type="number" min="0" class="form-control" id="total_days_required" name="total_days_required" 
                                   value="<?php echo htmlspecialchars($_POST['total_days_required'] ?? $contract['total_days_required']); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="working_hours_per_day" class="form-label"><?php echo __('working_hours_per_day'); ?></label>
                            <input type="number" min="1" max="24" class="form-control" id="working_hours_per_day" name="working_hours_per_day" 
                                   value="<?php echo htmlspecialchars($_POST['working_hours_per_day'] ?? $contract['working_hours_per_day']); ?>">
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('update_contract'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>