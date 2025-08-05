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

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $project_id = (int)$_GET['delete'];
    
    try {
        // Check if project exists and belongs to company
        $stmt = $conn->prepare("
            SELECT project_code, name 
            FROM projects 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$project_id, $company_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$project) {
            throw new Exception("Project not found or you don't have permission to delete it.");
        }
        
        // Check for related contracts
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contracts WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $contract_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($contract_count > 0) {
            throw new Exception("Cannot delete project '{$project['project_code']}' because it has {$contract_count} active contract(s). Remove contracts first.");
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete project
        $stmt = $conn->prepare("DELETE FROM projects WHERE id = ? AND company_id = ?");
        $stmt->execute([$project_id, $company_id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("No records were deleted. The project may have already been removed.");
        }
        
        // Commit transaction
        $conn->commit();
        
        $success = "Project '{$project['project_code']}' - {$project['name']} deleted successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get all projects for this company
$stmt = $conn->prepare("
    SELECT p.*, 
           COUNT(c.id) as active_contracts,
           SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as running_contracts
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    WHERE p.company_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute([$company_id]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE company_id = ?");
$stmt->execute([$company_id]);
$total_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company_id]);
$active_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE company_id = ? AND status = 'completed'");
$stmt->execute([$company_id]);
$completed_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT SUM(budget) as total FROM projects WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company_id]);
$total_budget = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-project-diagram"></i> <?php echo __('projects'); ?>
        </h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('add_project'); ?>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_projects'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_projects; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?php echo __('active'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_projects; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                <?php echo __('completed'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $completed_projects; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                <?php echo __('total_budget'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($total_budget); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('projects_list'); ?></h6>
        </div>
        <div class="card-body">
            <?php if (empty($projects)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-project-diagram fa-3x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-600"><?php echo __('no_projects_found'); ?></h5>
                    <p class="text-gray-500"><?php echo __('add_your_first_project'); ?></p>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_project'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="projectsTable">
                        <thead>
                            <tr>
                                <th><?php echo __('project_info'); ?></th>
                                <th><?php echo __('client_location'); ?></th>
                                <th><?php echo __('dates'); ?></th>
                                <th><?php echo __('budget'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-3">
                                            <div class="avatar-title bg-primary-subtle text-primary rounded-circle">
                                                <i class="fas fa-project-diagram"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($project['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($project['project_code']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($project['client_name'] ?? 'N/A'); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($project['location'] ?? 'No location'); ?></small>
                                </td>
                                <td>
                                    <strong>Start:</strong> <?php echo date('M j, Y', strtotime($project['start_date'])); ?><br>
                                    <small class="text-muted">
                                        <strong>End:</strong> 
                                        <?php echo $project['end_date'] ? date('M j, Y', strtotime($project['end_date'])) : 'Not set'; ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?php echo formatCurrency($project['budget'] ?? 0); ?></strong><br>
                                    <small class="text-muted">
                                        <?php 
                                        $priority_colors = ['low' => 'secondary', 'medium' => 'primary', 'high' => 'warning', 'urgent' => 'danger'];
                                        $priority_color = $priority_colors[$project['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $priority_color; ?>"><?php echo ucfirst($project['priority']); ?></span>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $project['status'] === 'active' ? 'success' : ($project['status'] === 'completed' ? 'primary' : 'secondary'); ?>">
                                        <?php echo ucfirst($project['status']); ?>
                                    </span>
                                    <?php if ($project['active_contracts'] > 0): ?>
                                        <br><small class="text-info"><?php echo $project['active_contracts']; ?> contract(s)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($project['active_contracts'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="confirmDelete(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
function confirmDelete(projectId, projectName) {
    const message = `Are you sure you want to delete project "${projectName}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${projectId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>