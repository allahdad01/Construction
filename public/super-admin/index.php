<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Check if user is authenticated and is super admin
requireAuth();
requireRole('super_admin');

$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies");
$stmt->execute();
$total_companies = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies WHERE subscription_status = 'active'");
$stmt->execute();
$active_companies = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM companies WHERE subscription_status = 'trial'");
$stmt->execute();
$trial_companies = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role != 'super_admin'");
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get monthly revenue
$stmt = $conn->prepare("
    SELECT SUM(amount) as total FROM company_payments 
    WHERE payment_status = 'completed' 
    AND MONTH(payment_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(payment_date) = YEAR(CURRENT_DATE())
");
$stmt->execute();
$monthly_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get recent companies
$stmt = $conn->prepare("
    SELECT * FROM companies 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get subscription plan statistics
$stmt = $conn->prepare("
    SELECT subscription_plan, COUNT(*) as count 
    FROM companies 
    WHERE subscription_status IN ('active', 'trial')
    GROUP BY subscription_plan
");
$stmt->execute();
$plan_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent payments
$stmt = $conn->prepare("
    SELECT cp.*, c.company_name 
    FROM company_payments cp
    JOIN companies c ON cp.company_id = c.id
    ORDER BY cp.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4"><?php echo __('super_admin_dashboard'); ?></h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_companies'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_companies; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
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
                                <?php echo __('active_subscriptions'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $active_companies; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                <?php echo __('trial_companies'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $trial_companies; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                <?php echo __('monthly_revenue'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo formatCurrency($monthly_revenue); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="companies/add.php" class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> <?php echo __('add_company'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="subscription-plans/" class="btn btn-success btn-block">
                                <i class="fas fa-list"></i> <?php echo __('manage_plans'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="payments/" class="btn btn-info btn-block">
                                <i class="fas fa-money-bill"></i> <?php echo __('view_payments'); ?>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="settings/" class="btn btn-warning btn-block">
                                <i class="fas fa-cogs"></i> <?php echo __('system_settings'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Companies and Payments -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('recent_companies'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_companies)): ?>
                        <p class="text-muted"><?php echo __('no_companies_found'); ?></p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_companies as $company): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($company['contact_email']); ?>
                                        </small>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge <?php 
                                            echo $company['subscription_status'] === 'active' ? 'bg-success' : 
                                                ($company['subscription_status'] === 'trial' ? 'bg-warning' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($company['subscription_status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo formatDate($company['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="companies/" class="btn btn-primary btn-sm"><?php echo __('view_all_companies'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('recent_payments'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_payments)): ?>
                        <p class="text-muted"><?php echo __('no_payments_found'); ?></p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_payments as $payment): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($payment['company_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($payment['payment_code']); ?>
                                        </small>
                                    </div>
                                    <div class="text-right">
                                        <strong class="text-success">
                                            <?php echo formatCurrency($payment['amount']); ?>
                                        </strong>
                                        <br>
                                        <span class="badge <?php 
                                            echo $payment['payment_status'] === 'completed' ? 'bg-success' : 
                                                ($payment['payment_status'] === 'pending' ? 'bg-warning' : 'bg-danger'); 
                                        ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-3">
                            <a href="payments/" class="btn btn-primary btn-sm"><?php echo __('view_all_payments'); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscription Plan Statistics -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('subscription_plan_statistics'); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($plan_stats)): ?>
                        <p class="text-muted"><?php echo __('no_subscription_data_available'); ?></p>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($plan_stats as $plan): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-left-info">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col mr-2">
                                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                        <?php echo ucfirst($plan['subscription_plan']); ?> <?php echo __('plan'); ?></div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                                        <?php echo $plan['count']; ?> <?php echo __('companies'); ?>
                                                    </div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- System Overview -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><?php echo __('system_overview'); ?></h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><?php echo __('system_information'); ?></h6>
                            <ul class="list-unstyled">
                                <li><strong><?php echo __('total_users'); ?>:</strong> <?php echo $total_users; ?></li>
                                <li><strong><?php echo __('active_companies'); ?>:</strong> <?php echo $active_companies; ?></li>
                                <li><strong><?php echo __('trial_companies'); ?>:</strong> <?php echo $trial_companies; ?></li>
                                <li><strong><?php echo __('monthly_revenue'); ?>:</strong> <?php echo formatCurrency($monthly_revenue); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><?php echo __('quick_links'); ?></h6>
                            <div class="list-group">
                                <a href="companies/" class="list-group-item list-group-item-action">
                                    <i class="fas fa-building"></i> <?php echo __('manage_companies'); ?>
                                </a>
                                <a href="subscription-plans/" class="list-group-item list-group-item-action">
                                    <i class="fas fa-list"></i> <?php echo __('subscription_plans'); ?>
                                </a>
                                <a href="payments/" class="list-group-item list-group-item-action">
                                    <i class="fas fa-money-bill"></i> <?php echo __('payment_history'); ?>
                                </a>
                                <a href="settings/" class="list-group-item list-group-item-action">
                                    <i class="fas fa-cogs"></i> <?php echo __('system_settings'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>