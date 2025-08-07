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

// Get expense ID from URL
$expense_id = $_GET['id'] ?? null;

if (!$expense_id) {
    header('Location: index.php');
    exit;
}

// Get expense details
$stmt = $conn->prepare("
    SELECT * FROM expenses 
    WHERE id = ? AND company_id = ?
");
$stmt->execute([$expense_id, $company_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: index.php');
    exit;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-receipt"></i> <?php echo __('expense_details'); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $expense_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> <?php echo __('edit_expense'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_expenses'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- Expense Details -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expense_information'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><?php echo __('expense_code'); ?>:</strong> <?php echo htmlspecialchars($expense['expense_code']); ?></p>
                            <p><strong><?php echo __('category'); ?>:</strong> 
                                <span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $expense['category'])); ?></span>
                            </p>
                            <p><strong><?php echo __('amount'); ?>:</strong> 
                                <span class="text-danger h5"><?php echo formatCurrency($expense['amount'], $expense['currency'] ?? 'USD'); ?></span>
                            </p>
                            <p><strong><?php echo __('expense_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($expense['expense_date'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong><?php echo __('payment_method'); ?>:</strong> <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?></p>
                            <?php if ($expense['reference_number']): ?>
                            <p><strong><?php echo __('reference_number'); ?>:</strong> <?php echo htmlspecialchars($expense['reference_number']); ?></p>
                            <?php endif; ?>
                            <p><strong><?php echo __('currency'); ?>:</strong> <?php echo strtoupper($expense['currency'] ?? 'USD'); ?></p>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong><?php echo __('description'); ?>:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($expense['description'])); ?></p>
                        </div>
                    </div>

                    <?php if ($expense['notes']): ?>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong><?php echo __('notes'); ?>:</strong></p>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($expense['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Expense Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('expense_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('total_amount'); ?></h6>
                        <h4 class="text-danger"><?php echo formatCurrency($expense['amount'], $expense['currency'] ?? 'USD'); ?></h4>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('category'); ?></h6>
                        <span class="badge badge-info badge-lg">
                            <?php echo ucfirst(str_replace('_', ' ', $expense['category'])); ?>
                        </span>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-primary"><?php echo __('payment_method'); ?></h6>
                        <span class="badge badge-secondary badge-lg">
                            <?php echo ucfirst(str_replace('_', ' ', $expense['payment_method'])); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Expense Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('timeline'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('expense_recorded'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($expense['created_at']); ?></p>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('expense_date'); ?></h6>
                                <p class="timeline-text"><?php echo date('M j, Y', strtotime($expense['expense_date'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($expense['updated_at'] && $expense['updated_at'] != $expense['created_at']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-secondary"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title"><?php echo __('last_updated'); ?></h6>
                                <p class="timeline-text"><?php echo formatDateTime($expense['updated_at']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $expense_id; ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> <?php echo __('edit_expense'); ?>
                        </a>
                        
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $expense_id; ?>, '<?php echo htmlspecialchars($expense['expense_code']); ?>')">
                            <i class="fas fa-trash"></i> <?php echo __('delete_expense'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Category Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('category_info'); ?></h6>
                </div>
                <div class="card-body">
                    <?php
                    $category_info = [
                        'fuel' => ['icon' => 'fas fa-gas-pump', 'color' => 'danger', 'desc' => 'Vehicle fuel expenses'],
                        'maintenance' => ['icon' => 'fas fa-tools', 'color' => 'warning', 'desc' => 'Equipment and vehicle maintenance'],
                        'materials' => ['icon' => 'fas fa-boxes', 'color' => 'info', 'desc' => 'Construction materials and supplies'],
                        'equipment' => ['icon' => 'fas fa-cogs', 'color' => 'secondary', 'desc' => 'Equipment purchase and rental'],
                        'office' => ['icon' => 'fas fa-building', 'color' => 'primary', 'desc' => 'Office supplies and utilities'],
                        'utilities' => ['icon' => 'fas fa-plug', 'color' => 'success', 'desc' => 'Electricity, water, and internet'],
                        'other' => ['icon' => 'fas fa-ellipsis-h', 'color' => 'dark', 'desc' => 'Miscellaneous expenses']
                    ];
                    $current_category = $category_info[$expense['category']] ?? $category_info['other'];
                    ?>
                    
                    <div class="text-center">
                        <i class="<?php echo $current_category['icon']; ?> fa-3x text-<?php echo $current_category['color']; ?> mb-3"></i>
                        <h5><?php echo ucfirst(str_replace('_', ' ', $expense['category'])); ?></h5>
                        <p class="text-muted"><?php echo $current_category['desc']; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #007bff;
}

.timeline-title {
    margin-bottom: 5px;
    font-weight: 600;
    color: #495057;
}

.timeline-text {
    margin: 0;
    color: #6c757d;
    font-size: 0.9em;
}

.badge-lg {
    font-size: 1em;
    padding: 0.5em 1em;
}
</style>

<script>
function confirmDelete(expenseId, expenseCode) {
    const message = `Are you sure you want to delete expense "${expenseCode}"? This action cannot be undone.`;
    if (confirm(message)) {
        window.location.href = `index.php?delete=${expenseId}`;
    }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>