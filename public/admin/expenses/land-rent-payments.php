<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';
requireAuth();
requireAnyRole(['company_admin','super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();
$error=''; $success='';

// Ensure payments table exists
try {
  $conn->exec("CREATE TABLE IF NOT EXISTS land_rent_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    method VARCHAR(50) NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at DATETIME NULL,
    INDEX idx_company_date(company_id, payment_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Handle new payment
if ($_SERVER['REQUEST_METHOD']==='POST') {
  try {
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    $amount = (float)($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'USD';
    $method = $_POST['method'] ?? 'cash';
    $reference = $_POST['reference'] ?? null;
    $notes = $_POST['notes'] ?? null;
    if ($amount <= 0) { throw new Exception('Amount must be greater than 0'); }
    $stmt=$conn->prepare("INSERT INTO land_rent_payments (company_id, payment_date, amount, currency, method, reference, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$company_id, $date, $amount, $currency, $method, $reference, $notes]);
    $success = 'Payment recorded.';
  } catch (Exception $e) { $error = $e->getMessage(); }
}

// Fetch payment history
$stmt=$conn->prepare("SELECT * FROM land_rent_payments WHERE company_id = ? ORDER BY payment_date DESC, id DESC");
$stmt->execute([$company_id]);
$payments=$stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Land Rent Payments';
require_once '../../../includes/header.php';

// Default currency from company setting (available after header include)
$defaultCurrency = 'USD';
if (function_exists('getCompanySettingLocal')) {
    try { $defaultCurrency = getCompanySettingLocal($conn, $company_id, 'land_rent_currency', 'USD'); } catch (Exception $e) {}
}
?>

<div class="container-fluid">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0"><i class="fas fa-credit-card me-2"></i>Land Rent Payments</h3>
    <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Expenses</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header">
          <h6 class="m-0">Add Payment</h6>
        </div>
        <div class="card-body">
          <form method="POST">
            <div class="mb-3">
              <label class="form-label">Date</label>
              <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Amount</label>
              <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="0.00" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Currency</label>
              <select name="currency" class="form-control">
                <option value="USD" <?php echo $defaultCurrency==='USD'?'selected':''; ?>>USD</option>
                <option value="AFN" <?php echo $defaultCurrency==='AFN'?'selected':''; ?>>AFN</option>
                <option value="EUR" <?php echo $defaultCurrency==='EUR'?'selected':''; ?>>EUR</option>
                <option value="GBP" <?php echo $defaultCurrency==='GBP'?'selected':''; ?>>GBP</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Method</label>
              <input type="text" name="method" class="form-control" placeholder="cash, bank, etc">
            </div>
            <div class="mb-3">
              <label class="form-label">Reference</label>
              <input type="text" name="reference" class="form-control" placeholder="Reference number">
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <input type="text" name="notes" class="form-control" placeholder="Optional">
            </div>
            <div class="d-grid">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Record Payment</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h6 class="m-0">Payment History</h6>
          <div>
            <button class="btn btn-sm btn-outline-secondary print-btn" data-target="paymentsCard"><i class="fas fa-print"></i></button>
          </div>
        </div>
        <div class="card-body" id="paymentsCard">
          <div class="table-responsive">
            <table class="table table-striped table-hover datatable" id="paymentsTable">
              <thead>
                <tr>
                  <th>Date</th>
                  <th class="text-end">Amount</th>
                  <th>Currency</th>
                  <th>Method</th>
                  <th>Reference</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($payments)): ?>
                  <tr><td colspan="6" class="text-muted">No payments recorded.</td></tr>
                <?php else: foreach ($payments as $p): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
                    <td class="text-end"><?php echo formatCurrencyAmount((float)$p['amount'], $p['currency'] ?? 'USD'); ?></td>
                    <td><?php echo htmlspecialchars($p['currency'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['method'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['reference'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($p['notes'] ?? ''); ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../../includes/footer.php'; ?>