<?php
require_once '../../../config/config.php';
require_once '../../../config/database.php';

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

// Get rental details with area information
$stmt = $conn->prepare("
    SELECT 
        ar.*,
        ra.area_name,
        ra.area_code,
        ra.area_type
    FROM area_rentals ar
    LEFT JOIN rental_areas ra ON ar.rental_area_id = ra.id
    WHERE ar.id = ? AND ar.company_id = ?
");
$stmt->execute([$rental_id, $company_id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    header('Location: index.php');
    exit;
}

// Detect maintenance table columns
$__maintCols = [];
try {
    $__maintCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM area_rental_maintenance")->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { $__maintCols = []; }
$__hasMaintCompanyId = in_array('company_id', $__maintCols, true);

// AJAX: fetch a maintenance record
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    try {
        $sql = "SELECT * FROM area_rental_maintenance WHERE id = ? AND area_rental_id = ?" . ($__hasMaintCompanyId ? " AND company_id = ?" : "");
        $stmt = $conn->prepare($sql);
        $params = [(int)($_GET['mid'] ?? 0), $rental_id];
        if ($__hasMaintCompanyId) { $params[] = $company_id; }
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
            $mid = (int)($_POST['id'] ?? 0);
            if (!$mid) { throw new Exception('Invalid maintenance id'); }
            // Determine updatable columns dynamically
            $cols = $__maintCols;
            $allowed = [
                'maintenance_type' => trim($_POST['maintenance_type'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'priority' => $_POST['priority'] ?? null,
                'status' => $_POST['status'] ?? null,
                'estimated_cost' => ($_POST['estimated_cost'] === '' ? null : $_POST['estimated_cost']),
                'actual_cost' => ($_POST['actual_cost'] === '' ? null : $_POST['actual_cost']),
                'maintenance_date' => ($_POST['maintenance_date'] === '' ? null : $_POST['maintenance_date']),
                'completed_date' => ($_POST['completed_date'] === '' ? null : $_POST['completed_date']),
                'notes' => trim($_POST['notes'] ?? '')
            ];
            $setParts = [];
            $params = [];
            foreach ($allowed as $col => $val) {
                if (in_array($col, $cols, true)) {
                    $setParts[] = "$col = ?";
                    $params[] = $val;
                }
            }
            if (empty($setParts)) { throw new Exception('Nothing to update'); }
            $params[] = $mid;
            $params[] = $rental_id;
            $sql = 'UPDATE area_rental_maintenance SET ' . implode(', ', $setParts) . ' WHERE id = ? AND area_rental_id = ?' . ($__hasMaintCompanyId ? ' AND company_id = ?' : '');
            if ($__hasMaintCompanyId) { $params[] = $company_id; }
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            header('Location: maintenance.php?id=' . $rental_id . '&success=1');
            exit;
        }
        if ($_POST['action'] === 'delete') {
            $mid = (int)($_POST['id'] ?? 0);
            if (!$mid) { throw new Exception('Invalid maintenance id'); }
            $sql = 'DELETE FROM area_rental_maintenance WHERE id = ? AND area_rental_id = ?' . ($__hasMaintCompanyId ? ' AND company_id = ?' : '');
            $stmt = $conn->prepare($sql);
            $params = [$mid, $rental_id];
            if ($__hasMaintCompanyId) { $params[] = $company_id; }
            $stmt->execute($params);
            header('Location: maintenance.php?id=' . $rental_id . '&success=1');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle form submission for new maintenance record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty(trim($_POST['maintenance_type']))) {
            throw new Exception("Maintenance type is required.");
        }

        if (empty(trim($_POST['description']))) {
            throw new Exception("Description is required.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Dynamic insert based on available columns
        $cols = $__maintCols;
        $columns = ['area_rental_id'];
        $placeholders = ['?'];
        $params = [$rental_id];
        if (in_array('company_id', $cols, true)) { $columns[] = 'company_id'; $placeholders[] = '?'; $params[] = $company_id; }
        $addCol = function($c, $v) use (&$columns,&$placeholders,&$params,$cols) {
            if (in_array($c, $cols, true)) { $columns[] = $c; $placeholders[] = '?'; $params[] = $v; }
        };
        $addCol('maintenance_type', trim($_POST['maintenance_type']));
        $addCol('description', trim($_POST['description']));
        $addCol('priority', $_POST['priority'] ?? 'medium');
        $addCol('status', $_POST['status'] ?? 'pending');
        $addCol('estimated_cost', $_POST['estimated_cost'] === '' ? null : ($_POST['estimated_cost'] ?? null));
        $addCol('actual_cost', $_POST['actual_cost'] === '' ? null : ($_POST['actual_cost'] ?? null));
        // date columns (try maintenance_date, then date)
        if (in_array('maintenance_date', $cols, true)) { $addCol('maintenance_date', $_POST['maintenance_date'] ?? date('Y-m-d')); }
        elseif (in_array('date', $cols, true)) { $addCol('date', $_POST['maintenance_date'] ?? date('Y-m-d')); }
        $addCol('completed_date', $_POST['completed_date'] === '' ? null : ($_POST['completed_date'] ?? null));
        $addCol('notes', trim($_POST['notes'] ?? ''));
        $createdLiteral = '';
        if (in_array('created_at', $cols, true)) { $columns[] = 'created_at'; $createdLiteral = ', NOW()'; }
        $sql = 'INSERT INTO area_rental_maintenance (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ($createdLiteral ? $createdLiteral : '') . ')';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        // Commit transaction
        $conn->commit();

        $success = "Maintenance record created successfully!";
        
        // Redirect to refresh the page
        header("Location: maintenance.php?id=$rental_id&success=1");
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get maintenance history
// Detect available date column for ordering to avoid unknown column errors
try {
    $colStmt = $conn->query("SHOW COLUMNS FROM area_rental_maintenance");
    $cols = array_map(function($r){ return $r['Field']; }, $colStmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    $cols = [];
}
$orderField = 'created_at';
if (in_array('maintenance_date', $cols, true)) {
    $orderField = 'maintenance_date';
} elseif (in_array('scheduled_date', $cols, true)) {
    $orderField = 'scheduled_date';
} elseif (in_array('date', $cols, true)) {
    $orderField = 'date';
}

$sql = "
    SELECT * FROM area_rental_maintenance 
    WHERE area_rental_id = ? 
    ORDER BY {$orderField} DESC, created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([$rental_id]);
$maintenance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Move header include after all potential redirects
require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-tools"></i> <?php echo __('area_rental_maintenance'); ?>
            </h1>
            <p class="text-muted mb-0"><?php echo __('manage_maintenance_for'); ?> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
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
        <div class="alert alert-success"><?php echo __('maintenance_record_created_successfully'); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Maintenance Form -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_maintenance_record'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="maintenanceForm">
                        <div class="mb-3">
                            <label for="maintenance_type" class="form-label"><?php echo __('maintenance_type'); ?> *</label>
                            <select class="form-control" id="maintenance_type" name="maintenance_type" required>
                                <option value=""><?php echo __('select_maintenance_type'); ?></option>
                                <option value="repair"><?php echo __('repair'); ?></option>
                                <option value="inspection"><?php echo __('inspection'); ?></option>
                                <option value="cleaning"><?php echo __('cleaning'); ?></option>
                                <option value="upgrade"><?php echo __('upgrade'); ?></option>
                                <option value="preventive"><?php echo __('preventive'); ?></option>
                                <option value="emergency"><?php echo __('emergency'); ?></option>
                                <option value="other"><?php echo __('other'); ?></option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo __('description'); ?> *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="<?php echo __('describe_the_maintenance_needed'); ?>"
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false" required></textarea>
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_descriptions'); ?></small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label"><?php echo __('priority'); ?></label>
                                    <select class="form-control" id="priority" name="priority">
                                        <option value="low"><?php echo __('low'); ?></option>
                                        <option value="medium" selected><?php echo __('medium'); ?></option>
                                        <option value="high"><?php echo __('high'); ?></option>
                                        <option value="urgent"><?php echo __('urgent'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label"><?php echo __('status'); ?></label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="pending" selected><?php echo __('pending'); ?></option>
                                        <option value="in_progress"><?php echo __('in_progress'); ?></option>
                                        <option value="completed"><?php echo __('completed'); ?></option>
                                        <option value="cancelled"><?php echo __('cancelled'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="maintenance_date" class="form-label"><?php echo __('maintenance_date'); ?></label>
                            <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="estimated_cost" class="form-label"><?php echo __('estimated_cost'); ?></label>
                                    <?php if (in_array('estimated_cost', $__maintCols, true)): ?>
                                    <input type="number" step="0.01" min="0" class="form-control" id="estimated_cost" name="estimated_cost" placeholder="0.00">
                                    <?php else: ?>
                                    <input type="number" class="form-control" placeholder="Not available" disabled>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="actual_cost" class="form-label"><?php echo __('actual_cost'); ?></label>
                                    <?php if (in_array('actual_cost', $__maintCols, true)): ?>
                                    <input type="number" step="0.01" min="0" class="form-control" id="actual_cost" name="actual_cost" placeholder="0.00">
                                    <?php else: ?>
                                    <input type="number" class="form-control" placeholder="Not available" disabled>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="completed_date" class="form-label"><?php echo __('completed_date'); ?></label>
                            <input type="date" class="form-control" id="completed_date" name="completed_date">
                            <small class="form-text text-muted"><?php echo __('leave_empty_if_not_completed_yet'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('additional_notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="<?php echo __('additional_notes_or_instructions'); ?>"
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_notes'); ?></small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> <?php echo __('add_maintenance_record'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Maintenance Summary -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> <?php echo __('maintenance_summary'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-primary"><?php echo __('total_records'); ?></h6>
                                <h4 class="text-primary"><?php echo count($maintenance_records); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-warning"><?php echo __('pending'); ?></h6>
                                <h4 class="text-warning">
                                    <?php echo count(array_filter($maintenance_records, function($r) { return $r['status'] === 'pending'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-info"><?php echo __('in_progress'); ?></h6>
                                <h4 class="text-info">
                                    <?php echo count(array_filter($maintenance_records, function($r) { return $r['status'] === 'in_progress'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-success"><?php echo __('completed'); ?></h6>
                                <h4 class="text-success">
                                    <?php echo count(array_filter($maintenance_records, function($r) { return $r['status'] === 'completed'; })); ?>
                                </h4>
                            </div>
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
                            <h6 class="text-secondary"><?php echo __('area_details'); ?></h6>
                            <p><strong><?php echo __('type'); ?>:</strong> <?php echo ucfirst($rental['area_type']); ?></p>
                            <p><strong><?php echo __('status'); ?>:</strong> 
                                <span class="badge bg-<?php echo $rental['status'] === 'active' ? 'success' : ($rental['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                    <?php echo ucfirst($rental['status']); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance History -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> <?php echo __('maintenance_history'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($maintenance_records)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo __('no_maintenance_records_found'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="maintenanceTable">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('type'); ?></th>
                                        <th><?php echo __('description'); ?></th>
                                        <th><?php echo __('priority'); ?></th>
                                        <th><?php echo __('status'); ?></th>
                                        <th><?php echo __('cost'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_records as $record): ?>
                                    <tr>
                                        <td><?php 
                                            $dateRaw = $record['maintenance_date'] ?? ($record['scheduled_date'] ?? ($record['date'] ?? ($record['created_at'] ?? null)));
                                            echo $dateRaw ? date('M j, Y', strtotime($dateRaw)) : '-';
                                        ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst((string)($record['maintenance_type'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['description']); ?></strong>
                                            <?php if (!empty($record['notes'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($record['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $priority_colors = [
                                                'low' => 'success',
                                                'medium' => 'warning', 
                                                'high' => 'danger',
                                                'urgent' => 'dark'
                                            ];
                                            $priority = strtolower((string)($record['priority'] ?? 'medium'));
                                            $priority_color = $priority_colors[$priority] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $priority_color; ?>">
                                                <?php echo ucfirst($priority); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_colors = [
                                                'pending' => 'warning',
                                                'in_progress' => 'info',
                                                'completed' => 'success',
                                                'cancelled' => 'secondary'
                                            ];
                                            $status = strtolower((string)($record['status'] ?? 'pending'));
                                            $status_color = $status_colors[$status] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $actual = isset($record['actual_cost']) ? (float)$record['actual_cost'] : null;
                                            $estimated = isset($record['estimated_cost']) ? (float)$record['estimated_cost'] : null;
                                            if ($actual !== null && $actual > 0) : ?>
                                                <strong class="text-success">
                                                    $<?php echo number_format($actual, 2); ?>
                                                </strong>
                                            <?php elseif ($estimated !== null && $estimated > 0) : ?>
                                                <span class="text-muted">
                                                    Est: $<?php echo number_format($estimated, 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="viewMaintenance(<?php echo (int)$record['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="editMaintenance(<?php echo (int)$record['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteMaintenance(<?php echo (int)$record['id']; ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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
<div class="modal fade" id="viewMaintenanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo __('maintenance_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewMaintenanceBody">
          <!-- Filled dynamically -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editMaintenanceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo __('edit_maintenance'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="editMaintenanceForm">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_id">
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label"><?php echo __('type'); ?></label>
            <input type="text" class="form-control" name="maintenance_type" id="edit_type">
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('description'); ?></label>
            <textarea class="form-control" name="description" id="edit_description"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('priority'); ?></label>
              <select class="form-control" name="priority" id="edit_priority">
                <option value="low"><?php echo __('low'); ?></option>
                <option value="medium"><?php echo __('medium'); ?></option>
                <option value="high"><?php echo __('high'); ?></option>
                <option value="urgent"><?php echo __('urgent'); ?></option>
              </select>
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('status'); ?></label>
              <select class="form-control" name="status" id="edit_status">
                <option value="pending"><?php echo __('pending'); ?></option>
                <option value="in_progress"><?php echo __('in_progress'); ?></option>
                <option value="completed"><?php echo __('completed'); ?></option>
                <option value="cancelled"><?php echo __('cancelled'); ?></option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('maintenance_date'); ?></label>
              <input type="date" class="form-control" name="maintenance_date" id="edit_maintenance_date">
            </div>
            <div class="col-md-6 mb-2">
            <label class="form-label"><?php echo __('completed_date'); ?></label>
              <input type="date" class="form-control" name="completed_date" id="edit_completed_date">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('estimated_cost'); ?></label>
              <input type="number" step="0.01" class="form-control" name="estimated_cost" id="edit_estimated_cost">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('actual_cost'); ?></label>
              <input type="number" step="0.01" class="form-control" name="actual_cost" id="edit_actual_cost">
            </div>
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
document.addEventListener('DOMContentLoaded', function() {
    // Enable spaces in text inputs and textareas
    const textInputs = document.querySelectorAll('input[type="text"], textarea');
    
    // Function to enable spaces in input fields
    function enableSpacesInInput(input) {
        if (input) {
            // Remove any existing event listeners that might block spaces
            input.removeEventListener('keydown', null);
            input.removeEventListener('keypress', null);
            input.removeEventListener('keyup', null);
            
            // Add space handling
            input.addEventListener('keydown', function(e) {
                // Explicitly allow space key
                if (e.key === ' ' || e.keyCode === 32) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Manually insert space
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    const value = this.value;
                    this.value = value.substring(0, start) + ' ' + value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 1;
                    
                    return false;
                }
            });
            
            // Ensure the input is properly configured
            if (input.type === 'text') {
                input.setAttribute('type', 'text');
                input.style.textTransform = 'none';
                input.style.letterSpacing = 'normal';
            }
        }
    }
    
    // Enable spaces in all text inputs and textareas
    textInputs.forEach(enableSpacesInInput);
    
    // Initialize DataTable for maintenance records
    if (document.getElementById('maintenanceTable')) {
        $('#maintenanceTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            language: {
                search: "<?php echo __('search_maintenance'); ?>:",
                lengthMenu: "<?php echo __('show_maintenance_records_per_page'); ?>",
                info: "<?php echo __('showing_start_to_end_of_total_maintenance_records'); ?>",
                infoEmpty: "<?php echo __('showing_0_to_0_of_0_maintenance_records'); ?>",
                infoFiltered: "(<?php echo __('filtered_from_total_maintenance_records'); ?>)"
            }
        });
    }
});

async function viewMaintenance(id) {
  try {
    const res = await fetch(`maintenance.php?id=<?php echo $rental_id; ?>&action=get&mid=${id}`);
    const data = await res.json();
    const html = `
      <div class="row">
        <div class="col-md-6"><strong><?php echo __('type'); ?>:</strong> ${data.maintenance_type ?? '-'}</div>
        <div class="col-md-6"><strong><?php echo __('status'); ?>:</strong> ${data.status ?? '-'}</div>
      </div>
      <div class="row mt-2">
        <div class="col-md-6"><strong><?php echo __('date'); ?>:</strong> ${data.maintenance_date ?? '-'}</div>
        <div class="col-md-6"><strong><?php echo __('completed'); ?>:</strong> ${data.completed_date ?? '-'}</div>
      </div>
      <div class="mt-2"><strong><?php echo __('description'); ?>:</strong><br>${(data.description ?? '').toString().replace(/</g,'&lt;')}</div>
      <div class="mt-2"><strong><?php echo __('notes'); ?>:</strong><br>${(data.notes ?? '').toString().replace(/</g,'&lt;')}</div>
      <div class="row mt-2">
        <div class="col-md-6"><strong><?php echo __('estimated_cost'); ?>:</strong> ${data.estimated_cost ?? '-'}</div>
        <div class="col-md-6"><strong><?php echo __('actual_cost'); ?>:</strong> ${data.actual_cost ?? '-'}</div>
      </div>
    `;
    document.getElementById('viewMaintenanceBody').innerHTML = html;
    const modal = new bootstrap.Modal(document.getElementById('viewMaintenanceModal'));
    modal.show();
  } catch (e) { alert('<?php echo __('failed_to_load_maintenance_details'); ?>'); }
}

async function editMaintenance(id) {
  try {
    const res = await fetch(`maintenance.php?id=<?php echo $rental_id; ?>&action=get&mid=${id}`);
    const d = await res.json();
    document.getElementById('edit_id').value = d.id || id;
    document.getElementById('edit_type').value = d.maintenance_type || '';
    document.getElementById('edit_description').value = d.description || '';
    document.getElementById('edit_priority').value = (d.priority || 'medium');
    document.getElementById('edit_status').value = (d.status || 'pending');
    document.getElementById('edit_maintenance_date').value = d.maintenance_date || '';
    document.getElementById('edit_completed_date').value = d.completed_date || '';
    document.getElementById('edit_estimated_cost').value = d.estimated_cost || '';
    document.getElementById('edit_actual_cost').value = d.actual_cost || '';
    document.getElementById('edit_notes').value = d.notes || '';
    const modal = new bootstrap.Modal(document.getElementById('editMaintenanceModal'));
    modal.show();
  } catch (e) { alert('<?php echo __('failed_to_load_maintenance_for_edit'); ?>'); }
}

async function deleteMaintenance(id) {
  if (!confirm('<?php echo __('delete_this_maintenance_record'); ?>')) return;
  const form = new FormData();
  form.append('action', 'delete');
  form.append('id', id);
  const res = await fetch(`maintenance.php?id=<?php echo $rental_id; ?>`, { method: 'POST', body: form });
  if (res.ok) { location.reload(); } else { alert('<?php echo __('failed_to_delete'); ?>'); }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>