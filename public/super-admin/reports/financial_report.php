<?php
// Super Admin Financial Report
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$rev_by_day = [];
$exp_by_day = [];
try {
  // Revenue from subscription payments
  $stmt = $conn->prepare("SELECT DATE(payment_date) d, COALESCE(currency,'USD') c, SUM(amount) s FROM company_payments WHERE payment_status='completed' AND payment_date BETWEEN ? AND ? GROUP BY DATE(payment_date), COALESCE(currency,'USD') ORDER BY d");
  $stmt->execute([$start_date, $end_date]);
  $rev_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  // Platform expenses (company_id IS NULL)
  $stmt = $conn->prepare("SELECT DATE(expense_date) d, COALESCE(currency,'USD') c, SUM(amount) s FROM expenses WHERE company_id IS NULL AND expense_date BETWEEN ? AND ? GROUP BY DATE(expense_date), COALESCE(currency,'USD') ORDER BY d");
  $stmt->execute([$start_date, $end_date]);
  $exp_by_day = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}
?>
<div class="card shadow mb-4">
  <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><?php echo __('financial_platform'); ?></h6></div>
  <div class="card-body">
    <div class="row">
      <div class="col-md-6">
        <h6><?php echo __('revenue_by_day'); ?></h6>
        <?php if ($rev_by_day): ?>
          <div class="table-responsive"><table class="table table-sm"><thead><tr><th><?php echo __('date'); ?></th><th><?php echo __('currency'); ?></th><th class="text-end"><?php echo __('amount'); ?></th></tr></thead><tbody>
            <?php foreach ($rev_by_day as $r): ?>
              <tr><td><?php echo htmlspecialchars($r['d']); ?></td><td><?php echo htmlspecialchars($r['c']); ?></td><td class="text-end"><?php echo formatCurrencyAmount((float)$r['s'], $r['c']); ?></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php else: ?>
          <div class="text-muted small"><?php echo __('no_revenue_in_range'); ?></div>
        <?php endif; ?>
      </div>
      <div class="col-md-6">
        <h6><?php echo __('expenses_by_day'); ?></h6>
        <?php if ($exp_by_day): ?>
          <div class="table-responsive"><table class="table table-sm"><thead><tr><th><?php echo __('date'); ?></th><th><?php echo __('currency'); ?></th><th class="text-end"><?php echo __('amount'); ?></th></tr></thead><tbody>
            <?php foreach ($exp_by_day as $r): ?>
              <tr><td><?php echo htmlspecialchars($r['d']); ?></td><td><?php echo htmlspecialchars($r['c']); ?></td><td class="text-end"><?php echo formatCurrencyAmount((float)$r['s'], $r['c']); ?></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php else: ?>
          <div class="text-muted small"><?php echo __('no_expenses_in_range'); ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>