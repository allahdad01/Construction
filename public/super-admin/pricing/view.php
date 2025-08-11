<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

require_once '../../../includes/header.php';

$db = new Database();
$conn = $db->getConnection();

$plan_id = (int)($_GET['id'] ?? 0);

if (!$plan_id) {
    header('Location: index.php');
    exit;
}

// Get pricing plan details
$stmt = $conn->prepare("SELECT * FROM pricing_plans WHERE id = ?");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plan) {
    header('Location: index.php');
    exit;
}

$features = json_decode($plan['features'], true) ?: [];
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-eye"></i> <?php echo __('view_pricing_plan'); ?>
        </h1>
        <div class="d-flex">
            <a href="edit.php?id=<?php echo $plan_id; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> <?php echo __('edit_plan'); ?>
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_pricing_plans'); ?>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('plan_details'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('plan_name'); ?></label>
                                <p class="form-control-plaintext"><?php echo htmlspecialchars($plan['plan_name']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('plan_code'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($plan['plan_code']); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if ($plan['description']): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo __('description'); ?></label>
                        <p class="form-control-plaintext"><?php echo htmlspecialchars($plan['description']); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('price'); ?></label>
                                <p class="form-control-plaintext">
                                    <strong class="text-success">
                                        <?php echo $plan['currency']; ?> <?php echo number_format($plan['price'], 2); ?>
                                    </strong>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('billing_cycle'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-info"><?php echo ucfirst($plan['billing_cycle']); ?></span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('currency'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($plan['currency']); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h6 class="font-weight-bold text-primary mb-3"><?php echo __('plan_limits'); ?></h6>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('max_employees'); ?></label>
                                <p class="form-control-plaintext">
                                    <?php if ($plan['max_employees'] > 0): ?>
                                        <span class="badge bg-info"><?php echo $plan['max_employees']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?php echo __('unlimited'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('max_machines'); ?></label>
                                <p class="form-control-plaintext">
                                    <?php if ($plan['max_machines'] > 0): ?>
                                        <span class="badge bg-info"><?php echo $plan['max_machines']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?php echo __('unlimited'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('max_projects'); ?></label>
                                <p class="form-control-plaintext">
                                    <?php if ($plan['max_projects'] > 0): ?>
                                        <span class="badge bg-info"><?php echo $plan['max_projects']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?php echo __('unlimited'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <h6 class="font-weight-bold text-primary mb-3"><?php echo __('plan_features'); ?></h6>

                    <?php if (!empty($features)): ?>
                        <div class="row">
                            <?php foreach ($features as $feature): ?>
                                <div class="col-md-6">
                                    <div class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo htmlspecialchars($feature); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted"><?php echo __('no_features_listed_for_this_plan'); ?></p>
                    <?php endif; ?>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('status'); ?></label>
                                <p class="form-control-plaintext">
                                    <span class="badge <?php echo $plan['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $plan['is_active'] ? __('active') : __('inactive'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('popular_plan'); ?></label>
                                <p class="form-control-plaintext">
                                    <?php if ($plan['is_popular']): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-star"></i> <?php echo __('popular'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted"><?php echo __('no'); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('created_at'); ?></label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($plan['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('updated_at'); ?></label>
                                <p class="form-control-plaintext"><?php echo formatDateTime($plan['updated_at']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Plan Summary -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('plan_summary'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong><?php echo __('plan_name'); ?>:</strong> <?php echo htmlspecialchars($plan['plan_name']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('plan_code'); ?>:</strong> <?php echo htmlspecialchars($plan['plan_code']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('price'); ?>:</strong> 
                        <span class="text-success fw-bold"><?php echo $plan['currency']; ?> <?php echo number_format($plan['price'], 2); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('billing_cycle'); ?>:</strong> <?php echo ucfirst($plan['billing_cycle']); ?>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('status'); ?>:</strong> 
                        <span class="badge <?php echo $plan['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $plan['is_active'] ? __('active') : __('inactive'); ?>
                        </span>
                    </div>
                    <?php if ($plan['is_popular']): ?>
                    <div class="mb-3">
                        <strong><?php echo __('popular'); ?>:</strong> 
                        <span class="badge bg-warning">
                            <i class="fas fa-star"></i> <?php echo __('yes'); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit.php?id=<?php echo $plan_id; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> <?php echo __('edit_plan'); ?>
                        </a>
                        <a href="delete.php?id=<?php echo $plan_id; ?>" class="btn btn-danger"
                           onclick="return confirm('<?php echo __('are_you_sure_you_want_to_delete_this_pricing_plan'); ?>')">
                                <i class="fas fa-trash"></i> <?php echo __('delete_plan'); ?>
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> <?php echo __('back_to_list'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Plan Statistics -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('plan_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <?php
                    // Get companies using this plan
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM companies WHERE subscription_plan = ?");
                    $stmt->execute([$plan['plan_code']]);
                    $companies_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    <div class="mb-3">
                        <strong><?php echo __('companies_using'); ?>:</strong> 
                        <span class="badge bg-info"><?php echo $companies_count; ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('features_count'); ?>:</strong> 
                        <span class="badge bg-secondary"><?php echo count($features); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong><?php echo __('plan_type'); ?>:</strong> 
                        <span class="badge bg-primary">
                            <?php 
                            if ($plan['max_employees'] == 0 && $plan['max_machines'] == 0 && $plan['max_projects'] == 0) {
                                echo __('enterprise');
                            } elseif ($plan['max_employees'] <= 10) {
                                echo __('basic');
                            } else {
                                echo __('professional');
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>