<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$space_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$space_id) {
    http_response_code(400);
    echo 'Missing space id';
    exit;
}

// Fetch parking space (include descriptive fields if present)
$stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ? AND company_id = ?");
$stmt->execute([$space_id, $company_id]);
$space = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$space) {
    http_response_code(404);
    echo 'Parking space not found';
    exit;
}
$currency = $space['currency'] ?? 'USD';

// Fetch rentals for this space (include commonly used fields)
$stmt = $conn->prepare("SELECT * FROM parking_rentals WHERE parking_space_id = ? AND company_id = ? ORDER BY start_date DESC");
$stmt->execute([$space_id, $company_id]);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Payments across rentals
$payCols = [];
try { $payCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM parking_payments")->fetchAll(PDO::FETCH_ASSOC)); } catch (Exception $e) {}
$hasCurrency = in_array('currency', $payCols, true);
$hasCompanyCol = in_array('company_id', $payCols, true);

$payments = [];
$total_by_currency = [];
if (!empty($rentals)) {
    $rental_ids = array_column($rentals, 'id');
    $ph = implode(',', array_fill(0, count($rental_ids), '?'));
    // Resolve column aliases based on existing schema
    $hasPaymentDate = in_array('payment_date', $payCols, true);
    $methodCol = in_array('method', $payCols, true) ? 'method' : (in_array('payment_method', $payCols, true) ? 'payment_method' : null);
    $referenceCol = in_array('reference', $payCols, true) ? 'reference' : (in_array('reference_number', $payCols, true) ? 'reference_number' : null);
    $statusCol = in_array('status', $payCols, true) ? 'status' : (in_array('payment_status', $payCols, true) ? 'payment_status' : null);
    $notesCol = in_array('notes', $payCols, true) ? 'notes' : null;

    $sql = "SELECT id, rental_id, amount";
    $sql .= $hasPaymentDate ? ", payment_date" : ", NULL as payment_date";
    $sql .= $methodCol ? ", $methodCol as method" : ", NULL as method";
    $sql .= $referenceCol ? ", $referenceCol as reference" : ", NULL as reference";
    $sql .= $statusCol ? ", $statusCol as status" : ", NULL as status";
    $sql .= $notesCol ? ", $notesCol as notes" : ", NULL as notes";
    $sql .= $hasCurrency ? ", COALESCE(currency,'USD') as currency" : ", ? as currency";
    $sql .= " FROM parking_payments WHERE rental_id IN ($ph) AND company_id = ? ORDER BY ";
    $sql .= $hasPaymentDate ? "payment_date DESC" : "id DESC";
    $stmt = $conn->prepare($sql);
    $params = $rental_ids; if (!$hasCurrency) { $params[] = $currency; } $params[] = $company_id;
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($payments as $p) {
        $cur = $p['currency'] ?? 'USD';
        $total_by_currency[$cur] = ($total_by_currency[$cur] ?? 0) + (float)($p['amount'] ?? 0);
    }
}

// Compute per-rental financials (days, expected, paid, due)
$perRental = [];
$overall_expected = 0.0;
foreach ($rentals as $r) {
    $rental_currency = $r['currency'] ?? $currency;
    $start = !empty($r['start_date']) ? new DateTime($r['start_date']) : new DateTime();
    $asOf = !empty($r['end_date']) ? new DateTime($r['end_date']) : new DateTime();
    $days = max(0, $start->diff($asOf)->days);
    $daily = ((float)($r['monthly_rate'] ?? 0)) / 30.0;
    $expected = !empty($r['total_amount']) ? (float)$r['total_amount'] : ($daily * $days);
    // Sum paid for this rental in matching currency
    $sumSql = "SELECT COALESCE(SUM(amount),0) FROM parking_payments WHERE rental_id = ? AND company_id = ?" . ($hasCurrency ? " AND COALESCE(currency, ?) = ?" : "");
    $sumStmt = $conn->prepare($sumSql);
    $sumParams = [$r['id'], $company_id]; if ($hasCurrency) { $sumParams[] = $rental_currency; $sumParams[] = $rental_currency; }
    $sumStmt->execute($sumParams);
    $paid = (float)$sumStmt->fetchColumn();
    $due = max(0.0, $expected - $paid);
    $perRental[] = [
        'rental_code' => $r['rental_code'] ?? 'N/A',
        'client_name' => $r['client_name'] ?? 'N/A',
        'client_contact' => $r['client_contact'] ?? '-',
        'vehicle_type' => $r['vehicle_type'] ?? '-',
        'vehicle_registration' => $r['vehicle_registration'] ?? '-',
        'start_date' => $r['start_date'] ?? null,
        'end_date' => $r['end_date'] ?? null,
        'days' => $days,
        'monthly_rate' => (float)($r['monthly_rate'] ?? 0),
        'currency' => $rental_currency,
        'expected' => $expected,
        'paid' => $paid,
        'due' => $due,
        'status' => $r['status'] ?? 'unknown',
    ];
    $overall_expected += $expected;
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parking Summary - <?php echo htmlspecialchars($space['space_code']); ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; }
  h1, h2, h3, h4 { margin: 0 0 10px 0; }
  .muted { color: #666; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 24px; }
  .card { border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; }
  .card-header { background: #f5f6fa; border-bottom: 1px solid #e5e7eb; padding: 10px 14px; font-weight: 600; }
  .card-body { padding: 12px 14px; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
  th { background: #f8f9fb; }
  .right { text-align: right; }
  .small { font-size: 12px; }
  .xsmall { font-size: 11px; }
  .stacked > div { line-height: 1.2; }
  .stacked > div.small { font-size: 12px; opacity: 0.85; }
  .actions { margin-bottom: 12px; }
  .btn { display:inline-block; padding:8px 12px; border:1px solid #444; border-radius:4px; text-decoration:none; color:#222; }
  .btn-primary { background:#111; color:#fff; border-color:#111; }
  .wrap { white-space: normal; word-break: break-word; }
  .wrap-any { white-space: normal; overflow-wrap: anywhere; word-break: break-word; hyphens: auto; }
  .nowrap { white-space: nowrap; }
  .table-narrow th, .table-narrow td { padding: 6px; font-size: 11px; }
  .w-10 { width: 10%; }
  .w-12 { width: 12%; }
  .w-14 { width: 14%; }
  .w-15 { width: 15%; }
  .w-16 { width: 16%; }
  .w-18 { width: 18%; }
  .w-20 { width: 20%; }
  .w-22 { width: 22%; }
  @media print { .actions { display: none; } body { margin: 12mm; } }
</style>
</head>
<body>
<div class="actions">
  <a href="javascript:window.print()" class="btn btn-primary"><?php echo __('print'); ?></a>
  <a href="view.php?id=<?php echo $space_id; ?>" class="btn"><?php echo __('back'); ?></a>
</div>

<h2><?php echo __('parking_space_summary'); ?></h2>
<div class="muted small"><?php echo __('printed'); ?>: <?php echo date('Y-m-d H:i'); ?></div>

<div class="card">
  <div class="card-header"><?php echo __('space_information'); ?></div>
  <div class="card-body">
    <div class="grid">
      <div><strong><?php echo __('space_code'); ?>:</strong> <?php echo htmlspecialchars($space['space_code']); ?></div>
      <div><strong><?php echo __('status'); ?>:</strong> <?php echo ucfirst(htmlspecialchars($space['status'])); ?></div>
      <div><strong><?php echo __('space_name'); ?>:</strong> <?php echo htmlspecialchars($space['space_name'] ?? 'N/A'); ?></div>
      <div><strong><?php echo __('vehicle_category'); ?>:</strong> <?php echo htmlspecialchars($space['vehicle_category'] ?? 'general'); ?></div>
      <div><strong><?php echo __('space_type'); ?>:</strong> <?php echo htmlspecialchars($space['space_type'] ?? 'standard'); ?></div>
      <div><strong><?php echo __('size'); ?>:</strong> <?php echo htmlspecialchars($space['size'] ?? 'medium'); ?></div>
      <div><strong><?php echo __('monthly_rate'); ?>:</strong> <?php echo formatCurrencyAmount((float)($space['monthly_rate'] ?? 0), $currency); ?></div>
      <div><strong><?php echo __('daily_rate'); ?>:</strong> <?php echo formatCurrencyAmount(((float)($space['monthly_rate'] ?? 0))/30.0, $currency); ?></div>
      <div><strong><?php echo __('capacity'); ?>:</strong> <?php echo isset($space['capacity']) ? (int)$space['capacity'] : '-'; ?></div>
      <div><strong><?php echo __('created'); ?>:</strong> <?php echo !empty($space['created_at']) ? date('M j, Y', strtotime($space['created_at'])) : '-'; ?></div>
      <?php if (!empty($space['description'])): ?>
      <div style="grid-column: 1 / span 2;"><strong><?php echo __('description'); ?>:</strong><br><span class="small wrap"><?php echo nl2br(htmlspecialchars($space['description'])); ?></span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('earnings'); ?></div>
  <div class="card-body">
    <?php if (empty($total_by_currency)): ?>
      <div class="small muted"><?php echo __('no_payments_received'); ?></div>
    <?php else: ?>
      <div class="stacked">
        <?php foreach ($total_by_currency as $cur => $sum): ?>
          <div><?php echo formatCurrencyAmount($sum, $cur); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('rentals'); ?></div>
  <div class="card-body">
    <?php if (empty($perRental)): ?>
      <div class="small muted"><?php echo __('no_rentals_found_for_this_space'); ?></div>
    <?php else: ?>
      <table class="table-narrow rentals-table">
        <thead>
          <tr>
            <th class="w-12"><?php echo __('rental_code'); ?></th>
            <th class="w-14"><?php echo __('client'); ?></th>
            <th class="w-14"><?php echo __('contact'); ?></th>
            <th class="w-14"><?php echo __('vehicle'); ?></th>
            <th class="w-18"><?php echo __('period'); ?></th>
            <th class="w-10 right"><?php echo __('days'); ?></th>
            <th class="w-14 right"><?php echo __('rate_mo'); ?></th>
            <th class="w-14 right"><?php echo __('expected'); ?></th>
            <th class="w-14 right"><?php echo __('paid'); ?></th>
            <th class="w-14 right"><?php echo __('due'); ?></th>
            <th class="w-10"><?php echo __('status'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($perRental as $row): ?>
            <tr>
              <td class="xsmall wrap-any"><?php echo htmlspecialchars($row['rental_code']); ?></td>
              <td class="xsmall wrap-any"><?php echo htmlspecialchars($row['client_name']); ?></td>
              <td class="xsmall wrap-any"><?php echo htmlspecialchars($row['client_contact']); ?></td>
              <td class="xsmall wrap-any"><?php echo htmlspecialchars(trim(($row['vehicle_type'] ?? '-') . ' ' . ($row['vehicle_registration'] ?? ''))); ?></td>
              <td class="xsmall wrap-any">
                <?php echo $row['start_date'] ? date('M j, Y', strtotime($row['start_date'])) : '-'; ?>
                <?php echo $row['end_date'] ? ' — ' . date('M j, Y', strtotime($row['end_date'])) : ' — Ongoing'; ?>
              </td>
              <td class="right xsmall nowrap"><?php echo (int)$row['days']; ?></td>
              <td class="right xsmall nowrap"><?php echo formatCurrencyAmount($row['monthly_rate'], $row['currency']); ?></td>
              <td class="right xsmall nowrap"><?php echo formatCurrencyAmount($row['expected'], $row['currency']); ?></td>
              <td class="right xsmall nowrap"><?php echo formatCurrencyAmount($row['paid'], $row['currency']); ?></td>
              <td class="right xsmall nowrap"><?php echo formatCurrencyAmount($row['due'], $row['currency']); ?></td>
              <td class="xsmall nowrap"><?php echo ucfirst(htmlspecialchars($row['status'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="small muted" style="margin-top:8px;"><?php echo __('overall_expected'); ?>: <?php echo formatCurrencyAmount($overall_expected, $currency); ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><?php echo __('payments'); ?></div>
  <div class="card-body">
    <?php if (empty($payments)): ?>
      <div class="small muted"><?php echo __('no_payments_found'); ?></div>
    <?php else: ?>
      <table class="table-narrow">
        <thead>
          <tr>
            <th class="w-14"><?php echo __('date'); ?></th>
            <th class="w-18"><?php echo __('reference'); ?></th>
            <th class="w-16"><?php echo __('method'); ?></th>
            <th class="w-12"><?php echo __('status'); ?></th>
            <th class="w-20"><?php echo __('notes'); ?></th>
            <th class="w-10 right"><?php echo __('amount'); ?></th>
            <th class="w-10"><?php echo __('currency'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td class="xsmall nowrap"><?php echo $p['payment_date'] ? date('M j, Y', strtotime($p['payment_date'])) : '-'; ?></td>
              <td class="xsmall wrap"><?php echo htmlspecialchars($p['reference'] ?? '-'); ?></td>
              <td class="xsmall wrap"><?php echo htmlspecialchars($p['method'] ?? '-'); ?></td>
              <td class="xsmall nowrap"><?php echo htmlspecialchars($p['status'] ?? '-'); ?></td>
              <td class="xsmall wrap"><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></td>
              <td class="xsmall right nowrap"><?php echo formatCurrencyAmount((float)($p['amount'] ?? 0), $p['currency'] ?? 'USD'); ?></td>
              <td class="xsmall nowrap"><?php echo htmlspecialchars($p['currency'] ?? 'USD'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>