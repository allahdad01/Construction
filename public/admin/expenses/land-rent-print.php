<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';
requireAuth();
requireAnyRole(['company_admin','super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

// Load settings
function cs(PDO $c, $cid, $key, $def=''){
  $s=$c->prepare('SELECT setting_value FROM company_settings WHERE company_id=? AND setting_key=?');
  $s->execute([$cid,$key]);
  $r=$s->fetch(PDO::FETCH_ASSOC); return $r ? $r['setting_value'] : $def;
}
$land = [
  'enabled' => (int)cs($conn,$company_id,'land_rent_enabled','0'),
  'type' => cs($conn,$company_id,'land_rent_type','monthly'),
  'amount' => (float)cs($conn,$company_id,'land_rent_amount','0'),
  'currency' => cs($conn,$company_id,'land_rent_currency','USD'),
  'start_date' => cs($conn,$company_id,'land_rent_start_date',date('Y-m-01')),
  'advance_paid' => (float)cs($conn,$company_id,'land_rent_advance_paid','0'),
  'extra_paid' => (float)cs($conn,$company_id,'land_rent_extra_paid','0'),
];
$days=0;$months=0;$owed=0.0;$remain=0.0;$paid=$land['advance_paid']+$land['extra_paid'];$daily=0.0;
try{ $st=new DateTime($land['start_date']); $td=new DateTime(date('Y-m-d')); if($td>=$st){ $di=$st->diff($td); $days=(int)$di->days; $months = (int)$di->m + ($di->y*12); $daily = ($land['type']==='yearly')?($land['amount']/365.0):($land['amount']/30.0); $owed=$daily*$days; $remain=max(0.0,$owed-$paid);} }catch(Exception $e){}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Land Rent Statement</title>
<style>
 body{font-family:Arial,Helvetica,sans-serif;margin:20px;color:#222}
 h2{margin:0 0 10px 0}
 .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px 24px}
 table{width:100%;border-collapse:collapse}
 th,td{border:1px solid #ddd;padding:8px;font-size:13px}
 th{background:#f7f7fb;text-align:left}
 .right{text-align:right}
 .muted{color:#666}
 .actions{margin-bottom:12px}
 .btn{display:inline-block;padding:8px 12px;border:1px solid #444;border-radius:4px;text-decoration:none;color:#222}
 .btn-primary{background:#111;color:#fff;border-color:#111}
 @media print{.actions{display:none}}
</style>
</head>
<body>
<div class="actions">
  <a href="javascript:window.print()" class="btn btn-primary">Print</a>
  <a href="index.php" class="btn">Back</a>
</div>
<h2>Land Rent Statement</h2>
<div class="muted">Generated: <?php echo date('Y-m-d H:i'); ?></div>

<table class="mt-2">
  <tr><th style="width:30%">Status</th><td><?php echo $land['enabled']? 'Rented' : 'Not Rented'; ?></td></tr>
  <tr><th>Type</th><td><?php echo ucfirst($land['type']); ?></td></tr>
  <tr><th>Amount</th><td><?php echo formatCurrencyAmount((float)$land['amount'], $land['currency']); ?> per <?php echo $land['type']==='yearly'?'year':'month'; ?></td></tr>
  <tr><th>Start Date</th><td><?php echo htmlspecialchars($land['start_date']); ?></td></tr>
</table>

<h3>Summary</h3>
<table>
  <tr><th>Duration</th><td><?php echo number_format($days); ?> days (<?php echo number_format($months); ?> months)</td></tr>
  <tr><th>Owed Until Today</th><td><?php echo formatCurrencyAmount($owed, $land['currency']); ?></td></tr>
  <tr><th>Paid To Date</th><td><?php echo formatCurrencyAmount($paid, $land['currency']); ?></td></tr>
  <tr><th>Remaining</th><td><?php echo formatCurrencyAmount($remain, $land['currency']); ?></td></tr>
</table>

</body>
</html>