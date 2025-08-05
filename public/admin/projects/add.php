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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['name', 'description', 'start_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        // Validate budget if provided
        if (!empty($_POST['total_budget']) && (!is_numeric($_POST['total_budget']) || $_POST['total_budget'] <= 0)) {
            throw new Exception("Budget must be a positive number.");
        }

        // Validate dates
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        
        if ($end_date && strtotime($end_date) <= strtotime($start_date)) {
            throw new Exception("End date must be after start date.");
        }

        // Check if project name already exists for this company
        $stmt = $conn->prepare("SELECT id FROM projects WHERE company_id = ? AND name = ?");
        $stmt->execute([$company_id, $_POST['name']]);
        if ($stmt->fetch()) {
            throw new Exception("Project with this name already exists for this company.");
        }

        // Generate project code
        $project_code = generateProjectCode($company_id);

        // Start transaction
        $conn->beginTransaction();

        // Create project record
        $stmt = $conn->prepare("
            INSERT INTO projects (
                company_id, project_code, name, description, 
                client_name, client_contact, start_date, end_date,
                total_budget, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");

        $stmt->execute([
            $company_id,
            $project_code,
            $_POST['name'],
            $_POST['description'],
            $_POST['client_name'] ?? '',
            $_POST['client_contact'] ?? '',
            $start_date,
            $end_date,
            $_POST['total_budget'] ?? null
        ]);

        $project_id = $conn->lastInsertId();

        // Commit transaction
        $conn->commit();

        $success = "Project added successfully! Project Code: $project_code";

        // Use JavaScript redirect instead of header redirect
        echo "<script>setTimeout(function(){ window.location.href = 'view.php?id=$project_id'; }, 2000);</script>";

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Helper function to generate project code
function generateProjectCode($company_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM projects WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    return 'PROJ' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-project-diagram"></i> <?php echo __('add_project'); ?>
        </h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_projects'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('project_information'); ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label"><?php echo __('project_name'); ?> *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_name" class="form-label"><?php echo __('client_name'); ?></label>
                            <input type="text" class="form-control" id="client_name" name="client_name" 
                                   value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="client_contact" class="form-label"><?php echo __('client_contact'); ?></label>
                            <input type="text" class="form-control" id="client_contact" name="client_contact" 
                                   value="<?php echo htmlspecialchars($_POST['client_contact'] ?? ''); ?>" 
                                   placeholder="Phone, email, or address">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="total_budget" class="form-label"><?php echo __('budget'); ?> (Optional)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="total_budget" name="total_budget" 
                                   value="<?php echo htmlspecialchars($_POST['total_budget'] ?? ''); ?>" 
                                   placeholder="Leave empty if budget is unknown">
                            <small class="form-text text-muted">
                                For machine rental contracts, budget may not be known upfront
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo __('description'); ?> *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required
                                      placeholder="Detailed description of the project"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>



                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label"><?php echo __('start_date'); ?> *</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo __('add_project'); ?>
                        </button>
                        <a href="index.php" class="btn btn-secondary ml-2">
                            <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>