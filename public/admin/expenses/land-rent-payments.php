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

// Create table if not exists (id, company_id, payment_date, amount, currency, method, reference, notes, created_at)
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

$stmt=$conn->prepare("SELECT * FROM land_rent_payments WHERE company_id = ? ORDER BY payment_date DESC, id DESC");
$stmt->execute([$company_id]);
$payments=$stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Land Rent Payments</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
 body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#222}
 table{width:100%;border-collapse:collapse}
 th,td{border:1px solid #ddd;padding:8px;font-size:13px}
 th{background:#f7f7fb;text-align:left}
 .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
 .btn{display:inline-block;padding:8px 12px;border:1px solid #444;border-radius:4px;text-decoration:none;color:#222}
 .btn-primary{background:#111;color:#fff;border-color:#111}
 .right{text-align:right}
 .alert{padding:10px;border-radius:4px;margin-bottom:10px}
 .alert-success{background:#e7f6ed;color:#17673a}
 .alert-danger{background:#fcebea;color:#9b1c1c}
</style>
</head>
<body>
<h2><i class="fas fa-credit-card"></i> Land Rent Payments</h2>
<div style="margin:10px 0"><a href="index.php" class="btn">Back to Expenses</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<h3>Add Payment</h3>
<form method="POST">
  <div class="grid">
    <div>
      <label>Date</label>
      <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" style="width:100%">
    </div>
    <div>
      <label>Amount</label>
      <input type="number" step="0.01" min="0.01" name="amount" style="width:100%">
    </div>
    <div>
      <label>Currency</label>
      <select name="currency" style="width:100%">
        <option value="USD">USD</option>
        <option value="AFN">AFN</option>
        <option value="EUR">EUR</option>
        <option value="GBP">GBP</option>
      </select>
    </div>
    <div>
      <label>Method</label>
      <input type="text" name="method" placeholder="cash, bank, etc" style="width:100%">
    </div>
    <div>
      <label>Reference</label>
      <input type="text" name="reference" placeholder="Reference number" style="width:100%">
    </div>
    <div>
      <label>Notes</label>
      <input type="text" name="notes" placeholder="Optional" style="width:100%">
    </div>
  </div>
  <div style="margin-top:12px"><button type="submit" class="btn btn-primary">Record Payment</button></div>
</form>

<h3 style="margin-top:20px">Payment History</h3>
<table>
  <thead>
    <tr>
      <th>Date</th>
      <th>Amount</th>
      <th>Currency</th>
      <th>Method</th>
      <th>Reference</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($payments)): ?>
    <tr><td colspan="6" class="muted">No payments recorded.</td></tr>
    <?php else: foreach ($payments as $p): ?>
    <tr>
      <td><?php echo htmlspecialchars($p['payment_date']); ?></td>
      <td class="right"><?php echo formatCurrencyAmount((float)$p['amount'], $p['currency'] ?? 'USD'); ?></td>
      <td><?php echo htmlspecialchars($p['currency'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($p['method'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($p['reference'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($p['notes'] ?? ''); ?></td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
</body>
</html>