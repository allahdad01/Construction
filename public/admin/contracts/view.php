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

// Include centralized currency helper
require_once '../../../config/currency_helper.php';

$error = '';
$success = '';

// Get contract ID from URL
$contract_id = $_GET['id'] ?? null;

if (!$contract_id) {
    header('Location: index.php');
    exit;
}

// Get contract details with related information
$stmt = $conn->prepare("
    SELECT c.*, 
           p.project_code, p.name as project_name,
           m.machine_code, m.name as machine_name, m.type as machine_type
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN machines m ON c.machine_id = m.id
    WHERE c.id = ? AND c.company_id = ?
");
$stmt->execute([$contract_id, $company_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header('Location: index.php');
    exit;
}

// Get working hours for this contract
$stmt = $conn->prepare("
    SELECT wh.*, e.name as employee_name, e.employee_code
    FROM working_hours wh
    LEFT JOIN employees e ON wh.employee_id = e.id
    WHERE wh.contract_id = ? AND wh.company_id = ?
    ORDER BY wh.date DESC
");
$stmt->execute([$contract_id, $company_id]);
$working_hours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total hours worked
$total_hours = array_sum(array_column($working_hours, 'hours_worked'));
$total_days = count($working_hours);
$total_amount = $total_hours * $contract['rate_amount'];
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-file-contract"></i> <?php echo __('contract_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $contract_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_contract'); ?>
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

    <!-- Contract Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('contract_code'); ?>:</strong> <?php echo htmlspecialchars($contract['contract_code']); ?></p>
                            <p><strong><?php echo __('project'); ?>:</strong> <?php echo htmlspecialchars($contract['project_code'] . ' - ' . $contract['project_name']); ?></p>
                            <p><strong><?php echo __('machine'); ?>:</strong> <?php echo htmlspecialchars($contract['machine_code'] . ' - ' . $contract['machine_name'] . ' (' . $contract['machine_type'] . ')'); ?></p>
                            <p><strong><?php echo __('contract_type'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($contract['contract_type'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Rate Amount:</strong> <?php echo formatCurrencyAmount($contract['rate_amount'], $contract['currency'] ?? 'USD'); ?></p>
                            <p><strong><?php echo __('start_date'); ?>:</strong> <?php echo formatDate($contract['start_date']); ?></p>
                            <p><strong><?php echo __('end_date'); ?>:</strong> <?php echo $contract['end_date'] ? formatDate($contract['end_date']) : __('not_set'); ?></p>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge badge-<?php echo $contract['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($contract['status'])); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('total_hours_required'); ?>:</strong> <?php echo $contract['total_hours_required'] ?: __('not_set'); ?></p>
                            <p><strong><?php echo __('total_days_required'); ?>:</strong> <?php echo $contract['total_days_required'] ?: __('not_set'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('working_hours_per_day'); ?>:</strong> <?php echo $contract['working_hours_per_day']; ?> <?php echo __('hours'); ?></p>
                            <p><strong><?php echo __('created_at'); ?>:</strong> <?php echo formatDateTime($contract['created_at']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('contract_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo $total_days; ?></h4>
                                <small class="text-muted"><?php echo __('days_worked'); ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="text-success"><?php echo number_format($total_hours, 2); ?></h4>
                                <small class="text-muted"><?php echo __('hours_worked'); ?></small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h4 class="text-info"><?php echo formatCurrencyAmount($total_amount, $contract['currency'] ?? 'USD'); ?></h4>
                        <small class="text-muted"><?php echo __('total_amount'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Working Hours -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary"><?php echo __('working_hours'); ?></h6>
            <a href="add-hours.php?contract_id=<?php echo $contract_id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('add_hours'); ?>
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($working_hours)): ?>
                <p class="text-muted text-center"><?php echo __('no_working_hours_found'); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered" id="workingHoursTable">
                        <thead>
                            <tr>
                                <th><?php echo __('date'); ?></th>
                                <th><?php echo __('employee'); ?></th>
                                <th><?php echo __('hours_worked'); ?></th>
                                <th><?php echo __('amount'); ?></th>
                                <th><?php echo __('notes'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($working_hours as $hours): ?>
                            <tr>
                                <td><?php echo formatDate($hours['date']); ?></td>
                                <td><?php echo htmlspecialchars($hours['employee_code'] . ' - ' . $hours['employee_name']); ?></td>
                                <td><?php echo number_format($hours['hours_worked'], 2); ?></td>
                                <td><?php echo formatCurrencyAmount($hours['hours_worked'] * $contract['rate_amount'], $contract['currency'] ?? 'USD'); ?></td>
                                <td><?php echo htmlspecialchars($hours['notes'] ?? ''); ?></td>
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
    $('#workingHoursTable').DataTable({
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