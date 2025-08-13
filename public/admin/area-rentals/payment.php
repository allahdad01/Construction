<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../config/currency_helper.php';

// Check if user is authenticated and has appropriate role
requireAuth();
requireAnyRole(['company_admin', 'super_admin']);

$db = new Database();
$conn = $db->getConnection();
$company_id = getCurrentCompanyId();

$error = '';
$success = '';

// Get rental ID from URL
$rental_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$rental_id) {
    header('Location: index.php');
    exit;
}

// Detect payments table columns and whether company_id exists
$payCols = [];
try { $payCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM area_rental_payments")->fetchAll(PDO::FETCH_ASSOC)); } catch (Exception $e) {}
$hasPaymentCompanyId = in_array('company_id', $payCols, true);

// AJAX: fetch a payment record
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    try {
        $sql = "SELECT * FROM area_rental_payments WHERE id = ? AND area_rental_id = ?" . ($hasPaymentCompanyId ? " AND company_id = ?" : "");
        $stmt = $conn->prepare($sql);
        $params = [(int)($_GET['pid'] ?? 0), $rental_id]; if ($hasPaymentCompanyId) { $params[] = $company_id; }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: []);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Update/Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update') {
            $pid = (int)($_POST['id'] ?? 0);
            if (!$pid) { throw new Exception('Invalid payment id'); }
            // Allowed columns (present in this page schema); guard existence
            $allowed = [
                'amount' => ($_POST['amount'] === '' ? null : $_POST['amount']),
                'payment_method' => $_POST['payment_method'] ?? null,
                'reference_number' => $_POST['reference_number'] ?? null,
                'payment_date' => $_POST['payment_date'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'currency' => $_POST['currency'] ?? null,
            ];
            $setParts = [];
            $params = [];
            foreach ($allowed as $col => $val) {
                if (in_array($col, $payCols, true) && $val !== null) { $setParts[] = "$col = ?"; $params[] = $val; }
            }
            if (empty($setParts)) { throw new Exception('Nothing to update'); }
            $params[] = $pid; $params[] = $rental_id; if ($hasPaymentCompanyId) { $params[] = $company_id; }
            $sql = 'UPDATE area_rental_payments SET ' . implode(', ', $setParts) . ' WHERE id = ? AND area_rental_id = ?' . ($hasPaymentCompanyId ? ' AND company_id = ?' : '');
            $stmt = $conn->prepare($sql); $stmt->execute($params);
            header('Location: payment.php?id=' . $rental_id . '&success=1');
            exit;
        }
        if ($_POST['action'] === 'delete') {
            $pid = (int)($_POST['id'] ?? 0);
            if (!$pid) { throw new Exception('Invalid payment id'); }
            $sql = 'DELETE FROM area_rental_payments WHERE id = ? AND area_rental_id = ?' . ($hasPaymentCompanyId ? ' AND company_id = ?' : '');
            $stmt = $conn->prepare($sql);
            $params = [$pid, $rental_id]; if ($hasPaymentCompanyId) { $params[] = $company_id; }
            $stmt->execute($params);
            header('Location: payment.php?id=' . $rental_id . '&success=1');
            exit;
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Get rental details with area information
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        ra.area_name,
        ra.area_code,
        ra.area_type,
        ra.currency as area_currency,
        COALESCE(SUM(arp.amount), 0) as total_paid,
        COUNT(arp.id) as payment_count
    FROM area_rentals ar
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    LEFT JOIN area_rental_payments arp ON ar.id = arp.area_rental_id
    WHERE ar.id = ? AND ar.company_id = ?
    GROUP BY ar.id
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    header('Location: index.php');
    exit;
}

// Calculate amounts
$total_amount = $rental['monthly_rate'];
$total_paid = $rental['total_paid'] ?? 0;
$remaining_amount = $total_amount - $total_paid;

// Handle form submission for new payment (create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        // Validate payment amount
        $payment_amount = (float)$_POST['payment_amount'];
        if ($payment_amount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }

        if ($payment_amount > $remaining_amount) {
            throw new Exception("Payment amount cannot exceed remaining balance.");
        }

        // Validate required fields
        if (empty(trim($_POST['payment_method']))) {
            throw new Exception("Payment method is required.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert payment record
        $columns = ['area_rental_id','amount','payment_method','reference_number','payment_date','notes','currency'];
        $placeholders = '?,?,?,?,?,?,?';
        $values = [
            $rental_id,
            $payment_amount,
            trim($_POST['payment_method']),
            trim($_POST['reference_number'] ?? ''),
            $_POST['payment_date'] ?? date('Y-m-d'),
            trim($_POST['notes'] ?? ''),
            $rental['currency'] ?? 'USD'
        ];
        if ($hasPaymentCompanyId) { $columns[] = 'company_id'; $placeholders .= ',?'; $values[] = $company_id; }
        $sql = 'INSERT INTO area_rental_payments (' . implode(',', $columns) . ') VALUES (' . $placeholders . ')';
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);

        // Update rental status if fully paid
        $new_total_paid = $total_paid + $payment_amount;
        if ($new_total_paid >= $total_amount) {
            $stmt = $conn->prepare("UPDATE area_rentals SET status = 'paid' WHERE id = ?");
            $stmt->execute([$rental_id]);
        }

        // Commit transaction
        $conn->commit();

        $success = "Payment recorded successfully!";
        header("Location: payment.php?id=$rental_id&success=1");
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $error = $e->getMessage();
    }
}

