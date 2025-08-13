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
if (!$rental_id) { http_response_code(400); echo 'Missing rental id'; exit; }

// Fetch rental with space
$stmt = $conn->prepare("SELECT pr.*, ps.space_code, ps.space_name, ps.vehicle_category, ps.space_type, ps.size FROM parking_rentals pr JOIN parking_spaces ps ON pr.parking_space_id = ps.id WHERE pr.id = ? AND pr.company_id = ?");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rental) { http_response_code(404); echo 'Rental not found'; exit; }
$currency = $rental['currency'] ?? 'USD';

// Duration and expected
$start = new DateTime($rental['start_date']);
$asOf = !empty($rental['end_date']) ? new DateTime($rental['end_date']) : new DateTime();
$days = max(0, $start->diff($asOf)->days);
$daily = ((float)($rental['monthly_rate'] ?? 0)) / 30.0;
$expected = !empty($rental['total_amount']) ? (float)$rental['total_amount'] : ($daily * $days);

// Payments sum and list
$payCols = [];
try { $payCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM parking_payments")->fetchAll(PDO::FETCH_ASSOC)); } catch (Exception $e) {}
$hasCurrency = in_array('currency', $payCols, true);
$hasPaymentDate = in_array('payment_date', $payCols, true);
$methodCol = in_array('method', $payCols, true) ? 'method' : (in_array('payment_method', $payCols, true) ? 'payment_method' : null);
$referenceCol = in_array('reference', $payCols, true) ? 'reference' : (in_array('reference_number', $payCols, true) ? 'reference_number' : null);
$statusCol = in_array('status', $payCols, true) ? 'status' : (in_array('payment_status', $payCols, true) ? 'payment_status' : null);
$notesCol = in_array('notes', $payCols, true) ? 'notes' : null;

$sumSql = "SELECT COALESCE(SUM(amount),0) FROM parking_payments WHERE rental_id = ? AND company_id = ?" . ($hasCurrency ? " AND COALESCE(currency, ?) = ?" : "");
$sumStmt = $conn->prepare($sumSql); $sumParams = [$rental_id, $company_id]; if ($hasCurrency) { $sumParams[] = $currency; $sumParams[] = $currency; }
$sumStmt->execute($sumParams); $paid = (float)$sumStmt->fetchColumn();
$due = max(0.0, $expected - $paid);

$sql = "SELECT id, amount";
$sql .= $hasPaymentDate ? ", payment_date" : ", NULL as payment_date";
$sql .= $methodCol ? ", $methodCol as method" : ", NULL as method";
$sql .= $referenceCol ? ", $referenceCol as reference" : ", NULL as reference";
$sql .= $statusCol ? ", $statusCol as status" : ", NULL as status";
$sql .= $notesCol ? ", $notesCol as notes" : ", NULL as notes";
$sql .= $hasCurrency ? ", COALESCE(currency,'USD') as currency" : ", ? as currency";
$sql .= " FROM parking_payments WHERE rental_id = ? AND company_id = ? ORDER BY ";
$sql .= $hasPaymentDate ? "payment_date DESC" : "id DESC";
$stmt = $conn->prepare($sql);
$params = []; if (!$hasCurrency) { $params[] = $currency; } $params[] = $rental_id; $params[] = $company_id; 
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parking Rental - <?php echo htmlspecialchars($rental['rental_code']); ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; }
  h2 { margin: 0 0 10px 0; }
  .muted { color: #666; }
  .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; }
  .card { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; }
  .card-header { background:#f5f6fa; padding:10px 14px; font-weight:600; border-bottom:1px solid #e5e7eb; }
  .card-body { padding: 12px 14px; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border:1px solid #ddd; padding:6px; font-size: 12px; }
  th { background:#f8f9fb; }
  .right { text-align:right; }
  .actions { margin-bottom:12px; }
  .btn { display:inline-block; padding:8px 12px; border:1px solid #444; border-radius:4px; text-decoration:none; color:#222; }
  .btn-primary { background:#111; color:#fff; border-color:#111; }
  .wrap { white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
  @media print { .actions { display:none; } body { margin: 12mm; } }
</style>
</head>
<body>
<div class="actions">
  <a href="javascript:window.print()" class="btn btn-primary"><?php echo __('print'); ?></a>
  <a href="view-rental.php?id=<?php echo $rental_id; ?>" class="btn"><?php echo __('back'); ?></a>
</div>

<h2><?php echo __('parking_rental_summary'); ?></h2>
<div class="muted"><?php echo __('printed'); ?>: <?php echo date('Y-m-d H:i'); ?></div>

<div class="card">
  <div class="card-header"><?php echo __('rental_information'); ?></div>
  <div class="card-body">
    <div class="grid">
      <div><strong><?php echo __('rental_code'); ?>:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></div>
      <div><strong><?php echo __('status'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($rental['status'])); ?></div>
      <div><strong><?php echo __('client'); ?>:</strong> <?php echo htmlspecialchars($rental['client_name'] ?? 'N/A'); ?></div>
      <div><strong><?php echo __('contact'); ?>:</strong> <?php echo htmlspecialchars($rental['client_contact'] ?? '-'); ?></div>
      <div><strong><?php echo __('vehicle'); ?>:</strong> <?php echo htmlspecialchars(trim(($rental['vehicle_type'] ?? '-') . ' ' . ($rental['vehicle_registration'] ?? ''))); ?></div>
      <div><strong><?php echo __('space'); ?>:</strong> <?php echo htmlspecialchars($rental['space_name'] . ' (' . $rental['space_code'] . ')'); ?></div>
      <div><strong><?php echo __('start_date'); ?>:</strong> <?php echo date('M j, Y', strtotime($rental['start_date'])); ?></div>
      <div><strong><?php echo __('end_date'); ?>:</strong> <?php echo !empty($rental['end_date']) ? date('M j, Y', strtotime($rental['end_date'])) : 'Ongoing'; ?></div>
      <div><strong><?php echo __('days'); ?>:</strong> <?php echo (int)$days; ?></div>
      <div><strong><?php echo __('monthly_rate'); ?>:</strong> <?php echo formatCurrencyAmount((float)$rental['monthly_rate'], $currency); ?></div>
      <div><strong><?php echo __('daily_rate'); ?>:</strong> <?php echo formatCurrencyAmount($daily, $currency); ?></div>
      <div><strong><?php echo __('expected'); ?>:</strong> <?php echo formatCurrencyAmount($expected, $currency); ?></div>
      <div><strong><?php echo __('paid'); ?>:</strong> <?php echo formatCurrencyAmount($paid, $currency); ?></div>
      <div><strong><?php echo __('due'); ?>:</strong> <?php echo formatCurrencyAmount($due, $currency); ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('payments'); ?></div>
  <div class="card-body">
    <?php if (empty($payments)): ?>
      <div class="muted"><?php echo __('no_payments_found'); ?></div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th style="width:16%"><?php echo __('date'); ?></th>
            <th style="width:20%"><?php echo __('reference'); ?></th>
            <th style="width:16%"><?php echo __('method'); ?></th>
            <th style="width:14%"><?php echo __('status'); ?></th>
            <th style="width:24%"><?php echo __('notes'); ?></th>
            <th class="right" style="width:10%"><?php echo __('amount'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?php echo $p['payment_date'] ? date('M j, Y', strtotime($p['payment_date'])) : '-'; ?></td>
              <td class="wrap"><?php echo htmlspecialchars($p['reference'] ?? '-'); ?></td>
              <td class="wrap"><?php echo htmlspecialchars($p['method'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($p['status'] ?? '-'); ?></td>
              <td class="wrap"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></td>
              <td class="right"><?php echo formatCurrencyAmount((float)($p['amount'] ?? 0), $currency); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>