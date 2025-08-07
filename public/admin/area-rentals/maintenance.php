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

        // Insert maintenance record
        $stmt = $conn->prepare("
            INSERT INTO area_rental_maintenance (
                area_rental_id, maintenance_type, description, priority, 
                status, estimated_cost, actual_cost, maintenance_date, 
                completed_date, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $rental_id,
            trim($_POST['maintenance_type']),
            trim($_POST['description']),
            $_POST['priority'] ?? 'medium',
            $_POST['status'] ?? 'pending',
            $_POST['estimated_cost'] ?? null,
            $_POST['actual_cost'] ?? null,
            $_POST['maintenance_date'] ?? date('Y-m-d'),
            $_POST['completed_date'] ?? null,
            trim($_POST['notes'] ?? '')
        ]);

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
$stmt = $conn->prepare("
    SELECT * FROM area_rental_maintenance 
    WHERE area_rental_id = ? 
    ORDER BY maintenance_date DESC, created_at DESC
");
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
                <i class="fas fa-tools"></i> Area Rental Maintenance
            </h1>
            <p class="text-muted mb-0">Manage maintenance for <?php echo htmlspecialchars($rental['rental_code']); ?></p>
        </div>
        <div class="btn-group" role="group">
            <a href="view.php?id=<?php echo $rental_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye"></i> View Details
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Rentals
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">Maintenance record created successfully!</div>
    <?php endif; ?>

    <div class="row">
        <!-- Maintenance Form -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus"></i> Add Maintenance Record
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="maintenanceForm">
                        <div class="mb-3">
                            <label for="maintenance_type" class="form-label">Maintenance Type *</label>
                            <select class="form-control" id="maintenance_type" name="maintenance_type" required>
                                <option value="">Select Maintenance Type</option>
                                <option value="repair">🔧 Repair</option>
                                <option value="inspection">🔍 Inspection</option>
                                <option value="cleaning">🧹 Cleaning</option>
                                <option value="upgrade">⚡ Upgrade</option>
                                <option value="preventive">🛡️ Preventive</option>
                                <option value="emergency">🚨 Emergency</option>
                                <option value="other">📋 Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Describe the maintenance needed..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false" required></textarea>
                            <small class="form-text text-muted">You can use spaces in descriptions.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-control" id="priority" name="priority">
                                        <option value="low">🟢 Low</option>
                                        <option value="medium" selected>🟡 Medium</option>
                                        <option value="high">🟠 High</option>
                                        <option value="urgent">🔴 Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="pending" selected>⏳ Pending</option>
                                        <option value="in_progress">🔄 In Progress</option>
                                        <option value="completed">✅ Completed</option>
                                        <option value="cancelled">❌ Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="maintenance_date" class="form-label">Maintenance Date</label>
                            <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="estimated_cost" class="form-label">Estimated Cost</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="estimated_cost" name="estimated_cost" 
                                           placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="actual_cost" class="form-label">Actual Cost</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="actual_cost" name="actual_cost" 
                                           placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="completed_date" class="form-label">Completed Date</label>
                            <input type="date" class="form-control" id="completed_date" name="completed_date">
                            <small class="form-text text-muted">Leave empty if not completed yet.</small>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes or instructions..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted">You can use spaces in notes.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Add Maintenance Record
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
                        <i class="fas fa-chart-bar"></i> Maintenance Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-primary">Total Records</h6>
                                <h4 class="text-primary"><?php echo count($maintenance_records); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-warning">Pending</h6>
                                <h4 class="text-warning">
                                    <?php echo count(array_filter($maintenance_records, function($r) { return $r['status'] === 'pending'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-info">In Progress</h6>
                                <h4 class="text-info">
                                    <?php echo count(array_filter($maintenance_records, function($r) { return $r['status'] === 'in_progress'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-success">Completed</h6>
                                <h4 class="text-success">
                                    <?php echo count(array_filter($maintenance_records, function($r) { return $r['status'] === 'completed'; })); ?>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <!-- Rental Details -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6 class="text-secondary">Rental Information</h6>
                            <p><strong>Code:</strong> <?php echo htmlspecialchars($rental['rental_code']); ?></p>
                            <p><strong>Client:</strong> <?php echo htmlspecialchars($rental['client_name']); ?></p>
                            <p><strong>Area:</strong> <?php echo htmlspecialchars($rental['area_name']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-secondary">Area Details</h6>
                            <p><strong>Type:</strong> <?php echo ucfirst($rental['area_type']); ?></p>
                            <p><strong>Status:</strong> 
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
                        <i class="fas fa-history"></i> Maintenance History
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($maintenance_records)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No maintenance records found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="maintenanceTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($record['maintenance_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo ucfirst($record['maintenance_type']); ?>
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
                                            $priority_color = $priority_colors[$record['priority']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $priority_color; ?>">
                                                <?php echo ucfirst($record['priority']); ?>
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
                                            $status_color = $status_colors[$record['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $record['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($record['actual_cost']): ?>
                                                <strong class="text-success">
                                                    $<?php echo number_format($record['actual_cost'], 2); ?>
                                                </strong>
                                            <?php elseif ($record['estimated_cost']): ?>
                                                <span class="text-muted">
                                                    Est: $<?php echo number_format($record['estimated_cost'], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="viewMaintenance(<?php echo $record['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="editMaintenance(<?php echo $record['id']; ?>)" title="Edit">
                                                    <i class="fas fa-edit"></i>
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
                search: "Search maintenance:",
                lengthMenu: "Show _MENU_ records per page",
                info: "Showing _START_ to _END_ of _TOTAL_ records",
                infoEmpty: "Showing 0 to 0 of 0 records",
                infoFiltered: "(filtered from _MAX_ total records)"
            }
        });
    }
});

function viewMaintenance(id) {
    // TODO: Implement view maintenance details modal
    alert('View maintenance details for ID: ' + id);
}

function editMaintenance(id) {
    // TODO: Implement edit maintenance modal
    alert('Edit maintenance for ID: ' + id);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>