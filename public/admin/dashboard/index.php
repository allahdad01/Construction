<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';
require_once '../../../includes/header.php';

requireAuth();
requireAnyRole(['company_admin','super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

// Counts filtered by company
$stmt = $conn->prepare("SELECT COUNT(*) FROM employees WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company_id]);
$employeeCount = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM machines WHERE company_id = ? AND (status IS NULL OR status != 'retired')");
$stmt->execute([$company_id]);
$machineCount = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM contracts WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company_id]);
$contractCount = (int)$stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COUNT(*) FROM projects WHERE company_id = ? AND status = 'active'");
$stmt->execute([$company_id]);
$projectCount = (int)$stmt->fetchColumn();

// Monthly expenses per currency
$stmt = $conn->prepare("SELECT COALESCE(currency,'USD') currency, COALESCE(SUM(amount),0) total FROM expenses WHERE company_id = ? AND MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE()) GROUP BY COALESCE(currency,'USD') ORDER BY total DESC");
$stmt->execute([$company_id]);
$monthlyExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly revenue per currency (sum payments)
$revenue = [];
try {
    // Contract payments
    $s = $conn->prepare("SELECT COALESCE(currency,'USD') currency, COALESCE(SUM(amount),0) total FROM contract_payments WHERE company_id = ? AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) GROUP BY COALESCE(currency,'USD')");
    $s->execute([$company_id]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $revenue[$r['currency']] = ($revenue[$r['currency']] ?? 0) + (float)$r['total']; }
} catch (Exception $e) {}
try {
    $s = $conn->prepare("SELECT COALESCE(currency,'USD') currency, COALESCE(SUM(amount),0) total FROM area_rental_payments WHERE company_id = ? AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) GROUP BY COALESCE(currency,'USD')");
    $s->execute([$company_id]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $revenue[$r['currency']] = ($revenue[$r['currency']] ?? 0) + (float)$r['total']; }
} catch (Exception $e) {}
try {
    $s = $conn->prepare("SELECT COALESCE(currency,'USD') currency, COALESCE(SUM(amount),0) total FROM parking_payments WHERE company_id = ? AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) GROUP BY COALESCE(currency,'USD')");
    $s->execute([$company_id]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $revenue[$r['currency']] = ($revenue[$r['currency']] ?? 0) + (float)$r['total']; }
} catch (Exception $e) {}
$monthlyRevenue = [];
foreach ($revenue as $cur => $amt) { $monthlyRevenue[] = ['currency'=>$cur,'total'=>$amt]; }
usort($monthlyRevenue, function($a,$b){ return $b['total'] <=> $a['total']; });

// Recent activities (company-scoped)
$stmt = $conn->prepare("(
    SELECT 'contract' as type, contract_code as code, 'New contract created' as description, created_at 
    FROM contracts WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
) UNION ALL (
    SELECT 'employee' as type, employee_code as code, CONCAT('New ', position, ' hired') as description, created_at 
    FROM employees WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
) UNION ALL (
    SELECT 'machine' as type, machine_code as code, 'New machine added' as description, created_at 
    FROM machines WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
) ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$company_id, $company_id, $company_id]);
$recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="row"><div class="col-12"><h1 class="h3 mb-4"><?php echo __('tenant_dashboard'); ?></h1></div></div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?php echo __('active_employees'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $employeeCount; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1"><?php echo __('available_machines'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $machineCount; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1"><?php echo __('active_contracts'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $contractCount; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-file-contract fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1"><?php echo __('active_projects'); ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $projectCount; ?></div>
                        </div>
                        <div class="col-auto"><i class="fas fa-project-diagram fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary (This Month) -->
    <div class="row">
        <div class="col-xl-8 col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('financial_summary_this_month'); ?></h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6 class="text-success"><?php echo __('total_revenue'); ?></h6>
                                <?php if (!empty($monthlyRevenue)): foreach ($monthlyRevenue as $i=>$r): ?>
                                    <div class="<?php echo $i>0?'small':''; ?>"><?php echo formatCurrencyAmount($r['total'], $r['currency']); ?></div>
                                <?php endforeach; else: ?>
                                    <div class="text-muted"><?php echo __('no_revenue'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-center">
                                <h6 class="text-danger"><?php echo __('total_expenses'); ?></h6>
                                <?php if (!empty($monthlyExpenses)): foreach ($monthlyExpenses as $i=>$e): ?>
                                    <div class="<?php echo $i>0?'small':''; ?>"><?php echo formatCurrencyAmount($e['total'], $e['currency']); ?></div>
                                <?php endforeach; else: ?>
                                    <div class="text-muted"><?php echo __('no_expenses'); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('quick_actions'); ?></h6></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3"><a href="../employees/add.php" class="btn btn-primary btn-block"><i class="fas fa-user-plus"></i> <?php echo __('add_employee'); ?></a></div>
                        <div class="col-6 mb-3"><a href="../machines/add.php" class="btn btn-success btn-block"><i class="fas fa-truck"></i> <?php echo __('add_machine'); ?></a></div>
                        <div class="col-6 mb-3"><a href="../contracts/add.php" class="btn btn-info btn-block"><i class="fas fa-file-contract"></i> <?php echo __('new_contract'); ?></a></div>
                        <div class="col-6 mb-3"><a href="../expenses/add.php" class="btn btn-warning btn-block"><i class="fas fa-dollar-sign"></i> <?php echo __('add_expense'); ?></a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                    <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('recent_activities'); ?></h6></div>
                <div class="card-body">
                    <?php if (empty($recentActivities)): ?>
                        <p class="text-muted"><?php echo __('no_recent_activities'); ?></p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($activity['code']); ?></strong>
                                        <span class="text-muted">- <?php echo htmlspecialchars($activity['description']); ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo formatDateTime($activity['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../../../includes/footer.php'; ?>