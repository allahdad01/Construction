<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$rental_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$rental_id) {
    http_response_code(400);
    echo 'Missing rental id';
    exit;
}

// Fetch rental with area info
$stmt = $conn->prepare("
    SELECT ar.*, ra.area_name, ra.area_code, ra.area_type, ra.size
    FROM area_rentals ar
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    WHERE ar.id = ? AND ar.company_id = ?
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rental) {
    http_response_code(404);
    echo 'Rental not found';
    exit;
}

$currency = $rental['currency'] ?? 'USD';

// Computations
$startDt = new DateTime($rental['start_date']);
$asOfDate = new DateTime();
$interval = $startDt->diff($asOfDate);
$days_elapsed = $interval->invert === 1 ? 0 : ($interval->days + 1);
$daily_rate_effective = (float)($rental['daily_rate'] ?? 0);
if ($daily_rate_effective <= 0) { $daily_rate_effective = ((float)($rental['monthly_rate'] ?? 0)) / 30.0; }
$amount_paid_so_far = (float)($rental['amount_paid'] ?? 0);
$owed_until_date = $daily_rate_effective * max(0, $days_elapsed);
$outstanding_due = max(0, $owed_until_date - $amount_paid_so_far);

$range_text = date('M j, Y', strtotime($rental['start_date'])) . ' to ' . ($rental['end_date'] ? date('M j, Y', strtotime($rental['end_date'])) : 'present');

// Fetch payments
$payCols = [];
try { $payCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM area_rental_payments")->fetchAll(PDO::FETCH_ASSOC)); } catch (Exception $e) {}
$hasCompanyCol = in_array('company_id', $payCols, true);

$sql = "SELECT id, COALESCE(currency,'USD') as currency, amount";
$sql .= in_array('payment_date', $payCols, true) ? ", payment_date" : ", NULL as payment_date";
$sql .= in_array('method', $payCols, true) ? ", method" : ", NULL as method";
$sql .= in_array('reference', $payCols, true) ? ", reference" : ", NULL as reference";
$sql .= in_array('status', $payCols, true) ? ", status" : ", NULL as status";
$sql .= " FROM area_rental_payments WHERE area_rental_id = ?" . ($hasCompanyCol ? " AND company_id = ?" : "") . " ORDER BY ";
$sql .= in_array('payment_date', $payCols, true) ? "payment_date DESC" : "id DESC";
$stmt = $conn->prepare($sql);
$params = [$rental_id]; if ($hasCompanyCol) { $params[] = $company_id; }
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$total_by_currency = [];
foreach ($payments as $p) {
    $cur = $p['currency'] ?? 'USD';
    $total_by_currency[$cur] = ($total_by_currency[$cur] ?? 0) + (float)($p['amount'] ?? 0);
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rental Summary - <?php echo htmlspecialchars($rental['rental_code']); ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; }
  h1, h2, h3, h4 { margin: 0 0 10px 0; }
  .muted { color: #666; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; }
  .card { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; }
  .card-header { background: #f5f6fa; border-bottom: 1px solid #e5e7eb; padding: 10px 14px; font-weight: 600; }
  .card-body { padding: 12px 14px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
  th { background: #f8f9fb; }
  .right { text-align: right; }
  .small { font-size: 12px; }
  .stacked > div { line-height: 1.2; }
  .stacked > div.small { font-size: 12px; opacity: 0.85; }
  .actions { margin-bottom: 12px; }
  .btn { display:inline-block; padding:8px 12px; border:1px solid #444; border-radius:4px; text-decoration:none; color:#222; }
  .btn-primary { background:#111; color:#fff; border-color:#111; }
  @media print {
    .actions { display: none; }
    body { margin: 12mm; }
  }
</style>
</head>
<body>
<div class="actions">
  <a href="javascript:window.print()" class="btn btn-primary">Print</a>
  <a href="view.php?id=<?php echo $rental_id; ?>" class="btn">Back</a>
</div>

<h2>Area Rental Summary</h2>
<div class="muted small">Printed: <?php echo date('Y-m-d H:i'); ?></div>

<div class="card">
  <div class="card-header">Rental Information</div>
  <div class="card-body">
    <div class="grid">
      <div><strong>Rental Code:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></div>
      <div><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($rental['status'])); ?></div>
      <div><strong>Client:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></div>
      <div><strong>Contact:</strong> <?php echo htmlspecialchars($rental['client_contact'] ?? '-'); ?></div>
      <div><strong>Area:</strong> <?php echo htmlspecialchars($rental['area_name'] . ' (' . $rental['area_code'] . ')'); ?></div>
      <div><strong>Type/Size:</strong> <?php echo ucfirst(htmlspecialchars($rental['area_type'])); ?><?php echo !empty($rental['size']) ? (' • ' . htmlspecialchars($rental['size'])) : ''; ?></div>
      <div><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></div>
      <div><strong>End Date:</strong> <?php echo $rental['end_date'] ? date('M j, Y', strtotime($rental['end_date'])) : 'Ongoing'; ?></div>
      <div><strong>Range:</strong> <?php echo htmlspecialchars($range_text); ?></div>
      <div><strong>Days Elapsed:</strong> <?php echo (int)$days_elapsed; ?> days</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Financials</div>
  <div class="card-body">
    <table>
      <tbody>
        <tr>
          <th>Monthly Rate</th>
          <td class="right"><?php echo formatCurrencyAmount((float)($rental['monthly_rate'] ?? 0), $currency); ?></td>
          <th>Daily Rate (effective)</th>
          <td class="right"><?php echo formatCurrencyAmount((float)$daily_rate_effective, $currency); ?></td>
        </tr>
        <tr>
          <th>Total Amount</th>
          <td class="right"><?php echo formatCurrencyAmount((float)($rental['total_amount'] ?? 0), $currency); ?></td>
          <th>Amount Paid</th>
          <td class="right"><?php echo formatCurrencyAmount($amount_paid_so_far, $currency); ?></td>
        </tr>
        <tr>
          <th>Owed Until <?php echo $asOfDate->format('M j, Y'); ?></th>
          <td class="right"><?php echo formatCurrencyAmount($owed_until_date, $currency); ?></td>
          <th>Outstanding Due</th>
          <td class="right"><?php echo formatCurrencyAmount($outstanding_due, $currency); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">Payments</div>
  <div class="card-body">
    <?php if (empty($payments)): ?>
      <div class="small muted">No payments found.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width: 18%">Date</th>
            <th style="width: 20%">Reference</th>
            <th style="width: 18%">Method</th>
            <th style="width: 14%">Status</th>
            <th class="right" style="width: 15%">Amount</th>
            <th style="width: 15%">Currency</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?php echo $p['payment_date'] ? date('M j, Y', strtotime($p['payment_date'])) : '-'; ?></td>
              <td><?php echo htmlspecialchars($p['reference'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($p['method'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($p['status'] ?? '-'); ?></td>
              <td class="right"><?php echo formatCurrencyAmount((float)($p['amount'] ?? 0), $p['currency'] ?? 'USD'); ?></td>
              <td><?php echo htmlspecialchars($p['currency'] ?? 'USD'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="stacked" style="margin-top:8px;">
        <div class="small muted">Totals:</div>
        <?php foreach ($total_by_currency as $cur => $sum): ?>
          <div><?php echo formatCurrencyAmount($sum, $cur); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>