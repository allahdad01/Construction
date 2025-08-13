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

// Detect visits table columns
$__visitCols = [];
try {
    $__visitCols = array_map(function($r){ return $r['Field']; }, $conn->query("SHOW COLUMNS FROM area_rental_visits")->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { $__visitCols = []; }
$__hasVisitCompanyId = in_array('company_id', $__visitCols, true);

// AJAX: fetch a visit record
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    try {
        $sql = "SELECT * FROM area_rental_visits WHERE id = ? AND area_rental_id = ?" . ($__hasVisitCompanyId ? " AND company_id = ?" : "");
        $stmt = $conn->prepare($sql);
        $params = [(int)($_GET['vid'] ?? 0), $rental_id];
        if ($__hasVisitCompanyId) { $params[] = $company_id; }
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
        $cols = $__visitCols;
        if ($_POST['action'] === 'update') {
            $vid = (int)($_POST['id'] ?? 0);
            if (!$vid) { throw new Exception('Invalid visit id'); }
            // Map allowed fields with optional fallbacks
            $map = [
                'visit_type' => ['visit_type','type'],
                'purpose' => ['purpose'],
                'visit_date' => ['visit_date','date'],
                'visitor_name' => ['visitor_name','visitor'],
                'visitor_contact' => ['visitor_contact','contact','phone'],
                'duration_minutes' => ['duration_minutes','duration'],
                'findings' => ['findings'],
                'recommendations' => ['recommendations'],
                'notes' => ['notes'],
            ];
            $setParts = [];
            $params = [];
            foreach ($map as $logical => $cands) {
                $val = $_POST[$logical] ?? null;
                if ($val === '' || $val === null) { continue; }
                foreach ($cands as $col) {
                    if (in_array($col, $cols, true)) { $setParts[] = "$col = ?"; $params[] = $val; break; }
                }
            }
            if (empty($setParts)) { throw new Exception('Nothing to update'); }
            $params[] = $vid;
            $params[] = $rental_id;
            $sql = 'UPDATE area_rental_visits SET ' . implode(', ', $setParts) . ' WHERE id = ? AND area_rental_id = ?' . ($__hasVisitCompanyId ? ' AND company_id = ?' : '');
            if ($__hasVisitCompanyId) { $params[] = $company_id; }
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            header('Location: visits.php?id=' . $rental_id . '&success=1');
            exit;
        }
        if ($_POST['action'] === 'delete') {
            $vid = (int)($_POST['id'] ?? 0);
            if (!$vid) { throw new Exception('Invalid visit id'); }
            $sql = 'DELETE FROM area_rental_visits WHERE id = ? AND area_rental_id = ?' . ($__hasVisitCompanyId ? ' AND company_id = ?' : '');
            $stmt = $conn->prepare($sql);
            $params = [$vid, $rental_id];
            if ($__hasVisitCompanyId) { $params[] = $company_id; }
            $stmt->execute($params);
            header('Location: visits.php?id=' . $rental_id . '&success=1');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle form submission for new visit record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty(trim($_POST['visit_type'] ?? ($_POST['type'] ?? '')))) {
            throw new Exception("Visit type is required.");
        }
        if (empty(trim($_POST['purpose'] ?? ''))) {
            throw new Exception("Purpose is required.");
        }
        // Start transaction
        $conn->beginTransaction();
        // Dynamic insert based on available columns
        $cols = $__visitCols;
        $columns = ['area_rental_id'];
        $placeholders = ['?'];
        $params = [$rental_id];
        if (in_array('company_id', $cols, true)) { $columns[] = 'company_id'; $placeholders[] = '?'; $params[] = $company_id; }
        // Helper to add if column exists
        $addCol = function($c, $v) use (&$columns,&$placeholders,&$params,$cols) {
            if (in_array($c, $cols, true)) { $columns[] = $c; $placeholders[] = '?'; $params[] = $v; }
        };
        // visit type
        if (in_array('visit_type', $cols, true)) { $addCol('visit_type', trim($_POST['visit_type'] ?? $_POST['type'] ?? '')); }
        elseif (in_array('type', $cols, true)) { $addCol('type', trim($_POST['visit_type'] ?? $_POST['type'] ?? '')); }
        // purpose
        $addCol('purpose', trim($_POST['purpose'] ?? ''));
        // visit date
        if (in_array('visit_date', $cols, true)) { $addCol('visit_date', $_POST['visit_date'] ?? date('Y-m-d')); }
        elseif (in_array('date', $cols, true)) { $addCol('date', $_POST['visit_date'] ?? date('Y-m-d')); }
        // visitor
        if (in_array('visitor_name', $cols, true)) { $addCol('visitor_name', trim($_POST['visitor_name'] ?? '')); }
        elseif (in_array('visitor', $cols, true)) { $addCol('visitor', trim($_POST['visitor_name'] ?? '')); }
        if (in_array('visitor_contact', $cols, true)) { $addCol('visitor_contact', trim($_POST['visitor_contact'] ?? '')); }
        elseif (in_array('contact', $cols, true)) { $addCol('contact', trim($_POST['visitor_contact'] ?? '')); }
        // duration
        if (in_array('duration_minutes', $cols, true)) { $addCol('duration_minutes', $_POST['duration_minutes'] ?? null); }
        elseif (in_array('duration', $cols, true)) { $addCol('duration', $_POST['duration_minutes'] ?? null); }
        // findings/recommendations/notes
        $addCol('findings', trim($_POST['findings'] ?? ''));
        $addCol('recommendations', trim($_POST['recommendations'] ?? ''));
        $addCol('notes', trim($_POST['notes'] ?? ''));
        // created_at if exists
        $createdLiteral = '';
        if (in_array('created_at', $cols, true)) { $columns[] = 'created_at'; $createdLiteral = ', NOW()'; }
        $sql = 'INSERT INTO area_rental_visits (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ($createdLiteral ? $createdLiteral : '') . ')';
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $conn->commit();
        $success = "Visit record created successfully!";
        header("Location: visits.php?id=$rental_id&success=1");
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        $error = $e->getMessage();
    }
}

