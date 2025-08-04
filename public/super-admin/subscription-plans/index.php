<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole(['super_admin']);

require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-credit-card"></i> <?php echo __('subscription_plans'); ?>
        </h1>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('subscription_plans_management'); ?></h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-info-circle fa-4x text-info mb-4"></i>
                    <h4 class="text-gray-800 mb-3"><?php echo __('subscription_plans_feature'); ?></h4>
                    <p class="text-gray-600 mb-4">
                        <?php echo __('subscription_plans_feature_description'); ?>
                    </p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('basic_plan'); ?></h6>
                                    <p class="card-text"><?php echo __('basic_plan_description'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-success">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('professional_plan'); ?></h6>
                                    <p class="card-text"><?php echo __('professional_plan_description'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-left-warning">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo __('enterprise_plan'); ?></h6>
                                    <p class="card-text"><?php echo __('enterprise_plan_description'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="/constract360/construction/public/super-admin/companies/" class="btn btn-primary">
                            <i class="fas fa-building"></i> <?php echo __('manage_companies'); ?>
                        </a>
                        <a href="/constract360/construction/public/super-admin/" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_dashboard'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>