// Get payment history
$sql = "SELECT * FROM area_rental_payments WHERE area_rental_id = ?" . ($hasPaymentCompanyId ? " AND company_id = ?" : "") . " ORDER BY payment_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$params = [$rental_id]; if ($hasPaymentCompanyId) { $params[] = $company_id; }
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Move header include after all potential redirects
require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-credit-card"></i> <?php echo __('area_rental_payment'); ?>
            </h1>
            <p class="text-muted mb-0"><?php echo __('manage_payments_for'); ?> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
        </div>
        <div class="btn-group" role="group">
            <a href="view.php?id=<?php echo $rental_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> <?php echo __('view_details'); ?>
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> <?php echo __('back_to_rentals'); ?>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo __('action_completed_successfully'); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Payment Form -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus"></i> <?php echo __('record_payment'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($remaining_amount > 0): ?>
                        <form method="POST" id="paymentForm">
                            <div class="mb-3">
                                <label for="payment_amount" class="form-label"><?php echo __('payment_amount'); ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text" id="currency-symbol">
                                        <?php echo $rental['currency'] === 'USD' ? '$' : ($rental['currency'] === 'AFN' ? '؋' : $rental['currency']); ?>
                                    </span>
                                    <input type="number" step="0.01" min="0.01" max="<?php echo $remaining_amount; ?>" 
                                           class="form-control" id="payment_amount" name="payment_amount" 
                                           value="<?php echo $remaining_amount; ?>" required>
                                </div>
                                <small class="form-text text-muted">
                                    <?php echo __('maximum'); ?>: <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                                </small>
                            </div>

                            <div class="mb-3">
                                <label for="payment_method" class="form-label"><?php echo __('payment_method'); ?> *</label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value=""><?php echo __('select_payment_method'); ?></option>
                                    <option value="cash"><?php echo __('cash'); ?></option>
                                    <option value="bank_transfer"><?php echo __('bank_transfer'); ?></option>
                                    <option value="check"><?php echo __('check'); ?></option>
                                    <option value="credit_card"><?php echo __('credit_card'); ?></option>
                                    <option value="mobile_payment"><?php echo __('mobile_payment'); ?></option>
                                    <option value="other"><?php echo __('other'); ?></option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="payment_date" class="form-label"><?php echo __('payment_date'); ?></label>
                                <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="reference_number" class="form-label"><?php echo __('reference_number'); ?></label>
                                <input type="text" class="form-control" id="reference_number" name="reference_number" 
                                       placeholder="<?php echo __('transaction_id_check_number_etc'); ?>"
                                       style="text-transform: none;" autocomplete="off" spellcheck="false">
                                <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_reference_numbers'); ?></small>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label"><?php echo __('notes'); ?></label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="<?php echo __('additional_payment_notes'); ?>"
                                          style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                                <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_notes'); ?></small>
                            </div>

                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-save"></i> <?php echo __('record_payment'); ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h6 class="text-success"><?php echo __('fully_paid'); ?>!</h6>
                            <p class="text-muted"><?php echo __('this_rental_has_been_fully_paid'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Payment Summary & History -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> <?php echo __('payment_summary'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="text-primary"><?php echo __('total_amount'); ?></h6>
                                <h4 class="text-primary">
                                    <?php echo formatCurrencyAmount($total_amount, $rental['currency'] ?? 'USD'); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="text-success"><?php echo __('total_paid'); ?></h6>
                                <h4 class="text-success">
                                    <?php echo formatCurrencyAmount($total_paid, $rental['currency'] ?? 'USD'); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center mb-3">
                                <h6 class="text-<?php echo $remaining_amount > 0 ? 'warning' : 'success'; ?>"><?php echo __('remaining'); ?></h6>
                                <h4 class="text-<?php echo $remaining_amount > 0 ? 'warning' : 'success'; ?>">
                                    <?php echo formatCurrencyAmount($remaining_amount, $rental['currency'] ?? 'USD'); ?>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="progress mb-3" style="height: 25px;">
                        <?php 
                        $percentage = $total_amount > 0 ? ($total_paid / $total_amount) * 100 : 0;
                        $percentage = min(100, max(0, $percentage));
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $percentage; ?>%" 
                             aria-valuenow="<?php echo $percentage; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                    </div>

                    <!-- Rental Details -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6 class="text-secondary"><?php echo __('rental_information'); ?></h6>
                            <p><strong><?php echo __('code'); ?>:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                            <p><strong><?php echo __('client'); ?>:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                            <p><strong><?php echo __('area'); ?>:</strong> <?php echo htmlspecialchars($rental['area_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-secondary"><?php echo __('payment_details'); ?></h6>
                            <p><strong><?php echo __('currency'); ?>:</strong> <?php echo $rental['currency'] ?? 'USD'; ?></p>
                            <p><strong><?php echo __('monthly_rate'); ?>:</strong> <?php echo formatCurrencyAmount($rental['monthly_rate'], $rental['currency'] ?? 'USD'); ?></p>
                            <p><strong><?php echo __('payments'); ?>:</strong> <?php echo $rental['payment_count']; ?> <?php echo __('records'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> <?php echo __('payment_history'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo __('no_payment_records_found'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="paymentsTable">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('amount'); ?></th>
                                        <th><?php echo __('method'); ?></th>
                                        <th><?php echo __('reference'); ?></th>
                                        <th><?php echo __('notes'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>
                                            <strong class="text-success">
                                                <?php echo formatCurrencyAmount($payment['amount'], $payment['currency'] ?? 'USD'); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($payment['reference_number'])): ?>
                                                <code><?php echo htmlspecialchars($payment['reference_number']); ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($payment['notes'])): ?>
                                                <?php echo nl2br(htmlspecialchars($payment['notes'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" onclick="viewPayment(<?php echo (int)$payment['id']; ?>)" title="View"><i class="fas fa-eye"></i></button>
                                                <button type="button" class="btn btn-outline-warning" onclick="editPayment(<?php echo (int)$payment['id']; ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                                                <button type="button" class="btn btn-outline-danger" onclick="deletePayment(<?php echo (int)$payment['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo __('payment_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewPaymentBody"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo __('edit_payment'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="editPaymentForm">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label"><?php echo __('amount'); ?></label>
            <input type="number" step="0.01" class="form-control" name="amount" id="edit_amount">
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('method'); ?></label>
            <select class="form-control" name="payment_method" id="edit_method">
              <option value="cash"><?php echo __('cash'); ?></option>
              <option value="bank_transfer"><?php echo __('bank_transfer'); ?></option>
              <option value="check"><?php echo __('check'); ?></option>
              <option value="credit_card"><?php echo __('credit_card'); ?></option>
              <option value="mobile_payment"><?php echo __('mobile_payment'); ?></option>
              <option value="other"><?php echo __('other'); ?></option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('payment_date'); ?></label>
            <input type="date" class="form-control" name="payment_date" id="edit_date">
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('reference'); ?></label>
            <input type="text" class="form-control" name="reference_number" id="edit_reference">
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('currency'); ?></label>
            <input type="text" class="form-control" name="currency" id="edit_currency">
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('notes'); ?></label>
            <textarea class="form-control" name="notes" id="edit_notes"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
          <button type="submit" class="btn btn-primary"><?php echo __('save_changes'); ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
async function viewPayment(id){
  try{
    const res = await fetch(`payment.php?id=<?php echo $rental_id; ?>&action=get&pid=${id}`);
    const d = await res.json();
    const html = `
      <div><strong><?php echo __('amount'); ?>:</strong> <?php echo getCurrencySymbol($rental['currency'] ?? 'USD'); ?>${(d.amount ?? 0)}</div>
      <div><strong><?php echo __('method'); ?>:</strong> ${(d.payment_method ?? '-')}</div>
      <div><strong><?php echo __('date'); ?>:</strong> ${(d.payment_date ?? '-')}</div>
      <div><strong><?php echo __('reference'); ?>:</strong> ${(d.reference_number ?? '-')}</div>
      <div><strong><?php echo __('currency'); ?>:</strong> ${(d.currency ?? '-')}</div>
      <div><strong><?php echo __('notes'); ?>:</strong><br>${(d.notes ?? '').toString().replace(/</g,'&lt;')}</div>
    `;
    document.getElementById('viewPaymentBody').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewPaymentModal')).show();
  }catch(e){ alert('<?php echo __('failed_to_load_payment'); ?>'); }
}

async function editPayment(id){
  try{
    const res = await fetch(`payment.php?id=<?php echo $rental_id; ?>&action=get&pid=${id}`);
    const d = await res.json();
    document.getElementById('edit_id').value = d.id || id;
    document.getElementById('edit_amount').value = d.amount || '';
    document.getElementById('edit_method').value = d.payment_method || 'cash';
    document.getElementById('edit_date').value = d.payment_date || '';
    document.getElementById('edit_reference').value = d.reference_number || '';
    document.getElementById('edit_currency').value = d.currency || '<?php echo $rental['currency'] ?? 'USD'; ?>';
    document.getElementById('edit_notes').value = d.notes || '';
    new bootstrap.Modal(document.getElementById('editPaymentModal')).show();
  }catch(e){ alert('<?php echo __('failed_to_load_payment_for_edit'); ?>'); }
}

async function deletePayment(id){
  if(!confirm('<?php echo __('delete_this_payment'); ?>')) return;
  const fd = new FormData();
  fd.append('action','delete');
  fd.append('id', id);
  const res = await fetch(`payment.php?id=<?php echo $rental_id; ?>`, { method:'POST', body: fd });
  if(res.ok){ location.reload(); } else { alert('Failed to delete'); }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>