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

// Handle form submission for new visit record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty(trim($_POST['visit_type']))) {
            throw new Exception("Visit type is required.");
        }

        if (empty(trim($_POST['purpose']))) {
            throw new Exception("Purpose is required.");
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert visit record
        $stmt = $conn->prepare("
            INSERT INTO area_rental_visits (
                area_rental_id, visit_type, purpose, visit_date, 
                visitor_name, visitor_contact, duration_minutes, 
                findings, recommendations, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $rental_id,
            trim($_POST['visit_type']),
            trim($_POST['purpose']),
            $_POST['visit_date'] ?? date('Y-m-d'),
            trim($_POST['visitor_name'] ?? ''),
            trim($_POST['visitor_contact'] ?? ''),
            $_POST['duration_minutes'] ?? null,
            trim($_POST['findings'] ?? ''),
            trim($_POST['recommendations'] ?? ''),
            trim($_POST['notes'] ?? '')
        ]);

        // Commit transaction
        $conn->commit();

        $success = "Visit record created successfully!";
        
        // Redirect to refresh the page
        header("Location: visits.php?id=$rental_id&success=1");
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get visit history
$stmt = $conn->prepare("
    SELECT * FROM area_rental_visits 
    WHERE area_rental_id = ? 
    ORDER BY visit_date DESC, created_at DESC
");
$stmt->execute([$rental_id]);
$visit_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Move header include after all potential redirects
require_once '../../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-calendar-check"></i> Area Rental Visits
            </h1>
            <p class="text-muted mb-0">Manage visits for <?php echo htmlspecialchars($rental['rental_code']); ?></p>
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
        <div class="alert alert-success">Visit record created successfully!</div>
    <?php endif; ?>

    <div class="row">
        <!-- Visit Form -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus"></i> Add Visit Record
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" id="visitForm">
                        <div class="mb-3">
                            <label for="visit_type" class="form-label">Visit Type *</label>
                            <select class="form-control" id="visit_type" name="visit_type" required>
                                <option value="">Select Visit Type</option>
                                <option value="inspection">🔍 Inspection</option>
                                <option value="maintenance">🔧 Maintenance</option>
                                <option value="client_visit">👤 Client Visit</option>
                                <option value="security_check">🛡️ Security Check</option>
                                <option value="cleaning">🧹 Cleaning</option>
                                <option value="emergency">🚨 Emergency</option>
                                <option value="other">📋 Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="purpose" class="form-label">Purpose *</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="2" 
                                      placeholder="Describe the purpose of the visit..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false" required></textarea>
                            <small class="form-text text-muted">You can use spaces in purpose descriptions.</small>
                        </div>

                        <div class="mb-3">
                            <label for="visit_date" class="form-label">Visit Date</label>
                            <input type="date" class="form-control" id="visit_date" name="visit_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visitor_name" class="form-label">Visitor Name</label>
                                    <input type="text" class="form-control" id="visitor_name" name="visitor_name" 
                                           placeholder="Name of visitor"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in visitor names.</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="visitor_contact" class="form-label">Visitor Contact</label>
                                    <input type="text" class="form-control" id="visitor_contact" name="visitor_contact" 
                                           placeholder="Phone or email"
                                           style="text-transform: none;" autocomplete="off" spellcheck="false">
                                    <small class="form-text text-muted">You can use spaces in contact information.</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="duration_minutes" class="form-label">Duration (Minutes)</label>
                            <input type="number" min="1" class="form-control" id="duration_minutes" name="duration_minutes" 
                                   placeholder="How long the visit took">
                        </div>

                        <div class="mb-3">
                            <label for="findings" class="form-label">Findings</label>
                            <textarea class="form-control" id="findings" name="findings" rows="3" 
                                      placeholder="What was found during the visit..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted">You can use spaces in findings.</small>
                        </div>

                        <div class="mb-3">
                            <label for="recommendations" class="form-label">Recommendations</label>
                            <textarea class="form-control" id="recommendations" name="recommendations" rows="3" 
                                      placeholder="Any recommendations or actions needed..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted">You can use spaces in recommendations.</small>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Additional notes or observations..."
                                      style="text-transform: none; resize: vertical;" autocomplete="off" spellcheck="false"></textarea>
                            <small class="form-text text-muted">You can use spaces in notes.</small>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> Add Visit Record
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
                        <i class="fas fa-chart-bar"></i> Visit Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-primary">Total Visits</h6>
                                <h4 class="text-primary"><?php echo count($visit_records); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-info">Inspections</h6>
                                <h4 class="text-info">
                                    <?php echo count(array_filter($visit_records, function($r) { return $r['visit_type'] === 'inspection'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-warning">Maintenance</h6>
                                <h4 class="text-warning">
                                    <?php echo count(array_filter($visit_records, function($r) { return $r['visit_type'] === 'maintenance'; })); ?>
                                </h4>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center mb-3">
                                <h6 class="text-success">Client Visits</h6>
                                <h4 class="text-success">
                                    <?php echo count(array_filter($visit_records, function($r) { return $r['visit_type'] === 'client_visit'; })); ?>
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

            <!-- Visit History -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> Visit History
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($visit_records)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No visit records found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="visitsTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Purpose</th>
                                        <th>Visitor</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
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
                                            $icon = $type_icons[$record['visit_type']] ?? '📋';
                                            ?>
                                            <span class="badge bg-info">
                                                <?php echo $icon; ?> <?php echo ucfirst(str_replace('_', ' ', $record['visit_type'])); ?>
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
                                                        onclick="viewVisit(<?php echo $record['id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning" 
                                                        onclick="editVisit(<?php echo $record['id']; ?>)" title="Edit">
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
    
    // Initialize DataTable for visit records
    if (document.getElementById('visitsTable')) {
        $('#visitsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            language: {
                search: "Search visits:",
                lengthMenu: "Show _MENU_ records per page",
                info: "Showing _START_ to _END_ of _TOTAL_ records",
                infoEmpty: "Showing 0 to 0 of 0 records",
                infoFiltered: "(filtered from _MAX_ total records)"
            }
        });
    }
});

function viewVisit(id) {
    // TODO: Implement view visit details modal
    alert('View visit details for ID: ' + id);
}

function editVisit(id) {
    // TODO: Implement edit visit modal
    alert('Edit visit for ID: ' + id);
}
</script>

<?php require_once '../../../includes/footer.php'; ?>