// Get visit history
$sql = "SELECT * FROM area_rental_visits WHERE area_rental_id = ?" . ($__hasVisitCompanyId ? " AND company_id = ?" : "") . " ORDER BY visit_date DESC, created_at DESC";
$stmt = $conn->prepare($sql);
$params = [$rental_id]; if ($__hasVisitCompanyId) { $params[] = $company_id; }
$stmt->execute($params);
$visit_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Move header include after all potential redirects
require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-calendar-check"></i> <?php echo __('area_rental_visits'); ?>
            </h1>
            <p class="text-muted mb-0"><?php echo __('manage_visits_for'); ?> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
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
        <div class="alert alert-success"><?php echo __('visit_record_created_successfully'); ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Visit Form -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus"></i> <?php echo __('add_visit_record'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="visitForm">
                        <div class="mb-3">
                            <label for="visit_type" class="form-label"><?php echo __('visit_type'); ?> *</label>
                            <select class="form-control" id="visit_type" name="visit_type" required>
                                <option value=""><?php echo __('select_visit_type'); ?></option>
                                <option value="inspection">🔍 <?php echo __('inspection'); ?></option>
                                <option value="maintenance">🔧 <?php echo __('maintenance'); ?></option>
                                <option value="client_visit">👤 <?php echo __('client_visit'); ?></option>
                                <option value="security_check">🛡️ <?php echo __('security_check'); ?></option>
                                <option value="cleaning">🧹 <?php echo __('cleaning'); ?></option>
                                <option value="emergency">🚨 <?php echo __('emergency'); ?></option>
                                <option value="other">📋 <?php echo __('other'); ?></option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="purpose" class="form-label"><?php echo __('purpose'); ?> *</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="2" 
                                      placeholder="<?php echo __('describe_the_purpose_of_the_visit'); ?>"
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false" required></textarea>
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_purpose_descriptions'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label for="visit_date" class="form-label"><?php echo __('visit_date'); ?></label>
                            <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visitor_name" class="form-label"><?php echo __('visitor_name'); ?></label>
                                    <input type="text" class="form-control" id="visitor_name" name="visitor_name" 
                                           placeholder="<?php echo __('name_of_visitor'); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_visitor_names'); ?></small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visitor_contact" class="form-label"><?php echo __('visitor_contact'); ?></label>
                                    <input type="text" class="form-control" id="visitor_contact" name="visitor_contact" 
                                           placeholder="<?php echo __('phone_or_email'); ?>"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_contact_information'); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="duration_minutes" class="form-label"><?php echo __('duration'); ?> (<?php echo __('minutes'); ?>)</label>
                            <input type="number" min="1" class="form-control" id="duration_minutes" name="duration_minutes" 
                                   placeholder="<?php echo __('how_long_the_visit_took'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="findings" class="form-label"><?php echo __('findings'); ?></label>
                            <textarea class="form-control" id="findings" name="findings" rows="3" 
                                      placeholder="<?php echo __('what_was_found_during_the_visit'); ?>"
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_findings'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label for="recommendations" class="form-label"><?php echo __('recommendations'); ?></label>
                            <textarea class="form-control" id="recommendations" name="recommendations" rows="3" 
                                      placeholder="<?php echo __('any_recommendations_or_actions_needed'); ?>"
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_recommendations'); ?></small>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label"><?php echo __('additional_notes'); ?></label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="<?php echo __('additional_notes_or_observations'); ?>"
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted"><?php echo __('you_can_use_spaces_in_notes'); ?></small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> <?php echo __('add_visit_record'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Visit Summary -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> <?php echo __('visit_summary'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-primary"><?php echo __('total_visits'); ?></h6>
                                <h4 class="text-primary"><?php echo count($visit_records); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-info"><?php echo __('inspections'); ?></h6>
                                <h4 class="text-info">
                                    <?php echo count(array_filter($visit_records, function($r) { $t = $r['visit_type'] ?? ($r['type'] ?? ''); return $t === 'inspection'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-warning"><?php echo __('maintenance'); ?></h6>
                                <h4 class="text-warning">
                                    <?php echo count(array_filter($visit_records, function($r) { $t = $r['visit_type'] ?? ($r['type'] ?? ''); return $t === 'maintenance'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-success"><?php echo __('client_visits'); ?></h6>
                                <h4 class="text-success">
                                    <?php echo count(array_filter($visit_records, function($r) { $t = $r['visit_type'] ?? ($r['type'] ?? ''); return $t === 'client_visit'; })); ?>
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

            <!-- Visit History -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> <?php echo __('visit_history'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($visit_records)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo __('no_visit_records_found'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="visitsTable">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('type'); ?></th>
                                        <th><?php echo __('purpose'); ?></th>
                                        <th><?php echo __('visitor'); ?></th>
                                        <th><?php echo __('duration'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($visit_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($record['visit_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $type_icons = [
                                                'inspection' => '🔍',
                                                'maintenance' => '🔧',
                                                'client_visit' => '👤',
                                                'security_check' => '🛡️',
                                                'cleaning' => '🧹',
                                                'emergency' => '🚨',
                                                'other' => '📋'
                                            ];
                                            $type = $record['visit_type'] ?? ($record['type'] ?? 'other');
                                            $icon = $type_icons[$type] ?? '📋';
                                            ?>
                                            <span class="badge bg-info">
                                                <?php echo $icon; ?> <?php echo ucfirst(str_replace('_', ' ', (string)$type)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['purpose']); ?></strong>
                                            <?php if (!empty($record['findings'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($record['findings']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($record['visitor_name'])): ?>
                                                <strong><?php echo htmlspecialchars($record['visitor_name']); ?></strong>
                                                <?php if (!empty($record['visitor_contact'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($record['visitor_contact']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['duration_minutes']): ?>
                                                <span class="text-info">
                                                    <?php echo $record['duration_minutes']; ?> min
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="viewVisit(<?php echo (int)$record['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="editVisit(<?php echo (int)$record['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        onclick="deleteVisit(<?php echo (int)$record['id']; ?>)" title="Delete">
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

<!-- View Visit Modal -->
<div class="modal fade" id="viewVisitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo __('visit_details'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="viewVisitBody"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('close'); ?></button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Visit Modal -->
<div class="modal fade" id="editVisitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo __('edit_visit'); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" id="editVisitForm">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_visit_id">
        <div class="modal-body">
          <div class="mb-2">
            <label class="form-label"><?php echo __('type'); ?></label>
            <input type="text" class="form-control" name="visit_type" id="edit_visit_type">
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('purpose'); ?></label>
            <textarea class="form-control" name="purpose" id="edit_purpose"></textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('visit_date'); ?></label>
              <input type="date" class="form-control" name="visit_date" id="edit_visit_date">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('duration'); ?> (<?php echo __('minutes'); ?>)</label>
              <input type="number" class="form-control" name="duration_minutes" id="edit_duration_minutes">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('visitor'); ?></label>
              <input type="text" class="form-control" name="visitor_name" id="edit_visitor_name">
            </div>
            <div class="col-md-6 mb-2">
              <label class="form-label"><?php echo __('contact'); ?></label>
              <input type="text" class="form-control" name="visitor_contact" id="edit_visitor_contact">
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('findings'); ?></label>
            <textarea class="form-control" name="findings" id="edit_findings"></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label"><?php echo __('recommendations'); ?></label>
            <textarea class="form-control" name="recommendations" id="edit_recommendations"></textarea>
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
    
    // Initialize DataTable for visit records
    if (document.getElementById('visitsTable')) {
        $('#visitsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            language: {
                search: "<?php echo __('search_visits'); ?>:",
                lengthMenu: "<?php echo __('show_records_per_page'); ?>",
                info: "<?php echo __('showing_start_to_end_of_total_records'); ?>",
                infoEmpty: "<?php echo __('showing_0_to_0_of_0_records'); ?>",
                infoFiltered: "(<?php echo __('filtered_from_total_records'); ?>)"
            }
        });
    }
});

async function viewVisit(id) {
  try {
    const res = await fetch(`visits.php?id=<?php echo $rental_id; ?>&action=get&vid=${id}`);
    const d = await res.json();
    const html = `
      <div class="row">
        <div class="col-md-6"><strong><?php echo __('type'); ?>:</strong> ${d.visit_type ?? d.type ?? '-'}</div>
        <div class="col-md-6"><strong><?php echo __('date'); ?>:</strong> ${d.visit_date ?? d.date ?? '-'}</div>
      </div>
      <div class="row mt-2">
        <div class="col-md-6"><strong><?php echo __('visitor'); ?>:</strong> ${d.visitor_name ?? d.visitor ?? '-'}</div>
        <div class="col-md-6"><strong><?php echo __('contact'); ?>:</strong> ${d.visitor_contact ?? d.contact ?? '-'}</div>
      </div>
      <div class="row mt-2">
        <div class="col-md-6"><strong><?php echo __('duration'); ?>:</strong> ${(d.duration_minutes ?? d.duration ?? '-') }</div>
      </div>
      <div class="mt-2"><strong><?php echo __('purpose'); ?>:</strong><br>${(d.purpose ?? '').toString().replace(/</g,'&lt;')}</div>
      <div class="mt-2"><strong><?php echo __('findings'); ?>:</strong><br>${(d.findings ?? '').toString().replace(/</g,'&lt;')}</div>
      <div class="mt-2"><strong><?php echo __('recommendations'); ?>:</strong><br>${(d.recommendations ?? '').toString().replace(/</g,'&lt;')}</div>
      <div class="mt-2"><strong><?php echo __('notes'); ?>:</strong><br>${(d.notes ?? '').toString().replace(/</g,'&lt;')}</div>
    `;
    document.getElementById('viewVisitBody').innerHTML = html;
    const modal = new bootstrap.Modal(document.getElementById('viewVisitModal'));
    modal.show();
  } catch (e) { alert('<?php echo __('failed_to_load_visit_details'); ?>'); }
}

async function editVisit(id) {
  try {
    const res = await fetch(`visits.php?id=<?php echo $rental_id; ?>&action=get&vid=${id}`);
    const d = await res.json();
    document.getElementById('edit_visit_id').value = d.id || id;
    document.getElementById('edit_visit_type').value = d.visit_type || d.type || '';
    document.getElementById('edit_purpose').value = d.purpose || '';
    document.getElementById('edit_visit_date').value = d.visit_date || d.date || '';
    document.getElementById('edit_duration_minutes').value = d.duration_minutes || d.duration || '';
    document.getElementById('edit_visitor_name').value = d.visitor_name || d.visitor || '';
    document.getElementById('edit_visitor_contact').value = d.visitor_contact || d.contact || '';
    document.getElementById('edit_findings').value = d.findings || '';
    document.getElementById('edit_recommendations').value = d.recommendations || '';
    document.getElementById('edit_notes').value = d.notes || '';
    const modal = new bootstrap.Modal(document.getElementById('editVisitModal'));
    modal.show();
  } catch (e) { alert('<?php echo __('failed_to_load_visit_for_edit'); ?>
}

async function deleteVisit(id) {
  if (!confirm('Delete this visit record?')) return;
  const form = new FormData();
  form.append('action', 'delete');
  form.append('id', id);
  const res = await fetch(`visits.php?id=<?php echo $rental_id; ?>`, { method: 'POST', body: form });
  if (res.ok) { location.reload(); } else { alert('Failed to delete'); }
}
</script>

<?php require_once '../../../includes/footer.php'; ?>